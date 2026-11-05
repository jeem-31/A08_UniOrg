<?php

namespace App\Services\AccountManagement;

/** A canonical organization appointment slot, never a caller-provided pivot role. */
interface OrganizationPosition
{
    public function routeValue(): string;

    public function role(): string;
}
