<?php

namespace App\Filament\Resources\InMemoriamProfiles\Pages;

use App\Filament\Resources\InMemoriamProfiles\InMemoriamProfileResource;
use App\Models\InMemoriamProfile;
use App\Models\User;
use App\Services\InMemoriamUrlService;
use App\Services\StaffAuditLogger;
use App\Support\PricingAmounts;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateInMemoriamProfile extends CreateRecord
{
    protected static string $resource = InMemoriamProfileResource::class;

    private ?string $pendingSlug = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = $data['status'] ?? InMemoriamProfile::STATUS_DRAFT;
        $data['verification_status'] = $data['verification_status'] ?? InMemoriamProfile::VERIFICATION_UNVERIFIED;
        $data['is_sealed'] = false;

        $pricing = PricingAmounts::forInMemoriam5yr();
        $data['commission_amount'] = PricingAmounts::paiseToDecimalString((int) $pricing['base_paise']);
        $data['commission_gst_amount'] = PricingAmounts::paiseToDecimalString((int) $pricing['gst_paise']);
        $data['commission_currency'] = PricingAmounts::CURRENCY;

        $slug = $data['slug'] ?? null;
        unset($data['slug']);
        $this->pendingSlug = filled($slug) ? (string) $slug : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var InMemoriamProfile $record */
        $record = $this->getRecord();
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return;
        }

        app(StaffAuditLogger::class)->log(
            action: 'in_memoriam.created',
            subject: $record,
            before: null,
            after: [
                'status' => $record->status,
                'deceased_full_name' => $record->deceased_full_name,
            ],
            actor: $actor,
        );

        if (filled($this->pendingSlug)) {
            try {
                app(InMemoriamUrlService::class)->assignSlug($record, $this->pendingSlug, $actor);
            } catch (ValidationException $e) {
                report($e);
            }
        }
    }
}
