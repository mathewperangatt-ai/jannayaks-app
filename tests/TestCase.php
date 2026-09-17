<?php

namespace Tests;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            $stateCount = DB::table('geo_states')->count();
            if ($stateCount === 0) {
                $this->seed(\Database\Seeders\GeographySeeder::class);
            }
        } catch (\Throwable) {
            // Table may not exist yet on first run; ignore.
        }
    }

    protected function unlockApplicationForInterview(Application $application): Application
    {
        $application->forceFill([
            'payment_status' => Application::PAYMENT_STATUS_PAID,
            'payment_settled_at' => now(),
            'status' => Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW,
        ])->save();

        Payment::query()->create([
            'application_id' => $application->id,
            'transaction_reference' => 'TEST-SETTLED-'.$application->id.'-'.Str::upper(Str::random(8)),
            'gateway' => Payment::GATEWAY_MANUAL,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '3000.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'event_type' => 'application_package',
        ]);

        return $application->fresh() ?? $application;
    }
}
