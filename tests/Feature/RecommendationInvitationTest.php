<?php

namespace Tests\Feature;

use App\Console\Commands\PruneUnansweredRecommendationsCommand;
use App\Http\Controllers\RecommendationController;
use App\Mail\RecommendationInvitationMail;
use App\Mail\RecommendationReceivedMail;
use App\Models\Profile;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RecommendationInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('recommend:'.sha1('127.0.0.1'));
    }

    public function test_the_form_asks_for_an_email_and_does_not_preselect_consent_or_name_sharing(): void
    {
        $page = $this->get('/recommend');
        $page->assertOk()
            ->assertSee('Share my name', false)
            ->assertSee('The person you recommend will see your name along with your recommendation message if you select this option.', false)
            ->assertSee('I know this person and reasonably believe they would not mind being invited.', false)
            ->assertSee('30 days', false)
            ->assertSee(route('legal.privacy'), false)
            ->assertDontSee('name="recommender_contact"', false);

        $html = $page->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="share_name"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="acknowledged_terms"[^>]*checked/', $html);

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Deleted automatically after 30 days', false)
            ->assertSee('Share my name', false);
    }

    public function test_one_invitation_includes_the_message_and_the_name_only_when_sharing_is_selected(): void
    {
        Mail::fake();

        $this->post('/recommend', $this->payload([
            'recommender_name' => 'Deepa Menon',
            'recommender_contact' => 'deepa@example.com',
        ]))->assertRedirect(route('recommend.show'))
            ->assertSessionHas('recommendation_status', 'sent');

        $this->get('/recommend')
            ->assertOk()
            ->assertSee('Jannayaks has sent one invitation', false)
            ->assertSee('No profile or account has been created', false)
            ->assertDontSee('deepa@example.com', false)
            ->assertDontSee('ravi.chandran@example.test', false);

        $row = Recommendation::query()->sole();
        $this->assertFalse($row->share_recommender_name);
        $this->assertSame(RecommendationController::NOTICE_VERSION, $row->consent_notice_version);
        $this->assertTrue($row->acknowledged_terms);
        $this->assertSame('', $row->recommender_contact);
        $this->assertNotNull($row->invitation_sent_at);
        $this->assertNull($row->invitation_failed_at);
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Profile::query()->count());

        Mail::assertSent(RecommendationInvitationMail::class, function (RecommendationInvitationMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('ravi.chandran@example.test')
                && $mail->envelope()->from->address === config('mail.from.address')
                && $mail->envelope()->replyTo === []
                && str_contains($html, 'quiet community teaching')
                && str_contains($html, 'does not guarantee selection or inclusion')
                && str_contains($html, 'has not created a profile')
                && str_contains($html, 'does not subscribe you')
                && ! str_contains($html, 'Deepa Menon')
                && ! str_contains($html, 'deepa@example.com');
        });
        Mail::assertNotSent(RecommendationReceivedMail::class);

        Mail::fake();
        $this->post('/recommend', $this->payload([
            'recommender_name' => 'Deepa Menon',
            'share_name' => '1',
            'recommended_email' => 'ravi.shared@example.test',
        ]))->assertRedirect(route('recommend.show'));

        $shared = Recommendation::query()->where('recommended_email', 'ravi.shared@example.test')->firstOrFail();
        $this->assertTrue($shared->share_recommender_name);
        Mail::assertSent(RecommendationInvitationMail::class, function (RecommendationInvitationMail $mail): bool {
            return $mail->hasTo('ravi.shared@example.test')
                && str_contains($mail->render(), 'Deepa Menon')
                && str_contains($mail->render(), 'quiet community teaching');
        });
    }

    public function test_sharing_a_name_requires_a_usable_name_and_a_valid_email_is_required(): void
    {
        Mail::fake();

        $this->post('/recommend', $this->payload([
            'recommender_name' => '',
            'share_name' => '1',
        ]))->assertSessionHasErrors('recommender_name');

        $this->post('/recommend', $this->payload([
            'recommended_email' => 'not-an-email',
        ]))->assertSessionHasErrors('recommended_email');

        $this->post('/recommend', $this->payload([
            'recommended_email' => '',
        ]))->assertSessionHasErrors('recommended_email');

        Mail::assertNothingSent();
        $this->assertSame(0, Recommendation::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_failed_send_is_not_recorded_as_delivered_and_duplicates_are_refused(): void
    {
        $failedOnce = false;
        Event::listen(MessageSending::class, function () use (&$failedOnce): void {
            if ($failedOnce) {
                return;
            }
            $failedOnce = true;
            throw new \RuntimeException('smtp down');
        });

        $this->from('/recommend')->post('/recommend', $this->payload())
            ->assertRedirect('/recommend')
            ->assertSessionHasErrors('recommend');

        $failed = Recommendation::query()->sole();
        $this->assertNull($failed->invitation_sent_at);
        $this->assertNotNull($failed->invitation_failed_at);
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Profile::query()->count());

        Mail::fake();
        $this->post('/recommend', $this->payload([
            'recommended_email' => 'first.person@example.test',
        ]))->assertSessionHas('recommendation_status', 'sent');

        $this->from('/recommend')->post('/recommend', $this->payload([
            'recommended_email' => 'First.Person@example.test',
            'recommended_name' => 'Another Attempt',
        ]))->assertSessionHasErrors('recommended_email');

        Mail::assertSent(RecommendationInvitationMail::class, 1);
        $this->assertSame(1, Recommendation::query()->whereNotNull('invitation_sent_at')->count());
    }

    public function test_the_hourly_limit_still_blocks_a_fourth_invitation(): void
    {
        Mail::fake();

        foreach (['one@example.test', 'two@example.test', 'three@example.test'] as $email) {
            $this->post('/recommend', $this->payload([
                'recommended_email' => $email,
            ]))->assertSessionHas('recommendation_status', 'sent');
        }

        $this->from('/recommend')->post('/recommend', $this->payload([
            'recommended_email' => 'four@example.test',
        ]))->assertSessionHasErrors('recommend');

        Mail::assertSent(RecommendationInvitationMail::class, 3);
        $this->assertSame(0, Recommendation::query()->where('recommended_email', 'four@example.test')->count());
    }

    public function test_unanswered_recommendations_are_deleted_after_thirty_days(): void
    {
        $old = Recommendation::query()->create($this->storedRow('old.person@example.test'));
        $failed = Recommendation::query()->create($this->storedRow('failed.person@example.test'));
        $recent = Recommendation::query()->create($this->storedRow('recent.person@example.test'));
        $old->forceFill(['created_at' => now()->subDays(31), 'invitation_sent_at' => now()->subDays(31)])->save();
        $failed->forceFill([
            'created_at' => now()->subDays(31),
            'invitation_sent_at' => null,
            'invitation_failed_at' => now()->subDays(31),
        ])->save();

        $this->artisan('jannayaks:prune-unanswered-recommendations')->assertSuccessful();

        $this->assertDatabaseMissing('recommendations', ['id' => $old->id]);
        $this->assertDatabaseMissing('recommendations', ['id' => $failed->id]);
        $this->assertDatabaseHas('recommendations', ['id' => $recent->id]);
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Profile::query()->count());

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($scheduled) => str_contains((string) $scheduled->command, 'prune-unanswered-recommendations'));
        $this->assertNotNull($event);
        $this->assertSame(PruneUnansweredRecommendationsCommand::RETENTION_DAYS, 30);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recommender_name' => 'Deepa Menon',
            'recommended_name' => 'Ravi Chandran',
            'recommended_email' => 'ravi.chandran@example.test',
            'recommended_location' => 'Kollam',
            'recommended_role' => 'Teacher and youth mentor',
            'reason' => 'Two decades of quiet community teaching and library work in her town.',
            'acknowledged_terms' => '1',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRow(string $email): array
    {
        return [
            'recommender_name' => 'Deepa Menon',
            'recommender_contact' => '',
            'recommended_name' => 'Ravi Chandran',
            'recommended_email' => $email,
            'recommended_location' => 'Kollam',
            'reason' => 'Two decades of quiet community teaching and library work in her town.',
            'acknowledged_terms' => true,
            'share_recommender_name' => false,
            'consent_notice_version' => RecommendationController::NOTICE_VERSION,
            'ip_hash' => hash('sha256', '127.0.0.1'),
        ];
    }
}
