<?php
/**
 * Plugin Name:       EventON APIfy
 * Plugin URI:        https://github.com/renatobo/eventon-apify
 * Description:       Protected REST API endpoints for EventON events with pagination, CRUD operations, and administrator-only access.
 * Version:           3.3.1
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Author:            Renato Bonomini
 * Author URI:        https://github.com/renatobo
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eventon-apify
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: https://github.com/renatobo/eventon-apify
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package EventON_APIfy
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EVENTON_APIFY_VERSION', '3.3.1');
define('EVENTON_APIFY_NAMESPACE', 'eventonapify/v1');
define('EVENTON_APIFY_OPTION_ENABLE_API', 'eventon_apify_enable_api');
define('EVENTON_APIFY_OPTION_API_CAPABILITIES', 'eventon_apify_api_capabilities');
define('EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT', 'eventon_apify_enable_wp_v2_compat');
define('EVENTON_APIFY_OPTION_SETTINGS_BACKUP', 'eventon_apify_settings_backup');
define('EVENTON_APIFY_OPTION_INSTALLED_VERSION', 'eventon_apify_installed_version');
define('EVENTON_APIFY_RSVP_UPDATED_AT_META', '_eventon_apify_updated_at_gmt');
define('EVENTON_APIFY_MAX_SLUG_FILTER', 100);
define('EVENTON_APIFY_PLUGIN_FILE', __FILE__);
define('EVENTON_APIFY_PLUGIN_DIR', __DIR__);

require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/class-plugin.php';

/**
 * EventON is a hard dependency: every endpoint reads its `ajde_events` CPT.
 *
 * The routes already fail closed on `eventon_apify_is_eventon_available()`, but
 * they fail closed with a 500 that only an API client ever sees. This is the
 * same condition said out loud, on every admin screen rather than only on the
 * settings page, so a missing EventON is diagnosable from the dashboard.
 *
 * A runtime check rather than a `Requires Plugins` header: EventON installs
 * into a directory named `eventON`, and core drops any dependency slug that is
 * not lowercase-and-hyphens, so it can never be declared there. It also catches
 * EventON being deactivated after this plugin was already running, which the
 * header would not.
 */
function eventon_apify_dependency_notice(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Deliberately the shared helper, which tests pin: a second definition of
    // "EventON is here" would drift from what the endpoints actually check.
    // function_exists because boot() skips every module on PHP < 8.0 and shows
    // its own notice instead; this one has nothing to report in that state.
    if (!function_exists('eventon_apify_is_eventon_available') || eventon_apify_is_eventon_available()) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html__('EventON APIfy needs EventON to be active. Every endpoint returns an error until it is.', 'eventon-apify');
    echo '</p></div>';
}
add_action('admin_notices', 'eventon_apify_dependency_notice');

\EventON_APIfy\Plugin::boot();

register_activation_hook(__FILE__, 'eventon_apify_activate');
