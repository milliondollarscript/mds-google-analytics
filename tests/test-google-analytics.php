<?php
/**
 * PHPUnit coverage for the Analytics extension.
 */

class MDS_Google_Analytics_Tests extends WP_UnitTestCase {
    private $extension;
    private $option_names = [
        'mds_ga_enabled',
        'mds_ga_tracking_id',
        'mds_ga_exclude_admins',
        'mds_ga_respect_consent_api',
        'mds_ga_disable_advertising',
        'mds_ga_generic_enabled',
        'mds_ga_generic_consent_category',
        'mds_ga_generic_head',
        'mds_ga_generic_body',
        'mds_ga_generic_footer',
    ];

    public function setUp(): void {
        parent::setUp();
        $this->extension = new MDS_Google_Analytics();
        foreach ($this->option_names as $option_name) {
            delete_option($option_name);
        }
    }

    private function callPrivate($method, array $arguments = []) {
        $reflection = new ReflectionMethod($this->extension, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->extension, $arguments);
    }

    public function test_extension_metadata_and_hooks() {
        $this->assertSame('1.1.1', MDS_GA_VERSION);
        $this->assertNotFalse(has_action('init', [$this->extension, 'init']));
        $this->assertNotFalse(has_action('admin_menu', [$this->extension, 'add_admin_menu']));
        $this->assertNotFalse(has_action('wp_enqueue_scripts', [$this->extension, 'enqueue_tracking_assets']));
        $this->assertNotFalse(has_action('wp_head', [$this->extension, 'output_generic_head_code']));
        $this->assertNotFalse(has_action('wp_body_open', [$this->extension, 'output_generic_body_open_code']));
        $this->assertNotFalse(has_action('wp_footer', [$this->extension, 'output_generic_footer_code']));
    }

    public function test_measurement_id_validation() {
        $this->assertSame('G-TEST12345', $this->callPrivate('normalize_tracking_id', [' g-test12345 ']));
        $this->assertSame('', $this->callPrivate('normalize_tracking_id', ['UA-12345-1']));
        $this->assertFalse($this->callPrivate('is_tracking_enabled'));

        update_option('mds_ga_enabled', '1');
        update_option('mds_ga_tracking_id', 'G-TEST12345');
        $this->assertTrue($this->callPrivate('is_tracking_enabled'));
    }

    public function test_frontend_assets_queue_one_config_page_view() {
        update_option('mds_ga_enabled', '1');
        update_option('mds_ga_tracking_id', 'G-TEST12345');
        update_option('mds_ga_exclude_admins', '0');
        update_option('mds_ga_disable_advertising', '1');
        wp_set_current_user(0);

        $this->extension->enqueue_tracking_assets();
        $this->assertTrue(wp_script_is('mds-analytics-google-tag', 'enqueued'));
        $this->assertTrue(wp_script_is('mds-analytics-events', 'enqueued'));

        $scripts = wp_scripts();
        $before = implode("\n", (array) ($scripts->registered['mds-analytics-google-tag']->extra['before'] ?? []));
        $this->assertSame(1, substr_count($before, 'gtag("config"'));
        $this->assertStringNotContainsString('page_view', $before);
        $this->assertStringContainsString('allow_google_signals', $before);
    }

    public function test_statistics_consent_filter_blocks_ga_and_snippets() {
        update_option('mds_ga_enabled', '1');
        update_option('mds_ga_tracking_id', 'G-TEST12345');
        update_option('mds_ga_exclude_admins', '0');
        update_option('mds_ga_generic_enabled', '1');
        update_option('mds_ga_generic_consent_category', 'statistics');
        update_option('mds_ga_generic_head', '<script data-test="analytics"></script>');
        wp_set_current_user(0);

        $deny = static function ($should_track, $category) {
            return 'statistics' === $category ? false : $should_track;
        };
        add_filter('mds_analytics_should_track_current_user', $deny, 10, 2);
        $this->extension->enqueue_tracking_assets();
        ob_start();
        $this->extension->output_generic_head_code();
        $output = ob_get_clean();
        remove_filter('mds_analytics_should_track_current_user', $deny, 10);

        $this->assertFalse(wp_script_is('mds-analytics-google-tag', 'enqueued'));
        $this->assertSame('', trim($output));
    }

    public function test_always_load_snippet_still_excludes_administrators() {
        update_option('mds_ga_generic_enabled', '1');
        update_option('mds_ga_generic_consent_category', 'always');
        update_option('mds_ga_generic_head', '<script data-test="always"></script>');
        update_option('mds_ga_exclude_admins', '0');

        wp_set_current_user(0);
        ob_start();
        $this->extension->output_generic_head_code();
        $guest_output = ob_get_clean();
        $this->assertStringContainsString('data-test="always"', $guest_output);

        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        update_option('mds_ga_exclude_admins', '1');
        ob_start();
        $this->extension->output_generic_head_code();
        $admin_output = ob_get_clean();
        $this->assertSame('', trim($admin_output));
    }

    public function test_tag_availability_uses_fixed_google_endpoint() {
        update_option('mds_ga_enabled', '1');
        update_option('mds_ga_tracking_id', 'G-TEST12345');
        $request = [];
        $filter = static function ($preempt, $args, $url) use (&$request) {
            $request = compact('args', 'url');
            return [
                'headers' => [],
                'body' => 'fixture',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies' => [],
                'filename' => null,
            ];
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $result = $this->extension->check_tag_availability();
        remove_filter('pre_http_request', $filter, 10);

        $this->assertIsArray($result);
        $this->assertStringStartsWith('https://www.googletagmanager.com/gtag/js', $request['url']);
        $this->assertSame(5, $request['args']['timeout']);
    }

    public function test_settings_save_normalizes_consent_and_snippets() {
        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $_POST = [
            'mds_ga_nonce' => wp_create_nonce('mds_ga_settings'),
            'mds_ga_enabled' => '1',
            'mds_ga_tracking_id' => 'G-SAVE12345',
            'mds_ga_respect_consent_api' => '1',
            'mds_ga_generic_enabled' => '1',
            'mds_ga_generic_consent_category' => 'marketing',
            'mds_ga_generic_head' => '<script data-test="save"></script><?php echo 1; ?>',
        ];
        $_REQUEST = $_POST;

        ob_start();
        $this->callPrivate('save_settings');
        $output = ob_get_clean();

        $this->assertStringContainsString('Settings saved successfully', $output);
        $this->assertSame('G-SAVE12345', get_option('mds_ga_tracking_id'));
        $this->assertSame('marketing', get_option('mds_ga_generic_consent_category'));
        $this->assertStringNotContainsString('<?', get_option('mds_ga_generic_head'));
        $this->assertStringNotContainsString('echo 1', get_option('mds_ga_generic_head'));
    }

    public function test_ajax_handler_rejects_missing_nonce() {
        $_REQUEST = [];
        try {
            $this->extension->ajax_test_tracking();
            $this->fail('Expected the AJAX nonce check to stop the request.');
        } catch (WPAjaxDieStopException $error) {
            $this->assertTrue(true);
        }
    }

    public function tearDown(): void {
        foreach ($this->option_names as $option_name) {
            delete_option($option_name);
        }
        wp_dequeue_script('mds-analytics-google-tag');
        wp_dequeue_script('mds-analytics-events');
        wp_dequeue_script('mds-analytics-admin');
        $_POST = [];
        $_REQUEST = [];
        wp_set_current_user(0);
        parent::tearDown();
    }
}
