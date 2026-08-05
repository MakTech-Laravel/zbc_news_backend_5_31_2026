<?php

namespace App\Services;

use App\Jobs\SendScheduledTaskFailedAdminEmailJob;
use App\Models\ScheduledTaskFailure;
use App\Models\User;
use App\Support\MailSender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ScheduledTaskFailureService
{
    public const NOTIFY_THROTTLE_MINUTES = 60;

    public const QUEUE_TASK_KEY_PREFIX = 'queue:';

    /** Recording these would loop: they are the jobs that deliver the failure alert itself. */
    private const NON_RECORDABLE_JOBS = [
        SendScheduledTaskFailedAdminEmailJob::class,
    ];

    public function __construct(
        private readonly UserNotificationService $userNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordFailure(string $taskKey, Throwable $exception, array $attributes = []): ?ScheduledTaskFailure
    {
        $definition = $this->definition($taskKey);
        if ($definition === null) {
            $definition = [
                'label' => $taskKey,
                'type' => 'unknown',
            ];
        }

        $message = mb_substr($exception->getMessage() ?: $exception::class, 0, 2000);
        $trace = mb_substr($exception->getTraceAsString(), 0, 20000);

        $existing = ScheduledTaskFailure::query()
            ->where('task_key', $taskKey)
            ->whereIn('status', ['failed', 'rerun_queued'])
            ->latest('failed_at')
            ->first();

        if ($existing) {
            $shouldNotify = $existing->last_notified_at === null
                || $existing->last_notified_at->lte(now()->subMinutes(self::NOTIFY_THROTTLE_MINUTES));

            $existing->forceFill(array_merge($attributes, [
                'exception_message' => $message,
                'exception_trace' => $trace,
                'status' => 'failed',
                'occurrence_count' => (int) $existing->occurrence_count + 1,
                'failed_at' => now(),
                'last_notified_at' => $shouldNotify ? now() : $existing->last_notified_at,
            ]))->save();

            if ($shouldNotify) {
                $this->notifyAdmins($existing->fresh());
            }

            return $existing->fresh();
        }

        $failure = ScheduledTaskFailure::query()->create(array_merge([
            'task_key' => $taskKey,
            'task_name' => (string) ($definition['label'] ?? $taskKey),
            'task_type' => (string) ($definition['type'] ?? 'unknown'),
            'exception_message' => $message,
            'exception_trace' => $trace,
            'status' => 'failed',
            'occurrence_count' => 1,
            'failed_at' => now(),
            'last_notified_at' => now(),
        ], $attributes));

        $this->notifyAdmins($failure);

        return $failure;
    }

    /**
     * Records a queue worker failure. Jobs that back a scheduled task are folded
     * into that task's row so the board shows one entry per logical task.
     */
    public function recordQueueJobFailure(
        string $jobClass,
        Throwable $exception,
        ?string $failedJobUuid = null,
        ?string $connection = null,
    ): ?ScheduledTaskFailure {
        if (in_array($jobClass, self::NON_RECORDABLE_JOBS, true)) {
            return null;
        }

        $scheduledKey = $this->resolveTaskKey($jobClass);

        if ($scheduledKey !== null) {
            return $this->recordFailure($scheduledKey, $exception, [
                'failed_job_uuid' => $failedJobUuid,
                'queue_connection' => $connection,
            ]);
        }

        return $this->recordFailure(self::QUEUE_TASK_KEY_PREFIX.$jobClass, $exception, [
            'task_name' => class_basename($jobClass),
            'task_type' => 'queue',
            'failed_job_uuid' => $failedJobUuid,
            'queue_connection' => $connection,
        ]);
    }

    public function canRerun(ScheduledTaskFailure $failure): bool
    {
        if ($failure->task_type === 'queue') {
            return $failure->failed_job_uuid !== null;
        }

        return $this->definition($failure->task_key) !== null;
    }

    public function recordFailureFromScheduleName(?string $scheduleName, Throwable $exception): ?ScheduledTaskFailure
    {
        $taskKey = $this->resolveTaskKey($scheduleName);
        if ($taskKey === null) {
            return null;
        }

        return $this->recordFailure($taskKey, $exception);
    }

    public function list(?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = ScheduledTaskFailure::query()->latest('failed_at');

        if ($status && in_array($status, ['failed', 'resolved', 'rerun_queued'], true)) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{failure: ScheduledTaskFailure, output: string}
     */
    public function rerun(ScheduledTaskFailure $failure, User $admin): array
    {
        if (! $this->canRerun($failure)) {
            throw new \InvalidArgumentException('This scheduled task cannot be re-run from the admin panel.');
        }

        $definition = $this->definition($failure->task_key);

        $failure->forceFill([
            'status' => 'rerun_queued',
        ])->save();

        if ($definition === null) {
            return [
                'failure' => $this->retryFailedQueueJob($failure),
                'output' => 'Failed queue job pushed back to the queue: '.$failure->task_name,
            ];
        }

        try {
            $output = $this->executeDefinition($definition);

            $failure->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $admin->id,
            ])->save();

            return [
                'failure' => $failure->fresh(),
                'output' => $output,
            ];
        } catch (Throwable $exception) {
            $this->recordFailure($failure->task_key, $exception);

            throw $exception;
        }
    }

    public function markResolved(ScheduledTaskFailure $failure, User $admin): ScheduledTaskFailure
    {
        $failure->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ])->save();

        return $failure->fresh();
    }

    public function sendAdminEmail(ScheduledTaskFailure $failure): void
    {
        $admins = User::query()
            ->role(['admin', 'super_admin'])
            ->get(['id', 'email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $siteName = MailSender::name();
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $adminUrl = $frontendUrl.'/admin/scheduled-tasks';
        $label = $failure->task_type === 'queue' ? 'Queue job failed' : 'Scheduled task failed';
        $subject = "{$label} — {$failure->task_name}";

        $html = view('emails.scheduled-task-failed-admin', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'taskName' => $failure->task_name,
            'taskKey' => $failure->task_key,
            'exceptionMessage' => $failure->exception_message,
            'occurrenceCount' => $failure->occurrence_count,
            'failedAt' => optional($failure->failed_at)?->timezone(config('app.timezone'))->toDayDateTimeString()
                ?? now()->timezone(config('app.timezone'))->toDayDateTimeString(),
            'adminUrl' => $adminUrl,
        ])->render();

        foreach ($admins as $admin) {
            try {
                Mail::html($html, function ($message) use ($admin, $subject, $siteName): void {
                    $message->to((string) $admin->email, (string) $admin->name)
                        ->subject($subject)
                        ->from(MailSender::address(), $siteName);
                });
            } catch (\Throwable) {
                // Keep failure recording running if admin mail transport fails.
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $taskKey): ?array
    {
        $tasks = config('scheduled_tasks', []);

        return is_array($tasks) && isset($tasks[$taskKey]) && is_array($tasks[$taskKey])
            ? $tasks[$taskKey]
            : null;
    }

    public function resolveTaskKey(?string $scheduleName): ?string
    {
        $name = trim((string) $scheduleName);
        if ($name === '') {
            return null;
        }

        if ($this->definition($name) !== null) {
            return $name;
        }

        foreach (config('scheduled_tasks', []) as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            if (($definition['job'] ?? null) === $name) {
                return (string) $key;
            }

            $command = trim((string) ($definition['command'] ?? ''));

            if ($command !== '' && ($command === $name || str_contains($name, $command))) {
                return (string) $key;
            }
        }

        return null;
    }

    private function notifyAdmins(ScheduledTaskFailure $failure): void
    {
        $this->userNotificationService->dispatchScheduledTaskFailedAdminNotifications($failure);
        SendScheduledTaskFailedAdminEmailJob::dispatch($failure->id);
    }

    /**
     * Pushes the original failed job back onto its queue. The row stays
     * `rerun_queued` until the worker either finishes it or fails it again.
     */
    private function retryFailedQueueJob(ScheduledTaskFailure $failure): ScheduledTaskFailure
    {
        try {
            $exitCode = Artisan::call('queue:retry', ['id' => [$failure->failed_job_uuid]]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'queue:retry failed.');
            }
        } catch (Throwable $exception) {
            $failure->forceFill(['status' => 'failed'])->save();

            throw $exception;
        }

        return $failure->fresh();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function executeDefinition(array $definition): string
    {
        $type = (string) ($definition['type'] ?? '');

        if ($type === 'job') {
            $jobClass = (string) ($definition['job'] ?? '');
            if ($jobClass === '' || ! class_exists($jobClass)) {
                throw new \InvalidArgumentException('Scheduled job class is not configured.');
            }

            $job = app($jobClass);
            Bus::dispatchSync($job);

            return 'Job dispatched synchronously: '.$jobClass;
        }

        if ($type === 'command') {
            $command = (string) ($definition['command'] ?? '');
            if ($command === '') {
                throw new \InvalidArgumentException('Scheduled command is not configured.');
            }

            Artisan::call($command);

            return trim(Artisan::output()) ?: 'Command completed: '.$command;
        }

        throw new \InvalidArgumentException('Unsupported scheduled task type.');
    }
}
