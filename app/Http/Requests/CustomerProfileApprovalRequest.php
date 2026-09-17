<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;

class CustomerProfileApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');
        if (! $application instanceof Application) {
            return false;
        }

        return $this->user() !== null
            && (int) $application->user_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'english_editorial_content_id' => ['required', 'integer'],
            'confirm_approval' => ['accepted'],
            // Block mass-assignment style status/payment tampering via the form.
            'status' => ['prohibited'],
            'application_id' => ['prohibited'],
            'profile_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_approval.accepted' => 'Please confirm that you are approving this profile for publication.',
        ];
    }
}
