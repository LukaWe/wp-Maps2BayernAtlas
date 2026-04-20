<?php
declare(strict_types=1);

/**
 * Core Classes for Maps to BayernAtlas Converter
 */

/**
 * Rate Limiter - Prevents API abuse
 */
class RateLimiter {
    private string $storagePath;

    public function __construct() {
        $this->storagePath = sys_get_temp_dir() . '/ba_ratelimit_';
    }

    public function check(string $ip): bool {
        // Whitelist check
        if (in_array($ip, IP_WHITELIST, true)) {
            return true;
        }

        $file = $this->storagePath . md5($ip);
        $now = time();
        $window = 60; // 1 minute window

        $data = ['count' => 0, 'start_time' => $now];
        
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $decoded = json_decode($content, true);
                if ($decoded) {
                    $data = $decoded;
                }
            }
        }

        // Reset if window passed
        if ($now - $data['start_time'] > $window) {
            $data = ['count' => 0, 'start_time' => $now];
        }

        if ($data['count'] >= API_RATE_LIMIT) {
            return false;
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data));
        return true;
    }
}

/**
 * Geo Converter - Handles coordinate transformations
 */
class GeoConverter {
    // GRS80 (ETRS89) Constants
    private const A = 6378137.0;
    private const INV_F = 298.257222101;
    private const K0 = 0.9996;
    private const FALSE_E = 500000.0;
    private const FALSE_N = 0.0;
    private const LON0 = 9.0; // Central Meridian for Zone 32N

    /**
     * Convert WGS84 coordinates to UTM Zone 32N
     */
    public static function wgs84ToUtm32(float $lat, float $lon): array {
        $radLat = deg2rad($lat);
        $radLon = deg2rad($lon);
        $radLon0 = deg2rad(self::LON0);

        $f = 1.0 / self::INV_F;
        $e2 = 2 * $f - $f * $f; // Eccentricity squared
        $ep2 = $e2 / (1 - $e2); // Second eccentricity squared
        
        // Helper calculations
        $N = self::A / sqrt(1 - $e2 * sin($radLat)**2);
        $T = tan($radLat)**2;
        $C = $ep2 * cos($radLat)**2;
        $A_val = ($radLon - $radLon0) * cos($radLat);

        // Meridional Arc Calculation (M)
        $M = self::A * (
            (1 - $e2/4 - 3*$e2*$e2/64 - 5*$e2**3/256) * $radLat
            - (3*$e2/8 + 3*$e2*$e2/32 + 45*$e2**3/1024) * sin(2*$radLat)
            + (15*$e2*$e2/256 + 45*$e2**3/1024) * sin(4*$radLat)
            - (35*$e2**3/3072) * sin(6*$radLat)
        );

        // Easting
        $easting = self::FALSE_E + self::K0 * $N * (
            $A_val + (1 - $T + $C) * $A_val**3 / 6
            + (5 - 18*$T + $T**2 + 72*$C - 58*$ep2) * $A_val**5 / 120
        );

        // Northing
        $northing = self::FALSE_N + self::K0 * (
            $M + $N * tan($radLat) * (
                $A_val**2 / 2
                + (5 - $T + 9*$C + 4*$C**2) * $A_val**4 / 24
                + (61 - 58*$T + $T**2 + 600*$C - 330*$ep2) * $A_val**6 / 720
            )
        );

        return [
            'easting' => round($easting),
            'northing' => round($northing)
        ];
    }

    /**
     * Check if a point is inside Bavaria using ray-casting algorithm
     */
    public static function isInsideBavaria(float $lat, float $lon): bool {
        $polygon = BAVARIA_POLYGON;
        $n = count($polygon);
        $inside = false;

        // Ray-casting algorithm
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0]; // lon
            $yi = $polygon[$i][1]; // lat
            $xj = $polygon[$j][0]; // lon
            $yj = $polygon[$j][1]; // lat

            // Check if ray from point crosses edge
            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

/**
 * Link Parser - Extracts coordinates from map URLs
 */
class LinkParser {
    /**
     * Extract coordinates from Google Maps or OpenStreetMap URL
     */
    public static function extractCoordinates(string $url): ?array {
        $finalUrl = $url;
        
        // Handle shortened URLs (maps.app.goo.gl)
        if (strpos($url, 'goo.gl') !== false || strpos($url, 'maps.app.goo.gl') !== false) {
            // Try get_headers first (works without cURL)
            $headers = @get_headers($url, true);
            if ($headers && isset($headers['Location'])) {
                $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
                $finalUrl = $location;
            } else {
                // Fallback: Use stream context to follow redirects
                $context = stream_context_create([
                    'http' => [
                        'method' => 'HEAD',
                        'follow_location' => 0,
                        'max_redirects' => 0,
                        'ignore_errors' => true,
                    ]
                ]);
                @file_get_contents($url, false, $context);
                if (isset($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (stripos($header, 'Location:') === 0) {
                            $finalUrl = trim(substr($header, 9));
                            break;
                        }
                    }
                }
            }
        }

        // Pattern 1: !3d...!4d... (HIGH PRIORITY - precise place/marker coordinates from Google Maps)
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $finalUrl, $matches)) {
            return [
                'lat' => (float)$matches[1],
                'lon' => (float)$matches[2]
            ];
        }

        // Pattern 2: @lat,lon (Google Maps viewport center)
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $finalUrl, $matches)) {
            return [
                'lat' => (float)$matches[1],
                'lon' => (float)$matches[2]
            ];
        }

        // Pattern 3: OpenStreetMap #map=zoom/lat/lon
        if (preg_match('/#map=\d+\/(-?\d+\.\d+)\/(-?\d+\.\d+)/', $finalUrl, $matches)) {
            return [
                'lat' => (float)$matches[1],
                'lon' => (float)$matches[2]
            ];
        }

        // Pattern 4: OpenStreetMap ?mlat=lat&mlon=lon (marker format)
        if (preg_match('/[?&]mlat=(-?\d+\.\d+)/', $finalUrl, $latMatch) &&
            preg_match('/[?&]mlon=(-?\d+\.\d+)/', $finalUrl, $lonMatch)) {
            return [
                'lat' => (float)$latMatch[1],
                'lon' => (float)$lonMatch[1]
            ];
        }

        return null;
    }
}
