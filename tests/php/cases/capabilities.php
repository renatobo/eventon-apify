<?php
/**
 * Tests for the capability map (capabilities.php). Locks in the Tier-1 change
 * that derives defaults from the capability definitions (single source of truth).
 */

test('default capabilities are derived from the definitions', function () {
    $defaults = eventon_apify_get_default_api_capabilities();
    $definitions = eventon_apify_get_api_capability_definitions();

    eq(array_keys($defaults), array_keys($definitions));
    eq($defaults['list'], true);
    eq($defaults['read'], true);
    eq($defaults['create'], true);
    eq($defaults['update'], true);
    eq($defaults['delete'], true);
    eq($defaults['rsvp_counts'], false);
    eq($defaults['rsvp_attendees'], false);
});

test('sanitize_capabilities coerces every known key to a boolean', function () {
    $sanitized = eventon_apify_sanitize_capabilities(array('list' => '1', 'create' => 0, 'unknown' => true));

    eq($sanitized['list'], true);
    eq($sanitized['create'], false);
    ok(!array_key_exists('unknown', $sanitized), 'unknown keys are dropped');
    eq(array_keys($sanitized), array_keys(eventon_apify_get_default_api_capabilities()));
});
