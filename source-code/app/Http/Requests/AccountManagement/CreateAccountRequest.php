<?php

namespace App\Http\Requests\AccountManagement;

use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AssignableNonDeanRole;
use App\Services\AccountManagement\CreateManagedAccountCommand;
use App\Services\AccountManagement\ManagedAccountProfile;
use App\Services\AccountManagement\NewAccountCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAccountRequest extends FormRequest
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
            'department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_if:role,super_admin'],
            'photo' => ['nullable', 'string'],
            'role' => ['required', 'string', 'in:osas_admin,super_admin'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toCommand(int $actorId): CreateManagedAccountCommand
    {
        return new CreateManagedAccountCommand(
            new ActorAccountId($actorId),
            ManagedAccountProfile::fromValidated($this->only(ManagedAccountProfile::editableFields())),
            NewAccountCredential::fromValidated($this->validated('password')),
            AssignableNonDeanRole::from($this->validated('role')),
        );
    }
}
