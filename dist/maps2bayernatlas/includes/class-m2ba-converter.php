<?php
declare(strict_types=1);

final class M2BA_Converter {
    private const BAYERNATLAS_BASE_URL = 'https://atlas.bayern.de/';
    private const DEFAULT_ZOOM = 16;

    // GRS80 / ETRS89 constants for UTM zone 32N.
    private const A = 6378137.0;
    private const INV_F = 298.257222101;
    private const K0 = 0.9996;
    private const FALSE_E = 500000.0;
    private const FALSE_N = 0.0;
    private const LON0 = 9.0;

    // Approximate Bavaria polygon with buffer, ported from the reference project.
    private const BAVARIA_POLYGON = [
        [8.90, 50.10],
        [9.35, 50.30],
        [9.85, 50.50],
        [10.40, 50.50],
        [10.85, 50.60],
        [11.50, 50.55],
        [12.00, 50.50],
        [12.15, 50.40],
        [12.35, 50.15],
        [12.60, 50.00],
        [12.85, 50.00],
        [13.10, 49.80],
        [13.25, 49.60],
        [13.50, 49.40],
        [13.60, 49.20],
        [13.65, 49.00],
        [13.85, 48.85],
        [13.95, 48.70],
        [13.90, 48.55],
        [13.85, 48.40],
        [13.55, 48.20],
        [13.10, 48.20],
        [12.95, 48.15],
        [12.80, 47.90],
        [13.10, 47.75],
        [12.95, 47.60],
        [12.80, 47.55],
        [12.50, 47.55],
        [12.25, 47.50],
        [12.00, 47.35],
        [11.50, 47.38],
        [11.20, 47.28],
        [10.90, 47.28],
        [10.70, 47.40],
        [10.40, 47.45],
        [10.10, 47.38],
        [10.00, 47.45],
        [9.90, 47.43],
        [9.60, 47.43],
        [9.40, 47.45],
        [9.45, 47.70],
        [9.60, 47.85],
        [9.65, 48.00],
        [9.75, 48.20],
        [9.90, 48.35],
        [9.95, 48.55],
        [9.85, 48.75],
        [9.80, 48.95],
        [9.60, 49.15],
        [9.35, 49.30],
        [9.30, 49.50],
        [9.25, 49.70],
        [9.10, 49.80],
        [8.95, 49.95],
        [8.90, 50.10],
    ];

    public static function get_default_zoom(): int {
        return self::DEFAULT_ZOOM;
    }

    public static function convert_url(string $url, ?callable $short_url_resolver = null, int $zoom = self::DEFAULT_ZOOM): array {
        $normalized_url = trim($url);

        if ($normalized_url === '') {
            throw new M2BA_Conversion_Exception(self::message('Bitte gib einen Link ein.'), 400, 'm2ba_empty_url');
        }

        $normalized_url = filter_var($normalized_url, FILTER_SANITIZE_URL) ?: '';

        if (! filter_var($normalized_url, FILTER_VALIDATE_URL)) {
            throw new M2BA_Conversion_Exception(self::message('Der Link ist kein gültiges URL-Format.'), 400, 'm2ba_invalid_url');
        }

        if (! self::is_supported_input_url($normalized_url)) {
            throw new M2BA_Conversion_Exception(
                self::message('Unterstützt werden nur Google-Maps- und OpenStreetMap-Links.'),
                400,
                'm2ba_unsupported_host'
            );
        }

        $resolved_url = $normalized_url;
        $zoom         = max(0, min(20, $zoom));

        if (self::is_short_google_url($normalized_url)) {
            if ($short_url_resolver === null) {
                throw new M2BA_Conversion_Exception(
                    self::message('Kurzlinks können in dieser Umgebung nicht aufgelöst werden.'),
                    500,
                    'm2ba_short_url_unavailable'
                );
            }

            $resolved_url = (string) call_user_func($short_url_resolver, $normalized_url);

            if (! filter_var($resolved_url, FILTER_VALIDATE_URL) || ! self::is_supported_input_url($resolved_url)) {
                throw new M2BA_Conversion_Exception(
                    self::message('Der Kurzlink konnte nicht sicher aufgelöst werden.'),
                    502,
                    'm2ba_short_url_invalid'
                );
            }
        }

        $coordinates = self::extract_coordinates($resolved_url);

        if ($coordinates === null) {
            throw new M2BA_Conversion_Exception(
                self::message('Aus dem Link konnten keine Koordinaten gelesen werden.'),
                400,
                'm2ba_coordinates_missing'
            );
        }

        self::assert_coordinate_range($coordinates['lat'], $coordinates['lon']);

        if (! self::is_inside_bavaria($coordinates['lat'], $coordinates['lon'])) {
            throw new M2BA_Conversion_Exception(
                self::message('Der Standort liegt außerhalb von Bayern.'),
                422,
                'm2ba_outside_bavaria'
            );
        }

        $utm = self::wgs84_to_utm32($coordinates['lat'], $coordinates['lon']);

        return [
            'success'         => true,
            'source_url'      => $normalized_url,
            'resolved_url'    => $resolved_url,
            'bayernatlas_url' => self::build_bayernatlas_url($utm['easting'], $utm['northing'], $zoom),
            'coordinates'     => [
                'lat'      => $coordinates['lat'],
                'lon'      => $coordinates['lon'],
                'easting'  => $utm['easting'],
                'northing' => $utm['northing'],
            ],
        ];
    }

    public static function convert_urls(array $urls, ?callable $short_url_resolver = null, int $zoom = self::DEFAULT_ZOOM): array {
        $results       = [];
        $success_count = 0;
        $error_count   = 0;

        foreach (array_values($urls) as $index => $url) {
            $source_url = is_string($url) ? trim($url) : '';

            if ($source_url === '') {
                continue;
            }

            try {
                $converted   = self::convert_url($source_url, $short_url_resolver, $zoom);
                $results[]   = [
                    'index' => $index + 1,
                ] + $converted;
                $success_count++;
            } catch (M2BA_Conversion_Exception $exception) {
                $results[] = [
                    'index'      => $index + 1,
                    'success'    => false,
                    'source_url' => $source_url,
                    'message'    => $exception->getMessage(),
                    'error_key'  => $exception->get_error_key(),
                ];
                $error_count++;
            }
        }

        return [
            'success' => $error_count === 0,
            'summary' => [
                'total'      => $success_count + $error_count,
                'successful' => $success_count,
                'failed'     => $error_count,
            ],
            'results' => $results,
        ];
    }

    public static function is_supported_input_url(string $url): bool {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        return self::is_google_host($host) || self::is_openstreetmap_host($host);
    }

    public static function is_short_google_url(string $url): bool {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $host === 'maps.app.goo.gl' || $host === 'goo.gl';
    }

    public static function extract_coordinates(string $url): ?array {
        $candidates = array_values(array_unique([
            $url,
            rawurldecode(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ]));

        foreach ($candidates as $candidate) {
            if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $candidate, $matches) === 1) {
                return [
                    'lat' => (float) $matches[1],
                    'lon' => (float) $matches[2],
                ];
            }

            if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $candidate, $matches) === 1) {
                return [
                    'lat' => (float) $matches[1],
                    'lon' => (float) $matches[2],
                ];
            }

            if (preg_match('/\/maps\/search\/(-?\d+(?:\.\d+)?)(?:,|%2C)(?:\+|%20|\s)*(-?\d+(?:\.\d+)?)(?:[\/?#]|$)/i', $candidate, $matches) === 1) {
                return [
                    'lat' => (float) $matches[1],
                    'lon' => (float) $matches[2],
                ];
            }

            if (preg_match('/#map=\d+\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)/', $candidate, $matches) === 1) {
                return [
                    'lat' => (float) $matches[1],
                    'lon' => (float) $matches[2],
                ];
            }

            $parts = parse_url($candidate);

            if (! is_array($parts) || empty($parts['query'])) {
                continue;
            }

            parse_str((string) $parts['query'], $query);

            if (isset($query['mlat'], $query['mlon']) && is_numeric($query['mlat']) && is_numeric($query['mlon'])) {
                return [
                    'lat' => (float) $query['mlat'],
                    'lon' => (float) $query['mlon'],
                ];
            }

            foreach (['q', 'query', 'll', 'sll', 'center'] as $key) {
                if (! isset($query[$key]) || ! is_string($query[$key])) {
                    continue;
                }

                $pair = self::parse_lat_lon_pair($query[$key]);

                if ($pair !== null) {
                    return $pair;
                }
            }
        }

        return null;
    }

    public static function build_bayernatlas_url(int $easting, int $northing, int $zoom = self::DEFAULT_ZOOM): string {
        $zoom = max(0, min(20, $zoom));

        return sprintf(
            '%s?c=%d,%d&z=%d&l=atkis&crh=true',
            rtrim(self::BAYERNATLAS_BASE_URL, '/'),
            $easting,
            $northing,
            $zoom
        );
    }

    public static function wgs84_to_utm32(float $lat, float $lon): array {
        $rad_lat  = deg2rad($lat);
        $rad_lon  = deg2rad($lon);
        $rad_lon0 = deg2rad(self::LON0);

        $f   = 1.0 / self::INV_F;
        $e2  = 2 * $f - $f * $f;
        $ep2 = $e2 / (1 - $e2);

        $n      = self::A / sqrt(1 - $e2 * sin($rad_lat) ** 2);
        $t      = tan($rad_lat) ** 2;
        $c      = $ep2 * cos($rad_lat) ** 2;
        $a_part = ($rad_lon - $rad_lon0) * cos($rad_lat);

        $m = self::A * (
            (1 - $e2 / 4 - 3 * $e2 * $e2 / 64 - 5 * $e2 ** 3 / 256) * $rad_lat
            - (3 * $e2 / 8 + 3 * $e2 * $e2 / 32 + 45 * $e2 ** 3 / 1024) * sin(2 * $rad_lat)
            + (15 * $e2 * $e2 / 256 + 45 * $e2 ** 3 / 1024) * sin(4 * $rad_lat)
            - (35 * $e2 ** 3 / 3072) * sin(6 * $rad_lat)
        );

        $easting = self::FALSE_E + self::K0 * $n * (
            $a_part
            + (1 - $t + $c) * $a_part ** 3 / 6
            + (5 - 18 * $t + $t ** 2 + 72 * $c - 58 * $ep2) * $a_part ** 5 / 120
        );

        $northing = self::FALSE_N + self::K0 * (
            $m + $n * tan($rad_lat) * (
                $a_part ** 2 / 2
                + (5 - $t + 9 * $c + 4 * $c ** 2) * $a_part ** 4 / 24
                + (61 - 58 * $t + $t ** 2 + 600 * $c - 330 * $ep2) * $a_part ** 6 / 720
            )
        );

        return [
            'easting'  => (int) round($easting),
            'northing' => (int) round($northing),
        ];
    }

    public static function is_inside_bavaria(float $lat, float $lon): bool {
        $inside = false;
        $count  = count(self::BAVARIA_POLYGON);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = self::BAVARIA_POLYGON[$i][0];
            $yi = self::BAVARIA_POLYGON[$i][1];
            $xj = self::BAVARIA_POLYGON[$j][0];
            $yj = self::BAVARIA_POLYGON[$j][1];

            if ((($yi > $lat) !== ($yj > $lat)) && ($lon < (($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi))) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function parse_lat_lon_pair(string $value): ?array {
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*[,;]\s*(-?\d+(?:\.\d+)?)\s*$/', trim($value), $matches) !== 1) {
            return null;
        }

        return [
            'lat' => (float) $matches[1],
            'lon' => (float) $matches[2],
        ];
    }

    private static function assert_coordinate_range(float $lat, float $lon): void {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new M2BA_Conversion_Exception(
                self::message('Die gelesenen Koordinaten liegen außerhalb des gültigen Bereichs.'),
                400,
                'm2ba_coordinates_invalid'
            );
        }
    }

    private static function is_google_host(string $host): bool {
        if ($host === 'maps.app.goo.gl' || $host === 'goo.gl') {
            return true;
        }

        return preg_match('/^(?:[a-z0-9-]+\.)*google\.(?:com|[a-z]{2}|co\.[a-z]{2}|com\.[a-z]{2})$/i', $host) === 1;
    }

    private static function is_openstreetmap_host(string $host): bool {
        return $host === 'openstreetmap.org' || $host === 'www.openstreetmap.org';
    }

    private static function message(string $message): string {
        if (function_exists('__')) {
            return (string) __($message, 'maps2bayernatlas');
        }

        return $message;
    }
}
