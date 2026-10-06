<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('emails:deliver')->everyMinute()->withoutOverlapping(10);

Schedule::command('stripe:reconcile')->everyFiveMinutes()->withoutOverlapping(10)
    ->when(fn () => config('stripe_reconciliation.enabled') === true);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
