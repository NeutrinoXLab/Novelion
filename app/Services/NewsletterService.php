<?php

namespace App\Services;

use App\Jobs\SendNewsletterJob;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\NewsletterCampaign;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

class NewsletterService
{
    /**
     * Trimite o campanie către un singur email pentru test.
     */
    public function sendTest(
        NewsletterCampaign $campaign,
        string $email
    ): void {
        $subscriber = Newsletter::where('email', $email)
            ->whereNull('unsubscribed_at')
            ->first();

        if (! $subscriber) {
            $subscriber = new Newsletter([
                'email' => $email,
                'unsubscribe_token' => bin2hex(random_bytes(32)),
            ]);

            $subscriber->subscribed_at = now();
        }

        Mail::to($email)->send(
            new NewsletterMail($campaign, $subscriber)
        );
    }

    /**
     * Pune newsletterul în Queue pentru toți abonații activi.
     */
    public function queueCampaign(NewsletterCampaign $campaign): int
    {
        $subscribers = Newsletter::whereNull('unsubscribed_at')
            ->whereNotNull('confirmed_at')
            ->get();

        $jobs = [];

        foreach ($subscribers as $subscriber) {
            $jobs[] = new SendNewsletterJob(
                $campaign,
                $subscriber
            );
        }

        if (count($jobs) === 0) {
            $campaign->update([
                'status' => 'sent',
                'recipients_count' => 0,
                'sent_at' => now(),
            ]);

            return 0;
        }

        $campaign->update([
            'status' => 'sending',
            'recipients_count' => count($jobs),
        ]);

        Bus::batch($jobs)
            ->name('Newsletter: '.$campaign->subject)
            ->then(function () use ($campaign): void {
                $campaign->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            })
            ->catch(function () use ($campaign): void {
                $campaign->update([
                    'status' => 'failed',
                ]);
            })
            ->dispatch();

        return count($jobs);
    }
}
