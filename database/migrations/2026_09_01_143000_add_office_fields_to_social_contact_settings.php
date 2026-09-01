<?php

use App\Support\SiteSocialContactSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $officeDefaults = [
        'contact_office_address' => "425 Fifth Avenue, Suite 1200\nNew York, NY 10016\nUnited States",
        'contact_office_maps_url' => 'https://maps.google.com/?q=425+Fifth+Avenue+Suite+1200+New+York+NY+10016',
    ];

    public function up(): void
    {
        DB::table('site_settings')
            ->select('id', 'social_contact_settings')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                $current = json_decode((string) ($row->social_contact_settings ?? ''), true);
                $merged = SiteSocialContactSettings::resolve(is_array($current) ? $current : null);

                foreach ($this->officeDefaults as $key => $value) {
                    if (! is_array($current) || ! array_key_exists($key, $current) || trim((string) ($current[$key] ?? '')) === '') {
                        $merged[$key] = $value;
                    }
                }

                DB::table('site_settings')
                    ->where('id', $row->id)
                    ->update(['social_contact_settings' => json_encode($merged)]);
            });
    }

    public function down(): void
    {
        // Non-destructive: office keys remain in JSON if rolled back.
    }
};
