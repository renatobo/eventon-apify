<?php

test('canonical create schema identifies required domain fields', function () {
    $args = eventon_apify_get_event_write_args(true);

    ok($args['title']['required']);
    ok($args['start_date']['required']);
    eq($args['slug']['required'], false);
});

test('route-compatible create schema defers required fields until normalization', function () {
    $args = eventon_apify_get_event_write_args(false);

    eq($args['title']['required'], false);
    eq($args['start_date']['required'], false);
});

test('update REST schema does not require create-only fields', function () {
    $args = eventon_apify_get_event_write_args(false);

    eq($args['title']['required'], false);
    eq($args['start_date']['required'], false);
});

test('REST schema preserves comma-separated taxonomy compatibility', function () {
    $args = eventon_apify_get_event_write_args(false);

    eq($args['event_type']['type'], array('array', 'string'));
    eq($args['tags']['type'], array('array', 'string'));
});

test('wp v2 custom fields expose schema metadata', function () {
    $schema = eventon_apify_get_wp_v2_field_schema('status');

    eq($schema['type'], 'string');
    eq($schema['enum'], array('publish', 'draft', 'private', 'pending', 'future'));
    eq($schema['context'], array('view', 'edit'));
});

test('REST schema exports nested object and array shapes', function () {
    $args = eventon_apify_get_event_write_args(false);

    eq($args['location']['properties']['lat']['type'], 'number');
    eq($args['organizers']['items']['properties']['email']['type'], 'string');
});
