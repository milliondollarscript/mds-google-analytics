<?php
/**
 * Plugin Name: Million Dollar Script - Analytics
 * Plugin URI: https://milliondollarscript.com/extensions/analytics
 * Description: Analytics integration for Million Dollar Script with Google Analytics 4, custom tracking snippets, event tracking, and an admin dashboard widget.
 * Version: 1.1.3
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * License: GPL v2 or later
 * Text Domain: mds-google-analytics
 * Requires at least: 6.0
 * Tested up to: 7.1.3
 * Requires PHP: 8.1
 * Requires Plugins: million-dollar-script
 * Requires MDS: 3.0.0
 * Requires MDS API: 1
 * MDS Compatible: MDS 3.0
 */

namespace MillionDollarScript\Extensions\Analytics;

use WP_Error;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('MDS_GA_VERSION', '1.1.3');
define('MDS_GA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDS_GA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Million Dollar Script - Analytics class.
 */
class MDS_Google_Analytics {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_tracking_assets']);
        add_action('wp_head', [$this, 'output_generic_head_code'], 50);
        add_action('wp_body_open', [$this, 'output_generic_body_open_code'], 5);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        add_action('wp_ajax_mds_ga_test_tracking', [$this, 'ajax_test_tracking']);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'extension_onboarding_items']);
        
        add_action('wp_footer', [$this, 'output_generic_footer_code'], 99);
    }

    /**
     * Add Analytics setup shortcuts and legal draft recommendations.
     */
    public function extension_onboarding_items($items) {
        $items['mds-google-analytics'] = [
            'name' => __('Analytics', 'mds-google-analytics'),
            'summary' => __('Review Google Analytics, custom tracking snippets, administrator exclusions, consent categories, advertising-feature settings, and visitor-tracking disclosures.', 'mds-google-analytics'),
            'priority' => 65,
            'actions' => [
                [
                    'label' => __('Open Analytics settings', 'mds-google-analytics'),
                    'url' => admin_url('admin.php?page=mds-google-analytics'),
                    'primary' => true,
                ],
            ],
            'legal_documents' => [
                [
                    'slug' => 'analytics-privacy-notice',
                    'title' => __('Analytics and Visitor Tracking Privacy Notice', 'mds-google-analytics'),
                    'description' => __('Privacy language for analytics page views, events, downloads, outbound links, forms, scroll depth, engagement time, custom tracking snippets, and privacy options.', 'mds-google-analytics'),
                    'page_slug' => 'analytics-visitor-tracking-privacy-notice',
                    'content' => $this->analytics_legal_content(),
                ],
            ],
        ];

        return $items;
    }

    /**
     * Draft analytics privacy content.
     */
    private function analytics_legal_content() {
        return '<h2>' . esc_html__('Analytics and Visitor Tracking Privacy Notice', 'mds-google-analytics') . '</h2>'
            . '<p>' . esc_html__('This notice explains how {{site_name}} may use Google Analytics, custom tracking snippets, and related event tracking on {{site_url}}. It supplements the main privacy policy at {{privacy_policy_url}}.', 'mds-google-analytics') . '</p>'
            . '<h3>' . esc_html__('Analytics Data Collected', 'mds-google-analytics') . '</h3>'
            . '<p>' . esc_html__('When analytics is enabled, visitor interactions may be sent to analytics providers, including page views, page type, logged-in or guest state, file downloads, outbound link clicks, form submissions, scroll depth, engagement time, device and browser signals, approximate location, referrers, and technical identifiers set or processed by those providers.', 'mds-google-analytics') . '</p>'
            . '<h3>' . esc_html__('Privacy Options and Third-Party Processing', 'mds-google-analytics') . '</h3>'
            . '<p>' . esc_html__('Administrators can configure options such as excluding administrators, following compatible WordPress consent signals, assigning consent categories to trusted snippets, and disabling advertising features or personalization signals. Google Analytics is operated by Google under its own terms, privacy policies, retention controls, regional transfer safeguards, and account settings. Other snippets may be operated by their own providers. Visitors may also use browser settings, consent tools, or provider opt-out tools where available.', 'mds-google-analytics') . '</p>'
            . '<h3>' . esc_html__('Use, Retention, and Requests', 'mds-google-analytics') . '</h3>'
            . '<p>' . esc_html__('Analytics records are used to understand site performance, diagnose issues, measure downloads and interactions, improve pages, detect abuse, and report aggregate trends. Requests about access, correction, deletion, objection, or analytics privacy can be sent through {{contact_method}}, subject to what {{site_name}} can access in analytics provider tools and to legal, security, backup, and dispute-retention obligations.', 'mds-google-analytics') . '</p>';
    }
    
    /**
     * Initialize the extension
     */
    public function init() {
        load_plugin_textdomain('mds-google-analytics', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue shared admin styling when available.
     */
    public function admin_assets($hook) {
        $is_analytics_page = false !== strpos((string) $hook, 'mds-google-analytics');
        $is_dashboard = 'index.php' === (string) $hook;
        if (!$is_analytics_page && !$is_dashboard) {
            return;
        }

        if ($is_analytics_page && class_exists('\MillionDollarScript\Core\Runtime')) {
            wp_enqueue_style('million-dollar-script-admin', \MillionDollarScript\Core\Runtime::url('assets/mds3/') . 'css/admin.css', [], \MillionDollarScript\Core\Runtime::version());
        }

        wp_enqueue_style(
            'mds-analytics-admin',
            MDS_GA_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MDS_GA_VERSION
        );
        if (!$is_analytics_page) {
            return;
        }

        wp_enqueue_script(
            'mds-analytics-admin',
            MDS_GA_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            MDS_GA_VERSION,
            true
        );
        wp_localize_script('mds-analytics-admin', 'mdsAnalyticsAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mds_ga_test_nonce'),
            'checking' => __('Checking Google tag availability...', 'mds-google-analytics'),
            'error' => __('The Google tag availability check failed.', 'mds-google-analytics'),
        ]);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            $this->admin_parent_slug(),
            __('Analytics', 'mds-google-analytics'),
            __('Analytics', 'mds-google-analytics'),
            'manage_options',
            'mds-google-analytics',
            [$this, 'admin_page']
        );
    }

    /**
     * Get the parent admin slug for the active MDS generation.
     */
    private function admin_parent_slug() {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::admin_parent_slug('milliondollarscript');
        }

        return class_exists('\MillionDollarScript\Core\Runtime') || class_exists('\\MillionDollarScript\\Core\\Runtime') ? 'mds3' : 'milliondollarscript';
    }
    
    /**
     * Get GA tracking ID from options
     */
    private function get_tracking_id() {
        return $this->normalize_tracking_id(get_option('mds_ga_tracking_id', ''));
    }
    
    /**
     * Check if tracking is enabled
     */
    private function is_tracking_enabled() {
        return $this->option_enabled('mds_ga_enabled', false) && !empty($this->get_tracking_id());
    }

    /**
     * Check whether custom tracking snippets should be output.
     */
    private function is_generic_tracking_enabled() {
        if (!$this->option_enabled('mds_ga_generic_enabled', false)) {
            return false;
        }

        return '' !== trim($this->generic_tracking_code('head'))
            || '' !== trim($this->generic_tracking_code('body'))
            || '' !== trim($this->generic_tracking_code('footer'));
    }

    /**
     * Check the shared visitor tracking exclusion settings.
     */
    private function analytics_should_track_current_user($consent_category = 'statistics') {
        if ($this->option_enabled('mds_ga_exclude_admins', true) && current_user_can('manage_options')) {
            return false;
        }

        $consent_category = sanitize_key((string) $consent_category);
        $should_track = true;

        if (
            'always' !== $consent_category
            && $this->option_enabled('mds_ga_respect_consent_api', true)
            && function_exists('wp_has_consent')
        ) {
            $should_track = (bool) wp_has_consent($consent_category);
        }

        return (bool) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/google-analytics/analytics/should/track/current/user', ['mds_analytics_should_track_current_user'], $should_track, $consent_category);
    }

    /**
     * Return the consent category assigned to trusted custom snippets.
     */
    private function generic_consent_category() {
        $category = sanitize_key((string) get_option('mds_ga_generic_consent_category', 'statistics'));

        return in_array($category, ['always', 'statistics', 'marketing'], true) ? $category : 'statistics';
    }

    /**
     * Read a boolean option stored as an explicit 1/0 value.
     */
    private function option_enabled($key, $default = false) {
        $value = get_option($key, $default ? '1' : '0');

        return in_array($value, ['1', 1, true, 'yes', 'on'], true);
    }

    /**
     * Normalize and validate a GA4 Measurement ID.
     */
    private function normalize_tracking_id($tracking_id) {
        if (!is_scalar($tracking_id)) {
            return '';
        }

        $tracking_id = strtoupper(sanitize_text_field(wp_unslash($tracking_id)));
        $tracking_id = preg_replace('/\s+/', '', $tracking_id);

        return preg_match('/^G-[A-Z0-9]{4,20}$/', $tracking_id) ? $tracking_id : '';
    }

    /**
     * Return custom tracking code for a supported page location.
     */
    private function generic_tracking_code($location) {
        $option_map = [
            'head' => 'mds_ga_generic_head',
            'body' => 'mds_ga_generic_body',
            'footer' => 'mds_ga_generic_footer',
        ];

        if (!isset($option_map[$location])) {
            return '';
        }

        $code = (string) get_option($option_map[$location], '');

        return (string) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/google-analytics/analytics/generic/tracking/code', ['mds_analytics_generic_tracking_code'], $code, $location);
    }

    /**
     * Sanitize a trusted administrator tracking snippet before storage.
     */
    private function sanitize_generic_tracking_code($code) {
        if (!is_scalar($code)) {
            return '';
        }

        $code = wp_check_invalid_utf8((string) wp_unslash($code));
        $code = preg_replace('/<\?(?:php|=)?[\s\S]*?(?:\?>|$)/i', '', $code);

        return trim((string) $code);
    }
    
    /**
     * Enqueue Google Analytics and the extension's event listeners.
     */
    public function enqueue_tracking_assets() {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        if (!$this->analytics_should_track_current_user('statistics')) {
            return;
        }

        $tracking_id = $this->get_tracking_id();
        if ('' === $tracking_id) {
            return;
        }

        $disable_advertising = $this->option_enabled('mds_ga_disable_advertising', false);
        $gtag_src = add_query_arg('id', rawurlencode($tracking_id), 'https://www.googletagmanager.com/gtag/js');

        $config = [
            'mds_page_type' => $this->get_page_type(),
            'mds_user_type' => is_user_logged_in() ? 'logged_in' : 'guest',
        ];
        if ($disable_advertising) {
            $config['allow_google_signals'] = false;
            $config['allow_ad_personalization_signals'] = false;
        }

        wp_enqueue_script('mds-analytics-google-tag', $gtag_src, [], null, false);
        wp_script_add_data('mds-analytics-google-tag', 'strategy', 'async');
        wp_add_inline_script(
            'mds-analytics-google-tag',
            'window.dataLayer=window.dataLayer||[];function gtag(){window.dataLayer.push(arguments);}gtag("js",new Date());gtag("config",'
                . wp_json_encode($tracking_id, JSON_HEX_TAG | JSON_HEX_AMP)
                . ',' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) . ');',
            'before'
        );
        wp_enqueue_script(
            'mds-analytics-events',
            MDS_GA_PLUGIN_URL . 'assets/js/frontend.js',
            ['mds-analytics-google-tag'],
            MDS_GA_VERSION,
            true
        );
    }

    /**
     * Backward-compatible alias for integrations using the old output method.
     */
    public function output_tracking_code() {
        $this->enqueue_tracking_assets();
    }

    /**
     * Output trusted custom tracking code in the page head.
     */
    public function output_generic_head_code() {
        $this->output_generic_tracking_code('head');
    }

    /**
     * Output trusted custom tracking code near the opening body tag.
     */
    public function output_generic_body_open_code() {
        $this->output_generic_tracking_code('body');
    }

    /**
     * Output trusted custom tracking code near the closing body tag.
     */
    public function output_generic_footer_code() {
        $this->output_generic_tracking_code('footer');
    }

    /**
     * Output a trusted custom tracking snippet.
     */
    private function output_generic_tracking_code($location) {
        $consent_category = $this->generic_consent_category();
        $should_output = $this->is_generic_tracking_enabled() && $this->analytics_should_track_current_user($consent_category);
        $should_output = (bool) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/google-analytics/analytics/should/output/generic/tracking', ['mds_analytics_should_output_generic_tracking'], $should_output, $location);

        if (!$should_output) {
            return;
        }

        $code = trim($this->generic_tracking_code($location));
        if ('' === $code) {
            return;
        }

        echo "\n<!-- Million Dollar Script - Analytics: " . esc_html($location) . " tracking code -->\n";
        echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Saved only by trusted administrators with unfiltered_html.
    }
    
    /**
     * Get current page type for tracking
     */
    private function get_page_type() {
        if (is_home() || is_front_page()) return 'homepage';
        if (is_single()) return 'post';
        if (is_page()) return 'page';
        if (is_category()) return 'category';
        if (is_tag()) return 'tag';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        return 'other';
    }
    
    /**
     * Backward-compatible alias for integrations using the old event method.
     */
    public function add_custom_events() {
        $this->enqueue_tracking_assets();
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (!$this->is_tracking_enabled() && !$this->is_generic_tracking_enabled()) {
            return;
        }
        
        wp_add_dashboard_widget(
            'mds_ga_widget',
            __('Analytics Overview', 'mds-google-analytics'),
            [$this, 'dashboard_widget_content']
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        $tracking_id = $this->get_tracking_id();
        $ga_active = $this->is_tracking_enabled();
        $generic_active = $this->is_generic_tracking_enabled();
        ?>
        <div class="mds-ga-widget">
            <p><?php esc_html_e('Analytics tracking is active for your website.', 'mds-google-analytics'); ?></p>
            <?php if ($ga_active): ?>
                <p><strong><?php esc_html_e('Measurement ID:', 'mds-google-analytics'); ?></strong> <?php echo esc_html($tracking_id); ?></p>
            <?php endif; ?>
            <?php if ($generic_active): ?>
                <p><strong><?php esc_html_e('Custom tracking:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('Enabled', 'mds-google-analytics'); ?></p>
            <?php endif; ?>
            
            <div class="mds-ga-quick-links">
                <?php if ($ga_active): ?>
                    <a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer" class="button button-primary">
                        <?php esc_html_e('Open Google Analytics', 'mds-google-analytics'); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mds-google-analytics')); ?>" class="button">
                    <?php esc_html_e('Settings', 'mds-google-analytics'); ?>
                </a>
            </div>
            
            <div class="mds-ga-info">
                <h4><?php esc_html_e('Tracked Events:', 'mds-google-analytics'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Page views with custom dimensions', 'mds-google-analytics'); ?></li>
                    <li><?php esc_html_e('File downloads', 'mds-google-analytics'); ?></li>
                    <li><?php esc_html_e('External link clicks', 'mds-google-analytics'); ?></li>
                    <li><?php esc_html_e('Form submissions', 'mds-google-analytics'); ?></li>
                    <li><?php esc_html_e('Scroll depth tracking', 'mds-google-analytics'); ?></li>
                    <li><?php esc_html_e('Time on page engagement', 'mds-google-analytics'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions', 'mds-google-analytics'));
        }

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['mds_ga_nonce'])) {
            $this->save_settings();
        }
        
        $tracking_id = $this->get_tracking_id();
        $enabled = $this->option_enabled('mds_ga_enabled', false);
        $exclude_admins = $this->option_enabled('mds_ga_exclude_admins', true);
        $respect_consent_api = $this->option_enabled('mds_ga_respect_consent_api', true);
        $disable_advertising = $this->option_enabled('mds_ga_disable_advertising', false);
        $generic_enabled = $this->option_enabled('mds_ga_generic_enabled', false);
        $generic_consent_category = $this->generic_consent_category();
        $generic_head = $this->generic_tracking_code('head');
        $generic_body = $this->generic_tracking_code('body');
        $generic_footer = $this->generic_tracking_code('footer');
        $ga_active = $this->is_tracking_enabled();
        $generic_active = $this->is_generic_tracking_enabled();
        $active_tab = sanitize_key((string) ($_GET['tab'] ?? 'settings'));
        if (!in_array($active_tab, ['settings', 'help'], true)) {
            $active_tab = 'settings';
        }
        $base_url = admin_url('admin.php?page=mds-google-analytics');
        ?>
        <div class="wrap mds3-admin">
            <div class="mds-extension-heading-row">
                <h1><?php esc_html_e('Analytics', 'mds-google-analytics'); ?></h1>
                <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-google-analytics')); ?>
            </div>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper wp-clearfix mds-analytics-tabs" aria-label="<?php esc_attr_e('Analytics workspace', 'mds-google-analytics'); ?>">
                <a class="nav-tab<?php echo 'settings' === $active_tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', 'settings', $base_url)); ?>" <?php echo 'settings' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Settings', 'mds-google-analytics'); ?></a>
                <a class="nav-tab<?php echo 'help' === $active_tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', 'help', $base_url)); ?>" <?php echo 'help' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Setup and events', 'mds-google-analytics'); ?></a>
            </nav>

            <?php if ('settings' === $active_tab) : ?>
                <div class="mds3-card mds-analytics-status-card">
                    <h2><?php esc_html_e('Tracking Status', 'mds-google-analytics'); ?></h2>
                    <?php if ($ga_active || $generic_active): ?>
                        <div class="mds-analytics-status-list">
                            <div>
                                <span class="mds-analytics-status mds-analytics-status-<?php echo $ga_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $ga_active ? esc_html__('Active', 'mds-google-analytics') : esc_html__('Inactive', 'mds-google-analytics'); ?>
                                </span>
                                <strong><?php esc_html_e('Google Analytics 4', 'mds-google-analytics'); ?></strong>
                                <?php if ($ga_active): ?><code><?php echo esc_html($tracking_id); ?></code><?php endif; ?>
                            </div>
                            <div>
                                <span class="mds-analytics-status mds-analytics-status-<?php echo $generic_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $generic_active ? esc_html__('Active', 'mds-google-analytics') : esc_html__('Inactive', 'mds-google-analytics'); ?>
                                </span>
                                <strong><?php esc_html_e('Custom tracking snippets', 'mds-google-analytics'); ?></strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mds-analytics-notice mds-analytics-notice-neutral">
                            <?php esc_html_e('No analytics provider is currently enabled.', 'mds-google-analytics'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($ga_active): ?>
                        <div class="mds-analytics-status-actions">
                            <button type="button" id="mds-ga-test-tracking" class="button"><?php esc_html_e('Check tag availability', 'mds-google-analytics'); ?></button>
                            <a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php esc_html_e('Open Google Analytics', 'mds-google-analytics'); ?></a>
                        </div>
                        <div id="mds-ga-test-result" class="mds-analytics-test-result" role="status" aria-live="polite"></div>
                        <p class="description"><?php esc_html_e('The availability check confirms that this server can reach the Google tag library. Confirm collected events in Google Analytics Realtime or DebugView from a frontend visit.', 'mds-google-analytics'); ?></p>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?php echo esc_url(add_query_arg('tab', 'settings', $base_url)); ?>">
                    <?php wp_nonce_field('mds_ga_settings', 'mds_ga_nonce'); ?>

                    <div class="mds3-card">
                        <h2><?php esc_html_e('Google Analytics 4', 'mds-google-analytics'); ?></h2>
                        <p><?php esc_html_e('Connect a GA4 Measurement ID when you want Google Analytics page views and Million Dollar Script event tracking.', 'mds-google-analytics'); ?></p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Google Analytics tracking', 'mds-google-analytics'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="mds_ga_enabled" value="1" <?php checked($enabled); ?>>
                                        <?php esc_html_e('Enable Google Analytics 4', 'mds-google-analytics'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Measurement ID', 'mds-google-analytics'); ?></th>
                                <td>
                                    <input type="text" name="mds_ga_tracking_id" value="<?php echo esc_attr($tracking_id); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                                    <p class="description"><?php esc_html_e('Enter your Google Analytics 4 Measurement ID. It starts with G-.', 'mds-google-analytics'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Privacy options', 'mds-google-analytics'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="mds_ga_exclude_admins" value="1" <?php checked($exclude_admins); ?>>
                                        <?php esc_html_e('Exclude administrators from all analytics tracking', 'mds-google-analytics'); ?>
                                    </label><br>

                                    <label>
                                        <input type="checkbox" name="mds_ga_respect_consent_api" value="1" <?php checked($respect_consent_api); ?>>
                                        <?php esc_html_e('Respect compatible WordPress Consent API signals', 'mds-google-analytics'); ?>
                                    </label><br>

                                    <label>
                                        <input type="checkbox" name="mds_ga_disable_advertising" value="1" <?php checked($disable_advertising); ?>>
                                        <?php esc_html_e('Disable Google advertising features and personalization', 'mds-google-analytics'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('When a compatible consent plugin is active, Google Analytics waits for statistics consent. Without a consent API provider, this setting does not block tracking.', 'mds-google-analytics'); ?></p>
                                    <p class="description"><?php esc_html_e('Google Analytics 4 discards IP addresses before logging them, so it does not use the legacy Universal Analytics IP-anonymization setting.', 'mds-google-analytics'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                <div class="mds3-card">
                    <h2><?php esc_html_e('Custom Tracking Code', 'mds-google-analytics'); ?></h2>
                    <p><?php esc_html_e('Add trusted analytics, pixels, consent-manager, or verification snippets without editing theme files. Google Analytics and custom snippets can be enabled at the same time.', 'mds-google-analytics'); ?></p>
                    <?php if (!current_user_can('unfiltered_html')): ?>
                        <div class="notice notice-warning inline">
                            <p><?php esc_html_e('Your account can change whether custom tracking is enabled, but only administrators with unfiltered HTML permission can edit tracking code snippets.', 'mds-google-analytics'); ?></p>
                        </div>
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Custom tracking', 'mds-google-analytics'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="mds_ga_generic_enabled" value="1" <?php checked($generic_enabled); ?>>
                                    <?php esc_html_e('Enable custom tracking snippets', 'mds-google-analytics'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mds-ga-generic-consent-category"><?php esc_html_e('Consent category', 'mds-google-analytics'); ?></label></th>
                            <td>
                                <select id="mds-ga-generic-consent-category" name="mds_ga_generic_consent_category">
                                    <option value="statistics" <?php selected($generic_consent_category, 'statistics'); ?>><?php esc_html_e('Statistics', 'mds-google-analytics'); ?></option>
                                    <option value="marketing" <?php selected($generic_consent_category, 'marketing'); ?>><?php esc_html_e('Marketing', 'mds-google-analytics'); ?></option>
                                    <option value="always" <?php selected($generic_consent_category, 'always'); ?>><?php esc_html_e('Always load', 'mds-google-analytics'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Statistics and marketing snippets follow the matching WordPress Consent API signal when consent integration is enabled. Use Always load only for essential code or the consent manager itself.', 'mds-google-analytics'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Head code', 'mds-google-analytics'); ?></th>
                            <td>
                                <textarea name="mds_ga_generic_head" class="large-text code" rows="6" spellcheck="false"><?php echo esc_textarea($generic_head); ?></textarea>
                                <p class="description"><?php esc_html_e('Outputs in wp_head before the closing head tag. Use this for most analytics loader scripts and site-verification tags.', 'mds-google-analytics'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Top of body code', 'mds-google-analytics'); ?></th>
                            <td>
                                <textarea name="mds_ga_generic_body" class="large-text code" rows="6" spellcheck="false"><?php echo esc_textarea($generic_body); ?></textarea>
                                <p class="description"><?php esc_html_e('Outputs on wp_body_open when the active theme supports that WordPress hook.', 'mds-google-analytics'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Footer code', 'mds-google-analytics'); ?></th>
                            <td>
                                <textarea name="mds_ga_generic_footer" class="large-text code" rows="6" spellcheck="false"><?php echo esc_textarea($generic_footer); ?></textarea>
                                <p class="description"><?php esc_html_e('Outputs in wp_footer near the closing body tag. Use this for deferred analytics scripts that do not need to load early.', 'mds-google-analytics'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="description"><?php esc_html_e('Only paste code from providers you trust, and make sure your privacy and consent notices cover every tracking provider you enable.', 'mds-google-analytics'); ?></p>
                </div>
                
                    <?php submit_button(); ?>
                </form>
            <?php else : ?>
                <div class="mds3-card">
                    <h2><?php esc_html_e('Setup Instructions', 'mds-google-analytics'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('Create a Google Analytics 4 property at', 'mds-google-analytics'); ?> <a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer">analytics.google.com</a></li>
                        <li><?php esc_html_e('Copy your Measurement ID (starts with G-) from the property settings', 'mds-google-analytics'); ?></li>
                        <li><?php esc_html_e('Open the Settings tab, paste the Measurement ID, and enable tracking', 'mds-google-analytics'); ?></li>
                        <li><?php esc_html_e('Configure privacy options according to your needs', 'mds-google-analytics'); ?></li>
                        <li><?php esc_html_e('Save settings and visit your website to start tracking', 'mds-google-analytics'); ?></li>
                    </ol>
                </div>

                <div class="mds3-card">
                    <h2><?php esc_html_e('Enhanced Tracking Features', 'mds-google-analytics'); ?></h2>
                    <p><?php esc_html_e('This extension automatically tracks the following events:', 'mds-google-analytics'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('Page Views:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('Enhanced with page type and user type dimensions', 'mds-google-analytics'); ?></li>
                        <li><strong><?php esc_html_e('File Downloads:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('PDF, ZIP, DOC, XLS files', 'mds-google-analytics'); ?></li>
                        <li><strong><?php esc_html_e('External Links:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('Clicks to external websites', 'mds-google-analytics'); ?></li>
                        <li><strong><?php esc_html_e('Form Submissions:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('Any form on your website', 'mds-google-analytics'); ?></li>
                        <li><strong><?php esc_html_e('Scroll Depth:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('25%, 50%, 75%, and 90% scroll milestones', 'mds-google-analytics'); ?></li>
                        <li><strong><?php esc_html_e('Engagement Time:', 'mds-google-analytics'); ?></strong> <?php esc_html_e('Time spent on page tracking', 'mds-google-analytics'); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions', 'mds-google-analytics'));
        }

        check_admin_referer('mds_ga_settings', 'mds_ga_nonce');

        $tracking_id = $this->normalize_tracking_id($_POST['mds_ga_tracking_id'] ?? '');
        $enabled = !empty($_POST['mds_ga_enabled']);
        $generic_enabled = !empty($_POST['mds_ga_generic_enabled']);
        $generic_consent_category = sanitize_key(wp_unslash($_POST['mds_ga_generic_consent_category'] ?? 'statistics'));
        if (!in_array($generic_consent_category, ['always', 'statistics', 'marketing'], true)) {
            $generic_consent_category = 'statistics';
        }
        $generic_fields = [
            'mds_ga_generic_head' => 'mds_ga_generic_head',
            'mds_ga_generic_body' => 'mds_ga_generic_body',
            'mds_ga_generic_footer' => 'mds_ga_generic_footer',
        ];
        $can_save_generic_code = current_user_can('unfiltered_html');
        $has_error = false;

        if ($enabled && '' === $tracking_id) {
            update_option('mds_ga_enabled', '0');
            update_option('mds_ga_tracking_id', '');
            echo '<div class="notice notice-error"><p>' . esc_html__('Enter a valid GA4 Measurement ID that starts with G-. Tracking was not enabled.', 'mds-google-analytics') . '</p></div>';
            $enabled = false;
            $has_error = true;
        }
        
        update_option('mds_ga_enabled', $enabled ? '1' : '0');
        update_option('mds_ga_tracking_id', $tracking_id);
        update_option('mds_ga_exclude_admins', !empty($_POST['mds_ga_exclude_admins']) ? '1' : '0');
        update_option('mds_ga_respect_consent_api', !empty($_POST['mds_ga_respect_consent_api']) ? '1' : '0');
        update_option('mds_ga_disable_advertising', !empty($_POST['mds_ga_disable_advertising']) ? '1' : '0');
        update_option('mds_ga_generic_enabled', $generic_enabled ? '1' : '0');
        update_option('mds_ga_generic_consent_category', $generic_consent_category);

        if ($can_save_generic_code) {
            foreach ($generic_fields as $posted_key => $option_key) {
                update_option($option_key, $this->sanitize_generic_tracking_code($_POST[$posted_key] ?? ''));
            }
        } elseif ($generic_enabled) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Custom tracking status was saved, but tracking code snippets were not changed because this account cannot save unfiltered HTML.', 'mds-google-analytics') . '</p></div>';
        }
        
        if (!$has_error) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'mds-google-analytics') . '</p></div>';
        }
    }
    
    /**
     * Check that the configured Google tag library is reachable.
     *
     * @return array|WP_Error
     */
    public function check_tag_availability() {
        if (!$this->is_tracking_enabled()) {
            return new WP_Error(
                'mds_analytics_not_configured',
                __('Enable Google Analytics with a valid Measurement ID before checking the tag.', 'mds-google-analytics'),
                ['status' => 400]
            );
        }

        $tracking_id = $this->get_tracking_id();
        $tag_url = add_query_arg('id', rawurlencode($tracking_id), 'https://www.googletagmanager.com/gtag/js');
        $response = wp_safe_remote_get($tag_url, [
            'timeout' => 5,
            'redirection' => 2,
            'limit_response_size' => 2048,
            'user-agent' => 'Million Dollar Script - Analytics/' . MDS_GA_VERSION . '; ' . home_url('/'),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'mds_analytics_tag_unreachable',
                __('This server could not reach the Google tag library. Check outbound HTTPS access and try again.', 'mds-google-analytics'),
                ['status' => 502]
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'mds_analytics_tag_http_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('Google tag availability returned HTTP %d. Try again or check the host firewall.', 'mds-google-analytics'),
                    absint($status_code)
                ),
                ['status' => 502]
            );
        }

        return [
            'message' => __('The Google tag library is reachable. Confirm actual page views and events in Google Analytics Realtime or DebugView.', 'mds-google-analytics'),
            'tracking_id' => $tracking_id,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Handle the authenticated tag-availability request.
     */
    public function ajax_test_tracking() {
        check_ajax_referer('mds_ga_test_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'mds-google-analytics')], 403);
        }

        $result = $this->check_tag_availability();
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            wp_send_json_error(
                ['message' => $result->get_error_message()],
                absint(is_array($data) ? ($data['status'] ?? 500) : 500)
            );
        }

        wp_send_json_success($result);
    }
}

if (!class_exists('MDS_Google_Analytics', false)) {
    class_alias(MDS_Google_Analytics::class, 'MDS_Google_Analytics');
}

// Initialize the extension
new MDS_Google_Analytics();

add_action('million-dollar-script/register/extensions', static function(): void {
    if (!class_exists('\\MillionDollarScript\\Extensions\\Registry')) {
        return;
    }
    \MillionDollarScript\Extensions\Registry::register([
        'id' => 'mds-google-analytics',
        'name' => 'Analytics',
        'description' => 'Configure analytics tags, consent-aware tracking, and reporting shortcuts.',
        'version' => MDS_GA_VERSION,
        'main_file' => __FILE__,
        'premium' => false,
        'license_required' => false,
        'cleanup' => [
            'description' => 'Analytics IDs, tracking snippets, consent settings, and extension preferences.',
        ],
    ]);
});
