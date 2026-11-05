<?php

namespace Tests\Unit;

use App\Models\OrganizationSettings;
use PHPUnit\Framework\TestCase;

class OrganizationSettingsTest extends TestCase
{
    /**
     * Test that getPrimaryColorAttribute returns default when value is null
     */
    public function test_get_primary_color_attribute_returns_default_when_null(): void
    {
        $settings = new OrganizationSettings();
        
        // Test the accessor directly
        $result = $settings->getPrimaryColorAttribute(null);
        
        $this->assertEquals('#8238B3', $result);
    }

    /**
     * Test that getPrimaryColorAttribute returns custom value when set
     */
    public function test_get_primary_color_attribute_returns_custom_value(): void
    {
        $settings = new OrganizationSettings();
        
        // Test the accessor directly
        $result = $settings->getPrimaryColorAttribute('#FF5733');
        
        $this->assertEquals('#FF5733', $result);
    }

    /**
     * Test that getComplementaryColorAttribute returns default when value is null
     */
    public function test_get_complementary_color_attribute_returns_default_when_null(): void
    {
        $settings = new OrganizationSettings();
        
        // Test the accessor directly
        $result = $settings->getComplementaryColorAttribute(null);
        
        $this->assertEquals('#D7AC28', $result);
    }

    /**
     * Test that getComplementaryColorAttribute returns custom value when set
     */
    public function test_get_complementary_color_attribute_returns_custom_value(): void
    {
        $settings = new OrganizationSettings();
        
        // Test the accessor directly
        $result = $settings->getComplementaryColorAttribute('#33FF57');
        
        $this->assertEquals('#33FF57', $result);
    }
}
