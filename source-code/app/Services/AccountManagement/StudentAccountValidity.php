<?php

namespace App\Services\AccountManagement;

use App\Services\MembershipFeeService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The fixed student-account validity window.
 *
 * A student account expires `lifetime(year level)` after the school year printed
 * on their COR. The resulting `expires_at` is stored once and never recomputed,
 * so the countdown runs on the clock regardless of later year-level or
 * accreditation-period edits. After `expires_at` the account stays active for a
 * two-month grace window before the scheduled job deactivates it.
 */
final class StudentAccountValidity
{
    public const GRACE_MONTHS = 2;

    /** Lifetime in months, keyed by canonical year level. */
    private const LIFETIME_MONTHS = [
        '1st Year' => 48,
        '2nd Year' => 36,
        '3rd Year' => 24,
        '4th Year' => 12,
    ];

    public function __construct(private MembershipFeeService $membershipFees) {}

    /** @return int|null Lifetime months for a canonical year level, null when unknown. */
    public static function lifetimeMonthsFor(?string $yearLevel): ?int
    {
        $canonical = self::normalizeYearLevel($yearLevel);

        return $canonical === null ? null : self::LIFETIME_MONTHS[$canonical] ?? null;
    }

    /**
     * Reduce OCR/free-form year-level values to a canonical `"Nth Year"` string
     * (accepts "1st Year", "Third Year", "3rd", "2", etc.). Returns null for
     * values that cannot name a first-to-fourth year.
     */
    public static function normalizeYearLevel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if (preg_match('/^(?:(\d)(?:st|nd|rd|th)?|(first|second|third|fourth))\b/i', $value, $matches) !== 1) {
            return null;
        }

        $token = strtolower(isset($matches[2]) ? $matches[2] : $matches[1]);

        $ordinals = [
            '1' => '1st', '2' => '2nd', '3' => '3rd', '4' => '4th',
            'first' => '1st', 'second' => '2nd', 'third' => '3rd', 'fourth' => '4th',
        ];

        return isset($ordinals[$token]) ? $ordinals[$token].' Year' : null;
    }

    /** @return int|null The starting year of a `"YYYY-YYYY"` COR school year. */
    public static function schoolYearStartYear(?string $corSchoolYear): ?int
    {
        if ($corSchoolYear === null) {
            return null;
        }

        if (preg_match('/^\s*(\d{4})\s*-\s*\d{4}\s*$/', $corSchoolYear, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * The fixed `expires_at` for a student: the COR school-year start plus the
     * lifetime for their year level. Returns null when no lifetime can be known.
     */
    public function expiresAtFor(
        ?string $yearLevel,
        ?string $corSchoolYear,
        ?CarbonInterface $periodStart,
        ?CarbonInterface $now = null,
    ): ?CarbonInterface {
        $lifetimeMonths = self::lifetimeMonthsFor($yearLevel);

        if ($lifetimeMonths === null) {
            return null;
        }

        return $this->entryAnchor($corSchoolYear, $periodStart, $now)->addMonths($lifetimeMonths);
    }

    /** The moment the account is fully inactive: expiry plus the grace period. */
    public function deactivateAt(CarbonInterface $expiresAt): CarbonInterface
    {
        return $expiresAt->toMutable()->addMonths(self::GRACE_MONTHS);
    }

    public function validityFor(?CarbonInterface $expiresAt, ?CarbonInterface $now = null): ValidityState
    {
        if ($expiresAt === null) {
            return ValidityState::None;
        }

        $now = $now ?? now(config('app.timezone'));

        if ($now->greaterThanOrEqualTo($this->deactivateAt($expiresAt))) {
            return ValidityState::Expired;
        }

        if ($now->greaterThanOrEqualTo($expiresAt)) {
            return ValidityState::Expiring;
        }

        return ValidityState::Active;
    }

    /** Whole days remaining until the grace window closes (floor of 0). */
    public function daysUntilDeactivation(CarbonInterface $expiresAt, ?CarbonInterface $now = null): int
    {
        $days = ($now ?? now(config('app.timezone')))
            ->startOfDay()
            ->diffInDays($this->deactivateAt($expiresAt)->startOfDay(), false);

        return max(0, (int) $days);
    }

    /**
     * The presentation status for one account row. Non-students and accounts
     * without an expiry keep the manual Active/Inactive mapping; students whose
     * window has closed are Expired, and those inside the grace window are
     * Expiring.
     *
     * @return array{state: string, expiresAt: ?CarbonInterface, daysLeft: ?int}
     */
    public function statusFor(
        string $role,
        bool $isActive,
        ?CarbonInterface $expiresAt,
        ?CarbonInterface $now = null,
    ): array {
        if ($role !== 'student' || $expiresAt === null) {
            return ['state' => $isActive ? 'active' : 'inactive', 'expiresAt' => null, 'daysLeft' => null];
        }

        return match ($this->validityFor($expiresAt, $now)) {
            ValidityState::Expired => ['state' => 'expired', 'expiresAt' => $expiresAt, 'daysLeft' => null],
            ValidityState::Expiring => [
                'state' => 'expiring',
                'expiresAt' => $expiresAt,
                'daysLeft' => $this->daysUntilDeactivation($expiresAt, $now),
            ],
            default => ['state' => $isActive ? 'active' : 'inactive', 'expiresAt' => $expiresAt, 'daysLeft' => null],
        };
    }

    /**
     * The reference moment a student's validity window starts. Prefers the COR
     * school year, falling back to the current accreditation period and finally
     * to the Aug 1 start of the running academic year.
     */
    public function entryAnchor(
        ?string $corSchoolYear,
        ?CarbonInterface $periodStart,
        ?CarbonInterface $now = null,
    ): CarbonInterface {
        $now = $now ?? now(config('app.timezone'));
        $startYear = self::schoolYearStartYear($corSchoolYear);

        if ($startYear !== null) {
            if ($periodStart !== null && (int) $periodStart->format('Y') === $startYear) {
                return CarbonImmutable::instance($periodStart);
            }

            return CarbonImmutable::create($startYear, 8, 1, 0, 0, 0, config('app.timezone'));
        }

        if ($periodStart !== null) {
            return CarbonImmutable::instance($periodStart);
        }

        return $this->membershipFees->cycleStart($now);
    }
}
