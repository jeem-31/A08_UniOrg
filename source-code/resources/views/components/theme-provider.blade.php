@php
    try {
        $themeService = app(\App\Services\ThemeService::class);
        $theme = $themeService->getThemeSettings();
        
        // Get organization ID for debugging
        $orgId = session('current_organization_id');
        if (!$orgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            $orgId = $firstOrg?->id ?? 'none';
        }
        
        // Convert hex colors to RGB values
        $primaryRgb = sscanf($theme['primary_color'], "#%02x%02x%02x");
        $complementaryRgb = sscanf($theme['complementary_color'], "#%02x%02x%02x");
        
        $theme['primary_color_rgb'] = implode(', ', $primaryRgb);
        $theme['complementary_color_rgb'] = implode(', ', $complementaryRgb);
        
        // DEBUG: Log theme values
        \Log::info('Theme Provider Debug', [
            'primary_color' => $theme['primary_color'],
            'complementary_color' => $theme['complementary_color'],
            'primary_color_rgb' => $theme['primary_color_rgb'],
            'complementary_color_rgb' => $theme['complementary_color_rgb'],
            'organization_id' => $orgId,
            'user_id' => auth()->id() ?? 'guest',
            'user_role' => auth()->user()->role ?? 'guest',
        ]);
    } catch (\Exception $e) {
        // Fallback to default theme if service fails
        \Log::error('Theme Provider Error', ['error' => $e->getMessage()]);
        $theme = [
            'primary_color' => '#8238B3',
            'complementary_color' => '#D7AC28',
            'primary_color_rgb' => '130, 56, 179',
            'complementary_color_rgb' => '215, 172, 40',
        ];
        $orgId = 'error';
    }
@endphp

<!-- DEBUG: Show theme values in HTML comment -->
<!-- Theme Debug: Primary={{ $theme['primary_color'] }}, Complementary={{ $theme['complementary_color'] }}, OrgID={{ $orgId }} -->

<style>
    :root {
        --primary-color: {{ $theme['primary_color'] }};
        --complementary-color: {{ $theme['complementary_color'] }};
        --primary-color-rgb: {{ $theme['primary_color_rgb'] }};
        --complementary-color-rgb: {{ $theme['complementary_color_rgb'] }};
    }
</style>

{{-- Force immediate CSS variable application with JavaScript --}}
<script>
    // Apply theme colors IMMEDIATELY before page renders
    (function() {
        const primaryColor = '{{ $theme['primary_color'] }}';
        const complementaryColor = '{{ $theme['complementary_color'] }}';
        const primaryColorRgb = '{{ $theme['primary_color_rgb'] }}';
        const complementaryColorRgb = '{{ $theme['complementary_color_rgb'] }}';
        
        console.log('🎨 Theme Provider Loading:', {
            primary: primaryColor,
            complementary: complementaryColor,
            primaryRgb: primaryColorRgb,
            complementaryRgb: complementaryColorRgb,
            orgId: '{{ $orgId }}'
        });
        
        const root = document.documentElement;
        root.style.setProperty('--primary-color', primaryColor);
        root.style.setProperty('--complementary-color', complementaryColor);
        root.style.setProperty('--primary-color-rgb', primaryColorRgb);
        root.style.setProperty('--complementary-color-rgb', complementaryColorRgb);
        
        console.log('✅ CSS Variables Set:', {
            primary: root.style.getPropertyValue('--primary-color'),
            complementary: root.style.getPropertyValue('--complementary-color'),
            primaryRgb: root.style.getPropertyValue('--primary-color-rgb'),
            complementaryRgb: root.style.getPropertyValue('--complementary-color-rgb')
        });
        
        // CRITICAL: Inject hover styles immediately with actual color values
        // Using VERY HIGH SPECIFICITY to override Tailwind CSS that loads later
        const styleEl = document.createElement('style');
        styleEl.id = 'immediate-hover-styles';
        styleEl.textContent = `
            /* Immediate hover styles with MAXIMUM SPECIFICITY - TEXT */
            nav.bg-white .nav-link:not(.active):hover,
            nav.bg-white .nav-link:not(.active):hover span,
            nav.bg-white .nav-link:not(.active):hover .tab-text,
            nav.bg-white a.nav-link:not(.active):hover,
            nav.bg-white a.nav-link:not(.active):hover span,
            nav.bg-white a.nav-link:not(.active):hover .tab-text {
                color: var(--primary-color) !important;
            }
            
            /* Immediate hover styles with MAXIMUM SPECIFICITY - ICONS */
            nav.bg-white .nav-link:not(.active):hover i.ph,
            nav.bg-white .nav-link:not(.active):hover i,
            nav.bg-white a.nav-link:not(.active):hover i.ph,
            nav.bg-white a.nav-link:not(.active):hover i {
                color: var(--primary-color) !important;
            }
            
            /* General navigation hover with high specificity */
            nav .nav-link:not(.active):hover,
            nav a.nav-link:not(.active):hover {
                color: var(--primary-color) !important;
            }
            
            /* Ensure all child elements change color on hover - NUCLEAR OPTION */
            nav.bg-white .nav-link:not(.active):hover *,
            nav.bg-white a.nav-link:not(.active):hover * {
                color: var(--primary-color) !important;
            }
        `;
        
        // Insert at the END of head to ensure it loads AFTER everything else
        document.head.appendChild(styleEl);
        
        // Also force re-inject after a delay to override Tailwind
        setTimeout(function() {
            if (document.getElementById('immediate-hover-styles')) {
                document.getElementById('immediate-hover-styles').remove();
            }
            const lateStyleEl = document.createElement('style');
            lateStyleEl.id = 'immediate-hover-styles';
            lateStyleEl.textContent = styleEl.textContent;
            document.head.appendChild(lateStyleEl);
            console.log('✅ Hover styles re-injected after Tailwind load');
        }, 500);
        
        console.log('✅ Immediate hover styles injected (text + icons) with primary color:', primaryColor);
    })();
</script>
