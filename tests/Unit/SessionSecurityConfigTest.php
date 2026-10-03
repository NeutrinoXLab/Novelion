<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SessionSecurityConfigTest extends TestCase
{
    #[RunInSeparateProcess]
    #[DataProvider('environments')]
    public function test_secure_cookie_defaults_and_overrides(string $environment, ?string $override, bool $expected): void
    {
        foreach (['APP_ENV', 'SESSION_SECURE_COOKIE', 'SESSION_HTTP_ONLY', 'SESSION_SAME_SITE'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        putenv('APP_ENV='.$environment);

        if ($override !== null) {
            putenv('SESSION_SECURE_COOKIE='.$override);
        }

        $app = new Application(dirname(__DIR__, 2));
        $config = require $app->configPath('session.php');

        $this->assertSame($expected, $config['secure']);
        $this->assertTrue($config['http_only']);
        $this->assertSame('lax', $config['same_site']);
    }

    public static function environments(): array
    {
        return [
            'production defaults to secure' => ['production', null, true],
            'local HTTP remains usable' => ['local', null, false],
            'testing remains usable' => ['testing', null, false],
            'explicit secure setting' => ['local', 'true', true],
            'explicit override remains supported' => ['production', 'false', false],
        ];
    }
}
