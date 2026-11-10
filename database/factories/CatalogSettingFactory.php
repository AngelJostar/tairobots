<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['categories' => json_decode(file_get_contents(database_path('data/demo.json')), true)['categories'], 'version' => 1];
    }
}
