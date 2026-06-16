<?php
/**
 * Tests for the events slug filter sanitizer (rest-request-validation.php).
 * Locks in the Tier-1 hardening: array form preserved, values slugified,
 * and the list capped at EVENTON_APIFY_MAX_SLUG_FILTER.
 */

test('slug filter sanitizes a comma-separated string', function () {
    eq(
        eventon_apify_sanitize_slug_filter('Ride To Big Bear, Bike Night'),
        array('ride-to-big-bear', 'bike-night')
    );
});

test('slug filter preserves and sanitizes the array form', function () {
    eq(
        eventon_apify_sanitize_slug_filter(array('Bike Night', 'ducati', '')),
        array('bike-night', 'ducati')
    );
});

test('slug filter returns an empty list for empty input', function () {
    eq(eventon_apify_sanitize_slug_filter(''), array());
    eq(eventon_apify_sanitize_slug_filter(array()), array());
});

test('slug filter caps the number of slugs', function () {
    $input = array();
    for ($i = 0; $i < 150; $i++) {
        $input[] = 'slug-' . $i;
    }
    $result = eventon_apify_sanitize_slug_filter($input);
    eq(count($result), EVENTON_APIFY_MAX_SLUG_FILTER);
    eq($result[0], 'slug-0');
});
