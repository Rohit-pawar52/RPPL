<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase runs migrate:fresh, which DROPS every table. phpunit.xml points tests at an
     * in-memory sqlite database, but running phpunit without that configuration (for example
     * `phpunit --no-configuration`) falls back to .env - the real local database - and wipes it.
     * So refuse to run at all unless the database is the in-memory sqlite one, whatever started us.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');

        if ($connection !== 'sqlite' || config("database.connections.{$connection}.database") !== ':memory:') {
            throw new \RuntimeException(
                "Refusing to run tests: the database is \"{$connection}\", not in-memory sqlite. "
                .'Run tests with `php artisan test` (it applies phpunit.xml) so a real database is never wiped.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the real internet; a test that needs an HTTP
        // answer fakes it with Http::fake().
        Http::preventStrayRequests();
    }
}
