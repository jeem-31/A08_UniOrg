<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationUser> */
class OrganizationUserFactory extends Factory
{
    protected $model = OrganizationUser::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'role' => 'President',
            'membership_status' => 'Active',
            'management_version' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['membership_status' => 'Pending']);
    }
}
