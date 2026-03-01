<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('eventon_apify_enable_api');
delete_option('eventon_apify_api_capabilities');
delete_option('eventon_apify_enable_wp_v2_compat');
