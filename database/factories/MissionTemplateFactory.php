<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MissionTemplateFactory extends Factory
{
    public function definition(): array
    {
        $data = json_decode(file_get_contents(database_path('data/demo.json')), true)['templates'][0];
        $data['id'] = (string) Str::uuid();

        return ['id' => $data['id'], 'data' => $data];
    }
}
