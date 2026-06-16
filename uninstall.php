<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('eventon_apify_enable_api');
delete_option('eventon_apify_api_capabilities');
delete_option('eventon_apify_enable_wp_v2_compat');
delete_option('eventon_apify_settings_backup');
delete_option('eventon_apify_installed_version');

// Remove the RSVP delta-sync timestamp meta written to RSVP posts.
// The plugin constant is not loaded during uninstall, so use the literal key.
delete_post_meta_by_key('_eventon_apify_updated_at_gmt');
