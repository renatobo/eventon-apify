<?php
/**
 * Ratchet for the per-handler readiness gate.
 *
 * Every eventonapify/v1 handler must open with an
 * eventon_apify_assert_*_capability_is_ready() call. The permission callback
 * covers who may reach the route; the assert covers the enable_api master
 * switch, EventON availability, and the per-route capability toggle.
 *
 * Rather than naming handlers one by one, this walks whatever
 * eventon_apify_register_routes() actually registered, so a route added later
 * is covered the day it lands. Each assert is the handler's first statement,
 * so with the API disabled every callback short-circuits before reaching
 * anything the stubs do not implement.
 */

/**
 * Register every route, including the RSVP pair, and return a flat endpoint list.
 *
 * The RSVP routes register only when eventon_apify_is_eventon_rsvp_available()
 * passes, which the default harness state fails. Without the class and post
 * type below the table would silently cover 7 of 9 endpoints.
 *
 * @return array<int, array{route: string, methods: string, endpoint: array<string, mixed>}>
 */
function eventon_test_collect_registered_endpoints() {
    if (!class_exists('EventON_rsvp')) {
        class EventON_rsvp {}
    }
    $GLOBALS['__eventon_test_post_types']['evo-rsvp'] = true;

    $GLOBALS['__eventon_test_routes'] = array();
    eventon_apify_register_routes();

    $endpoints = array();
    foreach ($GLOBALS['__eventon_test_routes'] as $route => $groups) {
        foreach ($groups as $group) {
            foreach ($group as $endpoint) {
                $endpoints[] = array(
                    'route' => $route,
                    'methods' => (string) ($endpoint['methods'] ?? ''),
                    'endpoint' => $endpoint,
                );
            }
        }
    }

    return $endpoints;
}

test('every registered endpoint is captured, RSVP routes included', function () {
    $endpoints = eventon_test_collect_registered_endpoints();

    // Guards the table itself: a new route must either be covered by the
    // assertions below or force this count to be updated deliberately.
    eq(count($endpoints), 9);

    $routes = array_values(array_unique(array_map(
        static function (array $entry) {
            return $entry['route'];
        },
        $endpoints
    )));

    ok(in_array(EVENTON_APIFY_NAMESPACE . '/events/(?P<id>\d+)/rsvps', $routes, true), 'RSVP attendee route must register');
    ok(in_array(EVENTON_APIFY_NAMESPACE . '/events/(?P<id>\d+)/rsvps/summary', $routes, true), 'RSVP summary route must register');

    // Discovery is part of the API surface. It once served the manifest while
    // the master switch was off, so it is named here rather than left to the count.
    ok(in_array(EVENTON_APIFY_NAMESPACE . '/mcp-schema', $routes, true), 'MCP discovery route must register');
});

test('every endpoint is gated on administrator authorization', function () {
    foreach (eventon_test_collect_registered_endpoints() as $entry) {
        eq(
            $entry['endpoint']['permission_callback'] ?? null,
            'eventon_apify_admin_only',
            $entry['methods'] . ' ' . $entry['route'] . ' permission_callback'
        );
    }
});

test('every handler refuses to run while the API master switch is off', function () {
    $endpoints = eventon_test_collect_registered_endpoints();

    // Authorized caller, EventON present: the disabled switch is the only
    // reason any of these may fail, which is what makes the assertion precise.
    eventon_test_set_current_user_can(true);
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, false);

    foreach ($endpoints as $entry) {
        $label = $entry['methods'] . ' ' . $entry['route'];
        $callback = $entry['endpoint']['callback'] ?? null;

        ok(is_callable($callback), $label . ' must declare a callable handler');

        $result = call_user_func($callback, new WP_REST_Request());

        ok(is_wp_error($result), $label . ' must not run while the API is disabled');
        eq($result->get_error_code(), 'eventon_apify_disabled', $label . ' error code');
    }
});

/**
 * The capability each endpoint must gate on, keyed by "METHODS route".
 *
 * The master-switch and EventON cases below pass for any handler that calls
 * an assert at all, including one that passes the wrong capability name or the
 * no-capability eventon_apify_assert_api_is_ready(). This table is what pins a
 * handler to its own toggle, so a copy-pasted assert cannot leave a route
 * responding while its switch reads off in Settings.
 *
 * mcp-schema is absent deliberately: discovery describes the contract rather
 * than reading event data, so it gates on the master switch only.
 *
 * Only the refusal direction is asserted. The mirror case, that a handler runs
 * when its own capability is the only one on, cannot be tested here: past the
 * assert the handlers reach rest_ensure_response() and other core functions the
 * stubs do not implement. Every case in this file works by short-circuiting
 * before that point.
 *
 * @return array<string, string>
 */
function eventon_test_endpoint_capability_map() {
    $events = EVENTON_APIFY_NAMESPACE . '/events';
    $event = EVENTON_APIFY_NAMESPACE . '/events/(?P<id>\d+)';

    return array(
        'GET ' . $events => 'list',
        'POST ' . $events => 'create',
        'GET ' . $event => 'read',
        // WP_REST_Server::EDITABLE is 'POST, PUT, PATCH', not 'PUT, PATCH'.
        'POST, PUT, PATCH ' . $event => 'update',
        'DELETE ' . $event => 'delete',
        'GET ' . $event . '/rsvps/summary' => 'rsvp_counts',
        'GET ' . $event . '/rsvps' => 'rsvp_attendees',
    );
}

test('the capability map names every capability-gated endpoint', function () {
    $map = eventon_test_endpoint_capability_map();

    // A new capability-gated route must be added to the map rather than
    // silently skipping the per-capability assertions below.
    eq(count($map), count(eventon_apify_get_api_capability_definitions()));

    foreach ($map as $label => $capability) {
        ok(
            array_key_exists($capability, eventon_apify_get_api_capability_definitions()),
            $label . ' must name a defined capability'
        );
    }

    $labels = array_map(
        static function (array $entry) {
            return $entry['methods'] . ' ' . $entry['route'];
        },
        eventon_test_collect_registered_endpoints()
    );

    foreach (array_keys($map) as $label) {
        ok(in_array($label, $labels, true), $label . ' must match a registered endpoint');
    }
});

test('every handler refuses to run while its own capability is off', function () {
    $endpoints = eventon_test_collect_registered_endpoints();
    $map = eventon_test_endpoint_capability_map();

    eventon_test_set_current_user_can(true);
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, true);

    foreach ($endpoints as $entry) {
        $label = $entry['methods'] . ' ' . $entry['route'];
        if (!isset($map[$label])) {
            continue;
        }

        // Every capability on, then exactly one off: the handler that responds
        // anyway is reading someone else's toggle, or none.
        $capabilities = array_fill_keys(array_keys(eventon_apify_get_api_capability_definitions()), true);
        $capabilities[$map[$label]] = false;
        update_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, $capabilities);

        $result = call_user_func($entry['endpoint']['callback'], new WP_REST_Request());

        ok(is_wp_error($result), $label . ' must not run while ' . $map[$label] . ' is off');
        eq($result->get_error_code(), 'eventon_apify_capability_disabled', $label . ' error code');
    }
});

test('every handler refuses to run when EventON is unavailable', function () {
    $endpoints = eventon_test_collect_registered_endpoints();

    eventon_test_set_current_user_can(true);
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, true);
    unset($GLOBALS['__eventon_test_post_types']['ajde_events']);

    foreach ($endpoints as $entry) {
        $label = $entry['methods'] . ' ' . $entry['route'];
        $result = call_user_func($entry['endpoint']['callback'], new WP_REST_Request());

        ok(is_wp_error($result), $label . ' must not run without EventON');
        eq($result->get_error_code(), 'eventon_apify_eventon_missing', $label . ' error code');
    }
});
