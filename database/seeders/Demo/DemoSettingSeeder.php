<?php

namespace Database\Seeders\Demo;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * A small set of demo General/Contact/Public settings only (Phase
 * 3.44B2) — deliberately never Razorpay or Firebase values, which stay
 * genuinely unconfigured in every seeded environment. Uses
 * Setting::firstOrCreate() directly rather than SettingsService::set(),
 * matching every other Demo\* seeder's convention of writing rows
 * straight through the model.
 */
class DemoSettingSeeder extends Seeder
{
    /**
     * @var list<array{group: string, key: string, value: string, type: string}>
     */
    private const SETTINGS = [
        ['group' => 'general', 'key' => 'application_name', 'value' => 'RajaBhoj Pawar Premier League', 'type' => Setting::TYPE_STRING],
        ['group' => 'general', 'key' => 'short_name', 'value' => 'RPPL', 'type' => Setting::TYPE_STRING],
        ['group' => 'general', 'key' => 'tagline', 'value' => 'One League. One Family.', 'type' => Setting::TYPE_STRING],
        ['group' => 'contact', 'key' => 'email', 'value' => 'info@rppl.test', 'type' => Setting::TYPE_STRING],
        ['group' => 'contact', 'key' => 'phone', 'value' => '+91 90000 00000', 'type' => Setting::TYPE_STRING],
        ['group' => 'public', 'key' => 'footer_text', 'value' => 'RajaBhoj Pawar Premier League — organized by the community, for the community.', 'type' => Setting::TYPE_STRING],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            Setting::firstOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type']],
            );
        }
    }
}
