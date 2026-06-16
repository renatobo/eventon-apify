<?php
/**
 * Test bootstrap: define plugin constants, load WordPress doubles and the test
 * harness, then load every plugin include so its functions are callable.
 */

define('ABSPATH', __DIR__ . '/');
define('EVENTON_APIFY_VERSION', '0.0.0-test');
define('EVENTON_APIFY_NAMESPACE', 'eventonapify/v1');
define('EVENTON_APIFY_OPTION_ENABLE_API', 'eventon_apify_enable_api');
define('EVENTON_APIFY_OPTION_API_CAPABILITIES', 'eventon_apify_api_capabilities');
define('EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT', 'eventon_apify_enable_wp_v2_compat');
define('EVENTON_APIFY_OPTION_SETTINGS_BACKUP', 'eventon_apify_settings_backup');
define('EVENTON_APIFY_OPTION_INSTALLED_VERSION', 'eventon_apify_installed_version');
define('EVENTON_APIFY_RSVP_UPDATED_AT_META', '_eventon_apify_updated_at_gmt');
define('EVENTON_APIFY_MAX_SLUG_FILTER', 100);
define('EVENTON_APIFY_PLUGIN_FILE', dirname(__DIR__, 2) . '/eventon-apify.php');
define('EVENTON_APIFY_PLUGIN_DIR', dirname(__DIR__, 2));

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/harness.php';

foreach (glob(EVENTON_APIFY_PLUGIN_DIR . '/includes/*.php') as $include) {
    require_once $include;
}
