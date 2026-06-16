<?php
/**
 * Tests for the alias-map merge helper extracted from normalize_request_payload
 * (rest-event-payload.php).
 */

test('alias map copies the first present alias', function () {
    $result = eventon_apify_apply_alias_map(
        array(),
        array('subtitle' => 'Hi'),
        array('event_subtitle' => array('event_subtitle', 'subtitle'))
    );
    eq($result, array('event_subtitle' => 'Hi'));
});

test('alias map prefers earlier aliases', function () {
    $result = eventon_apify_apply_alias_map(
        array(),
        array('event_subtitle' => 'A', 'subtitle' => 'B'),
        array('event_subtitle' => array('event_subtitle', 'subtitle'))
    );
    eq($result['event_subtitle'], 'A');
});

test('alias map never overwrites an existing normalized value', function () {
    $result = eventon_apify_apply_alias_map(
        array('event_subtitle' => 'explicit'),
        array('subtitle' => 'from-source'),
        array('event_subtitle' => array('subtitle'))
    );
    eq($result['event_subtitle'], 'explicit');
});

test('alias map skips targets with no matching alias', function () {
    $result = eventon_apify_apply_alias_map(
        array(),
        array('unrelated' => 'x'),
        array('event_color' => array('color', 'event_color'))
    );
    eq($result, array());
});
