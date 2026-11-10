<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RobotClientFactory extends Factory
{
    public function definition(): array
    {
        $state = json_decode(file_get_contents(database_path('data/demo.json')), true)['clients'][0];
        $state['id'] = (string) Str::uuid();

        return ['id' => $state['id'], 'name' => $state['name'], 'state' => $state, 'version' => 1];
    }
}
