<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\NewsletterCampaign;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendNewsletterJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public NewsletterCampaign $campaign,
        public Newsletter $subscriber,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $subscriber = Newsletter::query()->find($this->subscriber->id);

        if (! $subscriber || $subscriber->unsubscribed_at !== null || ! $subscriber->confirmed_at) {
            return;
        }

        Mail::to($subscriber->email)->send(
            new NewsletterMail(
                $this->campaign,
                $subscriber
            )
        );
    }
}
