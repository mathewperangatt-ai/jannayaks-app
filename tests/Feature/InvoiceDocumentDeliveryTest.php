<?php

namespace Tests\Feature;

use App\Mail\PaymentDocumentMail;
use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceDeliveryService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Part 15 (pre-launch refinement): PDF generation, authenticated document
 * downloads, and idempotent document-email delivery. Payment confirmations
 * are exercised through the existing settlement mechanisms with fakes only;
 * no live gateway calls are made.
 */
class InvoiceDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_pdf_builds_and_downloads_for_owner(): void
    {
        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $payment->refresh();

        $pdf = app(InvoicePdfService::class);
        $built = $pdf->build($payment->fresh(), InvoicePdfService::KIND_RECEIPT);
        $this->assertStringStartsWith('%PDF', $built['bytes']);
        $this->assertNotEmpty($built['filename']);

        $response = $this->actingAs($user)->get(route('payments.document.pdf', [$payment->fresh(), 'receipt']));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_tax_invoice_pdf_is_refused_without_seller_configuration(): void
    {
        [$user, $payment] = $this->seedSettledApplicationPayment();
        config(['jannayaks.tier_pricing.billing.legal_name' => '', 'jannayaks.tier_pricing.billing.gstin' => '']);
        app(InvoiceService::class)->assignReceiptReference($payment);
        $payment->refresh();

        $pdf = app(InvoicePdfService::class);
        // No invalid tax invoice is issued; the payment receipt stays available.
        $this->assertFalse($pdf->isAvailable($payment->fresh(), InvoicePdfService::KIND_TAX_INVOICE));
        $this->assertTrue($pdf->isAvailable($payment->fresh(), InvoicePdfService::KIND_RECEIPT));
        $this->assertNotNull($payment->fresh()->invoice_number);
    }

    public function test_tax_invoice_pdf_is_issued_when_seller_details_are_configured(): void
    {
        config([
            'jannayaks.tier_pricing.billing.legal_name' => 'Aurex Network',
            'jannayaks.tier_pricing.billing.gstin' => '32ABCDE1234F1Z5',
            'jannayaks.tier_pricing.billing.address' => '3-532A, Manchadi Jn, Thiruvananthapuram, Kerala 695573',
            'jannayaks.tier_pricing.billing.state' => 'Kerala',
        ]);
        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $payment->refresh();

        $pdf = app(InvoicePdfService::class);
        $built = $pdf->build($payment->fresh(), InvoicePdfService::KIND_TAX_INVOICE);
        $this->assertStringStartsWith('%PDF', $built['bytes']);
    }

    public function test_document_pdf_download_denied_to_other_users_and_guests(): void
    {
        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);

        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get(route('payments.document.pdf', [$payment->fresh(), 'receipt']))->assertForbidden();
        $this->get(route('payments.document.pdf', [$payment->fresh(), 'receipt']))->assertForbidden();
    }

    public function test_admin_pdf_download_requires_finance_role(): void
    {
        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);

        $nonFinanceAdmin = User::factory()->create(['email_verified_at' => now()]);
        $financeAdmin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->actingAs($nonFinanceAdmin)->get(route('admin.payments.document.pdf', [$payment->fresh(), 'receipt']))->assertForbidden();
        $this->actingAs($financeAdmin)->get(route('admin.payments.document.pdf', [$payment->fresh(), 'receipt']))->assertOk();
    }

    public function test_settlement_documents_email_exactly_once_and_repeats_are_idempotent(): void
    {
        config(['jannayaks.tier_pricing.billing.legal_name' => 'Aurex Network', 'jannayaks.tier_pricing.billing.gstin' => '32ABCDE1234F1Z5']);
        Mail::fake();

        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $payment->refresh();

        $delivery = app(InvoiceDeliveryService::class);
        $this->assertTrue($delivery->sendSettlementDocuments($payment));
        // A repeat (or retried webhook) hits the idempotence guard: no second email.
        $this->assertFalse($delivery->sendSettlementDocuments($payment->fresh()));
        $this->assertFalse($delivery->sendSettlementDocuments($payment->fresh()));

        Mail::assertSentTimes(PaymentDocumentMail::class, 1);
        $payment->refresh();
        $this->assertNotNull($payment->invoice_emailed_at);
    }

    public function test_disabled_document_delivery_keeps_issued_documents_retryable(): void
    {
        config(['jannayaks.tier_pricing.billing.document_emails' => false]);
        Mail::fake();

        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $payment->refresh();

        $delivery = app(InvoiceDeliveryService::class);
        $this->assertFalse($delivery->sendSettlementDocuments($payment));
        Mail::assertNothingSent();

        // Documents remain issued and available for a later, configured retry.
        $payment->refresh();
        $this->assertNotNull($payment->invoice_number);
        $this->assertNull($payment->invoice_emailed_at);

        config(['jannayaks.tier_pricing.billing.document_emails' => true]);
        $this->assertTrue($delivery->sendSettlementDocuments($payment->fresh()));
    }

    public function test_credit_note_pdf_builds_after_recorded_refund(): void
    {
        config(['jannayaks.tier_pricing.billing.legal_name' => 'Aurex Network', 'jannayaks.tier_pricing.billing.gstin' => '32ABCDE1234F1Z5']);
        [$user, $payment] = $this->seedSettledApplicationPayment();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $payment->forceFill([
            'status' => Payment::STATUS_REFUNDED,
            'refund_amount' => $payment->amount,
            'refunded_at' => now(),
            'refund_note' => 'Application rejected before publication.',
        ])->save();

        $pdf = app(InvoicePdfService::class);
        $built = $pdf->build($payment->fresh(), InvoicePdfService::KIND_CREDIT_NOTE);
        $this->assertStringStartsWith('%PDF', $built['bytes']);

        $owner = $user;
        $this->actingAs($owner)->get(route('payments.document.pdf', [$payment->fresh(), 'credit-note']))->assertOk();
    }

    /**
     * Seeds an owner + a settled application payment with a registered email.
     *
     * @return array{0: User, 1: Payment}
     */
    private function seedSettledApplicationPayment(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($user)->paid()->create([
            'package_tier' => 'emerging',
            'preferred_contact_email' => 'billing.example@example.com',
        ]);
        $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();

        return [$user, $payment];
    }
}
