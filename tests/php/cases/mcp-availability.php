<?php
/**
 * Tests for the MCP availability visibility gate (mcp-availability.php).
 * Locks in the security fix: admin-only flags are hidden from anonymous callers.
 */

test('build_mcp_availability hides admin-only flags from non-admins', function () {
    eventon_test_set_current_user_can(false);
    $result = eventon_apify_build_mcp_availability(
        array('public_flag' => true),
        array('secret_flag' => true)
    );
    eq($result, array('public_flag' => true));
});

test('build_mcp_availability exposes admin-only flags to administrators', function () {
    eventon_test_set_current_user_can(true);
    $result = eventon_apify_build_mcp_availability(
        array('public_flag' => true),
        array('secret_flag' => true)
    );
    eq($result, array('public_flag' => true, 'secret_flag' => true));
});

test('availability state omits the capability matrix for anonymous callers', function () {
    eventon_test_set_current_user_can(false);
    $state = eventon_apify_get_mcp_availability_state();
    ok(!array_key_exists('custom_event_api_capabilities', $state), 'capability matrix must be admin-only');
    ok(array_key_exists('eventon_available', $state), 'coarse flags stay public');
});

test('availability state includes the capability matrix for administrators', function () {
    eventon_test_set_current_user_can(true);
    $state = eventon_apify_get_mcp_availability_state();
    ok(array_key_exists('custom_event_api_capabilities', $state));
});
