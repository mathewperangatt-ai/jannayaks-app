<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHostingDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $application = $this->route('application');

        if (! $user instanceof User || ! $application instanceof Application) {
            return false;
        }

        return (int) $application->user_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hosting_destination' => [
                'required',
                'string',
                Rule::in(Application::hostingDestinations()),
            ],
            'profile_id' => ['prohibited'],
            'application_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hosting_destination.required' => 'Choose where your public profile should be hosted.',
            'hosting_destination.in' => 'Choose either Jannayaks or IconsHerald as the hosting destination.',
        ];
    }
}
