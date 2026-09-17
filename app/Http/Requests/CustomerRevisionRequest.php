<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRevisionRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        // Server owns revision accounting — ignore client-supplied counters.
        $this->getInputSource()->remove('round_number');
        $this->getInputSource()->remove('included_revision_rounds_used');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_type' => [
                'required',
                'string',
                Rule::in([
                    EditorialRevisionRequest::TYPE_REVISION,
                    EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION,
                ]),
            ],
            'request_text' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
