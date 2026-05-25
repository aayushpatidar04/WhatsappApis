<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlatformSetting::defaults() as $setting) {
            PlatformSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✓ Platform settings seeded (' . count(PlatformSetting::defaults()) . ' settings).');
    }
}