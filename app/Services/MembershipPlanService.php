<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Collection;

class MembershipPlanService
{
    public function __construct(
        private MembershipPlan $membershipPlan
    ) {}

    public function getAll(): Collection
    {
        return $this->membershipPlan->all();
    }

    public function getTrashed(): Collection
    {
        return $this->membershipPlan->onlyTrashed()->get();
    }

    public function findById(int $id): MembershipPlan
    {
        return $this->membershipPlan->findOrFail($id);
    }

    public function create(array $data): MembershipPlan
    {
        return $this->membershipPlan->create($data);
    }

    public function update(array $data, int $id): MembershipPlan
    {
        $plan = $this->findById($id);
        $plan->update($data);
        return $plan->fresh();
    }

    // public function delete(int $id): bool
    // {
    //     $plan = $this->findById($id);

    //     $hasActiveSubscription = $plan->subscriptions()
    //         ->where('status', 'active')
    //         ->exists();

    //     if ($hasActiveSubscription) {
    //         throw new \Exception('This plan has active subscription, cannot be deleted.');
    //     }

    //     return $plan->delete();
    // }

    public function delete(int $id): bool
    {
        $plan = $this->findById($id);

        return $plan->delete();
    }

    public function restore(int $id): MembershipPlan
    {
        $plan = $this->membershipPlan
            ->onlyTrashed()
            ->findOrFail($id);

        $plan->restore();
        return $plan->fresh();
    }

    public function permanentDelete(int $id): bool
    {
        $plan = $this->membershipPlan
            ->onlyTrashed()
            ->findOrFail($id);

        return $plan->forceDelete();
    }
}
