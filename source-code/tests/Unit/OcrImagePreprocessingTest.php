<?php

namespace Tests\Unit;

use App\Services\OcrService;
use PHPUnit\Framework\TestCase;

final class OcrImagePreprocessingTest extends TestCase
{
    private function makeJpeg(int $width, int $height, string $color = 'white'): string
    {
        $path = sys_get_temp_dir() . '/ocr-prep-' . uniqid() . '.jpg';
        $img = imagecreatetruecolor($width, $height);

        if ($color === 'red') {
            $fill = imagecolorallocate($img, 200, 40, 40);
        } else {
            $fill = imagecolorallocate($img, 255, 255, 255);
        }

        imagefill($img, 0, 0, $fill);
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    public function test_large_image_is_downscaled_and_grayscaled(): void
    {
        $path = $this->makeJpeg(3000, 2000, 'red');
        $prep = null;

        try {
            $service = new OcrService();
            $prep = $service->preprocessImage($path);

            $this->assertNotSame($path, $prep, 'A large image must produce a preprocessed copy');
            $this->assertFileExists($prep);

            [$width, $height] = getimagesize($prep);
            $this->assertSame(2000, max($width, $height));
            $this->assertLessThanOrEqual(2000, $width);
            $this->assertLessThanOrEqual(2000, $height);

            // The preprocessed copy must be grayscale (r === g === b).
            $sample = imagecreatefromjpeg($prep);
            $rgb = imagecolorat($sample, 10, 10);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            imagedestroy($sample);

            $this->assertSame($r, $g);
            $this->assertSame($g, $b);
        } finally {
            @unlink($path);
            if ($prep && $prep !== $path) {
                @unlink($prep);
            }
        }
    }

    public function test_small_image_returns_original_path_unchanged(): void
    {
        $path = $this->makeJpeg(800, 600);
        $service = new OcrService();

        try {
            $this->assertSame($path, $service->preprocessImage($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_non_image_paths_are_returned_unchanged(): void
    {
        $service = new OcrService();

        $this->assertSame('file.txt', $service->preprocessImage('file.txt'));
        $this->assertSame('doc.pdf', $service->preprocessImage('doc.pdf'));
        $this->assertSame('archive.docx', $service->preprocessImage('archive.docx'));
    }

    public function test_missing_image_returns_original_path(): void
    {
        $service = new OcrService();
        $missing = sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.jpg';

        $this->assertSame($missing, $service->preprocessImage($missing));
    }
}
