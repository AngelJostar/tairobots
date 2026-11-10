<?php

namespace Database\Seeders;

use App\Models\CatalogSetting;
use App\Models\MissionTemplate;
use App\Models\RobotClient;
use Illuminate\Database\Seeder;

class TairobotsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $demo = json_decode(file_get_contents(database_path('data/demo.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($demo['clients'] as $state) {
            $now = (int) floor(microtime(true) * 1000);
            $state['lastPulse'] = $now;
            foreach ($state['robots'] as &$robot) {
                $robot['lastSeen'] = $now;
                $robot['maintenanceDue'] = $now + 30 * 86400000;
            } unset($robot);
            RobotClient::firstOrCreate(['id' => $state['id']], ['name' => $state['name'], 'state' => $state]);
        }
        foreach ($demo['templates'] as $template) {
            MissionTemplate::firstOrCreate(['id' => $template['id']], ['data' => $template]);
        }
        CatalogSetting::firstOrCreate(['id' => 1], ['categories' => $demo['categories']]);
    }
}
