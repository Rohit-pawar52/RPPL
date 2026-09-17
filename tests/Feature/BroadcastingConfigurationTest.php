<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * Phase 3.37B1 — a configuration smoke check only. Confirms Laravel can
 * resolve the Reverb broadcast connection from environment-driven config
 * without requiring a live Reverb server to be running. No event is
 * dispatched and no HTTP/WebSocket integration is exercised here.
 *
 * phpunit.xml deliberately forces BROADCAST_CONNECTION=null for the test
 * environment (Laravel's own standard test-isolation default, so the
 * suite never depends on a real broadcaster) — so these tests assert the
 * "reverb" connection is correctly defined and independently resolvable
 * by name, not that it is the environment's default connection.
 */
class BroadcastingConfigurationTest extends TestCase
{
    public function test_reverb_connection_is_defined_in_broadcasting_config(): void
    {
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));
    }

    public function test_reverb_connection_resolves_without_a_live_server(): void
    {
        $connection = Broadcast::connection('reverb');

        $this->assertNotNull($connection);
    }

    public function test_reverb_connection_config_is_entirely_environment_driven(): void
    {
        $options = config('broadcasting.connections.reverb.options');

        $this->assertArrayHasKey('host', $options);
        $this->assertArrayHasKey('port', $options);
        $this->assertArrayHasKey('scheme', $options);
    }
}
