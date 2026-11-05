<?php

namespace App\Services\AccountManagement;

enum AdviserPosition: string implements OrganizationPosition
{
    case SeniorAdviser = 'Senior Adviser';
    case JuniorAdviser = 'Junior Adviser';

    public function routeValue(): string
    {
        return match ($this) {
            self::SeniorAdviser => 'senior-adviser',
            self::JuniorAdviser => 'junior-adviser',
        };
    }

    public function role(): string
    {
        return $this->value;
    }
}
