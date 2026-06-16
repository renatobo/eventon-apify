<?php
/**
 * Plugin Name:       EventON APIfy
 * Plugin URI:        https://github.com/renatobo/eventon-apify
 * Description:       Protected REST API endpoints for EventON events with pagination, CRUD operations, and administrator-only access.
 * Version:           2.2.1
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

define('EVENTON_APIFY_VERSION', '2.2.1');
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

require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/bootstrap.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/capabilities.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/settings-backup.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/wp-v2-compat.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/admin.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-field-definitions.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-field-metadata.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-contract-builder.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-validation.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-examples.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-availability.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-rsvp.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/mcp-manifest.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-helpers.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-access-control.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-request-validation.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-wp-v2-compat.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-events-read.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-events-list.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-events-write.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-event-payload.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-event-validation.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-event-meta.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-event-terms.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-rsvp.php';
require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/rest-routes.php';

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    add_action('admin_notices', 'eventon_apify_php_version_notice');
    return;
}

add_action('admin_menu', 'eventon_apify_add_settings_page');
add_action('admin_enqueue_scripts', 'eventon_apify_enqueue_settings_assets');
add_action('admin_init', 'eventon_apify_register_settings');
add_action('rest_api_init', 'eventon_apify_register_routes');
add_action('rest_api_init', 'eventon_apify_register_wp_v2_compatibility_fields');
add_action('plugins_loaded', 'eventon_apify_load_textdomain');
add_action('plugins_loaded', 'eventon_apify_bootstrap_settings');
add_action('save_post_evo-rsvp', 'eventon_apify_touch_rsvp_post_on_save', 10, 2);
add_action('added_post_meta', 'eventon_apify_touch_rsvp_post_on_meta_change', 10, 3);
add_action('updated_post_meta', 'eventon_apify_touch_rsvp_post_on_meta_change', 10, 3);
add_action('deleted_post_meta', 'eventon_apify_touch_rsvp_post_on_meta_change', 10, 3);
add_filter('register_post_type_args', 'eventon_apify_filter_post_type_args_for_wp_v2_compat', 10, 2);
add_filter('register_taxonomy_args', 'eventon_apify_filter_taxonomy_args_for_wp_v2_compat', 10, 2);
add_filter('rest_pre_dispatch', 'eventon_apify_restrict_wp_v2_compatibility_routes', 10, 3);
add_filter('rest_post_dispatch', 'eventon_apify_filter_wp_v2_compatibility_responses', 10, 3);
add_filter('rest_endpoints', 'eventon_apify_filter_wp_v2_compatibility_endpoints');
add_filter('rest_post_search_query', 'eventon_apify_filter_wp_v2_compatibility_post_search_query', 10, 1);
add_filter('rest_term_search_query', 'eventon_apify_filter_wp_v2_compatibility_term_search_query', 10, 1);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'eventon_apify_add_plugin_action_links');
add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), 'eventon_apify_add_plugin_action_links');
add_action('updated_option', 'eventon_apify_sync_settings_backup_on_option_change', 10, 3);
add_action('added_option', 'eventon_apify_sync_settings_backup_on_option_add', 10, 2);
register_activation_hook(__FILE__, 'eventon_apify_activate');
