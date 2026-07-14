<?php

namespace App\Services;

use App\Enums\ContactInquiryStatus;
use App\Jobs\SendContactInquiryReplyEmailJob;
use App\Models\ContactInquiry;
use App\Models\ContactInquiryReply;
use App\Models\User;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactInquiryService
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    public function store(array $data, Request $request): ContactInquiry
    {
        $inquiry = ContactInquiry::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'subject' => filled($data['subject'] ?? null) ? trim((string) $data['subject']) : null,
            'message' => trim($data['message']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => ContactInquiryStatus::NEW,
        ]);

        if (! empty($data['subscribe_newsletter'])) {
            try {
                $this->newsletterService->subscribe(
                    [
                        'email' => $inquiry->email,
                        'name' => $inquiry->name,
                        'source' => 'contact-form',
                    ],
                    $request->user(),
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $inquiry;
    }

    public function adminList(?string $status, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildFilteredQuery($status, $search)
            ->latest()
            ->paginate($perPage);
    }

    public function exportList(?string $status, ?string $search): Collection
    {
        return $this->buildFilteredQuery($status, $search)
            ->latest()
            ->get();
    }

    public function countNew(): int
    {
        return ContactInquiry::query()
            ->where('status', ContactInquiryStatus::NEW)
            ->count();
    }

    public function showAndMarkRead(int $id): ContactInquiry
    {
        $inquiry = ContactInquiry::query()
            ->with(['replies.user'])
            ->findOrFail($id);

        if (in_array($inquiry->status, [ContactInquiryStatus::NEW], true)) {
            $inquiry->update(['status' => ContactInquiryStatus::READ]);
            $inquiry->refresh();
        }

        return $inquiry;
    }

    public function markRead(ContactInquiry $inquiry): ContactInquiry
    {
        if ($inquiry->status === ContactInquiryStatus::REPLIED) {
            return $inquiry;
        }

        if ($inquiry->status === ContactInquiryStatus::ARCHIVED) {
            return $inquiry;
        }

        $inquiry->update(['status' => ContactInquiryStatus::READ]);

        return $inquiry->fresh(['replies.user']);
    }

    public function markUnread(ContactInquiry $inquiry): ContactInquiry
    {
        if ($inquiry->status === ContactInquiryStatus::REPLIED) {
            throw ValidationException::withMessages([
                'status' => ['Replied messages cannot be marked as unread.'],
            ]);
        }

        if ($inquiry->status === ContactInquiryStatus::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => ['Archived messages must be restored before marking as unread.'],
            ]);
        }

        $inquiry->update(['status' => ContactInquiryStatus::NEW]);

        return $inquiry->fresh(['replies.user']);
    }

    public function markReplied(ContactInquiry $inquiry): ContactInquiry
    {
        $inquiry->update([
            'status' => ContactInquiryStatus::REPLIED,
            'replied_at' => $inquiry->replied_at ?? now(),
        ]);

        return $inquiry->fresh(['replies.user']);
    }

    public function archive(ContactInquiry $inquiry): ContactInquiry
    {
        $inquiry->update(['status' => ContactInquiryStatus::ARCHIVED]);

        return $inquiry->fresh(['replies.user']);
    }

    public function restore(ContactInquiry $inquiry): ContactInquiry
    {
        if ($inquiry->status !== ContactInquiryStatus::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => ['Only archived messages can be restored.'],
            ]);
        }

        $status = $inquiry->replies()->exists()
            ? ContactInquiryStatus::REPLIED
            : ContactInquiryStatus::READ;

        $inquiry->update(['status' => $status]);

        return $inquiry->fresh(['replies.user']);
    }

    public function delete(ContactInquiry $inquiry): void
    {
        $inquiry->delete();
    }

    public function bulkAction(string $action, array $ids): int
    {
        $inquiries = ContactInquiry::query()->whereIn('id', $ids)->get();
        $count = 0;

        DB::transaction(function () use ($action, $inquiries, &$count): void {
            foreach ($inquiries as $inquiry) {
                match ($action) {
                    'mark_read' => $this->markRead($inquiry),
                    'mark_unread' => $this->markUnread($inquiry),
                    'mark_replied' => $this->markReplied($inquiry),
                    'archive' => $this->archive($inquiry),
                    'restore' => $this->restore($inquiry),
                    'delete' => $this->delete($inquiry),
                    default => throw ValidationException::withMessages([
                        'action' => ['Unsupported bulk action.'],
                    ]),
                };
                $count++;
            }
        });

        return $count;
    }

    public function reply(ContactInquiry $inquiry, User $admin, array $data): ContactInquiryReply
    {
        $reply = ContactInquiryReply::create([
            'contact_inquiry_id' => $inquiry->id,
            'user_id' => $admin->id,
            'subject' => trim($data['subject']),
            'body' => trim($data['body']),
            'sent_at' => now(),
        ]);

        $inquiry->update([
            'status' => ContactInquiryStatus::REPLIED,
            'replied_at' => now(),
        ]);

        SendContactInquiryReplyEmailJob::dispatch($reply->id);

        return $reply->load('user');
    }

    private function buildFilteredQuery(?string $status, ?string $search): Builder
    {
        $query = ContactInquiry::query();

        if ($status && in_array($status, ContactInquiryStatus::filterable(), true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        }

        return $query;
    }
}
