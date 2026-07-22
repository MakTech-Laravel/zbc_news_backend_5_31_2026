<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;
use Throwable;

trait NormalizesDatetimeInput
{
    /**
     * Normalize inbound datetimes to a UTC `Y-m-d H:i:s` string.
     *
     * ISO values with Z/offset are converted to UTC. Naive values are
     * interpreted in the app timezone (UTC), matching stored schedule times.
     */
    private function normalizeDatetimeInput(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        try {
            return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $fallback = trim(str_replace('T', ' ', $value));

            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $fallback)) {
                return $fallback.':00';
            }

            return $fallback;
        }
    }
}
