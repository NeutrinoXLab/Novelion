<?php

namespace App\Console\Commands;

use App\Services\StripeReconciler;
use Illuminate\Console\Command;

class ReconcileStripe extends Command
{
    protected $signature = 'stripe:reconcile';

    protected $description = 'Recover Stripe state through validated GET snapshots (disabled by default).';

    public function handle(StripeReconciler $reconciler): int
    {
        if (config('stripe_reconciliation.enabled') !== true) {
            $this->info('Stripe reconciliation disabled.');

            return self::SUCCESS;
        }
        $this->line(json_encode($reconciler->run(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
