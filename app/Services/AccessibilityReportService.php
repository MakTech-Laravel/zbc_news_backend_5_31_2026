<?php

namespace App\Services;

use App\Models\AccessibilityReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AccessibilityReportService
{
    public function store(array $data, Request $request): AccessibilityReport
    {
        return AccessibilityReport::query()->create([
            'issue' => trim($data['issue']),
            'page_url' => filled($data['page_url'] ?? null) ? trim((string) $data['page_url']) : null,
            'email' => filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null,
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function adminList(?string $status, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return AccessibilityReport::query()
            ->when($status && in_array($status, ['new', 'reviewed', 'resolved'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search, function ($query) use ($search) {
                $needle = trim($search);
                $query->where(function ($inner) use ($needle) {
                    $inner->where('issue', 'like', "%{$needle}%")
                        ->orWhere('email', 'like', "%{$needle}%")
                        ->orWhere('page_url', 'like', "%{$needle}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function updateStatus(AccessibilityReport $report, string $status): AccessibilityReport
    {
        $report->status = $status;
        $report->resolved_at = $status === 'resolved' ? now() : null;
        $report->save();

        return $report->fresh();
    }
}
