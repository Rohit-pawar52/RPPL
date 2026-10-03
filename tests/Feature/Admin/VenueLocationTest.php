<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueLocationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_admin_creates_a_village_venue_with_map_location(): void
    {
        $this->actingAs($this->admin())->post(route('admin.venues.store'), [
            'name' => 'Gram Panchayat Maidan',
            'village' => 'Chicholi',
            'tehsil' => 'Betul',
            'district' => 'Betul',
            'latitude' => '21.9012345',
            'longitude' => '77.9054321',
        ])->assertRedirect(route('admin.venues.index'));

        $venue = Venue::where('name', 'Gram Panchayat Maidan')->firstOrFail();

        $this->assertSame('Chicholi', $venue->village);
        $this->assertSame('Betul', $venue->tehsil);
        $this->assertSame('Betul', $venue->district);
        $this->assertSame(21.9012345, $venue->latitude);
        $this->assertSame(77.9054321, $venue->longitude);
    }

    public function test_venue_can_be_created_without_a_map_location(): void
    {
        $this->actingAs($this->admin())->post(route('admin.venues.store'), [
            'name' => 'Unpinned Ground',
            'village' => 'Chicholi',
        ])->assertSessionHasNoErrors();

        $venue = Venue::where('name', 'Unpinned Ground')->firstOrFail();

        $this->assertFalse($venue->hasCoordinates());
        $this->assertNull($venue->directionsUrl());
    }

    public function test_invalid_or_half_given_coordinates_are_rejected(): void
    {
        $admin = $this->admin();

        $cases = [
            'latitude out of range' => [['latitude' => '91', 'longitude' => '77'], 'latitude'],
            'longitude out of range' => [['latitude' => '21', 'longitude' => '181'], 'longitude'],
            'not numeric' => [['latitude' => 'abc', 'longitude' => '77'], 'latitude'],
            'latitude only' => [['latitude' => '21.5'], 'longitude'],
            'longitude only' => [['longitude' => '77.5'], 'latitude'],
        ];

        foreach ($cases as [$coordinates, $errorField]) {
            $this->actingAs($admin)
                ->post(route('admin.venues.store'), ['name' => 'Bad Pin Ground'] + $coordinates)
                ->assertSessionHasErrors($errorField);
        }

        $this->assertDatabaseMissing('venues', ['name' => 'Bad Pin Ground']);
    }

    public function test_update_changes_location_and_keeps_old_city_and_country(): void
    {
        $venue = Venue::factory()->create(['city' => 'Pune', 'country' => 'India']);

        $this->actingAs($this->admin())->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'village' => 'Khed',
            'tehsil' => 'Khed',
            'district' => 'Pune',
            'latitude' => '18.7',
            'longitude' => '73.9',
            'is_active' => 1,
        ])->assertRedirect(route('admin.venues.index'));

        $venue->refresh();

        $this->assertSame('Khed', $venue->village);
        $this->assertSame(18.7, $venue->latitude);
        $this->assertSame('Pune', $venue->city);
        $this->assertSame('India', $venue->country);
    }

    public function test_update_can_clear_the_map_location(): void
    {
        $venue = Venue::factory()->create(['latitude' => 18.7, 'longitude' => 73.9]);

        $this->actingAs($this->admin())->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'latitude' => '',
            'longitude' => '',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($venue->refresh()->hasCoordinates());
    }

    public function test_form_contains_the_map_picker_and_coordinate_inputs(): void
    {
        $admin = $this->admin();
        $venue = Venue::factory()->create(['latitude' => 21.9, 'longitude' => 77.9]);

        foreach ([route('admin.venues.create'), route('admin.venues.edit', $venue)] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('data-venue-map', false)
                ->assertSee('data-map-canvas', false)
                ->assertSee('name="latitude"', false)
                ->assertSee('name="longitude"', false)
                ->assertSee('name="village"', false)
                ->assertDontSee('name="city"', false)
                ->assertDontSee('name="country"', false);
        }

        $this->actingAs($admin)->get(route('admin.venues.edit', $venue))
            ->assertSee('value="21.9"', false)
            ->assertSee('value="77.9"', false);
    }

    public function test_admin_screens_show_village_and_fall_back_to_old_city(): void
    {
        $admin = $this->admin();
        Venue::factory()->create(['name' => 'Village Ground', 'village' => 'Chicholi', 'tehsil' => 'Betul', 'district' => 'Betul', 'city' => 'Ignored City']);
        $old = Venue::factory()->create(['name' => 'Old Ground', 'city' => 'Nagpur', 'country' => 'India']);

        $this->actingAs($admin)->get(route('admin.venues.index'))
            ->assertSee('Chicholi, Betul, Betul')
            ->assertDontSee('Ignored City')
            ->assertSee('Nagpur, India');

        $this->actingAs($admin)->get(route('admin.venues.show', $old))
            ->assertOk()
            ->assertSee('Nagpur, India');
    }
}
