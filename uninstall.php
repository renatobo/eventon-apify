<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Options are per site, so a network uninstall has to visit every site.
// The plugin constants are not loaded during uninstall, so use literal keys.
$eventon_apify_site_ids = is_multisite() ? get_sites(array('fields' => 'ids', 'number' => 0)) : array(0);

foreach ($eventon_apify_site_ids as $eventon_apify_site_id) {
    if ($eventon_apify_site_id) {
        switch_to_blog((int) $eventon_apify_site_id);
    }

    delete_option('eventon_apify_enable_api');
    delete_option('eventon_apify_api_capabilities');
    delete_option('eventon_apify_enable_wp_v2_compat');
    delete_option('eventon_apify_cascade_delete_rsvps');
    delete_option('eventon_apify_settings_backup');
    delete_option('eventon_apify_installed_version');

    // Remove the RSVP delta-sync timestamp meta written to RSVP posts.
    delete_post_meta_by_key('_eventon_apify_updated_at_gmt');

    if ($eventon_apify_site_id) {
        restore_current_blog();
    }
}
