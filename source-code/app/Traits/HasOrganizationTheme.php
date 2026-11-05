<?php

namespace App\Traits;

use App\Models\Organization;
use App\Models\OrganizationSettings;

trait HasOrganizationTheme
{
    private const DEFAULT_LOGO_URL = 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
    /**
     * Get organization theme data for the notifiable user
     */
    protected function getOrganizationTheme($notifiable, ?Organization $organization = null): array
    {
        // A notification may belong to a specific organization (for example, an
        // election). Use that organization instead of inferring one from the
        // recipient, who can belong to multiple related organizations.
        if (!$organization) {
            $organization = $notifiable->activeOrganizations()->first();

            if (!$organization) {
                $organization = $notifiable->organizations()->first();
            }
        }

        if (!$organization) {
            return [
                'organization_name' => 'UniOrg',
                'primary_color' => '#8238B3',
                'complementary_color' => '#D7AC28',
                'logo_url' => self::DEFAULT_LOGO_URL,
            ];
        }

        // The logo_url accessor resolves R2 object keys to the configured public URL.
        $settings = OrganizationSettings::where('organization_id', $organization->id)->first();

        return [
            'organization_name' => $organization->name,
            'organization_acronym' => $organization->org_acronym ?? $organization->name,
            'primary_color' => $settings?->primary_color ?? '#8238B3',
            'complementary_color' => $settings?->complementary_color ?? '#D7AC28',
            'logo_url' => $settings?->logo_url ?? self::DEFAULT_LOGO_URL,
            'from_email' => config('mail.from.address'),
        ];
    }
}
