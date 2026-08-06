<?php

namespace App\Http\Requests\Api\V1;

use App\Services\AdminNotificationPreferenceService;
use Illuminate\Foundation\Http\FormRequest;

class AdminNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['admin', 'super_admin']);
    }

    public function rules(): array
    {
        $events = array_keys(AdminNotificationPreferenceService::DEFAULTS);

        return [
            'admin_notification_email' => ['required', 'email', 'max:255'],
            'settings.*' => ['required', 'array:dashboard,email'],
            'settings.*.dashboard' => ['required', 'boolean'],
            'settings.*.email' => ['required', 'boolean'],
            'settings' => [
                'required',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($events): void {
                    if (! is_array($value) || array_diff($events, array_keys($value))) {
                        $fail('All notification event settings are required.');
                    }

                    foreach (array_keys(is_array($value) ? $value : []) as $event) {
                        if (! in_array($event, $events, true)) {
                            $fail("The {$event} notification event is not supported.");
                        }
                    }
                },
            ],
        ];
    }
}
