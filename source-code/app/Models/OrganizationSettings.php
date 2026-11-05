<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationSettings extends Model
{
    protected $fillable = [
        'organization_id',
        'theme_color',
        'primary_color',
        'complementary_color',
        'logo_url',
        'has_announcements',
        'has_events',
        'has_payments',
        'has_elections',
        'has_elibrary',
        'has_edushare',
        'has_chatbot',
        'has_complaints',
        'has_documents',
        'welcome_text',
    ];

    protected $casts = [
        'has_announcements' => 'boolean',
        'has_events' => 'boolean',
        'has_payments' => 'boolean',
        'has_elections' => 'boolean',
        'has_elibrary' => 'boolean',
        'has_edushare' => 'boolean',
        'has_chatbot' => 'boolean',
        'has_complaints' => 'boolean',
        'has_documents' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the logo URL with fallback to default
     */
    public function getLogoUrlAttribute($value)
    {
        if ($value) {
            // If it's a full URL (starts with http), return as is
            if (str_starts_with($value, 'http')) {
                return $value;
            }
            
            // Check if this is the default logo filename
            if (str_contains($value, 'soccsyncLogo-removebg-preview.png')) {
                // Return local asset instead of trying to load from Cloudflare
                return 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
            }
            
            // If it's a path, use storage_url helper
            return storage_url($value);
        }
        
        // Fallback to default logo
        return 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
    }

    /**
     * Get primary color with fallback to default
     */
    public function getPrimaryColorAttribute($value): string
    {
        return $value ?? '#8238B3';
    }

    /**
     * Get complementary color with fallback to default
     */
    public function getComplementaryColorAttribute($value): string
    {
        return $value ?? '#D7AC28';
    }

    /**
     * Get permanent modules that are always enabled
     *
     * @return array
     */
    public static function permanentModules(): array
    {
        return ['announcements', 'events', 'payments', 'chatbot'];
    }

    /**
     * Get optional modules that can be enabled/disabled
     *
     * @return array
     */
    public static function optionalModules(): array
    {
        return ['elections', 'elibrary', 'edushare', 'complaints', 'documents'];
    }

    /**
     * Get all enabled modules for this organization
     *
     * @return array
     */
    public function enabledModules(): array
    {
        $enabled = self::permanentModules();

        foreach (self::optionalModules() as $module) {
            $hasColumn = 'has_' . $module;
            if ($this->$hasColumn) {
                $enabled[] = $module;
            }
        }

        return $enabled;
    }
}
