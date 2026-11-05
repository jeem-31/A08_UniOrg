<?php

namespace App\Services\AccountManagement;

/**
 * The sole application registry for organization appointment routes and roles.
 * Routes resolve strictly; labels, aliases, and arbitrary pivot roles are invalid.
 */
final class OrganizationPositionRegistry
{
    /** @return list<AdviserPosition> */
    public static function advisers(): array
    {
        return AdviserPosition::cases();
    }

    /** @return list<OfficerPosition> */
    public static function officers(): array
    {
        return OfficerPosition::cases();
    }

    /** @return list<OrganizationPosition> */
    public static function all(): array
    {
        return [...self::advisers(), ...self::officers()];
    }

    public static function adviserForRoute(string $routeValue): ?AdviserPosition
    {
        foreach (self::advisers() as $position) {
            if ($position->routeValue() === $routeValue) {
                return $position;
            }
        }

        return null;
    }

    public static function officerForRoute(string $routeValue): ?OfficerPosition
    {
        foreach (self::officers() as $position) {
            if ($position->routeValue() === $routeValue) {
                return $position;
            }
        }

        return null;
    }

    public static function positionForRoute(string $routeValue): AdviserPosition|OfficerPosition|null
    {
        return self::adviserForRoute($routeValue) ?? self::officerForRoute($routeValue);
    }

    /** @return list<string> */
    public static function adviserRoles(): array
    {
        return array_map(static fn (AdviserPosition $position): string => $position->role(), self::advisers());
    }

    /** @return list<string> */
    public static function officerRoles(): array
    {
        return array_map(static fn (OfficerPosition $position): string => $position->role(), self::officers());
    }
}
