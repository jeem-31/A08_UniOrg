<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\DeleteManagedAccountCommand;
use App\Services\AccountManagement\DestructiveConfirmationToken;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string'],
            'freshness' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toCommand(int $actorId, int $targetId, FreshnessSnapshot $snapshot): DeleteManagedAccountCommand
    {
        return new DeleteManagedAccountCommand(
            new ActorAccountId($actorId),
            new ManagedAccountId($targetId),
            $snapshot,
            DestructiveConfirmationToken::fromRequest($this->validated('confirmation')),
        );
    }
}