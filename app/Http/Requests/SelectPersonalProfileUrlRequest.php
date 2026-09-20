<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\User;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SelectPersonalProfileUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $application = $this->route('application');

        if (! $user instanceof User || ! $application instanceof Application) {
            return false;
        }

        // Ownership only — never trust a hidden profile_id from the browser.
        return (int) $application->user_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64'],
            // Explicitly ignore any client-supplied ownership fields.
            'profile_id' => ['prohibited'],
            'application_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Application $application */
            $application = $this->route('application');
            $urls = app(ProfileUrlService::class);

            if (! $urls->packageTierAllowsPersonalSlug((string) $application->package_tier)) {
                $validator->errors()->add('slug', 'Your membership tier cannot choose a personal URL.');

                return;
            }

            $result = $urls->validatePersonalSlugCandidate(
                (string) $this->input('slug'),
                $application->profile_id ? (int) $application->profile_id : null,
                (string) ($application->profile?->full_name ?: $application->full_name),
                $application->profile?->profession,
            );

            if (! $result['ok']) {
                $validator->errors()->add('slug', $result['error'] ?? 'Invalid personal URL.');
            }
        });
    }
}
