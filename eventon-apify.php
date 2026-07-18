<?php
/**
 * Plugin Name:       EventON APIfy
 * Plugin URI:        https://github.com/renatobo/eventon-apify
 * Description:       Protected REST API endpoints for EventON events with pagination, CRUD operations, and administrator-only access.
 * Version:           3.0.0
 * Requires at least: 6.0
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

define('EVENTON_APIFY_VERSION', '3.0.0');
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

\EventON_APIfy\Plugin::boot();

register_activation_hook(__FILE__, 'eventon_apify_activate');
