<?php

namespace Tests\Unit;

use App\Services\CertificationOfOfficersRoleSlotRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\CertificationOfOfficersFixtureBuilder;

/** **Validates: Requirements 2.2, 2.5, 2.7, 2.9–2.11, 3.5, 3.6** */
final class CertificationOfOfficersRoleSlotRegistryTest extends TestCase
{
    #[DataProvider('documentedRoleRecords')]
    public function test_every_documented_source_role_allocates_to_its_exact_slot(array $records, array $slots): void
    {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate($records);

        self::assertSame($slots, array_keys($result['officers']));
        self::assertSame([], $result['warnings']);
    }

    public static function documentedRoleRecords(): iterable
    {
        foreach (CertificationOfOfficersFixtureBuilder::roleFixtures() as [$role, $slot]) {
            yield $slot => [[self::record($role, 0)], [$slot]];
        }
    }

    #[DataProvider('representativeSourceLabels')]
    public function test_numeric_and_written_representative_labels_share_the_exact_destination(
        string $role,
        string $slot,
    ): void {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate([
            self::record($role, 0),
        ]);

        self::assertSame([$slot], array_keys($result['officers']));
        self::assertSame([], $result['warnings']);
    }

    public static function representativeSourceLabels(): iterable
    {
        yield 'numeric first' => ['1ST YEAR REPRESENTATIVE', 'rep_1st_year'];
        yield 'split numeric first' => ['1 ST YEAR REPRESENTATIVE', 'rep_1st_year'];
        yield 'superscript numeric first' => ['1ˢᵗ YEAR REPRESENTATIVE', 'rep_1st_year'];
        yield 'written first' => ['FIRST YEAR REPRESENTATIVE', 'rep_1st_year'];
        yield 'numeric second' => ['2ND YEAR REPRESENTATIVE', 'rep_2nd_year'];
        yield 'split numeric second' => ['2 ND YEAR REPRESENTATIVE', 'rep_2nd_year'];
        yield 'superscript numeric second' => ['2ⁿᵈ YEAR REPRESENTATIVE', 'rep_2nd_year'];
        yield 'written second' => ['SECOND YEAR REPRESENTATIVE', 'rep_2nd_year'];
        yield 'numeric third' => ['3RD YEAR REPRESENTATIVE', 'rep_3rd_year'];
        yield 'split numeric third' => ['3 RD YEAR REPRESENTATIVE', 'rep_3rd_year'];
        yield 'superscript numeric third' => ['3ʳᵈ YEAR REPRESENTATIVE', 'rep_3rd_year'];
        yield 'written third' => ['THIRD YEAR REPRESENTATIVE', 'rep_3rd_year'];
        yield 'numeric fourth' => ['4TH YEAR REPRESENTATIVE', 'rep_4th_year'];
        yield 'split numeric fourth' => ['4 TH YEAR REPRESENTATIVE', 'rep_4th_year'];
        yield 'superscript numeric fourth' => ['4ᵗʰ YEAR REPRESENTATIVE', 'rep_4th_year'];
        yield 'written fourth' => ['FOURTH YEAR REPRESENTATIVE', 'rep_4th_year'];
    }

    public function test_representative_aliases_share_capacity_and_preserve_first_source_record(): void
    {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate([
            self::record("  first\t year   representative ", 0),
            self::record('1ST YEAR REPRESENTATIVE', 1),
        ]);

        self::assertSame(['rep_1st_year', 'custom_1'], array_keys($result['officers']));
        self::assertSame('First0', $result['officers']['rep_1st_year']['first_name']);
        self::assertSame('1st Year Representative', $result['officers']['custom_1']['position_name']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('captured', $result['warnings'][0]);
    }

    #[DataProvider('unsupportedRepresentativeNearMisses')]
    public function test_representative_near_misses_are_captured_as_custom_slots_with_a_warning(string $role, string $positionName): void
    {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate([
            self::record($role, 0),
        ]);

        self::assertSame(['custom_1'], array_keys($result['officers']));
        self::assertSame('First0', $result['officers']['custom_1']['first_name']);
        self::assertSame('Last0', $result['officers']['custom_1']['last_name']);
        self::assertSame($positionName, $result['officers']['custom_1']['position_name']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('captured', $result['warnings'][0]);
    }

    public static function unsupportedRepresentativeNearMisses(): iterable
    {
        yield 'hyphenated written ordinal' => ['FIRST-YEAR REPRESENTATIVE', 'First-year Representative'];
        yield 'hyphenated numeric ordinal' => ['1ST-YEAR REPRESENTATIVE', '1st-year Representative'];
        yield 'incorrect numeric suffix' => ['1TH YEAR REPRESENTATIVE', '1th Year Representative'];
        yield 'split suffix without full role' => ['1 ST YEAR REP', '1 St Year Rep'];
        yield 'abbreviated role' => ['FIRST YEAR REP', 'First Year Rep'];
        yield 'plural role' => ['SECOND YEAR REPRESENTATIVES', 'Second Year Representatives'];
        yield 'unsupported year' => ['FIFTH YEAR REPRESENTATIVE', 'Fifth Year Representative'];
        yield 'reordered words' => ['YEAR THIRD REPRESENTATIVE', 'Year Third Representative'];
    }

    #[DataProvider('seededMixedRoleSequences')]
    public function test_seeded_mixed_sequences_allocate_in_source_order_without_replacement(array $records, array $expectedSlots, int $expectedWarnings): void
    {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate($records);

        self::assertSame($expectedSlots, array_keys($result['officers']));
        self::assertCount($expectedWarnings, $result['warnings']);
    }

    public static function seededMixedRoleSequences(): iterable
    {
        mt_srand(32440);
        $sequences = [
            ['EVENT COORDINATOR', 'BUSINESS MANAGER', 'PUBLIC INFORMATION OFFICER', 'BUSINESS MANAGER'],
            ['PUBLIC INFORMATION OFFICER', 'EVENT COORDINATOR', 'PUBLIC INFORMATION OFFICER', 'EVENT COORDINATOR'],
            ['AUDITOR', 'SECRETARY', '4TH YEAR REPRESENTATIVE', 'PRESIDENT'],
            ['1 ST YEAR REPRESENTATIVE', '2 ND YEAR REPRESENTATIVE', '3 RD YEAR REPRESENTATIVE', '4 TH YEAR REPRESENTATIVE'],
        ];
        $expected = [
            ['event_coordinator', 'business_manager', 'pio', 'custom_1'],
            ['pio', 'event_coordinator', 'custom_1', 'custom_2'],
            ['auditor', 'secretary', 'rep_4th_year', 'president'],
            ['rep_1st_year', 'rep_2nd_year', 'rep_3rd_year', 'rep_4th_year'],
        ];
        $expectedWarnings = [1, 2, 0, 0];
        foreach ($sequences as $index => $sequence) {
            $records = [];
            foreach ($sequence as $page => $role) {
                $records[] = self::record($role, $page, mt_rand(1, 4));
            }
            yield "seeded-sequence-{$index}" => [$records, $expected[$index], $expectedWarnings[$index]];
        }
    }

    public function test_over_capacity_roles_warn_while_unsupported_roles_are_captured_as_custom_slots(): void
    {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate([
            self::record('BUSINESS MANAGER', 0), self::record('BUSINESS MANAGER', 1),
            self::record('BUSINESS MANAGER', 2), self::record('VICE-PRESIDENT', 3),
        ]);

        self::assertSame(['business_manager', 'custom_1', 'custom_2', 'custom_3'], array_keys($result['officers']));
        self::assertSame('Business Manager', $result['officers']['custom_1']['position_name']);
        self::assertSame('Business Manager', $result['officers']['custom_2']['position_name']);
        self::assertSame('Vice-president', $result['officers']['custom_3']['position_name']);
        self::assertCount(3, $result['warnings']);
        self::assertStringContainsString('captured', $result['warnings'][0]);
        self::assertStringContainsString('captured', $result['warnings'][1]);
        self::assertStringContainsString('captured', $result['warnings'][2]);
    }

    public function test_multiple_unknown_roles_are_captured_as_sequential_custom_slots_in_source_order(): void
    {
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate([
            self::record('ASSISTANT SECRETARY', 0),
            self::record('HUMAN RESOURCES', 1),
            self::record('SECRETARY', 2),
        ]);

        self::assertSame(['custom_1', 'custom_2', 'secretary'], array_keys($result['officers']));
        self::assertSame('Assistant Secretary', $result['officers']['custom_1']['position_name']);
        self::assertSame('Human Resources', $result['officers']['custom_2']['position_name']);
        self::assertArrayNotHasKey('position_name', $result['officers']['secretary']);
        self::assertCount(2, $result['warnings']);
    }

    public function test_custom_slot_capacity_is_guarded_at_the_upper_bound(): void
    {
        $records = [];
        for ($page = 0; $page < 22; $page++) {
            $records[] = self::record("CUSTOM POSITION {$page}", $page);
        }
        $result = (new CertificationOfOfficersRoleSlotRegistry)->allocate($records);

        $customKeys = array_values(array_filter(array_keys($result['officers']), static fn (string $key): bool => CertificationOfOfficersRoleSlotRegistry::isCustomSlot($key)));
        self::assertCount(20, $customKeys);
        self::assertSame('custom_1', $customKeys[0]);
        self::assertSame('custom_20', $customKeys[19]);
        $overCapacityWarnings = array_values(array_filter(
            $result['warnings'],
            static fn (string $warning): bool => str_contains($warning, 'exceeds the supported capacity'),
        ));
        self::assertCount(2, $overCapacityWarnings);
    }

    public function test_is_custom_slot_detects_only_dynamic_other_position_keys(): void
    {
        self::assertTrue(CertificationOfOfficersRoleSlotRegistry::isCustomSlot('custom_1'));
        self::assertTrue(CertificationOfOfficersRoleSlotRegistry::isCustomSlot('custom_20'));
        self::assertFalse(CertificationOfOfficersRoleSlotRegistry::isCustomSlot('custom'));
        self::assertFalse(CertificationOfOfficersRoleSlotRegistry::isCustomSlot('secretary'));
        self::assertFalse(CertificationOfOfficersRoleSlotRegistry::isCustomSlot('business_manager'));
    }

    public function test_role_qualified_certifiers_are_deduplicated_and_only_first_destinations_are_retained(): void
    {
        $registry = new CertificationOfOfficersRoleSlotRegistry;
        $certifiers = $registry->certifierDestinations([
            self::certifier('organization_adviser', 'AIDEN', 'NORTH', 'MA'),
            self::certifier('organization_adviser', 'aiden', 'north', 'ma'),
            self::certifier('organization_adviser', 'BLAIR', 'SOUTH', 'PhD'),
            self::certifier('organization_adviser', 'CASEY', 'EAST', 'EdD'),
        ], [
            self::certifier('dean_assoc_dean', 'DREW', 'WEST', 'EdD'),
            self::certifier('dean_assoc_dean', 'drew', 'west', 'edd'),
            self::certifier('dean_assoc_dean', 'ELLIOT', 'NORTH', 'PhD'),
        ]);

        self::assertSame('AIDEN', $certifiers['senior_adviser']['first_name']);
        self::assertSame('BLAIR', $certifiers['junior_adviser']['first_name']);
        self::assertSame('DREW', $certifiers['dean']['first_name']);
    }

    public function test_browser_positions_use_the_same_explicit_first_slot_keys_without_alias_duplicates(): void
    {
        $positions = CertificationOfOfficersRoleSlotRegistry::browserPositions();
        $positionKeys = array_column($positions, 'key');
        $documentedSlots = CertificationOfOfficersRoleSlotRegistry::documentedSlots();

        self::assertSame(['senior_adviser', 'junior_adviser', ...$documentedSlots], $positionKeys);
        self::assertSame($positionKeys, array_values(array_unique($positionKeys)));
        self::assertSame($documentedSlots, array_values(array_unique($documentedSlots)));
        self::assertSame(1, count(array_keys($documentedSlots, 'rep_1st_year', true)));
        self::assertSame(1, count(array_keys($documentedSlots, 'rep_2nd_year', true)));
        self::assertSame(1, count(array_keys($documentedSlots, 'rep_3rd_year', true)));
        self::assertSame(1, count(array_keys($documentedSlots, 'rep_4th_year', true)));
        self::assertNotContains('secretary_1', $positionKeys);
        self::assertNotContains('business_manager_1', $positionKeys);
        self::assertNotContains('pio_1', $positionKeys);
        self::assertNotContains('event_coordinator_1', $positionKeys);
        self::assertContains('comelec', $positionKeys);
    }

    private static function record(string $role, int $page, int $year = 2): array
    {
        return ['page_index' => $page, 'position' => $role, 'first_name' => "First{$page}", 'middle_name' => 'M.',
            'last_name' => "Last{$page}", 'suffix' => '', 'year_level' => (string) $year, 'section' => 'A'];
    }

    private static function certifier(string $role, string $first, string $last, string $suffix): array
    {
        return ['role' => $role, 'first_name' => $first, 'middle_name' => 'M.', 'last_name' => $last, 'suffix' => $suffix];
    }
}
