<?php

namespace App\Services;

use App\Models\OrganizationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ThemeService
{
    const DEFAULT_PRIMARY_COLOR = '#8238B3';
    const DEFAULT_COMPLEMENTARY_COLOR = '#D7AC28';
    const DEFAULT_LOGO_PATH = 'images/soccsyncLogo-removebg-preview.png';
    
    /**
     * Get theme settings for the authenticated user's organization
     * 
     * @return array{primary_color: string, complementary_color: string, logo_url: string}
     */
    public function getThemeSettings(): array
    {
        // Try to get organization_id from session first (for admin users)
        $organizationId = session('current_organization_id');
        
        // If not in session, try to get from user's first active organization (for students)
        if (!$organizationId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            $organizationId = $firstOrg?->id;
        }
        
        if (!$organizationId) {
            return $this->getDefaultTheme();
        }
        
        $settings = OrganizationSettings::where('organization_id', $organizationId)->first();
        
        if (!$settings) {
            return $this->getDefaultTheme();
        }
        
        return [
            'primary_color' => $settings->primary_color ?? self::DEFAULT_PRIMARY_COLOR,
            'complementary_color' => $settings->complementary_color ?? self::DEFAULT_COMPLEMENTARY_COLOR,
            'logo_url' => $settings->logo_url ?? asset(self::DEFAULT_LOGO_PATH),
        ];
    }
    
    /**
     * Update theme settings for the authenticated user's organization
     * 
     * @param array $data
     * @return OrganizationSettings
     * @throws \Exception
     */
    public function updateThemeSettings(array $data): OrganizationSettings
    {
        // Try to get organization_id from session first (for admin users)
        $organizationId = session('current_organization_id');
        
        // If not in session, try to get from user's first active organization (for students)
        if (!$organizationId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            $organizationId = $firstOrg?->id;
        }
        
        if (!$organizationId) {
            throw new \Exception('User is not associated with an organization');
        }
        
        $settings = OrganizationSettings::firstOrCreate(
            ['organization_id' => $organizationId]
        );
        
        // Handle logo upload
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            try {
                $logoPath = $this->uploadLogo($data['logo'], $organizationId);
                $settings->logo_url = $logoPath;
            } catch (\Exception $e) {
                Log::error('Logo upload failed', [
                    'error' => $e->getMessage(),
                    'organization_id' => $organizationId,
                ]);
                throw new \Exception('Failed to upload logo');
            }
        }
        
        // Update colors with validation
        if (isset($data['primary_color'])) {
            if ($this->isValidHexColor($data['primary_color'])) {
                $settings->primary_color = $data['primary_color'];
            } else {
                throw new \Exception('Invalid primary color format');
            }
        }
        
        if (isset($data['complementary_color'])) {
            if ($this->isValidHexColor($data['complementary_color'])) {
                $settings->complementary_color = $data['complementary_color'];
            } else {
                throw new \Exception('Invalid complementary color format');
            }
        }
        
        $settings->save();
        
        return $settings;
    }
    
    /**
     * Upload and store logo file
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param int|null $organizationId
     * @return string Logo path
     */
    public function uploadLogo(UploadedFile $file, ?int $organizationId = null): string
    {
        // Try to get organization_id from session first (for admin users)
        if (!$organizationId) {
            $organizationId = session('current_organization_id');
        }
        
        // If not in session, try to get from user's first active organization (for students)
        if (!$organizationId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            $organizationId = $firstOrg?->id;
        }
        
        if (!$organizationId) {
            throw new \Exception('User is not associated with an organization');
        }
        
        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = 'logo_' . $organizationId . '_' . time() . '.' . $extension;
        
        // Store in logos directory using the configured disk (s3 or public)
        $disk = config('filesystems.default');
        $path = $file->storeAs('logos', $filename, $disk);
        
        return $path;
    }
    
    /**
     * Validate hex color format
     * 
     * @param string $color
     * @return bool
     */
    public function isValidHexColor(string $color): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1;
    }
    
    /**
     * Get default theme values
     * 
     * @return array{primary_color: string, complementary_color: string, logo_url: string}
     */
    public function getDefaultTheme(): array
    {
        return [
            'primary_color' => self::DEFAULT_PRIMARY_COLOR,
            'complementary_color' => self::DEFAULT_COMPLEMENTARY_COLOR,
            'logo_url' => asset(self::DEFAULT_LOGO_PATH),
        ];
    }
}
