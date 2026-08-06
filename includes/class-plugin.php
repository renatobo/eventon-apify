<?php

namespace EventON_APIfy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates module loading and WordPress hook registration.
 *
 * Public procedural callbacks remain available for backward compatibility,
 * while this class provides one explicit composition root for the plugin.
 */
final class Plugin {
    /** @var bool */
    private static $booted = false;

    /**
     * Load plugin modules and register runtime hooks once.
     */
    public static function boot() {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/bootstrap.php';
            add_action('admin_notices', 'eventon_apify_php_version_notice');
            return;
        }

        self::load_modules();
        self::register_hooks();
    }

    /**
     * Load modules in dependency order.
     */
    private static function load_modules() {
        $modules = array(
            'bootstrap.php',
            'capabilities.php',
            'settings-backup.php',
            'wp-v2-compat.php',
            'mcp-field-definitions.php',
            'mcp-field-metadata.php',
            'mcp-contract-builder.php',
            'mcp-validation.php',
            'mcp-examples.php',
            'mcp-availability.php',
            'mcp-rsvp.php',
            'mcp-manifest.php',
            'rest-helpers.php',
            'rest-access-control.php',
            'rest-request-validation.php',
            'rest-schema.php',
            'rest-wp-v2-compat.php',
            'rest-events-read.php',
            'rest-events-list.php',
            'rest-event-payload.php',
            'rest-event-validation.php',
            'rest-event-meta.php',
            'eventon-taxonomy-meta-store.php',
            'rest-event-terms.php',
            'event-write-coordinator.php',
            'rest-events-write.php',
            'class-rsvp-attendee-formatter.php',
            'class-rsvp-attendee-repository.php',
            'rest-rsvp.php',
            'rest-routes.php',
        );

        if (is_admin()) {
            $modules[] = 'admin.php';
        }

        foreach ($modules as $module) {
            require_once EVENTON_APIFY_PLUGIN_DIR . '/includes/' . $module;
        }
    }

    /**
     * Register all plugin integration points.
     */
    private static function register_hooks() {
        if (is_admin()) {
            add_action('admin_menu', 'eventon_apify_add_settings_page');
            add_action('admin_enqueue_scripts', 'eventon_apify_enqueue_settings_assets');
            add_action('admin_init', 'eventon_apify_register_settings');
        }

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
        add_filter('plugin_action_links_' . plugin_basename(EVENTON_APIFY_PLUGIN_FILE), 'eventon_apify_add_plugin_action_links');
        add_filter('network_admin_plugin_action_links_' . plugin_basename(EVENTON_APIFY_PLUGIN_FILE), 'eventon_apify_add_plugin_action_links');
        add_action('updated_option', 'eventon_apify_sync_settings_backup_on_option_change', 10, 3);
        add_action('added_option', 'eventon_apify_sync_settings_backup_on_option_add', 10, 2);
    }
}
