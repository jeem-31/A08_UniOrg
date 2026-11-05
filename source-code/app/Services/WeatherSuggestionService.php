<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Smart event-date suggestions powered by Open-Meteo (free, no API key).
 *
 * Combines a 16-day weather forecast with the existing venue-conflict
 * check so students only get clickable "good days" inside the month
 * they picked their start date in.
 */
class WeatherSuggestionService
{
    public const CAMPUS_LAT = 14.2628;

    public const CAMPUS_LON = 121.3974;

    public const CAMPUS_LABEL = 'LSPU Sta. Cruz Campus';

    public const FORECAST_DAYS_LIMIT = 16;

    /**
     * Geocode a free-text place (used when venue is "Outside of School").
     *
     * @return array<int, array{name: string, latitude: float, longitude: float, country: ?string, admin1: ?string}>
     */
    public function geocode(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return [];
        }

        $cacheKey = 'geocode:ph:'.mb_strtolower($query);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($query) {
            try {
                $response = Http::timeout(10)->get('https://geocoding-api.open-meteo.com/v1/search', [
                    'name' => $query,
                    'count' => 5,
                    'language' => 'en',
                    'format' => 'json',
                    'countryCode' => 'PH',
                ]);

                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json('results', []))
                    ->map(fn ($r) => [
                        'name' => trim(($r['name'] ?? '').(isset($r['admin1']) ? ', '.$r['admin1'] : '')),
                        'latitude' => (float) ($r['latitude'] ?? 0),
                        'longitude' => (float) ($r['longitude'] ?? 0),
                        'country' => $r['country'] ?? null,
                        'admin1' => $r['admin1'] ?? null,
                    ])
                    ->filter(fn ($r) => $r['latitude'] !== 0.0)
                    ->values()
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Build scored day suggestions for a single month.
     *
     * @return array{month_label: string, location: array, forecast_available_until: ?string, suggestions: array}
     */
    public function suggest(
        int $year,
        int $month,
        ?string $venue = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $locationLabel = null,
        ?int $excludeEventId = null,
        string $startTime = '09:00',
        string $endTime = '11:00'
    ): array {
        $lat = $latitude ?? self::CAMPUS_LAT;
        $lon = $longitude ?? self::CAMPUS_LON;
        $label = $locationLabel ?: self::CAMPUS_LABEL;

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $today = Carbon::now('Asia/Manila')->startOfDay();

        // Whole month is in the past — nothing to suggest.
        if ($monthEnd->lt($today)) {
            return $this->emptyResult($monthStart, $lat, $lon, $label, null);
        }

        $rangeStart = $monthStart->copy()->max($today);
        $forecastEnd = $today->copy()->addDays(self::FORECAST_DAYS_LIMIT - 1);
        $rangeEnd = $monthEnd->copy()->min($forecastEnd);

        $fetch = $this->fetchForecast($lat, $lon, $rangeStart, $rangeEnd, $startTime, $endTime);
        $forecast = $fetch['days'];
        $windowLabel = $this->windowLabel($startTime, $endTime);
        $forecastAvailableUntil = $rangeEnd->toDateString();

        // Days fully beyond the 16-day horizon legitimately have no data;
        // anything else empty means the weather service failed.
        $beyondHorizon = $rangeStart->gt($forecastEnd);
        $weatherAvailable = $beyondHorizon || ! empty($forecast);
        $weatherError = $weatherAvailable ? null : $fetch['error'];

        $suggestions = [];
        for ($day = $rangeStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $dateStr = $day->toDateString();
            $weather = $forecast[$dateStr] ?? null;

            // Venue conflicts are independent of weather — always compute
            // so booked days stay marked even when the forecast is down.
            $conflict = $this->checkDayConflict($dateStr, $venue, $excludeEventId, $startTime, $endTime);

            if ($weather === null) {
                $suggestions[] = [
                    'date' => $dateStr,
                    'weekday' => $day->format('D'),
                    'day' => (int) $day->format('j'),
                    'score' => null,
                    'label' => 'No forecast',
                    'reason' => $weatherAvailable
                        ? 'Beyond the 16-day forecast window.'
                        : 'Weather data unavailable.',
                    'temperature_max' => null,
                    'precipitation_probability' => null,
                    'wind_speed_max' => null,
                    'weathercode' => null,
                    'icon' => 'ph-question',
                    'hourly_scored' => false,
                    'window_label' => $windowLabel,
                    'conflicted' => $conflict['conflicted'],
                    'conflict_count' => $conflict['count'],
                    'conflict_title' => $conflict['title'],
                    'selectable' => false,
                ];

                continue;
            }

            $scoreData = $this->scoreDay($weather);

            $suggestions[] = [
                'date' => $dateStr,
                'weekday' => $day->format('D'),
                'day' => (int) $day->format('j'),
                'score' => $scoreData['score'],
                'label' => $scoreData['label'],
                'reason' => $scoreData['reason'],
                'temperature_max' => $weather['temperature_max'],
                'precipitation_probability' => $weather['precipitation_probability'],
                'wind_speed_max' => $weather['wind_speed_max'],
                'weathercode' => $weather['weathercode'],
                'icon' => $this->iconFor($weather['weathercode']),
                'hourly_scored' => $weather['hourly_scored'] ?? false,
                'window_label' => $windowLabel,
                'conflicted' => $conflict['conflicted'],
                'conflict_count' => $conflict['count'],
                'conflict_title' => $conflict['title'],
                'selectable' => ! $conflict['conflicted'],
            ];
        }

        // Nearest day first — the panel highlights the top picks separately.
        usort($suggestions, fn ($a, $b) => $a['date'] <=> $b['date']);

        return [
            'month_label' => $monthStart->format('F Y'),
            'location' => ['latitude' => $lat, 'longitude' => $lon, 'label' => $label],
            'forecast_available_until' => $forecastAvailableUntil,
            'weather_available' => $weatherAvailable,
            'weather_error' => $weatherError,
            'suggestions' => $suggestions,
        ];
    }

    private function emptyResult(Carbon $monthStart, float $lat, float $lon, string $label, ?string $until): array
    {
        return [
            'month_label' => $monthStart->format('F Y'),
            'location' => ['latitude' => $lat, 'longitude' => $lon, 'label' => $label],
            'forecast_available_until' => $until,
            'weather_available' => true,
            'weather_error' => null,
            'suggestions' => [],
        ];
    }

    /**
     * Fetch daily + hourly data, then score each date using only the hours
     * inside the event time window (more accurate than whole-day maxima).
     *
     * @return array{days: array<string, array{temperature_max: ?float, precipitation_probability: ?int, wind_speed_max: ?float, weathercode: ?int, hourly_scored: bool}>, error: ?string}
     */
    private function fetchForecast(float $lat, float $lon, Carbon $start, Carbon $end, string $startTime, string $endTime): array
    {
        if ($end->lt($start)) {
            return ['days' => [], 'error' => null];
        }

        // NOTE: failures are never cached — a single failed fetch must not
        // poison suggestions for hours. v3 keys include the time window.
        $cacheKey = sprintf(
            'weather:v3:%s:%s:%s:%s:%s:%s',
            round($lat, 3),
            round($lon, 3),
            $start->toDateString(),
            $end->toDateString(),
            str_replace(':', '', $startTime),
            str_replace(':', '', $endTime)
        );

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return ['days' => $cached, 'error' => null];
        }

        // Short cooldown while rate-limited so we stop hammering a 429.
        $coolKey = sprintf('weather:cool:v3:%s:%s', round($lat, 3), round($lon, 3));
        if (Cache::has($coolKey)) {
            return ['days' => [], 'error' => 'rate_limited'];
        }

        $payload = null;
        $error = null;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'UniOrg-EventScheduler/1.0'])
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'daily' => 'weathercode,temperature_2m_max,precipitation_probability_max,wind_speed_10m_max',
                    'hourly' => 'temperature_2m,precipitation_probability,weathercode,wind_speed_10m',
                    'timezone' => 'Asia/Manila',
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ]);

            if ($response->successful()) {
                $payload = $response->json();
            } else {
                $status = $response->status();
                $error = $status === 429 ? 'rate_limited' : 'unavailable';
                if ($status === 429) {
                    Cache::put($coolKey, true, now()->addMinutes(5));
                }
                Log::warning('WeatherSuggestionService: Open-Meteo forecast failed', [
                    'status' => $response->status(),
                    'lat' => $lat,
                    'lon' => $lon,
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WeatherSuggestionService: Open-Meteo forecast exception', [
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lon' => $lon,
            ]);

            return ['days' => [], 'error' => 'unavailable'];
        }

        $daily = is_array($payload) ? ($payload['daily'] ?? null) : null;
        if (! is_array($daily) || empty($daily['time'])) {
            return ['days' => [], 'error' => $error ?? 'unavailable'];
        }

        $hourlyIndex = $this->indexHourly(is_array($payload) ? ($payload['hourly'] ?? null) : null);

        $out = [];
        foreach ($daily['time'] as $i => $date) {
            $window = $this->aggregateWindow($hourlyIndex, $date, $startTime, $endTime);

            if ($window !== null) {
                $out[$date] = $window + ['hourly_scored' => true];

                continue;
            }

            // Fallback to whole-day maxima when hourly data is missing.
            $out[$date] = [
                'temperature_max' => isset($daily['temperature_2m_max'][$i]) ? (float) $daily['temperature_2m_max'][$i] : null,
                'precipitation_probability' => isset($daily['precipitation_probability_max'][$i]) ? (int) $daily['precipitation_probability_max'][$i] : null,
                'wind_speed_max' => isset($daily['wind_speed_10m_max'][$i]) ? (float) $daily['wind_speed_10m_max'][$i] : null,
                'weathercode' => isset($daily['weathercode'][$i]) ? (int) $daily['weathercode'][$i] : null,
                'hourly_scored' => false,
            ];
        }

        // Only successful fetches are cached — never failures.
        if ($out !== []) {
            Cache::put($cacheKey, $out, now()->addHours(3));
        }

        return ['days' => $out, 'error' => null];
    }

    /**
     * Index hourly series by local datetime ("2026-09-14T09:00").
     *
     * @return array<string, array{temperature: ?float, precipitation_probability: ?int, weathercode: ?int, wind_speed: ?float}>
     */
    private function indexHourly(mixed $hourly): array
    {
        if (! is_array($hourly) || empty($hourly['time'])) {
            return [];
        }

        $out = [];
        foreach ($hourly['time'] as $i => $t) {
            $out[$t] = [
                'temperature' => isset($hourly['temperature_2m'][$i]) ? (float) $hourly['temperature_2m'][$i] : null,
                'precipitation_probability' => isset($hourly['precipitation_probability'][$i]) ? (int) $hourly['precipitation_probability'][$i] : null,
                'weathercode' => isset($hourly['weathercode'][$i]) ? (int) $hourly['weathercode'][$i] : null,
                'wind_speed' => isset($hourly['wind_speed_10m'][$i]) ? (float) $hourly['wind_speed_10m'][$i] : null,
            ];
        }

        return $out;
    }

    /**
     * Aggregate one date's event-window hours. Null when no hourly data.
     *
     * Rules (mirrored in the browser fallback): rain = mean probability in
     * the window; weathercode = worst in the window; temp/wind = max.
     *
     * @param  array<string, array{temperature: ?float, precipitation_probability: ?int, weathercode: ?int, wind_speed: ?float}>  $index
     * @return ?array{temperature_max: ?float, precipitation_probability: ?int, wind_speed_max: ?float, weathercode: ?int}
     */
    private function aggregateWindow(array $index, string $date, string $startTime, string $endTime): ?array
    {
        $probs = [];
        $temps = [];
        $winds = [];
        $worstCode = null;
        $worstRank = -1;

        for ($h = 0; $h < 24; $h++) {
            $hm = sprintf('%02d:00', $h);
            if ($hm < $startTime || $hm >= $endTime) {
                continue;
            }

            $row = $index[$date.'T'.$hm] ?? null;
            if ($row === null) {
                continue;
            }

            if ($row['precipitation_probability'] !== null) {
                $probs[] = $row['precipitation_probability'];
            }
            if ($row['temperature'] !== null) {
                $temps[] = $row['temperature'];
            }
            if ($row['wind_speed'] !== null) {
                $winds[] = $row['wind_speed'];
            }
            if ($row['weathercode'] !== null) {
                $rank = $this->weathercodeSeverity($row['weathercode']);
                if ($rank > $worstRank) {
                    $worstRank = $rank;
                    $worstCode = $row['weathercode'];
                }
            }
        }

        if ($probs === [] && $temps === [] && $winds === [] && $worstCode === null) {
            return null;
        }

        return [
            'temperature_max' => $temps === [] ? null : max($temps),
            'precipitation_probability' => $probs === [] ? null : (int) round(array_sum($probs) / count($probs)),
            'wind_speed_max' => $winds === [] ? null : max($winds),
            'weathercode' => $worstCode,
        ];
    }

    private function weathercodeSeverity(int $code): int
    {
        if (in_array($code, [95, 96, 99], true)) {
            return 3;
        }
        if (in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true)) {
            return 2;
        }
        if (in_array($code, [3, 45, 48], true)) {
            return 1;
        }
        if ($code === 2) {
            return 0;
        }

        return -1;
    }

    private function windowLabel(string $startTime, string $endTime): string
    {
        try {
            $s = Carbon::createFromFormat('H:i', $startTime)->format('g A');
            $e = Carbon::createFromFormat('H:i', $endTime)->format('g A');

            return $s.' - '.$e;
        } catch (\Throwable) {
            return $startTime.' - '.$endTime;
        }
    }

    /**
     * @param  array{temperature_max: ?float, precipitation_probability: ?int, wind_speed_max: ?float, weathercode: ?int}  $w
     */
    private function scoreDay(array $w): array
    {
        $score = 100;
        $notes = [];

        $rainProb = $w['precipitation_probability'] ?? 0;
        if ($rainProb > 0) {
            $score -= round($rainProb * 0.6);
            if ($rainProb >= 60) {
                $notes[] = $rainProb.'% chance of rain';
            }
        }

        $code = $w['weathercode'] ?? 0;
        if (in_array($code, [95, 96, 99], true)) {
            $score -= 30;
            $notes[] = 'Thunderstorms likely';
        } elseif (in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true)) {
            $score -= 20;
            $notes[] = 'Rain expected';
        } elseif (in_array($code, [3, 45, 48], true)) {
            $score -= 8;
            $notes[] = 'Overcast';
        } elseif ($code === 2) {
            $score -= 3;
        }

        $wind = $w['wind_speed_max'] ?? 0;
        if ($wind >= 30) {
            $score -= 15;
            $notes[] = 'Strong winds';
        } elseif ($wind >= 20) {
            $score -= 8;
            $notes[] = 'Breezy';
        } elseif ($wind >= 12) {
            $score -= 3;
        }

        $temp = $w['temperature_max'] ?? null;
        if ($temp !== null && ($temp >= 36 || $temp <= 17)) {
            $score -= 5;
            $notes[] = $temp >= 36 ? 'Extreme heat' : 'Unusually cool';
        }

        $score = max(0, min(100, (int) $score));

        $label = match (true) {
            $score >= 80 => 'Best',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            default => 'Avoid',
        };

        if ($label === 'Best') {
            $notes[] = 'Ideal conditions';
        }

        return ['score' => $score, 'label' => $label, 'reason' => implode(' · ', array_unique($notes)) ?: '—'];
    }

    private function iconFor(?int $code): string
    {
        return match (true) {
            in_array($code, [95, 96, 99], true) => 'ph-cloud-lightning',
            in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true) => 'ph-cloud-rain',
            in_array($code, [3, 45, 48], true) => 'ph-cloud',
            $code === 2 => 'ph-cloud-sun',
            default => 'ph-sun',
        };
    }

    private function checkDayConflict(string $date, ?string $venue, ?int $excludeId, string $startTime, string $endTime): array
    {
        if (! $venue) {
            return ['conflicted' => false, 'count' => 0, 'title' => null];
        }

        try {
            $dayStart = Carbon::parse($date.' '.$startTime, 'Asia/Manila');
            $dayEnd = Carbon::parse($date.' '.$endTime, 'Asia/Manila');
            if ($dayEnd->lte($dayStart)) {
                $dayEnd = $dayStart->copy()->addHours(2);
            }

            $query = Event::allOrganizations()
                ->where('start_date', '<', $dayEnd)
                ->where('end_date', '>', $dayStart)
                ->where('venue', $venue)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

            $count = (clone $query)->count();

            if ($count === 0) {
                return ['conflicted' => false, 'count' => 0, 'title' => null];
            }

            $first = (clone $query)->orderBy('start_date')->first(['title']);

            return ['conflicted' => true, 'count' => $count, 'title' => $first?->title];
        } catch (\Throwable) {
            return ['conflicted' => false, 'count' => 0, 'title' => null];
        }
    }
}
