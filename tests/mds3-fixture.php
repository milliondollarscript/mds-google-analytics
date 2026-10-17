<?php
/**
 * Local workflow fixture for Analytics.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/mds-google-analytics/tests/mds3-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MDS_Google_Analytics')) {
    fwrite(STDERR, "Analytics is not active.\n");
    exit(1);
}

wp_set_current_user(1);
$analytics = new MDS_Google_Analytics();
$failure = '';
$missing_option = '__mds_analytics_fixture_missing__';
$option_names = [
    'mds_ga_enabled',
    'mds_ga_tracking_id',
    'mds_ga_exclude_admins',
    'mds_ga_anonymize_ip',
    'mds_ga_respect_consent_api',
    'mds_ga_disable_advertising',
    'mds_ga_generic_enabled',
    'mds_ga_generic_consent_category',
    'mds_ga_generic_head',
    'mds_ga_generic_body',
    'mds_ga_generic_footer',
];
$options_before = [];
foreach ($option_names as $option_name) {
    $options_before[$option_name] = get_option($option_name, $missing_option);
}
$request_method_before = $_SERVER['REQUEST_METHOD'] ?? null;
$post_before = $_POST;
$request_before = $_REQUEST;
$remote_request = [];
$http_filter = static function ($preempt, $args, $url) use (&$remote_request) {
    if (0 !== strpos((string) $url, 'https://www.googletagmanager.com/gtag/js')) {
        return $preempt;
    }

    $remote_request = [
        'url' => (string) $url,
        'args' => $args,
    ];

    return [
        'headers' => [],
        'body' => 'fixture',
        'response' => ['code' => 200, 'message' => 'OK'],
        'cookies' => [],
        'filename' => null,
    ];
};

$call_private = static function ($object, $method, array $arguments = []) {
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $arguments);
};

try {
    if ('1.1.1' !== MDS_GA_VERSION) {
        throw new RuntimeException('Analytics version metadata is stale.');
    }

    $analytics->add_admin_menu();
    global $submenu;
    $menu_slugs = array_map(static function ($item) {
        return (string) ($item[2] ?? '');
    }, (array) ($submenu['mds3'] ?? []));
    if (!in_array('mds-google-analytics', $menu_slugs, true)) {
        throw new RuntimeException('Analytics did not register its extension submenu.');
    }

    $onboarding = apply_filters('million-dollar-script/extension/onboarding/items', []);
    if (empty($onboarding['mds-google-analytics']['actions']) || empty($onboarding['mds-google-analytics']['legal_documents'])) {
        throw new RuntimeException('Analytics onboarding is incomplete.');
    }
    $legal_content = (string) ($onboarding['mds-google-analytics']['legal_documents'][0]['content'] ?? '');
    if (false === strpos($legal_content, 'consent categories') || false !== strpos($legal_content, 'anonymizing IP addresses')) {
        throw new RuntimeException('Analytics privacy guidance is stale.');
    }

    delete_option('mds_ga_enabled');
    delete_option('mds_ga_tracking_id');
    if ($call_private($analytics, 'is_tracking_enabled')) {
        throw new RuntimeException('Analytics should be disabled without a Measurement ID.');
    }
    if ('G-ABC12345' !== $call_private($analytics, 'normalize_tracking_id', [' g-abc12345 '])) {
        throw new RuntimeException('Analytics did not normalize a valid Measurement ID.');
    }
    if ('' !== $call_private($analytics, 'normalize_tracking_id', ['UA-1234-1'])) {
        throw new RuntimeException('Analytics accepted a non-GA4 tracking ID.');
    }

    $_POST = [
        'mds_ga_nonce' => wp_create_nonce('mds_ga_settings'),
        'mds_ga_enabled' => '1',
        'mds_ga_tracking_id' => 'G-FIXTURE123',
        'mds_ga_respect_consent_api' => '1',
        'mds_ga_disable_advertising' => '1',
        'mds_ga_generic_enabled' => '1',
        'mds_ga_generic_consent_category' => 'statistics',
        'mds_ga_generic_head' => '<script data-fixture="head">window.fixtureHead=true;</script><?php echo "unsafe"; ?>',
        'mds_ga_generic_body' => '<noscript data-fixture="body">Analytics fallback</noscript>',
        'mds_ga_generic_footer' => '<script data-fixture="footer">window.fixtureFooter=true;</script>',
    ];
    $_REQUEST = $_POST;
    ob_start();
    $call_private($analytics, 'save_settings');
    $save_output = ob_get_clean();
    if (false === strpos($save_output, 'Settings saved successfully')) {
        throw new RuntimeException('Analytics settings did not report a successful save.');
    }
    if ('1' !== get_option('mds_ga_enabled') || 'G-FIXTURE123' !== get_option('mds_ga_tracking_id')) {
        throw new RuntimeException('Analytics did not save its GA4 settings.');
    }
    if (
        'statistics' !== get_option('mds_ga_generic_consent_category')
        || false !== strpos((string) get_option('mds_ga_generic_head'), '<?')
        || false !== strpos((string) get_option('mds_ga_generic_head'), 'unsafe')
    ) {
        throw new RuntimeException('Analytics custom snippet settings were not normalized safely.');
    }

    wp_set_current_user(0);
    wp_dequeue_script('mds-analytics-google-tag');
    wp_dequeue_script('mds-analytics-events');
    $analytics->enqueue_tracking_assets();
    if (!wp_script_is('mds-analytics-google-tag', 'enqueued') || !wp_script_is('mds-analytics-events', 'enqueued')) {
        throw new RuntimeException('Analytics frontend assets were not enqueued.');
    }
    $scripts = wp_scripts();
    $tag_src = (string) ($scripts->registered['mds-analytics-google-tag']->src ?? '');
    $before = implode("\n", (array) ($scripts->registered['mds-analytics-google-tag']->extra['before'] ?? []));
    if (false === strpos($tag_src, 'www.googletagmanager.com/gtag/js') || false === strpos($tag_src, 'G-FIXTURE123')) {
        throw new RuntimeException('Analytics enqueued the wrong Google tag URL.');
    }
    if (1 !== substr_count($before, 'gtag("config"') || false !== strpos($before, 'page_view') || false === strpos($before, 'allow_google_signals')) {
        throw new RuntimeException('Analytics GA4 initialization would duplicate page views or omit privacy settings.');
    }

    wp_dequeue_script('mds-analytics-google-tag');
    wp_dequeue_script('mds-analytics-events');
    $deny_statistics = static function ($should_track, $category) {
        return 'statistics' === $category ? false : $should_track;
    };
    add_filter('mds_analytics_should_track_current_user', $deny_statistics, 10, 2);
    $analytics->enqueue_tracking_assets();
    if (wp_script_is('mds-analytics-google-tag', 'enqueued')) {
        throw new RuntimeException('Analytics ignored a denied statistics consent signal.');
    }
    ob_start();
    $analytics->output_generic_head_code();
    $denied_generic_output = ob_get_clean();
    if ('' !== trim($denied_generic_output)) {
        throw new RuntimeException('Analytics output a statistics snippet without consent.');
    }
    remove_filter('mds_analytics_should_track_current_user', $deny_statistics, 10);

    update_option('mds_ga_generic_consent_category', 'always');
    ob_start();
    $analytics->output_generic_head_code();
    $always_output = ob_get_clean();
    if (false === strpos($always_output, 'data-fixture="head"') || false !== strpos($always_output, '<?')) {
        throw new RuntimeException('Analytics did not output a trusted always-load snippet safely.');
    }

    add_filter('pre_http_request', $http_filter, 10, 3);
    $availability = $analytics->check_tag_availability();
    remove_filter('pre_http_request', $http_filter, 10);
    if (is_wp_error($availability) || 'G-FIXTURE123' !== (string) ($availability['tracking_id'] ?? '')) {
        throw new RuntimeException('Analytics tag availability check failed.');
    }
    if (false === strpos((string) ($remote_request['url'] ?? ''), 'id=G-FIXTURE123') || 5 !== absint($remote_request['args']['timeout'] ?? 0)) {
        throw new RuntimeException('Analytics availability check did not use the fixed Google endpoint and bounded timeout.');
    }

    wp_set_current_user(1);
    $analytics->admin_assets('million-dollar-script_page_mds-google-analytics');
    if (!wp_style_is('mds-analytics-admin', 'enqueued') || !wp_script_is('mds-analytics-admin', 'enqueued')) {
        throw new RuntimeException('Analytics admin assets were not enqueued.');
    }
    $_POST = [];
    $_REQUEST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $analytics->admin_page();
    $admin_html = ob_get_clean();
    if (false === strpos($admin_html, 'Check tag availability') || false !== strpos($admin_html, 'Send Test Event')) {
        throw new RuntimeException('Analytics admin verification controls are misleading or incomplete.');
    }
    if (false !== strpos($admin_html, '<script') || false !== strpos($admin_html, '<style')) {
        throw new RuntimeException('Analytics admin page still embeds inline scripts or styles.');
    }

    $frontend_script = file_get_contents(MDS_GA_PLUGIN_DIR . 'assets/js/frontend.js');
    if (false === strpos($frontend_script, 'url.origin + url.pathname') || false !== strpos($frontend_script, 'event_label: url.href')) {
        throw new RuntimeException('Analytics outbound-link events may expose query-string data.');
    }
} catch (Throwable $error) {
    $failure = $error->getMessage();
} finally {
    remove_filter('pre_http_request', $http_filter, 10);
    foreach ($options_before as $option_name => $option_value) {
        if ($missing_option === $option_value) {
            delete_option($option_name);
        } else {
            update_option($option_name, $option_value, false);
        }
    }
    $_POST = $post_before;
    $_REQUEST = $request_before;
    if (null === $request_method_before) {
        unset($_SERVER['REQUEST_METHOD']);
    } else {
        $_SERVER['REQUEST_METHOD'] = $request_method_before;
    }
    wp_set_current_user(1);
}

if ($failure) {
    fwrite(STDERR, "Analytics fixture failed: " . $failure . "\n");
    exit(1);
}

echo "Success: Analytics fixture passed.\n";
