<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_default_admin_can_log_in_to_the_admin_panel(): void
    {
        // A freshly migrated database has no roles seeded - the seeder must cope with that itself.
        $this->seed(AdminUserSeeder::class);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@gmail.com',
            'password' => '12345678',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'admin@gmail.com')->firstOrFail());
    }

    public function test_running_it_again_never_duplicates_or_resets_a_changed_password(): void
    {
        $this->seed(AdminUserSeeder::class);

        User::where('email', 'admin@gmail.com')->update(['password' => Hash::make('changed-after-first-login')]);

        // Every Render restart runs the seeder again.
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@gmail.com')->count());
        $this->assertTrue(Hash::check('changed-after-first-login', User::where('email', 'admin@gmail.com')->value('password')));
    }
}
