<?php

namespace App\Console\Commands;

use App\Models\TransactionalEmail;
use App\Services\TransactionalEmails;
use Illuminate\Console\Command;

class SendTransactionalEmails extends Command
{
    protected $signature = 'emails:deliver {--limit=50} {--status : Inspect backlog without sending} {--retry= : Reset one failed delivery by ID}';

    protected $description = 'Deliver persisted transactional emails independently of business requests';

    public function handle(TransactionalEmails $delivery): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! $this->option('status') && ($limit === false || $limit < 1 || $limit > 500)) {
            $this->error('Limit must be between 1 and 500.');

            return self::FAILURE;
        }

        if ($this->option('retry') !== null) {
            if ($this->option('status') || ! ctype_digit((string) $this->option('retry'))) {
                $this->error('Use --retry with a numeric failed delivery ID, without --status.');

                return self::FAILURE;
            }
            $reset = TransactionalEmail::query()->whereKey($this->option('retry'))
                ->whereNull('sent_at')->whereNotNull('failed_at')
                ->update(['attempts' => 0, 'failed_at' => null, 'next_attempt_at' => now(), 'last_error_type' => null]);
            if (! $reset) {
                $this->error('No failed, unsent delivery with that ID.');

                return self::FAILURE;
            }
        }

        if (! $this->option('status')) {
            $ids = TransactionalEmail::query()->whereNull('sent_at')->whereNull('failed_at')
                ->where('next_attempt_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
            foreach ($ids as $id) {
                $delivery->deliver($id);
            }
        }

        $pending = TransactionalEmail::query()->whereNull('sent_at')->whereNull('failed_at')->count();
        $failed = TransactionalEmail::query()->whereNotNull('failed_at')->count();
        $overdue = TransactionalEmail::query()->whereNull('sent_at')->whereNull('failed_at')
            ->where('next_attempt_at', '<=', now()->subMinutes(10))->count();
        $this->info("Pending: {$pending}; failed: {$failed}; overdue by 10 minutes: {$overdue}");

        return $failed || $overdue ? self::FAILURE : self::SUCCESS;
    }
}
