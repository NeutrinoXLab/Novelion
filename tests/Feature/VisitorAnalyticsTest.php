<?php

namespace Tests\Feature;

use App\Filament\Widgets\VisitsChart;
use App\Filament\Widgets\VisitsOverview;
use App\Models\DailyVisit;
use App\Models\Visit;
use App\Services\VisitorAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisitorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Request::setTrustedProxies([], 0);
        parent::tearDown();
    }

    public function test_public_requests_deduplicate_across_sessions_and_ignore_forwarded_spoofing(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->withHeaders(['User-Agent' => 'Mozilla/5.0', 'X-Forwarded-For' => '203.0.113.1']);
        $this->get('/despre-noi')->assertOk();
        $this->flushSession();
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.2'])->get('/contact')->assertOk();
        $this->assertDatabaseCount('daily_visits', 1);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->get('/contact')->assertOk();
        $this->assertDatabaseCount('daily_visits', 2);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_normalized_ipv6_and_mapped_ipv4_have_stable_keyed_hashes(): void
    {
        $a = app(VisitorAnalytics::class);
        $this->assertSame($a->hashIp('2001:db8::1'), $a->hashIp('2001:0db8:0000:0000:0000:0000:0000:0001'));
        $this->assertSame($a->hashIp('192.0.2.1'), $a->hashIp('::ffff:192.0.2.1'));
        $this->assertNotSame(hash('sha256', '192.0.2.1'), $a->hashIp('192.0.2.1'));
        $this->assertNull($a->hashIp('invalid'));
        $first = $a->hashIp('192.0.2.1');
        CarbonImmutable::setTestNow('2026-10-09T12:00:00Z');
        $this->assertSame($first, $a->hashIp('192.0.2.1'));
    }

    public static function rollovers(): array
    {
        return [
            ['2026-10-08T20:59:59Z', '2026-10-08T21:00:00Z', '2026-10-08', '2026-10-09'],
            ['2026-03-28T21:59:59Z', '2026-03-28T22:00:00Z', '2026-03-28', '2026-03-29'],
            ['2026-03-29T20:59:59Z', '2026-03-29T21:00:00Z', '2026-03-29', '2026-03-30'],
            ['2026-10-25T21:59:59Z', '2026-10-25T22:00:00Z', '2026-10-25', '2026-10-26'],
        ];
    }

    #[DataProvider('rollovers')]
    public function test_bucharest_calendar_rollover(string $before, string $after, string $first, string $second): void
    {
        config(['app.timezone' => 'UTC']);
        $a = app(VisitorAnalytics::class);
        CarbonImmutable::setTestNow($before);
        $a->record('192.0.2.1');
        CarbonImmutable::setTestNow($after);
        $a->record('192.0.2.1');
        $this->assertSame([$first, $second], DailyVisit::orderBy('visit_date')->pluck('visit_date')->all());
    }

    public function test_dst_repeated_hour_does_not_count_twice_and_cache_miss_is_safe(): void
    {
        CarbonImmutable::setTestNow('2026-10-25T00:30:00Z');
        $a = app(VisitorAnalytics::class);
        $a->record('192.0.2.1');
        Cache::flush();
        CarbonImmutable::setTestNow('2026-10-25T01:30:00Z');
        $a->record('192.0.2.1');
        $this->assertDatabaseCount('daily_visits', 1);
        $this->assertSame(1, DailyVisit::query()->insertOrIgnore(['visit_date' => '2026-10-26', 'ip_hash' => $a->hashIp('192.0.2.1')]));
        $this->assertSame(0, DailyVisit::query()->insertOrIgnore(['visit_date' => '2026-10-26', 'ip_hash' => $a->hashIp('192.0.2.1')]));
    }

    public function test_database_rejects_duplicate_pair_even_without_cache_or_service(): void
    {
        $row = ['visit_date' => '2026-10-08', 'ip_hash' => str_repeat('a', 64)];
        DailyVisit::create($row);
        $this->expectException(QueryException::class);
        DailyVisit::create($row);
    }

    public function test_cached_repeat_does_not_issue_another_analytics_insert(): void
    {
        $a = app(VisitorAnalytics::class);
        $a->record('192.0.2.1');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $a->record('192.0.2.1');
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_bot_admin_health_assets_and_ajax_are_excluded(): void
    {
        foreach (['Googlebot', 'bingbot', 'curl/8', 'python-requests', 'facebookexternalhit', ''] as $agent) {
            $this->withHeaders(['User-Agent' => $agent])->get('/despre-noi')->assertOk();
        }
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get('/admin/login')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/missing.png')->assertNotFound();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/contact')->assertOk();
        $this->assertDatabaseCount('daily_visits', 0);
    }

    public function test_aggregates_and_existing_widgets_exclude_legacy_and_sum_daily_uniques(): void
    {
        CarbonImmutable::setTestNow('2026-10-08T12:00:00Z');
        $a = app(VisitorAnalytics::class);
        foreach ([0, 1, 6, 7, 29, 30] as $offset) {
            DailyVisit::create(['visit_date' => $a->day()->subDays($offset)->toDateString(), 'ip_hash' => $a->hashIp('192.0.2.1')]);
        }
        Visit::create(['session_id' => 'legacy', 'ip_address' => '192.0.2.1']);
        $s = $a->summary();
        $this->assertSame([1, 1, 3, 5, 6], [$s['today'], $s['yesterday'], $s['seven'], $s['thirty'], $s['total']]);
        $stats = (new \ReflectionMethod(VisitsOverview::class, 'getStats'))->invoke(new VisitsOverview);
        $this->assertSame([1, 1, 3, 5, 6], array_map(fn ($s) => $s->getValue(), $stats));
        $chart = (new \ReflectionMethod(VisitsChart::class, 'getData'))->invoke(new VisitsChart);
        $this->assertCount(30, $chart['labels']);
        $this->assertSame(5, array_sum($chart['datasets'][0]['data']));
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_concurrent_writers_cannot_duplicate_the_same_day_ip_pair(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'visits-race-');
        $worker = tempnam(sys_get_temp_dir(), 'visits-worker-');
        $pdo = new \PDO('sqlite:'.$file);
        // Use the actual migrated table definition, not a weaker stand-in.
        $ddl = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='daily_visits'")->sql;
        $index = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='index' AND name='daily_visits_visit_date_ip_hash_unique'")->sql;
        $pdo->exec($ddl);
        $pdo->exec($index);
        $sql = DailyVisit::query()->getQuery()->getGrammar()->compileInsertOrIgnore(
            DailyVisit::query()->getQuery(), ['visit_date' => '2026-10-08', 'ip_hash' => str_repeat('a', 64)]
        );
        file_put_contents($worker, '<?php $p=new PDO("sqlite:".$argv[1]);$p->exec("PRAGMA busy_timeout=10000");$s=$p->prepare($argv[2]);$s->execute(["2026-10-08",str_repeat("a",64)]);');
        $workers = [];
        try {
            for ($i = 0; $i < 8; $i++) {
                $workers[] = proc_open([PHP_BINARY, $worker, $file, $sql], [], $pipes);
            }
            foreach ($workers as $process) {
                $this->assertSame(0, proc_close($process));
            }
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM daily_visits')->fetchColumn());
        } finally {
            $pdo = null;
            unlink($file);
            unlink($worker);
        }
    }

    public function test_cache_failure_still_records_visit(): void
    {
        Cache::shouldReceive('get')->once()->andThrow(new \RuntimeException('Test cache unavailable'));
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('Test cache unavailable'));
        app(VisitorAnalytics::class)->record('192.0.2.1');
        $this->assertDatabaseCount('daily_visits', 1);
    }
}
