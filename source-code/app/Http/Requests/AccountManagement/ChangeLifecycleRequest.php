<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\AccountLifecycleAction;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\ChangeAccountLifecycleCommand;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use Illuminate\Foundation\Http\FormRequest;

class ChangeLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:activate,deactivate'],
            'freshness' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toCommand(int $actorId, int $targetId, FreshnessSnapshot $snapshot): ChangeAccountLifecycleCommand
    {
        return new ChangeAccountLifecycleCommand(
            new ActorAccountId($actorId),
            new ManagedAccountId($targetId),
            AccountLifecycleAction::from($this->validated('action')),
            $snapshot
        );
    }
}