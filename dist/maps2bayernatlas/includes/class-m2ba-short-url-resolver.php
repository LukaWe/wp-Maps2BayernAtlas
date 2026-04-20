<?php
declare(strict_types=1);

final class M2BA_Short_Url_Resolver {
    private const MAX_REDIRECTS = 5;

    public static function resolve(string $url): string {
        $current_url = $url;

        for ($attempt = 0; $attempt < self::MAX_REDIRECTS; $attempt++) {
            $response = wp_safe_remote_request(
                $current_url,
                [
                    'method'              => 'GET',
                    'timeout'             => (int) apply_filters('m2ba_http_timeout', 10),
                    'redirection'         => 0,
                    'limit_response_size' => 2048,
                    'headers'             => [
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ],
                    'user-agent'          => 'Maps2BayernAtlas/' . M2BA_PLUGIN_VERSION . '; ' . home_url('/'),
                ]
            );

            if (is_wp_error($response)) {
                throw new M2BA_Conversion_Exception(
                    self::message('Der Kurzlink konnte nicht aufgelöst werden.'),
                    502,
                    'm2ba_short_url_request_failed'
                );
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);

            if ($status_code >= 300 && $status_code < 400) {
                $location = (string) wp_remote_retrieve_header($response, 'location');

                if ($location === '') {
                    throw new M2BA_Conversion_Exception(
                        self::message('Die Weiterleitung des Kurzlinks ist unvollständig.'),
                        502,
                        'm2ba_short_url_location_missing'
                    );
                }

                $next_url = self::make_absolute_url($current_url, $location);

                if (! M2BA_Converter::is_supported_input_url($next_url)) {
                    throw new M2BA_Conversion_Exception(
                        self::message('Der Kurzlink verweist nicht auf einen unterstützten Kartendienst.'),
                        400,
                        'm2ba_short_url_target_unsupported'
                    );
                }

                $current_url = $next_url;
                continue;
            }

            if ($status_code >= 200 && $status_code < 300) {
                return $current_url;
            }

            throw new M2BA_Conversion_Exception(
                self::message('Der Kurzlink lieferte keine verwertbare Antwort.'),
                502,
                'm2ba_short_url_bad_response'
            );
        }

        throw new M2BA_Conversion_Exception(
            self::message('Der Kurzlink hat zu viele Weiterleitungen erzeugt.'),
            502,
            'm2ba_short_url_too_many_redirects'
        );
    }

    private static function make_absolute_url(string $current_url, string $location): string {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($current_url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $location;
        }

        $scheme = (string) $parts['scheme'];
        $host   = (string) $parts['host'];
        $port   = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $dir  = preg_replace('#/[^/]*$#', '/', $path) ?: '/';

        return $scheme . '://' . $host . $port . $dir . ltrim($location, '/');
    }

    private static function message(string $message): string {
        return (string) __($message, 'maps2bayernatlas');
    }
}
