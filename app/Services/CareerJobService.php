<?php

namespace App\Services;

use App\Enums\CareerJobStatus;
use App\Models\CareerJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CareerJobService
{
    public function publicList(?string $search, ?string $department, ?string $type): Collection
    {
        $query = CareerJob::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title');

        $this->applyFilters($query, $search, $department, $type, null);

        return $query->get();
    }

    public function adminList(
        ?string $status,
        ?string $department,
        ?string $search,
        bool $trashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = CareerJob::query()
            ->when($trashed, fn (Builder $q) => $q->onlyTrashed())
            ->orderBy('sort_order')
            ->orderByDesc('id');

        $this->applyFilters($query, $search, $department, null, $status);

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id, bool $withTrashed = false): CareerJob
    {
        $query = CareerJob::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    public function create(array $data): CareerJob
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['status'] = $data['status'] ?? CareerJobStatus::DRAFT->value;

        if (($data['status'] ?? null) === CareerJobStatus::PUBLISHED->value) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        return CareerJob::query()->create($data);
    }

    public function update(CareerJob $job, array $data): CareerJob
    {
        if (isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $job->id);
        }

        if (($data['status'] ?? null) === CareerJobStatus::PUBLISHED->value && ! $job->published_at) {
            $data['published_at'] = now();
        }

        $job->update($data);

        return $job->fresh();
    }

    public function delete(CareerJob $job): void
    {
        $job->delete();
    }

    public function restore(int $id): CareerJob
    {
        $job = CareerJob::onlyTrashed()->findOrFail($id);
        $job->restore();

        return $job->fresh();
    }

    public function forceDelete(int $id): void
    {
        CareerJob::onlyTrashed()->findOrFail($id)->forceDelete();
    }

    private function applyFilters(
        Builder $query,
        ?string $search,
        ?string $department,
        ?string $type,
        ?string $status,
    ): void {
        if ($status) {
            $query->where('status', $status);
        }

        if ($department && $department !== 'All') {
            $query->where('department', $department);
        }

        if ($type && $type !== 'All Types') {
            $query->where('employment_type', $type);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('department', 'like', $like)
                    ->orWhere('employment_type', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'job';
        $slug = $base;
        $i = 1;

        while (
            CareerJob::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
