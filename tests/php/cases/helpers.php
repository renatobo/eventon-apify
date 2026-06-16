<?php
/**
 * Tests for the low-level helpers in rest-helpers.php.
 */

test('array_get returns first present key, else default', function () {
    eq(eventon_apify_array_get(array('b' => 2, 'a' => 1), array('a', 'b')), 1);
    eq(eventon_apify_array_get(array('b' => 2), array('a', 'b')), 2);
    eq(eventon_apify_array_get(array(), array('a'), 'fallback'), 'fallback');
});

test('array_has_any detects any present key', function () {
    ok(eventon_apify_array_has_any(array('x' => 1), array('a', 'x')));
    ok(!eventon_apify_array_has_any(array('x' => 1), array('a', 'b')));
});

test('is_yes interprets EventON yes/no inputs', function () {
    ok(eventon_apify_is_yes(true));
    ok(eventon_apify_is_yes('yes'));
    ok(eventon_apify_is_yes('YES'));
    ok(eventon_apify_is_yes('1'));
    ok(eventon_apify_is_yes(1));
    ok(!eventon_apify_is_yes('no'));
    ok(!eventon_apify_is_yes(0));
    ok(!eventon_apify_is_yes(''));
});

test('to_yes_no maps to yes/no strings', function () {
    eq(eventon_apify_to_yes_no('on'), 'yes');
    eq(eventon_apify_to_yes_no('off'), 'no');
});

test('split_time_string parses and rejects', function () {
    eq(eventon_apify_split_time_string('09:30'), array('hour' => '9', 'minute' => '30'));
    eq(eventon_apify_split_time_string('23:05'), array('hour' => '23', 'minute' => '05'));
    eq(eventon_apify_split_time_string('24:00'), null);
    eq(eventon_apify_split_time_string('9:5'), null);
    eq(eventon_apify_split_time_string(''), null);
});

test('build_timestamp is deterministic for an explicit timezone', function () {
    $expected = (new DateTimeImmutable('2026-04-01 09:00', new DateTimeZone('UTC')))->getTimestamp();
    eq(eventon_apify_build_timestamp('2026-04-01', '09:00', 'UTC'), $expected);
    eq(eventon_apify_build_timestamp('', '09:00', 'UTC'), null);
});

test('interaction mode mapping round-trips', function () {
    eq(eventon_apify_map_interaction_code_to_mode('2'), 'external_link');
    eq(eventon_apify_map_interaction_mode_to_code('external_link'), '2');
    eq(eventon_apify_normalize_interaction_mode('garbage'), 'slide_down_eventcard');
    eq(eventon_apify_map_interaction_mode_to_code('garbage'), '1');
});
