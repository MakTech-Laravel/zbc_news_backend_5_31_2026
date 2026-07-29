<?php

namespace App\Services;

use App\Enums\CareerApplicationStatus;
use App\Enums\CareerJobStatus;
use App\Models\CareerApplication;
use App\Models\CareerJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CareerApplicationService
{
    public function store(array $data, UploadedFile $resume, Request $request): CareerApplication
    {
        $job = CareerJob::query()->findOrFail($data['career_job_id']);

        if ($job->status !== CareerJobStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'career_job_id' => ['This position is not accepting applications.'],
            ]);
        }

        $path = $resume->store('career-resumes', 'local');

        return CareerApplication::query()->create([
            'career_job_id' => $job->id,
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'cover_letter' => filled($data['cover_letter'] ?? null) ? trim((string) $data['cover_letter']) : null,
            'resume_path' => $path,
            'resume_original_name' => $resume->getClientOriginalName(),
            'resume_mime' => $resume->getClientMimeType(),
            'resume_size' => $resume->getSize(),
            'status' => CareerApplicationStatus::NEW,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function adminList(
        ?string $status,
        ?string $search,
        ?int $jobId,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->buildFilteredQuery($status, $search, $jobId)
            ->with('job:id,title,slug,department')
            ->latest()
            ->paginate($perPage);
    }

    public function exportList(?string $status, ?string $search, ?int $jobId): Collection
    {
        return $this->buildFilteredQuery($status, $search, $jobId)
            ->with('job:id,title,slug,department')
            ->latest()
            ->get();
    }

    public function showAndMarkReviewed(int $id): CareerApplication
    {
        $application = CareerApplication::query()
            ->with('job')
            ->findOrFail($id);

        if ($application->status === CareerApplicationStatus::NEW) {
            $application->update(['status' => CareerApplicationStatus::REVIEWED]);
            $application->refresh();
        }

        return $application->load('job');
    }

    public function updateStatus(CareerApplication $application, string $status): CareerApplication
    {
        if (! in_array($status, CareerApplicationStatus::filterable(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid application status.'],
            ]);
        }

        $application->update(['status' => $status]);

        return $application->fresh(['job']);
    }

    public function delete(CareerApplication $application): void
    {
        if ($application->resume_path && Storage::disk('local')->exists($application->resume_path)) {
            Storage::disk('local')->delete($application->resume_path);
        }

        $application->delete();
    }

    public function bulkAction(string $action, array $ids): int
    {
        $applications = CareerApplication::query()->whereIn('id', $ids)->get();
        $count = 0;

        DB::transaction(function () use ($action, $applications, &$count): void {
            foreach ($applications as $application) {
                match ($action) {
                    'mark_reviewed' => $this->updateStatus($application, CareerApplicationStatus::REVIEWED->value),
                    'shortlist' => $this->updateStatus($application, CareerApplicationStatus::SHORTLISTED->value),
                    'reject' => $this->updateStatus($application, CareerApplicationStatus::REJECTED->value),
                    'archive' => $this->updateStatus($application, CareerApplicationStatus::ARCHIVED->value),
                    'delete' => $this->delete($application),
                    default => throw ValidationException::withMessages([
                        'action' => ['Unsupported bulk action.'],
                    ]),
                };
                $count++;
            }
        });

        return $count;
    }

    public function downloadResume(CareerApplication $application): StreamedResponse
    {
        if (! $application->resume_path || ! Storage::disk('local')->exists($application->resume_path)) {
            abort(404, 'Resume file not found.');
        }

        return Storage::disk('local')->download(
            $application->resume_path,
            $application->resume_original_name,
        );
    }

    private function buildFilteredQuery(?string $status, ?string $search, ?int $jobId): Builder
    {
        $query = CareerApplication::query();

        if ($status && in_array($status, CareerApplicationStatus::filterable(), true)) {
            $query->where('status', $status);
        }

        if ($jobId) {
            $query->where('career_job_id', $jobId);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('cover_letter', 'like', $like);
            });
        }

        return $query;
    }
}
