<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\DeanAppointmentCommand;
use App\Services\AccountManagement\DestructiveConfirmationToken;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\NonDeanOutcome;
use Illuminate\Foundation\Http\FormRequest;

class DeanAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'min:1'],
            'outcome' => ['nullable', 'string', 'in:reassign,deactivate'],
            'former_dean_role' => ['nullable', 'string', 'in:osas_admin,admin,student'],
            'confirmation' => ['nullable', 'string'],
            'freshness' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toCommand(int $actorId, FreshnessSnapshot $snapshot): DeanAppointmentCommand
    {
        return new DeanAppointmentCommand(
            new ActorAccountId($actorId),
            new ManagedAccountId((int) $this->validated('candidate_id')),
            $snapshot,
            $this->outcome(),
            $this->validated('confirmation')
                ? DestructiveConfirmationToken::fromRequest($this->validated('confirmation'))
                : null,
        );
    }

    private function outcome(): ?NonDeanOutcome
    {
        return match ($this->validated('outcome')) {
            'reassign' => NonDeanOutcome::reassign($this->validated('former_dean_role') ?? 'osas_admin'),
            'deactivate' => NonDeanOutcome::deactivate(),
            default => null,
        };
    }
}