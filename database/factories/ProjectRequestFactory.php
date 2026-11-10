<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(), 'payload_hash' => hash('sha256', 'demo'), 'nombre' => fake()->name(), 'empresa' => fake()->company(), 'email' => fake()->safeEmail(), 'sector' => 'logistica', 'objetivo' => fake()->paragraph(), 'consentimiento' => true];
    }
}
