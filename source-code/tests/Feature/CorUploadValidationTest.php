<?php

namespace Tests\Feature;

use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CorUploadValidationTest extends TestCase
{
    use RefreshDatabase;

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

    private function mockOcr(string $text): void
    {
        $this->mock(OcrService::class, function ($mock) use ($text) {
            $mock->shouldReceive('basahinAngPicture')->andReturn($text);
        });
    }

    public function test_non_cor_image_is_rejected_with_clearer_image_message(): void
    {
        Storage::fake('local');
        $this->mockOcr('Lorem ipsum dolor sit amet, consectetur adipiscing elit.');

        $this->post('/org/upload-cor', [
            'cor_file' => UploadedFile::fake()->image('random.jpg'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This file does not appear to be a valid COR. Please upload a clearer image or PDF of your COR.');
    }

    public function test_unreadable_image_requests_a_clearer_image(): void
    {
        Storage::fake('local');
        $this->mockOcr('');

        $this->post('/org/upload-cor', [
            'cor_file' => UploadedFile::fake()->image('blurry.jpg'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This file does not appear to be a valid COR. Please upload a clearer image or PDF of your COR.');
    }

    public function test_non_cor_pdf_is_rejected_with_valid_cor_message(): void
    {
        Storage::fake('local');
        $this->mockOcr('Random PDF text without any COR markers.');

        $this->post('/org/upload-cor', [
            'cor_file' => UploadedFile::fake()->create('random.pdf', 10, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This file does not appear to be a valid COR. Please upload your Certificate of Registration.');
    }

    public function test_valid_cor_image_is_accepted(): void
    {
        Storage::fake('local');
        $this->mockOcr(self::REAL_COR_TEXT);

        $this->post('/org/upload-cor', [
            'cor_file' => UploadedFile::fake()->image('cor.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_number', '0123-2172')
            ->assertJsonPath('data.course', 'Bachelor of Science in Information Technology')
            ->assertJsonPath('data.year_level', '4th Year');
    }

    public function test_invalid_file_type_is_still_rejected(): void
    {
        Storage::fake('local');
        $this->mockOcr(self::REAL_COR_TEXT);

        $this->postJson('/org/upload-cor', [
            'cor_file' => UploadedFile::fake()->create('cor.exe', 10, 'application/x-msdownload'),
        ])
            ->assertStatus(422);
    }
}
