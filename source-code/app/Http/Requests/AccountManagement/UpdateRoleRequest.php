<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AssignableNonDeanRole;
use App\Services\AccountManagement\ChangeNonDeanRoleCommand;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:admin,student,osas_admin'],
            'freshness' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toCommand(int $actorId, int $targetId, FreshnessSnapshot $snapshot): ChangeNonDeanRoleCommand
    {
        return new ChangeNonDeanRoleCommand(
            new ActorAccountId($actorId),
            new ManagedAccountId($targetId),
            AssignableNonDeanRole::from($this->validated('role')),
            $snapshot
        );
    }
}