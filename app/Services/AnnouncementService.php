<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AnnouncementService
{
    public function __construct(
        private readonly UserNotificationService $notificationService,
    ) {}

    public function list(): Collection
    {
        return Announcement::query()
            ->with('author:id,name')
            ->latest()
            ->get();
    }

    public function create(User $user, array $data): Announcement
    {
        return Announcement::query()->create([
            ...$data,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        if ($announcement->status === 'published') {
            unset($data['audience']);
        }

        $announcement->update($data);

        return $announcement->fresh();
    }

    public function publish(Announcement $announcement): Announcement
    {
        $announcement->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->notificationService->dispatchAnnouncementNotifications($announcement->fresh());

        return $announcement->fresh();
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
    }

    public function findOrFail(int $id): Announcement
    {
        return Announcement::query()->with('author:id,name')->findOrFail($id);
    }
}
