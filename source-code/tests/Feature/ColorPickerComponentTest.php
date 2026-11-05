<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ColorPickerComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_color_picker_is_rendered_on_settings_page(): void
    {
        $response = $this->settingsPage();

        $response->assertOk();
        $response->assertSee('Primary Color');
        $response->assertSee('Main brand color for navigation and buttons');
        $response->assertSee('type="color"', false);
        $response->assertSee('name="primary_color"', false);
        $response->assertSee('placeholder="#RRGGBB"', false);
        $response->assertSee('function colorPicker(initialColor, colorType)', false);
    }

    public function test_primary_color_picker_initializes_with_default_color(): void
    {
        $this->settingsPage()->assertSee('#8238B3', false);
    }

    public function test_primary_color_picker_initializes_with_custom_color(): void
    {
        $this->settingsPage('#FF5733')->assertSee('#FF5733', false);
    }

    public function test_color_picker_has_required_methods(): void
    {
        $response = $this->settingsPage();

        $response->assertSee('updateFromHex()', false);
        $response->assertSee('updatePreview()', false);
        $response->assertSee('this.$watch(\'color\'', false);
        $response->assertSee('/^#[0-9A-Fa-f]{6}$/', false);
        $response->assertSee('Invalid color. Use #RRGGBB format');
    }

    private function settingsPage(?string $primaryColor = null): TestResponse
    {
        $organization = Organization::factory()->create();
        if ($primaryColor !== null) {
            OrganizationSettings::create([
                'organization_id' => $organization->id,
                'primary_color' => $primaryColor,
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->organizations()->attach($organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        return $this->actingAs($admin)
            ->withSession(['current_organization_id' => $organization->id])
            ->get(route('admin.settings.index'));
    }
}
