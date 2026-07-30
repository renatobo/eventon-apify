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

    eq($args['location']['properties']['lat']['type'], 'string');
    eq($args['location']['properties']['lon']['type'], 'string');
    eq($args['organizers']['items']['properties']['email']['type'], 'string');
});

test('coercible fields accept both object and string forms', function () {
    $args = eventon_apify_get_event_write_args(false);

    eq($args['location']['type'], array('object', 'string'));
    eq($args['timezone']['type'], array('object', 'string'));
    eq($args['organizers']['type'], 'array');
    eq($args['organizers']['items']['type'], array('object', 'string'));
});

test('wp v2 field schemas accept both object and string forms for coercible fields', function () {
    eq(eventon_apify_get_wp_v2_field_schema('location')['type'], array('object', 'string'));
    eq(eventon_apify_get_wp_v2_field_schema('timezone')['type'], array('object', 'string'));
    eq(eventon_apify_get_wp_v2_field_schema('organizers')['items']['type'], array('object', 'string'));
});

test('fields and custom_fields wrappers are declared write args', function () {
    $args = eventon_apify_get_event_write_args(false);

    eq($args['fields']['type'], 'object');
    eq($args['fields']['required'], false);
    eq($args['custom_fields']['type'], 'object');
    eq($args['custom_fields']['required'], false);

    $create_args = eventon_apify_get_event_write_args(true);
    eq($create_args['fields']['required'], false);
    eq($create_args['custom_fields']['required'], false);
});
