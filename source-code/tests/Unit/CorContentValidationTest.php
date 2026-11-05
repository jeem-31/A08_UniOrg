<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\OrgAuthController;
use PHPUnit\Framework\TestCase;

final class CorContentValidationTest extends TestCase
{
    private const REAL_COR_TEXT = <<<TXT
Republic of the Philippines
Laguna State Polytechnic University
Province of Laguna
Santa Cruz Campus
OFFICE OF THE UNIVERSITY REGISTRAR
CERTIFICATE OF REGISTRATION
First (1st) Semester, A.Y. 2026-2027
Student Name : Mazel Catherine Platero Joya Student No. : 0123-2172
Course : Bachelor of Science in Information Technology Year Level : Fourth Year
Gender : Female   Date Enrolled   :   Jul 24, 2026 Scholar :
Subject Code Subject Title Section Unit Time/Day Room Professor
ITEP 415 Capstone Project 2 BSIT 4C 3 10:00 AM - 01:00 PM / Thu ROOM 107 Reynalen Justo
Total Units 12
GRAND TOTAL: 3,380.00
TXT;

    public function test_real_cor_text_scores_above_threshold(): void
    {
        $this->assertGreaterThanOrEqual(4, OrgAuthController::corScore(self::REAL_COR_TEXT));
        $this->assertSame(7, OrgAuthController::corScore(self::REAL_COR_TEXT));
    }

    public function test_random_text_scores_zero(): void
    {
        $this->assertSame(0, OrgAuthController::corScore('Lorem ipsum dolor sit amet, consectetur adipiscing elit.'));
    }

    public function test_payslip_text_scores_below_threshold(): void
    {
        $text = "Laguna State Polytechnic University\nEmployee Pay Slip\nEmployee No. 0123-4567\nSalary 25,000.00";
        $this->assertLessThan(4, OrgAuthController::corScore($text));
    }

    public function test_certificate_of_employment_scores_below_threshold(): void
    {
        $this->assertSame(0, OrgAuthController::corScore('Certificate of Employment - Jollibee. This certifies that Juan Cruz is employed.'));
    }

    public function test_blurry_but_real_cor_text_still_passes(): void
    {
        $text = "Student Name : Mazel Catherine Joya Student No. : 0123-2172\nCourse : Bachelor of Science in Information Technology\nYear Level : Fourth Year";
        $this->assertGreaterThanOrEqual(4, OrgAuthController::corScore($text));
    }

    public function test_empty_text_scores_zero(): void
    {
        $this->assertSame(0, OrgAuthController::corScore(''));
        $this->assertSame(0, OrgAuthController::corScore('   '));
        $this->assertSame(0, OrgAuthController::corScore(null));
    }

    public function test_ocr_garbled_certificate_header_still_counts(): void
    {
        $text = "CERTIFICATE OF REGISTRATIQN\nStudent Name : Mazel Joya\nStudent No. : 0123-2172\nA.Y. 2026-2027";
        $this->assertGreaterThanOrEqual(4, OrgAuthController::corScore($text));
    }
}
