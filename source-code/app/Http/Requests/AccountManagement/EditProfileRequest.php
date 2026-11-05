<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\ManagedAccountProfile;
use App\Services\AccountManagement\UpdateManagedAccountProfileCommand;
use Illuminate\Foundation\Http\FormRequest;

class EditProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'student_id' => ['nullable', 'string', 'max:50'],
            'course' => ['nullable', 'string', 'max:255'],
            'year_level' => ['nullable', 'string', 'max:50'],
            'section' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer'],
            'photo' => ['nullable', 'string'],
            'freshness' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toCommand(int $actorId, int $targetId, FreshnessSnapshot $snapshot): UpdateManagedAccountProfileCommand
    {
        return new UpdateManagedAccountProfileCommand(
            new ActorAccountId($actorId),
            new ManagedAccountId($targetId),
            ManagedAccountProfile::fromValidated($this->only(ManagedAccountProfile::editableFields())),
            $snapshot
        );
    }
}