<?php
declare(strict_types=1);

final class M2BA_Rate_Limiter {
    private static int $last_retry_after = 60;

    public static function assert_request_allowed(int $cost = 1, string $payload_fingerprint = ''): void {
        if (current_user_can('manage_options')) {
            return;
        }

        $client_ip = self::get_client_ip();

        if ($client_ip === '') {
            return;
        }

        $cost = max(1, $cost);

        self::assert_cooldown($client_ip);
        self::assert_duplicate_window($client_ip, $payload_fingerprint);
        self::assert_rate_limit($client_ip, $cost);

        self::mark_request($client_ip, $payload_fingerprint, $cost);
    }

    public static function get_retry_after(): int {
        return max(1, self::$last_retry_after);
    }

    private static function assert_cooldown(string $client_ip): void {
        $cooldown = max(0, (int) M2BA_Options::get('request_cooldown_seconds'));

        if ($cooldown < 1) {
            return;
        }

        $last_request = (int) get_transient(self::get_request_timestamp_key($client_ip));
        $elapsed      = time() - $last_request;

        if ($last_request > 0 && $elapsed < $cooldown) {
            self::$last_retry_after = $cooldown - $elapsed;

            throw new M2BA_Conversion_Exception(
                sprintf(
                    /* translators: %d: seconds */
                    __('Bitte warte noch %d Sekunden, bevor du erneut anfragst.', 'maps2bayernatlas'),
                    self::$last_retry_after
                ),
                429,
                'm2ba_request_cooldown'
            );
        }
    }

    private static function assert_duplicate_window(string $client_ip, string $payload_fingerprint): void {
        $window = max(0, (int) M2BA_Options::get('duplicate_window_seconds'));

        if ($window < 1 || $payload_fingerprint === '') {
            return;
        }

        $key = self::get_duplicate_key($client_ip, $payload_fingerprint);

        if ((int) get_transient($key) > 0) {
            self::$last_retry_after = $window;

            throw new M2BA_Conversion_Exception(
                __('Die gleiche Anfrage wurde gerade erst verarbeitet. Bitte warte kurz.', 'maps2bayernatlas'),
                429,
                'm2ba_duplicate_request'
            );
        }
    }

    private static function assert_rate_limit(string $client_ip, int $cost): void {
        $limit = max(0, (int) M2BA_Options::get('rate_limit_per_minute'));

        if ($limit < 1) {
            return;
        }

        $key  = self::get_rate_limit_key($client_ip);
        $now  = time();
        $data = get_transient($key);

        if (! is_array($data) || ! isset($data['count'], $data['window_started'])) {
            $data = [
                'count'          => 0,
                'window_started' => $now,
            ];
        }

        if (($now - (int) $data['window_started']) >= 60) {
            $data = [
                'count'          => 0,
                'window_started' => $now,
            ];
        }

        if (((int) $data['count'] + $cost) > $limit) {
            self::$last_retry_after = max(1, 60 - ($now - (int) $data['window_started']));

            throw new M2BA_Conversion_Exception(
                __('Das eingestellte Rate-Limit wurde überschritten. Bitte versuche es später erneut.', 'maps2bayernatlas'),
                429,
                'm2ba_rate_limit_exceeded'
            );
        }
    }

    private static function mark_request(string $client_ip, string $payload_fingerprint, int $cost): void {
        $now = time();

        set_transient(self::get_request_timestamp_key($client_ip), $now, 120);

        $duplicate_window = max(0, (int) M2BA_Options::get('duplicate_window_seconds'));

        if ($payload_fingerprint !== '' && $duplicate_window > 0) {
            set_transient(self::get_duplicate_key($client_ip, $payload_fingerprint), 1, $duplicate_window);
        }

        $limit = max(0, (int) M2BA_Options::get('rate_limit_per_minute'));

        if ($limit < 1) {
            return;
        }

        $key  = self::get_rate_limit_key($client_ip);
        $data = get_transient($key);

        if (! is_array($data) || ! isset($data['count'], $data['window_started'])) {
            $data = [
                'count'          => 0,
                'window_started' => $now,
            ];
        }

        if (($now - (int) $data['window_started']) >= 60) {
            $data = [
                'count'          => 0,
                'window_started' => $now,
            ];
        }

        $data['count'] = (int) $data['count'] + $cost;

        set_transient($key, $data, 65);
    }

    private static function get_rate_limit_key(string $client_ip): string {
        return 'm2ba_rl_' . substr(self::hash($client_ip . '|rate'), 0, 32);
    }

    private static function get_request_timestamp_key(string $client_ip): string {
        return 'm2ba_cd_' . substr(self::hash($client_ip . '|cooldown'), 0, 32);
    }

    private static function get_duplicate_key(string $client_ip, string $payload_fingerprint): string {
        return 'm2ba_dup_' . substr(self::hash($client_ip . '|' . $payload_fingerprint), 0, 32);
    }

    private static function hash(string $value): string {
        return hash('sha256', wp_salt('auth') . '|' . $value);
    }

    private static function get_client_ip(): string {
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        return filter_var($client_ip, FILTER_VALIDATE_IP) ? $client_ip : '';
    }
}
