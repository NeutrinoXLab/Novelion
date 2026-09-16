<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterJob;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\NewsletterCampaign;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_without_subscribers_is_sent_without_remaining_in_sending(): void
    {
        $campaign = NewsletterCampaign::create([
            'subject' => 'Empty campaign',
            'title' => 'Empty',
            'content' => 'Test',
            'status' => 'draft',
        ]);

        $count = app(NewsletterService::class)->queueCampaign($campaign);

        $this->assertSame(0, $count);
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame(0, $campaign->fresh()->recipients_count);
        $this->assertNotNull($campaign->fresh()->sent_at);
    }

    public function test_queued_newsletter_is_not_sent_after_subscriber_unsubscribes(): void
    {
        Mail::fake();
        $campaign = NewsletterCampaign::create([
            'subject' => 'Queued campaign',
            'title' => 'Queued',
            'content' => 'Test',
            'status' => 'sending',
        ]);
        $subscriber = Newsletter::create([
            'email' => 'subscriber@example.test',
            'subscribed_at' => now(),
            'unsubscribe_token' => str_repeat('a', 64),
        ]);

        $job = new SendNewsletterJob($campaign, $subscriber);
        $subscriber->update(['unsubscribed_at' => now()]);
        $job->handle();

        Mail::assertNotSent(NewsletterMail::class);
    }
}
