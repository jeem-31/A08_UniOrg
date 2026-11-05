<?php

namespace Tests\Unit;

use App\Models\Organization;
use PHPUnit\Framework\TestCase;

final class OrganizationAccreditationYearTest extends TestCase
{
    public function test_it_derives_the_school_year_label_from_a_spanning_period(): void
    {
        self::assertSame(
            '2025-2026',
            Organization::accreditationSchoolYear('2025-08-01', '2026-03-31'),
        );
    }

    public function test_it_returns_null_when_the_period_is_incomplete_or_flat(): void
    {
        self::assertNull(Organization::accreditationSchoolYear(null, '2026-03-31'));
        self::assertNull(Organization::accreditationSchoolYear('2026-01-01', '2026-12-31'));
        self::assertNull(Organization::accreditationSchoolYear('2026-01-01', '2026-01-01'));
        self::assertNull(Organization::accreditationSchoolYear(null, null));
    }
}