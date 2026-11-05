<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\OrganizationAppointmentCommand;
use App\Services\AccountManagement\OrganizationId;
use App\Services\AccountManagement\OrganizationPositionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class OrganizationAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer'],
            'position' => ['required', 'string'],
            'freshness' => ['required', 'string'],
        ];
    }

    public function toCommand(int $actorId, int $organizationId, FreshnessSnapshot $snapshot): OrganizationAppointmentCommand
    {
        $positionStr = $this->validated('position');
        
        if ($adviser = OrganizationPositionRegistry::adviserForRoute($positionStr)) {
            return OrganizationAppointmentCommand::forAdviser(
                new ActorAccountId($actorId),
                new OrganizationId($organizationId),
                new ManagedAccountId((int) $this->validated('candidate_id')),
                $adviser,
                $snapshot
            );
        }
        
        if ($officer = OrganizationPositionRegistry::officerForRoute($positionStr)) {
            return OrganizationAppointmentCommand::forOfficer(
                new ActorAccountId($actorId),
                new OrganizationId($organizationId),
                new ManagedAccountId((int) $this->validated('candidate_id')),
                $officer,
                $snapshot
            );
        }
        
        throw new InvalidArgumentException("Invalid position route value");
    }
}