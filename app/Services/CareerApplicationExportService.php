<?php

namespace App\Services;

use App\Models\CareerApplication;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CareerApplicationExportService
{
    public function toCsv(Collection $applications): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="career-applications.csv"',
        ];

        return response()->stream(function () use ($applications): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Job',
                'Name',
                'Email',
                'Phone',
                'Status',
                'Resume',
                'Submitted At',
            ]);

            /** @var CareerApplication $application */
            foreach ($applications as $application) {
                fputcsv($handle, [
                    $application->id,
                    $application->job?->title,
                    $application->name,
                    $application->email,
                    $application->phone,
                    $application->status?->value ?? $application->status,
                    $application->resume_original_name,
                    $application->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
