<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleCloudUsageTracker
{
    const CACHE_KEY_VISION = 'google_cloud_vision_usage';
    const CACHE_KEY_NLP = 'google_cloud_nlp_usage';
    const FREE_TIER_VISION = 1000; // per month
    const FREE_TIER_NLP = 5000; // per month

    /**
     * Track Cloud Vision API usage
     * 
     * @param int $units Number of units used (pages processed)
     */
    public static function trackVisionUsage(int $units = 1): void
    {
        $currentMonth = now()->format('Y-m');
        $cacheKey = self::CACHE_KEY_VISION . '_' . $currentMonth;
        
        $currentUsage = Cache::get($cacheKey, 0);
        $newUsage = $currentUsage + $units;
        
        // Store until end of month
        $expiresAt = now()->endOfMonth();
        Cache::put($cacheKey, $newUsage, $expiresAt);
        
        Log::info('📊 [Cloud Vision] Usage tracked', [
            'units_used' => $units,
            'total_this_month' => $newUsage,
            'remaining_free' => max(0, self::FREE_TIER_VISION - $newUsage),
            'percentage' => round(($newUsage / self::FREE_TIER_VISION) * 100, 2) . '%'
        ]);
        
        // Alert if approaching limit
        if ($newUsage >= self::FREE_TIER_VISION * 0.8 && $newUsage < self::FREE_TIER_VISION) {
            Log::warning('⚠️ [Cloud Vision] Approaching free tier limit!', [
                'used' => $newUsage,
                'limit' => self::FREE_TIER_VISION,
                'remaining' => self::FREE_TIER_VISION - $newUsage
            ]);
        }
        
        // Alert if exceeded
        if ($newUsage > self::FREE_TIER_VISION) {
            Log::warning('💰 [Cloud Vision] Free tier exceeded!', [
                'used' => $newUsage,
                'limit' => self::FREE_TIER_VISION,
                'overage' => $newUsage - self::FREE_TIER_VISION,
                'estimated_cost' => '$' . number_format((($newUsage - self::FREE_TIER_VISION) / 1000) * 1.50, 2)
            ]);
        }
    }

    /**
     * Track Cloud NLP API usage
     * 
     * @param int $units Number of units used
     */
    public static function trackNLPUsage(int $units = 1): void
    {
        $currentMonth = now()->format('Y-m');
        $cacheKey = self::CACHE_KEY_NLP . '_' . $currentMonth;
        
        $currentUsage = Cache::get($cacheKey, 0);
        $newUsage = $currentUsage + $units;
        
        // Store until end of month
        $expiresAt = now()->endOfMonth();
        Cache::put($cacheKey, $newUsage, $expiresAt);
        
        Log::info('📊 [Cloud NLP] Usage tracked', [
            'units_used' => $units,
            'total_this_month' => $newUsage,
            'remaining_free' => max(0, self::FREE_TIER_NLP - $newUsage),
            'percentage' => round(($newUsage / self::FREE_TIER_NLP) * 100, 2) . '%'
        ]);
        
        // Alert if approaching limit
        if ($newUsage >= self::FREE_TIER_NLP * 0.8 && $newUsage < self::FREE_TIER_NLP) {
            Log::warning('⚠️ [Cloud NLP] Approaching free tier limit!', [
                'used' => $newUsage,
                'limit' => self::FREE_TIER_NLP,
                'remaining' => self::FREE_TIER_NLP - $newUsage
            ]);
        }
        
        // Alert if exceeded
        if ($newUsage > self::FREE_TIER_NLP) {
            Log::warning('💰 [Cloud NLP] Free tier exceeded!', [
                'used' => $newUsage,
                'limit' => self::FREE_TIER_NLP,
                'overage' => $newUsage - self::FREE_TIER_NLP,
                'estimated_cost' => '$' . number_format((($newUsage - self::FREE_TIER_NLP) / 1000) * 1.00, 2)
            ]);
        }
    }

    /**
     * Get current month's Cloud Vision usage
     */
    public static function getVisionUsage(): array
    {
        $currentMonth = now()->format('Y-m');
        $cacheKey = self::CACHE_KEY_VISION . '_' . $currentMonth;
        $usage = Cache::get($cacheKey, 0);
        
        return [
            'used' => $usage,
            'limit' => self::FREE_TIER_VISION,
            'remaining' => max(0, self::FREE_TIER_VISION - $usage),
            'percentage' => round(($usage / self::FREE_TIER_VISION) * 100, 2),
            'exceeded' => $usage > self::FREE_TIER_VISION,
            'estimated_cost' => $usage > self::FREE_TIER_VISION 
                ? round((($usage - self::FREE_TIER_VISION) / 1000) * 1.50, 2)
                : 0
        ];
    }

    /**
     * Get current month's Cloud NLP usage
     */
    public static function getNLPUsage(): array
    {
        $currentMonth = now()->format('Y-m');
        $cacheKey = self::CACHE_KEY_NLP . '_' . $currentMonth;
        $usage = Cache::get($cacheKey, 0);
        
        return [
            'used' => $usage,
            'limit' => self::FREE_TIER_NLP,
            'remaining' => max(0, self::FREE_TIER_NLP - $usage),
            'percentage' => round(($usage / self::FREE_TIER_NLP) * 100, 2),
            'exceeded' => $usage > self::FREE_TIER_NLP,
            'estimated_cost' => $usage > self::FREE_TIER_NLP 
                ? round((($usage - self::FREE_TIER_NLP) / 1000) * 1.00, 2)
                : 0
        ];
    }

    /**
     * Get combined usage summary
     */
    public static function getUsageSummary(): array
    {
        return [
            'vision' => self::getVisionUsage(),
            'nlp' => self::getNLPUsage(),
            'month' => now()->format('F Y')
        ];
    }

    /**
     * Check if within free tier limits
     */
    public static function isWithinFreeTier(): array
    {
        $visionUsage = self::getVisionUsage();
        $nlpUsage = self::getNLPUsage();
        
        return [
            'vision' => !$visionUsage['exceeded'],
            'nlp' => !$nlpUsage['exceeded'],
            'both' => !$visionUsage['exceeded'] && !$nlpUsage['exceeded']
        ];
    }

    /**
     * Reset usage (for testing only)
     */
    public static function resetUsage(): void
    {
        $currentMonth = now()->format('Y-m');
        Cache::forget(self::CACHE_KEY_VISION . '_' . $currentMonth);
        Cache::forget(self::CACHE_KEY_NLP . '_' . $currentMonth);
        
        Log::info('🔄 [Usage Tracker] Usage counters reset');
    }
}
