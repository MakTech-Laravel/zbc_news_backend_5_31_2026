<?php

namespace App\Services;

use App\Models\AdSlot;
use App\Models\AdSlotEvent;
use App\Models\NewsletterSubscriber;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MonetizationAnalyticsService
{
    public function track(string $slotKey, string $eventType, Request $request): bool
    {
        if (! in_array($eventType, ['impression', 'click'], true)) {
            return false;
        }

        $slot = AdSlot::query()
            ->where('slot_key', $slotKey)
            ->where('is_active', true)
            ->first();

        if (! $slot) {
            return false;
        }

        $sessionId = (string) $request->input('session_id', '');

        if ($eventType === 'impression' && $sessionId !== '') {
            $alreadyTracked = AdSlotEvent::query()
                ->where('ad_slot_id', $slot->id)
                ->where('event_type', 'impression')
                ->where('session_id', $sessionId)
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadyTracked) {
                return false;
            }
        }

        $revenueCents = 0;
        if ($eventType === 'impression') {
            $revenueCents = (int) round(config('monetization.cpm_cents', 200) / 1000);
        }

        AdSlotEvent::query()->create([
            'ad_slot_id' => $slot->id,
            'event_type' => $eventType,
            'revenue_cents' => $revenueCents,
            'session_id' => $sessionId !== '' ? $sessionId : null,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        return true;
    }

    public function getOverview(): array
    {
        $todayStart = now()->startOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();
        $weekStart = now()->startOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();
        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $todayRevenue = $this->sumRevenue($todayStart, now());
        $yesterdayRevenue = $this->sumRevenue($yesterdayStart, $yesterdayEnd);
        $weekRevenue = $this->sumRevenue($weekStart, now());
        $lastWeekRevenue = $this->sumRevenue($lastWeekStart, $lastWeekEnd);

        $monthImpressions = $this->countEvents('impression', $monthStart, now());
        $lastMonthImpressions = $this->countEvents('impression', $lastMonthStart, $lastMonthEnd);

        $monthClicks = $this->countEvents('click', $monthStart, now());
        $lastMonthClicks = $this->countEvents('click', $lastMonthStart, $lastMonthEnd);

        $ctr = $monthImpressions > 0 ? round(($monthClicks / $monthImpressions) * 100, 2) : 0.0;
        $lastCtr = $lastMonthImpressions > 0
            ? round(($lastMonthClicks / $lastMonthImpressions) * 100, 2)
            : 0.0;

        return [
            'metrics' => [
                'today_revenue' => $this->metricPayload($todayRevenue, $yesterdayRevenue, 'currency'),
                'week_revenue' => $this->metricPayload($weekRevenue, $lastWeekRevenue, 'currency'),
                'total_impressions' => $this->metricPayload($monthImpressions, $lastMonthImpressions, 'count'),
                'average_ctr' => $this->metricPayload($ctr, $lastCtr, 'percent'),
            ],
            'monthly_earnings' => $this->getMonthlyEarnings(),
            'weekly_performance' => $this->getWeeklyPerformance(),
            'placements' => $this->getPlacementStats(),
        ];
    }

    public function getMonthlyEarnings(): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->startOfMonth());
        }

        $subscriberValue = (int) config('monetization.newsletter_subscriber_value_cents', 500);

        return $months->map(function (Carbon $monthStart) use ($subscriberValue) {
            $monthEnd = $monthStart->copy()->endOfMonth();

            $adRevenueCents = (int) AdSlotEvent::query()
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('revenue_cents');

            $newSubscribers = NewsletterSubscriber::query()
                ->where('status', 'verified')
                ->whereBetween('verified_at', [$monthStart, $monthEnd])
                ->count();

            $subscriptionRevenueCents = $newSubscribers * $subscriberValue;

            return [
                'label' => $monthStart->format('M'),
                'ad_revenue_cents' => $adRevenueCents,
                'subscription_revenue_cents' => $subscriptionRevenueCents,
            ];
        })->values()->all();
    }

    public function getWeeklyPerformance(): array
    {
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $days->push(now()->subDays($i)->startOfDay());
        }

        return $days->map(function (Carbon $dayStart) {
            $dayEnd = $dayStart->copy()->endOfDay();

            $impressions = $this->countEvents('impression', $dayStart, $dayEnd);
            $revenueCents = $this->sumRevenue($dayStart, $dayEnd);

            return [
                'label' => $dayStart->format('D'),
                'impressions' => $impressions,
                'revenue_cents' => $revenueCents,
            ];
        })->values()->all();
    }

    public function getPlacementStats(): array
    {
        $slots = AdSlot::query()->orderBy('slot_key')->get();

        return $slots->map(function (AdSlot $slot) {
            $impressions = AdSlotEvent::query()
                ->where('ad_slot_id', $slot->id)
                ->where('event_type', 'impression')
                ->count();

            $clicks = AdSlotEvent::query()
                ->where('ad_slot_id', $slot->id)
                ->where('event_type', 'click')
                ->count();

            $revenueCents = (int) AdSlotEvent::query()
                ->where('ad_slot_id', $slot->id)
                ->sum('revenue_cents');

            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;

            return [
                'id' => $slot->id,
                'slot_key' => $slot->slot_key,
                'name' => $slot->name,
                'placement' => $slot->placement,
                'is_active' => $slot->is_active,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
                'revenue_cents' => $revenueCents,
            ];
        })->values()->all();
    }

    private function sumRevenue(Carbon $from, Carbon $to): int
    {
        return (int) AdSlotEvent::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('revenue_cents');
    }

    private function countEvents(string $eventType, Carbon $from, Carbon $to): int
    {
        return AdSlotEvent::query()
            ->where('event_type', $eventType)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function metricPayload(float|int $current, float|int $previous, string $format): array
    {
        $trendPercent = $this->percentChange($current, $previous);

        return [
            'value' => $current,
            'previous' => $previous,
            'trend_percent' => $trendPercent,
            'trend_direction' => $trendPercent < 0 ? 'down' : 'up',
            'format' => $format,
        ];
    }

    private function percentChange(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
