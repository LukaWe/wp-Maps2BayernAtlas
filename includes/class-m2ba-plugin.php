<?php
declare(strict_types=1);

require_once M2BA_PLUGIN_DIR . 'includes/class-m2ba-conversion-exception.php';
require_once M2BA_PLUGIN_DIR . 'includes/class-m2ba-converter.php';
require_once M2BA_PLUGIN_DIR . 'includes/class-m2ba-options.php';
require_once M2BA_PLUGIN_DIR . 'includes/class-m2ba-short-url-resolver.php';
require_once M2BA_PLUGIN_DIR . 'includes/class-m2ba-rate-limiter.php';

final class M2BA_Plugin {
    private const SHORTCODE = 'maps2bayernatlas';
    private const REST_NAMESPACE = 'maps2bayernatlas/v1';
    private const REST_SINGLE_ROUTE = '/convert';
    private const REST_BATCH_ROUTE = '/convert-batch';

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        add_option(M2BA_Options::OPTION_NAME, M2BA_Options::defaults());
    }

    private function __construct() {
        add_action('init', [$this, 'register_shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_init', [M2BA_Options::class, 'register_settings']);
        add_action('admin_menu', [M2BA_Options::class, 'add_admin_menu']);
    }

    public function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    public function register_assets(): void {
        wp_register_style(
            'm2ba-frontend',
            M2BA_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            M2BA_PLUGIN_VERSION
        );

        wp_register_script(
            'm2ba-frontend',
            M2BA_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            M2BA_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'm2ba-frontend',
            'M2BASettings',
            [
                'singleRestUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_SINGLE_ROUTE)),
                'bulkRestUrl'   => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_BATCH_ROUTE)),
                'restNonce'     => wp_create_nonce('wp_rest'),
                'defaultZoom'   => M2BA_Converter::get_default_zoom(),
                'bulkMaxUrls'   => max(1, min(10, (int) M2BA_Options::get('bulk_max_urls'))),
                'messages'      => [
                    'emptyUrl'        => __('Bitte gib einen Google-Maps- oder OpenStreetMap-Link ein.', 'maps2bayernatlas'),
                    'emptyBulk'       => __('Bitte füge mindestens eine URL ein.', 'maps2bayernatlas'),
                    'bulkTooMany'     => sprintf(
                        /* translators: %d: maximum URL count */
                        __('Bitte füge höchstens %d URLs gleichzeitig ein.', 'maps2bayernatlas'),
                        max(1, min(10, (int) M2BA_Options::get('bulk_max_urls')))
                    ),
                    'genericError'    => __('Die Umwandlung ist fehlgeschlagen.', 'maps2bayernatlas'),
                    'copySuccess'     => __('Der BayernAtlas-Link wurde in die Zwischenablage kopiert.', 'maps2bayernatlas'),
                    'copyAllSuccess'  => __('Alle erfolgreichen BayernAtlas-Links wurden kopiert.', 'maps2bayernatlas'),
                    'copyError'       => __('Der Link konnte nicht kopiert werden.', 'maps2bayernatlas'),
                    'singleLoading'   => __('Link wird umgewandelt …', 'maps2bayernatlas'),
                    'bulkLoading'     => __('Bulk-Umwandlung läuft …', 'maps2bayernatlas'),
                    'bulkCounter'     => __('Erkannte URLs', 'maps2bayernatlas'),
                    'bulkOutputEmpty' => __('Noch keine erfolgreichen BayernAtlas-Links vorhanden.', 'maps2bayernatlas'),
                ],
            ]
        );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_SINGLE_ROUTE,
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'handle_convert_request'],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'maps_url' => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => [$this, 'sanitize_url_param'],
                            'validate_callback' => [$this, 'validate_url_param'],
                        ],
                        'zoom'     => [
                            'required'          => false,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_BATCH_ROUTE,
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'handle_batch_convert_request'],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'maps_urls' => [
                            'required' => true,
                            'type'     => 'array',
                        ],
                        'zoom'      => [
                            'required'          => false,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );
    }

    public function sanitize_url_param($value): string {
        return is_string($value) ? trim($value) : '';
    }

    public function validate_url_param($value): bool {
        return is_string($value) && trim($value) !== '' && strlen($value) <= 2048;
    }

    public function handle_convert_request(WP_REST_Request $request) {
        $maps_url = $this->sanitize_url_param($request->get_param('maps_url'));

        try {
            if (! $this->validate_url_param($maps_url)) {
                throw new M2BA_Conversion_Exception(
                    __('Bitte gib einen gültigen Kartenlink an.', 'maps2bayernatlas'),
                    400,
                    'm2ba_invalid_request'
                );
            }

            M2BA_Rate_Limiter::assert_request_allowed(1, $this->get_payload_fingerprint([$maps_url]));

            $result = M2BA_Converter::convert_url(
                $maps_url,
                [M2BA_Short_Url_Resolver::class, 'resolve'],
                $this->get_request_zoom($request)
            );

            return new WP_REST_Response($result, 200);
        } catch (M2BA_Conversion_Exception $exception) {
            return $this->conversion_exception_to_error($exception);
        } catch (Throwable $exception) {
            return new WP_Error(
                'm2ba_unexpected_error',
                __('Die Umwandlung konnte nicht abgeschlossen werden.', 'maps2bayernatlas'),
                ['status' => 500]
            );
        }
    }

    public function handle_batch_convert_request(WP_REST_Request $request) {
        try {
            $urls      = $this->normalize_bulk_urls($request->get_param('maps_urls'));
            $bulk_max  = max(1, min(10, (int) M2BA_Options::get('bulk_max_urls')));
            $url_count = count($urls);

            if ($url_count < 1) {
                throw new M2BA_Conversion_Exception(
                    __('Bitte gib mindestens eine URL für die Bulk-Umwandlung an.', 'maps2bayernatlas'),
                    400,
                    'm2ba_bulk_empty'
                );
            }

            if ($url_count > $bulk_max) {
                throw new M2BA_Conversion_Exception(
                    sprintf(
                        /* translators: %d: maximum URL count */
                        __('Es dürfen maximal %d URLs gleichzeitig verarbeitet werden.', 'maps2bayernatlas'),
                        $bulk_max
                    ),
                    400,
                    'm2ba_bulk_too_many'
                );
            }

            M2BA_Rate_Limiter::assert_request_allowed($url_count, $this->get_payload_fingerprint($urls));

            $result = M2BA_Converter::convert_urls(
                $urls,
                [M2BA_Short_Url_Resolver::class, 'resolve'],
                $this->get_request_zoom($request)
            );

            $result['limits'] = [
                'bulk_max_urls'         => $bulk_max,
                'rate_limit_per_minute' => (int) M2BA_Options::get('rate_limit_per_minute'),
            ];

            return new WP_REST_Response($result, 200);
        } catch (M2BA_Conversion_Exception $exception) {
            return $this->conversion_exception_to_error($exception);
        } catch (Throwable $exception) {
            return new WP_Error(
                'm2ba_unexpected_error',
                __('Die Bulk-Umwandlung konnte nicht abgeschlossen werden.', 'maps2bayernatlas'),
                ['status' => 500]
            );
        }
    }

    public function render_shortcode(array $atts = []): string {
        $atts = shortcode_atts(
            [
                'zoom'              => (string) M2BA_Converter::get_default_zoom(),
                'placeholder'       => __('Google Maps- oder OpenStreetMap-Link einfügen', 'maps2bayernatlas'),
                'bulk_placeholder'  => __("Pro Zeile eine URL einfügen\nhttps://maps.app.goo.gl/...\nhttps://www.google.com/maps/@48.137,11.575,15z", 'maps2bayernatlas'),
                'button'            => __('In BayernAtlas umwandeln', 'maps2bayernatlas'),
                'bulk_button'       => __('Mehrere Links umwandeln', 'maps2bayernatlas'),
                'title'             => __('Maps2BayernAtlas', 'maps2bayernatlas'),
            ],
            $atts,
            self::SHORTCODE
        );

        $zoom         = max(0, min(20, (int) $atts['zoom']));
        $bulk_max     = max(1, min(10, (int) M2BA_Options::get('bulk_max_urls')));
        $single_input = wp_unique_id('m2ba-single-url-');
        $bulk_input   = wp_unique_id('m2ba-bulk-url-');

        wp_enqueue_style('m2ba-frontend');
        wp_enqueue_script('m2ba-frontend');

        ob_start();
        ?>
        <div class="m2ba-app" data-zoom="<?php echo esc_attr((string) $zoom); ?>" data-bulk-max="<?php echo esc_attr((string) $bulk_max); ?>">
            <div class="m2ba-shell">
                <div class="m2ba-heading">
                    <h2 class="m2ba-title"><?php echo esc_html($atts['title']); ?></h2>
                    <p class="m2ba-subtitle"><?php esc_html_e('Wandle einzelne oder mehrere Google-Maps- und OpenStreetMap-Links in BayernAtlas-Links um. Kurzlinks werden aufgelöst, Ergebnisse bleiben pro URL nachvollziehbar.', 'maps2bayernatlas'); ?></p>
                </div>

                <div class="m2ba-switcher" role="tablist" aria-label="<?php esc_attr_e('Modus wählen', 'maps2bayernatlas'); ?>">
                    <button class="m2ba-switch is-active" type="button" data-role="switch" data-target="single" aria-selected="true"><?php esc_html_e('Einzeln', 'maps2bayernatlas'); ?></button>
                    <button class="m2ba-switch" type="button" data-role="switch" data-target="bulk" aria-selected="false"><?php esc_html_e('Bulk', 'maps2bayernatlas'); ?></button>
                </div>

                <section class="m2ba-panel is-active" data-panel="single">
                    <form class="m2ba-form" data-role="single-form" novalidate>
                        <label class="m2ba-label" for="<?php echo esc_attr($single_input); ?>">
                            <?php esc_html_e('Einzelne Karten-URL', 'maps2bayernatlas'); ?>
                        </label>

                        <div class="m2ba-row">
                            <input
                                id="<?php echo esc_attr($single_input); ?>"
                                class="m2ba-input"
                                type="url"
                                inputmode="url"
                                autocomplete="off"
                                placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                                required
                            >
                            <button
                                class="m2ba-submit"
                                type="submit"
                                data-default-label="<?php echo esc_attr($atts['button']); ?>"
                                data-loading-label="<?php echo esc_attr__('Link wird umgewandelt …', 'maps2bayernatlas'); ?>"
                            >
                                <span class="m2ba-submit-label"><?php echo esc_html($atts['button']); ?></span>
                            </button>
                        </div>

                        <p class="m2ba-help">
                            <?php esc_html_e('Für einzelne Links mit direkter Vorschau, Koordinaten und Copy-Button.', 'maps2bayernatlas'); ?>
                        </p>
                    </form>

                    <div class="m2ba-status" data-role="single-status" aria-live="polite"></div>

                    <div class="m2ba-result" data-role="single-result" hidden>
                        <div class="m2ba-grid">
                            <div class="m2ba-card">
                                <span class="m2ba-card-label"><?php esc_html_e('WGS84', 'maps2bayernatlas'); ?></span>
                                <code class="m2ba-card-value" data-role="single-wgs84">-</code>
                            </div>

                            <div class="m2ba-card">
                                <span class="m2ba-card-label"><?php esc_html_e('UTM Zone 32N', 'maps2bayernatlas'); ?></span>
                                <code class="m2ba-card-value" data-role="single-utm">-</code>
                            </div>
                        </div>

                        <div class="m2ba-link-block">
                            <span class="m2ba-card-label"><?php esc_html_e('BayernAtlas-Link', 'maps2bayernatlas'); ?></span>
                            <a class="m2ba-link" data-role="single-open-link" href="#" target="_blank" rel="noopener noreferrer">
                                <span data-role="single-link-text">-</span>
                            </a>
                        </div>

                        <div class="m2ba-actions">
                            <a class="m2ba-action m2ba-action-primary" data-role="single-open-link-button" href="#" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('BayernAtlas öffnen', 'maps2bayernatlas'); ?>
                            </a>
                            <button class="m2ba-action m2ba-action-secondary" type="button" data-role="single-copy-link">
                                <?php esc_html_e('Link kopieren', 'maps2bayernatlas'); ?>
                            </button>
                        </div>
                    </div>
                </section>

                <section class="m2ba-panel" data-panel="bulk" hidden>
                    <form class="m2ba-form" data-role="bulk-form" novalidate>
                        <div class="m2ba-bulk-header">
                            <label class="m2ba-label" for="<?php echo esc_attr($bulk_input); ?>">
                                <?php esc_html_e('Mehrere Karten-URLs', 'maps2bayernatlas'); ?>
                            </label>
                            <div class="m2ba-counter" data-role="bulk-counter">
                                <?php echo esc_html(sprintf(__('0 von %d URLs erkannt', 'maps2bayernatlas'), $bulk_max)); ?>
                            </div>
                        </div>

                        <textarea
                            id="<?php echo esc_attr($bulk_input); ?>"
                            class="m2ba-textarea"
                            rows="8"
                            placeholder="<?php echo esc_attr($atts['bulk_placeholder']); ?>"
                        ></textarea>

                        <div class="m2ba-row m2ba-row-actions">
                            <p class="m2ba-help">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %d: maximum URL count */
                                        __('Eine URL pro Zeile. Leere Zeilen werden ignoriert. Maximal %d URLs pro Durchlauf.', 'maps2bayernatlas'),
                                        $bulk_max
                                    )
                                );
                                ?>
                            </p>
                            <button
                                class="m2ba-submit"
                                type="submit"
                                data-default-label="<?php echo esc_attr($atts['bulk_button']); ?>"
                                data-loading-label="<?php echo esc_attr__('Bulk-Umwandlung läuft …', 'maps2bayernatlas'); ?>"
                            >
                                <span class="m2ba-submit-label"><?php echo esc_html($atts['bulk_button']); ?></span>
                            </button>
                        </div>
                    </form>

                    <div class="m2ba-status" data-role="bulk-status" aria-live="polite"></div>

                    <div class="m2ba-result m2ba-result-bulk" data-role="bulk-result" hidden>
                        <div class="m2ba-grid m2ba-grid-summary">
                            <div class="m2ba-card">
                                <span class="m2ba-card-label"><?php esc_html_e('Gesamt', 'maps2bayernatlas'); ?></span>
                                <strong class="m2ba-stat-value" data-role="bulk-total">0</strong>
                            </div>
                            <div class="m2ba-card">
                                <span class="m2ba-card-label"><?php esc_html_e('Erfolgreich', 'maps2bayernatlas'); ?></span>
                                <strong class="m2ba-stat-value m2ba-stat-success" data-role="bulk-success">0</strong>
                            </div>
                            <div class="m2ba-card">
                                <span class="m2ba-card-label"><?php esc_html_e('Fehler', 'maps2bayernatlas'); ?></span>
                                <strong class="m2ba-stat-value m2ba-stat-error" data-role="bulk-failed">0</strong>
                            </div>
                        </div>

                        <div class="m2ba-link-block">
                            <div class="m2ba-output-header">
                                <span class="m2ba-card-label"><?php esc_html_e('Erfolgreiche BayernAtlas-Links', 'maps2bayernatlas'); ?></span>
                                <button class="m2ba-action m2ba-action-secondary" type="button" data-role="bulk-copy-all">
                                    <?php esc_html_e('Alle Erfolgreichen kopieren', 'maps2bayernatlas'); ?>
                                </button>
                            </div>
                            <textarea class="m2ba-output" data-role="bulk-output" rows="6" readonly></textarea>
                        </div>

                        <div class="m2ba-bulk-list" data-role="bulk-results"></div>
                    </div>
                </section>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function normalize_bulk_urls($value): array {
        if (is_string($value)) {
            $value = preg_split('/\R+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $urls = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $url = trim($item);

            if ($url === '') {
                continue;
            }

            $urls[] = substr($url, 0, 2048);
        }

        return $urls;
    }

    private function get_request_zoom(WP_REST_Request $request): int {
        $zoom = $request->get_param('zoom');

        if ($zoom === null || $zoom === '') {
            return M2BA_Converter::get_default_zoom();
        }

        return max(0, min(20, (int) $zoom));
    }

    private function get_payload_fingerprint(array $urls): string {
        return hash('sha256', wp_json_encode(array_values($urls)) ?: '');
    }

    private function conversion_exception_to_error(M2BA_Conversion_Exception $exception): WP_Error {
        $error = new WP_Error(
            $exception->get_error_key(),
            $exception->getMessage(),
            ['status' => $exception->get_status()]
        );

        if ($exception->get_status() === 429) {
            $error->add_data(['retry_after' => M2BA_Rate_Limiter::get_retry_after()], $exception->get_error_key());
        }

        return $error;
    }
}
