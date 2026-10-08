<?php

namespace App\Services;

use App\Models\DailyVisit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class VisitorAnalytics
{
    public const TIMEZONE = 'Europe/Bucharest';

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public function hashIp(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        // IPv4 and IPv4-mapped IPv6 must represent the same address.
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Visitor hashing key is unavailable.');
        }

        return hash_hmac('sha256', "novelion:daily-visitors:v1\0".$packed, $key);
    }

    public function record(string $ip): void
    {
        $hash = $this->hashIp($ip);
        if ($hash === null) {
            return;
        }
        $day = $this->day();
        $key = 'daily-visit:v1:'.$day->toDateString().':'.$hash;
        try {
            if (Cache::get($key) === true) {
                return;
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        // Unique constraint is authoritative, including concurrent cache misses.
        DailyVisit::query()->insertOrIgnore(['visit_date' => $day->toDateString(), 'ip_hash' => $hash]);
        try {
            Cache::put($key, true, $day->addDay());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function summary(): array
    {
        $today = $this->day();
        $days = DailyVisit::query()->whereBetween('visit_date', [$today->subDays(29)->toDateString(), $today->toDateString()])
            ->selectRaw('visit_date, COUNT(*) AS total')->groupBy('visit_date')->pluck('total', 'visit_date');
        $seven = 0;
        for ($i = 0; $i < 7; $i++) {
            $seven += (int) ($days[$today->subDays($i)->toDateString()] ?? 0);
        }

        return ['today' => (int) ($days[$today->toDateString()] ?? 0),
            'yesterday' => (int) ($days[$today->subDay()->toDateString()] ?? 0),
            'seven' => $seven, 'thirty' => (int) $days->sum(),
            'total' => DailyVisit::query()->where('visit_date', '<=', $today->toDateString())->count(), 'days' => $days];
    }
}
