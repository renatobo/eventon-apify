<?php
/**
 * Characterization tests for eventon_apify_validate_event_payload
 * (rest-event-validation.php). These lock the per-field validation error codes
 * so the table-driven refactor of that function provably preserves behavior.
 *
 * Helper: run validation and return the WP_Error code, or 'OK' when valid.
 * Each per-field block short-circuits before the datetime check, so a "good"
 * value for one field simply falls through to a later (datetime) error — we
 * assert only that the field's own error code is NOT produced.
 */

function eventon_test_validate_code(array $params, $is_create = false, $post_id = 0) {
    $result = eventon_apify_validate_event_payload($params, $is_create, $post_id);
    return is_wp_error($result) ? $result->get_error_code() : 'OK';
}

test('title is required on create and cannot be blank', function () {
    eq(eventon_test_validate_code(array(), true), 'eventon_apify_missing_title');
    eq(eventon_test_validate_code(array('title' => '   ')), 'eventon_apify_invalid_title');
});

test('start_date is required on create', function () {
    eq(eventon_test_validate_code(array('title' => 'X'), true), 'eventon_apify_missing_start_date');
});

test('status must be a known post status', function () {
    eq(eventon_test_validate_code(array('status' => 'bogus')), 'eventon_apify_invalid_status');
    ok(eventon_test_validate_code(array('status' => 'publish')) !== 'eventon_apify_invalid_status');
});

test('event_color must be a hex color', function () {
    eq(eventon_test_validate_code(array('event_color' => 'notacolor')), 'eventon_apify_invalid_color');
    ok(eventon_test_validate_code(array('event_color' => '#ff0000')) !== 'eventon_apify_invalid_color');
    ok(eventon_test_validate_code(array('event_color' => 'ff0000')) !== 'eventon_apify_invalid_color');
});

test('event_status must be in the allowed set', function () {
    eq(eventon_test_validate_code(array('event_status' => 'bogus')), 'eventon_apify_invalid_event_status');
    ok(eventon_test_validate_code(array('event_status' => 'scheduled')) !== 'eventon_apify_invalid_event_status');
});

test('attendance_mode must be in the allowed set', function () {
    eq(eventon_test_validate_code(array('attendance_mode' => 'bogus')), 'eventon_apify_invalid_attendance_mode');
    ok(eventon_test_validate_code(array('attendance_mode' => 'offline')) !== 'eventon_apify_invalid_attendance_mode');
});

test('time_extend_type must be n/dl/ml/yl', function () {
    eq(eventon_test_validate_code(array('time_extend_type' => 'zz')), 'eventon_apify_invalid_time_extend_type');
    ok(eventon_test_validate_code(array('time_extend_type' => 'dl')) !== 'eventon_apify_invalid_time_extend_type');
});

test('timezone_key must be a valid identifier when non-empty', function () {
    eq(eventon_test_validate_code(array('timezone_key' => 'Not/AZone')), 'eventon_apify_invalid_timezone');
    ok(eventon_test_validate_code(array('timezone_key' => 'America/Los_Angeles')) !== 'eventon_apify_invalid_timezone');
    ok(eventon_test_validate_code(array('timezone_key' => '')) !== 'eventon_apify_invalid_timezone');
});

test('gradient_angle must be numeric when non-empty', function () {
    eq(eventon_test_validate_code(array('gradient_angle' => 'abc')), 'eventon_apify_invalid_gradient_angle');
    ok(eventon_test_validate_code(array('gradient_angle' => '45')) !== 'eventon_apify_invalid_gradient_angle');
});

test('location coordinates must be numeric when non-empty', function () {
    eq(eventon_test_validate_code(array('location_lat' => 'x')), 'eventon_apify_invalid_location_coordinate');
    eq(eventon_test_validate_code(array('location_lon' => 'y')), 'eventon_apify_invalid_location_coordinate');
    ok(eventon_test_validate_code(array('location_lat' => '34.2')) !== 'eventon_apify_invalid_location_coordinate');
});

test('known URL fields must be valid absolute URLs', function () {
    eq(eventon_test_validate_code(array('location_link' => 'not a url')), 'eventon_apify_invalid_url');
    ok(eventon_test_validate_code(array('location_link' => 'https://example.com')) !== 'eventon_apify_invalid_url');
});

test('interaction_url must be a valid URL', function () {
    eq(eventon_test_validate_code(array('interaction_url' => 'nope')), 'eventon_apify_invalid_interaction_url');
    ok(eventon_test_validate_code(array('interaction_url' => 'https://ex.com')) !== 'eventon_apify_invalid_interaction_url');
});

test('interaction_mode normalizes unknown values, so it never errors', function () {
    // normalize_interaction_mode() maps any unknown value to slide_down_eventcard,
    // which is always in the allowed set, so this branch is effectively unreachable.
    ok(eventon_test_validate_code(array('interaction_mode' => 'bogus_mode_xyz')) !== 'eventon_apify_invalid_interaction_mode');
});

test('organizer link must be a valid URL', function () {
    eq(
        eventon_test_validate_code(array('organizers' => array(array('link' => 'bad url')))),
        'eventon_apify_invalid_organizer_url'
    );
    ok(
        eventon_test_validate_code(array('organizers' => array(array('link' => 'https://ex.com')))) !== 'eventon_apify_invalid_organizer_url'
    );
});

test('repeat_frequency must be in the allowed set when non-empty', function () {
    eq(eventon_test_validate_code(array('repeat_frequency' => 'bogus')), 'eventon_apify_invalid_repeat_frequency');
});
