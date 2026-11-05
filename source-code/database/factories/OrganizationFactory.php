<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => fake()->randomElement(['Academic', 'Cultural', 'Sports', 'Religious']),
            'status' => 'Active',
            'management_version' => 1,
        ];
    }
}
