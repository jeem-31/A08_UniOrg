<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NameDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_title_case_name_helper_handles_all_caps_suffixes_and_apostrophes(): void
    {
        $this->assertSame('Juan Carlos Dela Cruz', title_case_name('JUAN CARLOS DELA CRUZ'));
        $this->assertSame('Maria Santos Jr.', title_case_name('MARIA SANTOS JR.'));
        $this->assertSame('Dela Cruz II', title_case_name('DELA CRUZ II'));
        $this->assertSame("O'Brien", title_case_name("O'BRIEN"));
        $this->assertSame('Anna-Marie', title_case_name('ANNA-MARIE'));
        $this->assertSame('John Dela Cruz', title_case_name('john dela cruz'));
        $this->assertSame('', title_case_name(''));
        $this->assertSame('', title_case_name('  '));
    }

    public function test_title_case_name_handles_academic_and_professional_credentials(): void
    {
        $this->assertSame('Juan Santos PhD', title_case_name('JUAN SANTOS PHD'));
        $this->assertSame('Juan Santos, PhD', title_case_name('JUAN SANTOS, PHD'));
        $this->assertSame('Mia Villarica, DIT, LPT', title_case_name('MIA VILLARICA, DIT, LPT'));
        $this->assertSame('Riley Dela Vega, EdD', title_case_name('RILEY DELA VEGA, EDD'));
        $this->assertSame('Casey West, MD', title_case_name('CASEY WEST, MD'));
        $this->assertSame('Ana Cruz, RN', title_case_name('ANA CRUZ, RN'));
        $this->assertSame('Leo Ramos, CPA, MBA', title_case_name('LEO RAMOS, CPA, MBA'));
        $this->assertSame('Nina Reyes, RPh', title_case_name('NINA REYES, RPH'));
        $this->assertSame('Paolo Santos, Engr.', title_case_name('PAOLO SANTOS, ENGR.'));
        $this->assertSame('Mae Dizon, PharmD', title_case_name('MAE DIZON, PHARMD'));
        $this->assertSame('Jay Cruz, BSEd', title_case_name('JAY CRUZ, BSED'));
    }

    public function test_title_case_name_does_not_mistake_name_words_for_credentials(): void
    {
        // "Ma." is the common abbreviation of Maria, not a Master of Arts suffix.
        $this->assertSame('Ma. Theresa Santos', title_case_name('MA. THERESA SANTOS'));
        // Short two-token names never map the final token to a degree.
        $this->assertSame('Juan Ma', title_case_name('JUAN MA'));
        $this->assertSame('Juan Do', title_case_name('JUAN DO'));
        // A credential-looking token in the middle of a name stays a normal word.
        $this->assertSame('Juan Ma Santos', title_case_name('JUAN MA SANTOS'));
    }

    public function test_name_with_suffix_builds_names_with_suffix(): void
    {
        $this->assertSame('Juan Dela Cruz Jr.', name_with_suffix('JUAN', null, 'DELA CRUZ', 'JR.'));
        $this->assertSame('Juan D. Dela Cruz PhD', name_with_suffix('JUAN', 'D.', 'DELA CRUZ', 'PHD'));
        $this->assertSame('Maria Santos EdD', name_with_suffix('MARIA', null, 'SANTOS', 'EDD'));
        $this->assertSame('Juan Dela Cruz', name_with_suffix('JUAN', null, 'DELA CRUZ', ''));
    }

    public function test_user_name_accessors_return_title_case(): void
    {
        $user = User::factory()->create([
            'first_name' => 'JUAN CARLOS',
            'middle_name' => 'DELA',
            'last_name' => 'CRUZ',
            'suffix' => 'JR.',
        ]);

        $this->assertSame('Juan Carlos Dela Cruz Jr.', $user->full_name);
        $this->assertSame('Juan Cruz', $user->short_name);
        $this->assertSame('Juan Carlos Cruz', $user->first_last_name);
        $this->assertSame('Juan Carlos Cruz', $user->announcement_display_name);
        $this->assertSame('Juan Carlos D. Cruz Jr.', $user->full_name_with_middle_initial);
    }

    public function test_login_response_user_name_is_title_cased(): void
    {
        $organization = Organization::factory()->create(['status' => 'Approved', 'has_system' => true]);
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'password' => Hash::make('password'),
        ]);
        $user->organizations()->attach($organization->id, [
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        $response = $this->postJson('/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_name' => 'Juan Dela Cruz',
        ]);
    }

    public function test_org_login_response_user_name_is_title_cased(): void
    {
        $organization = Organization::factory()->create(['status' => 'Approved', 'has_system' => true]);
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'password' => Hash::make('password'),
        ]);
        $user->organizations()->attach($organization->id, [
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_name' => 'Juan Dela Cruz',
        ]);
    }
}
