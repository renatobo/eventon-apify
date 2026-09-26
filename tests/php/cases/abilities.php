<?php
/**
 * WordPress abilities: a read-only adapter over eventonapify/v1.
 *
 * Core's schema validation and permission flow need real WordPress; the
 * integration smoke covers them. These cases pin registration, the mapping to
 * and from the REST route, and the discovery guards.
 */

function eventon_test_registered_abilities() {
    eventon_apify_register_ability_category();
    eventon_apify_register_abilities();

    return $GLOBALS['__eventon_test_abilities'];
}

test('three read-only public abilities are registered in the plugin category', function () {
    $abilities = eventon_test_registered_abilities();

    eq(array_keys($abilities), array('eventon-apify/get-status', 'eventon-apify/search-events', 'eventon-apify/get-event'));
    ok(isset($GLOBALS['__eventon_test_ability_categories']['eventon-apify']));

    foreach ($abilities as $name => $args) {
        eq($args['category'], 'eventon-apify', $name);
        eq($args['permission_callback'], 'eventon_apify_admin_only', $name . ' uses the REST permission check');
        eq($args['meta']['public'], true, $name);
        eq($args['meta']['annotations'], array('readonly' => true, 'destructive' => false, 'idempotent' => true), $name);
    }
});

test('ability input schemas are closed and bounded', function () {
    $abilities = eventon_test_registered_abilities();

    $search = $abilities['eventon-apify/search-events']['input_schema'];
    eq($search['additionalProperties'], false);
    eq($search['properties']['per_page']['maximum'], 100);
    eq($search['properties']['search']['maxLength'], 200);
    eq($search['properties']['status']['items']['enum'], eventon_apify_get_allowed_post_statuses());

    $get = $abilities['eventon-apify/get-event']['input_schema'];
    eq($get['additionalProperties'], false);
    eq($get['properties']['id'], array('type' => 'integer', 'minimum' => 1, 'description' => 'Event post ID.', 'required' => true));
});

test('search dispatches the events route with only known parameters', function () {
    $GLOBALS['__eventon_test_rest_responder'] = static function () {
        return array('total' => 0, 'pages' => 0, 'page' => 2, 'per_page' => 5, 'events' => array());
    };

    eventon_apify_execute_search_events_ability(array(
        'search' => 'ride',
        'page' => 2,
        'per_page' => 5,
        'status' => array('publish', 'draft'),
        'order' => 'desc',
        'smuggled' => 'x',
    ));

    $request = $GLOBALS['__eventon_test_rest_requests'][0];
    eq($request->get_method(), 'GET');
    eq($request->get_route(), '/eventonapify/v1/events');
    eq($request->get_query_params(), array('search' => 'ride', 'page' => 2, 'per_page' => 5, 'order' => 'desc', 'status' => 'publish,draft'));
});

test('search returns summaries without contact details or secrets', function () {
    $GLOBALS['__eventon_test_rest_responder'] = static function () {
        return array(
            'total' => 1, 'pages' => 1, 'page' => 1, 'per_page' => 20, 'truncated' => true,
            'events' => array(array(
                'id' => 7, 'title' => 'Ride', 'slug' => 'ride', 'status' => 'publish', 'link' => 'https://example.test/ride',
                'start_at' => '2030-01-01T09:00:00+00:00', 'end_at' => '2030-01-01T11:00:00+00:00', 'timezone' => array('key' => 'UTC', 'text' => 'Coordinated Universal Time'),
                'event_status' => 'scheduled', 'attendance_mode' => 'offline',
                'location' => array('name' => 'Big Bear', 'email' => 'venue@example.test'),
                'organizers' => array(array('name' => 'Org', 'email' => 'org@example.test')),
                'virtual' => array('url' => 'https://zoom.example/secret'),
                'event_type' => array('Rides', 3),
            )),
        );
    };

    $result = eventon_apify_execute_search_events_ability(array());

    eq($result['truncated'], true);
    eq($result['events'], array(array(
        'id' => 7, 'title' => 'Ride', 'slug' => 'ride', 'status' => 'publish', 'link' => 'https://example.test/ride',
        'start_at' => '2030-01-01T09:00:00+00:00', 'end_at' => '2030-01-01T11:00:00+00:00', 'timezone' => 'UTC',
        'event_status' => 'scheduled', 'attendance_mode' => 'offline', 'location_name' => 'Big Bear',
        'event_type' => array('Rides', '3'),
    )));
    ok(strpos(json_encode($result), 'example.test/secret') === false && strpos(json_encode($result), '@example.test') === false);
});

test('route errors reach the caller with their code and status', function () {
    $GLOBALS['__eventon_test_rest_responder'] = static function () {
        return new WP_Error('eventon_apify_capability_disabled', 'List events is disabled.', array('status' => 403));
    };

    $error = eventon_apify_execute_search_events_ability(array());

    ok(is_wp_error($error));
    eq($error->get_error_code(), 'eventon_apify_capability_disabled');
    eq($error->get_error_data(), array('status' => 403));
});

test('get-event reads the single-event route and redacts contacts and secrets', function () {
    $GLOBALS['__eventon_test_rest_responder'] = static function () {
        return array(
            'id' => 9, 'title' => 'Ride',
            'location' => array('name' => 'Big Bear', 'email' => 'venue@example.test', 'phone' => '555'),
            'organizers' => array(array('name' => 'Org', 'email' => 'org@example.test', 'phone' => '555', 'address' => '1 St')),
            'virtual' => array('type' => 'zoom', 'url' => 'https://zoom.example/secret', 'password' => 'pw'),
            'rsvp' => array('enabled' => true, 'additional_emails' => 'ops@example.test'),
        );
    };

    $event = eventon_apify_execute_get_event_ability(array('id' => 9));

    eq($GLOBALS['__eventon_test_rest_requests'][0]->get_route(), '/eventonapify/v1/events/9');
    eq($event['location'], array('name' => 'Big Bear'));
    eq($event['organizers'], array(array('name' => 'Org')));
    eq($event['virtual'], array('type' => 'zoom'));
    eq($event['rsvp'], array('enabled' => true));
});

test('status reports availability and toggles without event data', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, false);
    update_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, array('list' => true, 'create' => false));

    $status = eventon_apify_execute_status_ability();

    eq($status['plugin_version'], EVENTON_APIFY_VERSION);
    eq($status['eventon_available'], true);
    eq($status['custom_event_api_enabled'], false);
    eq($status['custom_event_api_capabilities']['list'], true);
    eq($status['custom_event_api_capabilities']['create'], false);
    eq(array_keys($status['custom_event_api_capabilities']), array_keys(eventon_apify_get_api_capability_definitions()));
    eq($GLOBALS['__eventon_test_rest_requests'], array(), 'status never dispatches an event route');
});

test('non-administrators do not discover this plugin\'s abilities', function () {
    $own = new WP_Ability('eventon-apify/get-event');
    $other = new WP_Ability('core/get-site-info');

    eventon_test_set_current_user_can(false);
    eq(eventon_apify_filter_ability_visibility(true, $own), false);
    eq(eventon_apify_filter_ability_visibility(true, $other), true, 'other plugins are untouched');
    eq(eventon_apify_filter_ability_visibility(false, $other), false, 'an exclusion by someone else stands');

    eventon_test_set_current_user_can(true);
    eq(eventon_apify_filter_ability_visibility(true, $own), true);
});

test('single-ability routes are refused for non-administrators only', function () {
    eventon_test_set_current_user_can(false);
    foreach (array('/wp-abilities/v1/abilities/eventon-apify/get-event', '/wp-abilities/v1/abilities/eventon-apify/search-events/run', '/wp-abilities/v1/abilities/EventON-APIfy/get-event') as $route) {
        $result = eventon_apify_restrict_ability_routes(null, null, new WP_REST_Request(array(), $route));
        ok(is_wp_error($result), $route);
        eq($result->get_error_code(), 'eventon_apify_ability_admin_only');
    }
    eq(eventon_apify_restrict_ability_routes('untouched', null, new WP_REST_Request(array(), '/wp-abilities/v1/abilities/core/get-site-info')), 'untouched');

    eventon_test_set_current_user_can(true);
    eq(eventon_apify_restrict_ability_routes('untouched', null, new WP_REST_Request(array(), '/wp-abilities/v1/abilities/eventon-apify/get-event')), 'untouched');
});
