<?php

namespace Tests\Unit\AccountManagement;

use App\Services\AccountManagement\StudentAccountValidity;
use App\Services\AccountManagement\ValidityState;
use App\Services\MembershipFeeService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class StudentAccountValidityTest extends TestCase
{
    private function validity(): StudentAccountValidity
    {
        return new StudentAccountValidity(new MembershipFeeService);
    }

    public function test_it_normalizes_year_level_values(): void
    {
        $validity = $this->validity();

        self::assertNull($validity->normalizeYearLevel(null));
        self::assertNull($validity->normalizeYearLevel(''));
        self::assertNull($validity->normalizeYearLevel('   '));
        self::assertNull($validity->normalizeYearLevel('Graduating'));
        self::assertNull($validity->normalizeYearLevel('5th Year'));

        self::assertSame('1st Year', $validity->normalizeYearLevel('1st Year'));
        self::assertSame('1st Year', $validity->normalizeYearLevel('1'));
        self::assertSame('1st Year', $validity->normalizeYearLevel('First Year'));
        self::assertSame('2nd Year', $validity->normalizeYearLevel('2nd'));
        self::assertSame('3rd Year', $validity->normalizeYearLevel('3rd year'));
        self::assertSame('4th Year', $validity->normalizeYearLevel('Fourth Year'));
    }

    public function test_it_maps_year_levels_to_lifetimes(): void
    {
        self::assertSame(48, $this->validity()->lifetimeMonthsFor('1st Year'));
        self::assertSame(36, $this->validity()->lifetimeMonthsFor('2nd Year'));
        self::assertSame(24, $this->validity()->lifetimeMonthsFor('3rd Year'));
        self::assertSame(12, $this->validity()->lifetimeMonthsFor('4th Year'));
        self::assertSame(48, $this->validity()->lifetimeMonthsFor('First Year'));
        self::assertNull($this->validity()->lifetimeMonthsFor('Unknown'));
        self::assertNull($this->validity()->lifetimeMonthsFor(null));
    }

    public function test_it_parses_cor_school_years(): void
    {
        self::assertSame(2026, $this->validity()->schoolYearStartYear('2026-2027'));
        self::assertSame(2031, $this->validity()->schoolYearStartYear(' 2031-2032 '));
        self::assertNull($this->validity()->schoolYearStartYear(null));
        self::assertNull($this->validity()->schoolYearStartYear('not-a-year'));
    }

    public function test_it_computes_expiry_from_the_cor_school_year(): void
    {
        $periodStart = CarbonImmutable::create(2026, 8, 1);

        $expiry = $this->validity()->expiresAtFor('1st Year', '2026-2027', $periodStart);

        self::assertNotNull($expiry);
        self::assertSame('2030-08-01', $expiry->toDateString());
    }

    public function test_it_uses_the_period_start_when_the_cor_year_matches(): void
    {
        $periodStart = CarbonImmutable::create(2026, 8, 1, 0, 0, 0, 'Asia/Manila');

        $expiry = $this->validity()->expiresAtFor('4th Year', '2026-2027', $periodStart);

        self::assertSame('2027-08-01', $expiry->toDateString());
    }

    public function test_it_falls_back_to_august_first_for_an_unknown_school_year(): void
    {
        $now = CarbonImmutable::create(2026, 8, 15, 0, 0, 0, 'Asia/Manila');

        $expiry = $this->validity()->expiresAtFor('2nd Year', null, null, $now);

        self::assertNotNull($expiry);
        self::assertSame('2029-08-01', $expiry->toDateString());
    }

    public function test_it_returns_null_when_the_year_level_is_unknown(): void
    {
        self::assertNull($this->validity()->expiresAtFor(null, '2026-2027', CarbonImmutable::create(2026, 8, 1)));
    }

    public function test_it_applies_the_two_month_grace_before_deactivation(): void
    {
        $expiresAt = CarbonImmutable::create(2026, 8, 1);

        self::assertSame('2026-10-01', $this->validity()->deactivateAt($expiresAt)->toDateString());
    }

    public function test_it_derives_validity_from_the_clock(): void
    {
        $validity = $this->validity();
        $now = CarbonImmutable::create(2026, 9, 1);

        $future = CarbonImmutable::create(2026, 12, 1);
        $inGrace = CarbonImmutable::create(2026, 8, 15);
        $pastGrace = CarbonImmutable::create(2026, 6, 1);

        self::assertSame(ValidityState::Active, $validity->validityFor($future, $now));
        self::assertSame(ValidityState::Expiring, $validity->validityFor($inGrace, $now));
        self::assertSame(ValidityState::Expired, $validity->validityFor($pastGrace, $now));
        self::assertSame(ValidityState::None, $validity->validityFor(null, $now));
    }

    public function test_it_counts_days_until_deactivation(): void
    {
        $validity = $this->validity();
        $now = CarbonImmutable::create(2026, 9, 1);
        $expiresAt = CarbonImmutable::create(2026, 8, 15);

        self::assertSame(44, $validity->daysUntilDeactivation($expiresAt, $now));
        self::assertSame(0, $validity->daysUntilDeactivation(CarbonImmutable::create(2026, 6, 1), $now));
    }

    public function test_it_builds_presentation_status_for_rows(): void
    {
        $validity = $this->validity();
        $now = CarbonImmutable::create(2026, 9, 1);

        $expired = $validity->statusFor('student', true, CarbonImmutable::create(2026, 6, 1), $now);
        self::assertSame('expired', $expired['state']);

        $expiring = $validity->statusFor('student', true, CarbonImmutable::create(2026, 8, 15), $now);
        self::assertSame('expiring', $expiring['state']);
        self::assertSame(44, $expiring['daysLeft']);

        $active = $validity->statusFor('student', true, CarbonImmutable::create(2026, 12, 1), $now);
        self::assertSame('active', $active['state']);

        $nonStudent = $validity->statusFor('osas_admin', true, null, $now);
        self::assertSame('active', $nonStudent['state']);

        $manualInactive = $validity->statusFor('student', false, CarbonImmutable::create(2026, 12, 1), $now);
        self::assertSame('inactive', $manualInactive['state']);
    }
}
