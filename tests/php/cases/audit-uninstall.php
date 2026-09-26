<?php
/**
 * R-4: uninstall cleans every site of a multisite network.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', 'eventon-apify/eventon-apify.php');
}

/**
 * Run uninstall.php and return what it deleted, per blog.
 *
 * @return array<int, array<int, string>>
 */
function eventon_test_run_uninstall() {
    $GLOBALS['__eventon_test_cleanup_log'] = array();
    include EVENTON_APIFY_PLUGIN_DIR . '/uninstall.php';

    $by_blog = array();
    foreach ($GLOBALS['__eventon_test_cleanup_log'] as $entry) {
        $by_blog[$entry[0]][] = $entry[1];
    }

    return $by_blog;
}

function eventon_test_expected_uninstall_cleanup() {
    return array(
        'option:eventon_apify_enable_api',
        'option:eventon_apify_api_capabilities',
        'option:eventon_apify_enable_wp_v2_compat',
        'option:eventon_apify_cascade_delete_rsvps',
        'option:eventon_apify_settings_backup',
        'option:eventon_apify_installed_version',
        'meta:_eventon_apify_updated_at_gmt',
    );
}

test('single-site uninstall removes every plugin option and meta key', function () {
    eq(eventon_test_run_uninstall(), array(1 => eventon_test_expected_uninstall_cleanup()));
});

test('multisite uninstall cleans every site and restores the current blog', function () {
    $GLOBALS['__eventon_test_multisite'] = true;
    $GLOBALS['__eventon_test_sites'] = array(1, 2, 5);

    $expected = eventon_test_expected_uninstall_cleanup();
    eq(eventon_test_run_uninstall(), array(1 => $expected, 2 => $expected, 5 => $expected));
    eq($GLOBALS['__eventon_test_current_blog'], 1);
});
