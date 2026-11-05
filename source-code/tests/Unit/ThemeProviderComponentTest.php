<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ThemeService;

class ThemeProviderComponentTest extends TestCase
{
    /**
     * Test that theme provider component file exists and has correct structure
     */
    public function test_theme_provider_component_exists(): void
    {
        $componentPath = resource_path('views/components/theme-provider.blade.php');
        
        // Assert file exists
        $this->assertFileExists($componentPath);
        
        // Read file content
        $content = file_get_contents($componentPath);
        
        // Assert it contains required elements
        $this->assertStringContainsString('ThemeService', $content);
        $this->assertStringContainsString('getThemeSettings', $content);
        $this->assertStringContainsString(':root', $content);
        $this->assertStringContainsString('--primary-color', $content);
        $this->assertStringContainsString('--complementary-color', $content);
        $this->assertStringContainsString('<style>', $content);
        $this->assertStringContainsString('</style>', $content);
    }
    
    /**
     * Test that ThemeService returns expected structure
     */
    public function test_theme_service_returns_expected_structure(): void
    {
        $themeService = new ThemeService();
        $defaultTheme = $themeService->getDefaultTheme();
        
        // Assert structure
        $this->assertIsArray($defaultTheme);
        $this->assertArrayHasKey('primary_color', $defaultTheme);
        $this->assertArrayHasKey('complementary_color', $defaultTheme);
        $this->assertArrayHasKey('logo_url', $defaultTheme);
        
        // Assert default values
        $this->assertEquals('#8238B3', $defaultTheme['primary_color']);
        $this->assertEquals('#D7AC28', $defaultTheme['complementary_color']);
    }
}
