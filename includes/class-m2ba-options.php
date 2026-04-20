<?php
declare(strict_types=1);

final class M2BA_Options {
    public const OPTION_NAME = 'm2ba_options';
    private const SETTINGS_GROUP = 'm2ba_settings_group';
    private const SETTINGS_PAGE = 'm2ba-settings';
    private const SECTION_GENERAL = 'm2ba_section_general';

    public static function defaults(): array {
        return [
            'rate_limit_per_minute'    => 20,
            'request_cooldown_seconds' => 2,
            'duplicate_window_seconds' => 15,
            'bulk_max_urls'            => 10,
        ];
    }

    public static function get_all(): array {
        $saved = get_option(self::OPTION_NAME, []);

        if (! is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, self::defaults());
    }

    public static function get(string $key) {
        $options = self::get_all();

        return $options[$key] ?? null;
    }

    public static function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize_options'],
                'default'           => self::defaults(),
            ]
        );

        add_settings_section(
            self::SECTION_GENERAL,
            __('Schutz- und Bulk-Einstellungen', 'maps2bayernatlas'),
            static function (): void {
                echo '<p>' . esc_html__('Diese Werte steuern öffentliche Aufrufe des Shortcodes und der REST-Schnittstellen.', 'maps2bayernatlas') . '</p>';
            },
            self::SETTINGS_PAGE
        );

        self::add_number_field(
            'rate_limit_per_minute',
            __('Umwandlungen pro Minute pro IP', 'maps2bayernatlas'),
            __('0 deaktiviert das Limit. Bei Bulk-Anfragen zählt jede einzelne URL als eigene Umwandlung.', 'maps2bayernatlas'),
            0,
            500
        );

        self::add_number_field(
            'request_cooldown_seconds',
            __('Mindestabstand zwischen Anfragen (Sek.)', 'maps2bayernatlas'),
            __('Verhindert schnelle Request-Serien derselben IP. 0 deaktiviert diesen Spam-Schutz.', 'maps2bayernatlas'),
            0,
            60
        );

        self::add_number_field(
            'duplicate_window_seconds',
            __('Gleiche Anfrage erneut blockieren (Sek.)', 'maps2bayernatlas'),
            __('Blockiert identische Payloads derselben IP für die angegebene Dauer. 0 deaktiviert die Duplikat-Sperre.', 'maps2bayernatlas'),
            0,
            600
        );

        self::add_number_field(
            'bulk_max_urls',
            __('Maximale URLs pro Bulk-Anfrage', 'maps2bayernatlas'),
            __('Obergrenze für die Batch-Funktion im Frontend und in der REST-API. Maximal 10.', 'maps2bayernatlas'),
            1,
            10
        );
    }

    public static function add_admin_menu(): void {
        add_options_page(
            __('Maps2BayernAtlas', 'maps2bayernatlas'),
            __('Maps2BayernAtlas', 'maps2bayernatlas'),
            'manage_options',
            self::SETTINGS_PAGE,
            [self::class, 'render_settings_page']
        );
    }

    public static function render_settings_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Maps2BayernAtlas Einstellungen', 'maps2bayernatlas'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                do_settings_sections(self::SETTINGS_PAGE);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function sanitize_options($input): array {
        $input    = is_array($input) ? $input : [];
        $defaults = self::defaults();

        return [
            'rate_limit_per_minute'    => self::sanitize_int($input['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'], 0, 500),
            'request_cooldown_seconds' => self::sanitize_int($input['request_cooldown_seconds'] ?? $defaults['request_cooldown_seconds'], 0, 60),
            'duplicate_window_seconds' => self::sanitize_int($input['duplicate_window_seconds'] ?? $defaults['duplicate_window_seconds'], 0, 600),
            'bulk_max_urls'            => self::sanitize_int($input['bulk_max_urls'] ?? $defaults['bulk_max_urls'], 1, 10),
        ];
    }

    private static function add_number_field(string $key, string $label, string $description, int $min, int $max): void {
        add_settings_field(
            $key,
            $label,
            static function () use ($key, $description, $min, $max): void {
                $options = self::get_all();
                $value   = (int) ($options[$key] ?? self::defaults()[$key]);
                ?>
                <input
                    type="number"
                    class="small-text"
                    name="<?php echo esc_attr(self::OPTION_NAME . '[' . $key . ']'); ?>"
                    value="<?php echo esc_attr((string) $value); ?>"
                    min="<?php echo esc_attr((string) $min); ?>"
                    max="<?php echo esc_attr((string) $max); ?>"
                    step="1"
                >
                <p class="description"><?php echo esc_html($description); ?></p>
                <?php
            },
            self::SETTINGS_PAGE,
            self::SECTION_GENERAL
        );
    }

    private static function sanitize_int($value, int $min, int $max): int {
        $value = is_numeric($value) ? (int) $value : $min;

        return max($min, min($max, $value));
    }
}
