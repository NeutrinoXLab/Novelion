<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait UsesCommittedDatabase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Committed delivery tests require an isolated SQLite :memory: database.');
        }

        $this->refreshTestDatabase();

        // Dispose of the in-memory database rather than running unrelated down migrations.
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }
}
