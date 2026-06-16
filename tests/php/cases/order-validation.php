<?php
/**
 * Tests for the events order/orderby validation callbacks
 * (rest-request-validation.php).
 */

test('order accepts asc/desc case-insensitively', function () {
    ok(eventon_apify_validate_events_order('asc') === true);
    ok(eventon_apify_validate_events_order('DESC') === true);
});

test('order rejects unknown directions with a WP_Error', function () {
    ok(is_wp_error(eventon_apify_validate_events_order('sideways')));
    ok(is_wp_error(eventon_apify_validate_events_order(array('asc'))));
});

test('orderby accepts the allowed fields', function () {
    foreach (array('start_at', 'created', 'modified', 'title') as $field) {
        ok(eventon_apify_validate_events_orderby($field) === true, $field);
    }
});

test('orderby rejects unknown fields with a WP_Error', function () {
    ok(is_wp_error(eventon_apify_validate_events_orderby('random_column')));
});
