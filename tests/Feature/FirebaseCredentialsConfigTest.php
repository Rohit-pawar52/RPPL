<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * A hardcoded, machine-specific absolute path in FIREBASE_CREDENTIALS
 * only works on the one machine it was written for and breaks on every
 * other environment (dev, staging, production, another developer's
 * checkout) — see AppServiceProvider::configureFirebaseCredentialsFallback().
 * This proves the fallback actually applies, and that an explicit value
 * (if ever set) still wins.
 */
class FirebaseCredentialsConfigTest extends TestCase
{
    public function test_blank_env_falls_back_to_the_conventional_storage_path(): void
    {
        config(['firebase.projects.app.credentials' => null]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame(
            storage_path('app/firebase/firebase-service-account.json'),
            config('firebase.projects.app.credentials'),
        );
    }

    public function test_an_explicit_configured_value_is_never_overridden(): void
    {
        config(['firebase.projects.app.credentials' => '/some/custom/path/service-account.json']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('/some/custom/path/service-account.json', config('firebase.projects.app.credentials'));
    }
}
