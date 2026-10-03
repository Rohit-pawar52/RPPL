<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the real internet; a test that needs an HTTP
        // answer fakes it with Http::fake().
        Http::preventStrayRequests();
    }
}
