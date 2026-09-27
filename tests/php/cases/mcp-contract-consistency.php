<?php
/**
 * Pin the MCP manifest contract (examples, filters, validation rules) to the
 * arguments actually registered on the eventonapify/v1 REST routes.
 */

/**
 * Return the registered arg names for one method of a captured route.
 *
 * @return array<int, string>
 */
function eventon_test_get_route_arg_names($route, $method) {
    $GLOBALS['__eventon_test_routes'] = array();
    eventon_apify_register_routes();

    $handlers = $GLOBALS['__eventon_test_routes'][$route][0] ?? array();
    foreach ($handlers as $handler) {
        if (($handler['methods'] ?? '') === $method) {
            return array_keys($handler['args'] ?? array());
        }
    }

    return array();
}

test('list example query uses only registered events route args', function () {
    $examples = eventon_apify_get_mcp_contract_examples();
    $list_args = eventon_test_get_route_arg_names(EVENTON_APIFY_NAMESPACE . '/events', WP_REST_Server::READABLE);

    eq($examples['list']['endpoint'], 'eventonapify/v1/events');
    ok(!empty($list_args), 'list route args should be captured');

    foreach (array_keys($examples['list']['query']) as $param) {
        ok(in_array($param, $list_args, true), "list example param {$param} must be a registered route arg");
    }
});

test('manifest read filters document only registered events route args', function () {
    $manifest = eventon_apify_get_mcp_content_type_manifest();
    $filters = $manifest['read_contract']['filters'];
    $list_args = eventon_test_get_route_arg_names(EVENTON_APIFY_NAMESPACE . '/events', WP_REST_Server::READABLE);

    foreach (array_keys($filters) as $filter_name) {
        ok(in_array($filter_name, $list_args, true), "filter {$filter_name} must be a registered route arg");
    }

    ok(isset($filters['starts_on_or_after']));
    eq($filters['starts_on_or_after']['inclusive'], true);
    ok(isset($filters['starts_before']));
    eq($filters['starts_before']['inclusive'], false);
    ok(isset($filters['upcoming']));
    ok(!isset($filters['after']));
    ok(!isset($filters['before']));
});

test('executable create rules require title plus start_date or start_at', function () {
    $rules = eventon_apify_get_mcp_validation_rules();

    eq($rules['required_for_create'], array('title'));
    eq($rules['one_of_required_for_create'], array(array('start_date', 'start_at')));
});

/**
 * Index the published ajde_events contract fields by name.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_test_get_manifest_fields_by_name() {
    $fields = array();
    foreach (eventon_apify_get_mcp_content_type_manifest()['fields'] as $field) {
        $fields[$field['name']] = $field;
    }

    return $fields;
}

/**
 * Index nested contract shape entries by name.
 *
 * @param array<int, array<string, mixed>> $shape Exported shape.
 * @return array<string, array<string, mixed>>
 */
function eventon_test_index_shape(array $shape) {
    $indexed = array();
    foreach ($shape as $entry) {
        $indexed[$entry['name']] = $entry;
    }

    return $indexed;
}

test('no published field claims required_on create alone', function () {
    // title is a core field outside the published list; the start requirement
    // is the start_date/start_at one-of group, not a per-field flag.
    $fields = eventon_test_get_manifest_fields_by_name();

    ok(isset($fields['start_date'], $fields['start_at']));
    foreach ($fields as $name => $field) {
        ok(!isset($field['required_on']), "{$name} must not declare required_on");
    }
});

test('create validation accepts start_at in place of start_date', function () {
    $without_start = eventon_apify_validate_event_payload(
        eventon_apify_normalize_request_payload(array('title' => 'No start')),
        true
    );
    ok(is_wp_error($without_start), 'create without start_date or start_at must fail');

    $payload = eventon_apify_normalize_request_payload(array(
        'title' => 'Start at only',
        'start_at' => '2026-04-01T09:00:00',
    ));

    ok(!empty($payload['start_date']), 'start_at must normalize into start_date');
    eq(eventon_apify_validate_event_payload($payload, true), true);
});

test('ajde_events manifest prefers the transactional eventonapify/v1 route', function () {
    $manifest = eventon_apify_get_mcp_content_type_manifest();

    eq($manifest['preferred_endpoint'], 'eventonapify/v1/events');
    eq($manifest['read_endpoint'], 'eventonapify/v1/events');
    ok(strpos($manifest['description'], 'wp/v2-compatible') === false);

    $related = array();
    foreach ($manifest['related_endpoints'] as $endpoint) {
        $related[$endpoint['name']] = $endpoint['endpoint'];
    }
    eq($related['item'], 'eventonapify/v1/events/{id}');
    eq($related['wp_v2_compat'], 'wp/v2/ajde_events');
});

test('supported_operations match the registered routes', function () {
    $GLOBALS['__eventon_test_routes'] = array();
    eventon_apify_register_routes();
    $routes = $GLOBALS['__eventon_test_routes'];

    $methods = static function ($route) use ($routes) {
        return array_map(
            static function ($handler) {
                return $handler['methods'] ?? '';
            },
            $routes[EVENTON_APIFY_NAMESPACE . $route][0] ?? array()
        );
    };

    $collection = $methods('/events');
    $item = $methods('/events/(?P<id>\\d+)');
    ok(in_array(WP_REST_Server::READABLE, $collection, true));
    ok(in_array(WP_REST_Server::CREATABLE, $collection, true));
    ok(in_array(WP_REST_Server::READABLE, $item, true));
    ok(in_array(WP_REST_Server::EDITABLE, $item, true));
    ok(in_array(WP_REST_Server::DELETABLE, $item, true));

    $manifest = eventon_apify_get_mcp_content_type_manifest();
    eq($manifest['supported_operations'], array('list', 'get', 'create', 'update', 'delete'));

    eq(eventon_apify_get_mcp_rsvp_content_type_manifest()['supported_operations'], array('list'));
});

test('location lat and lon publish a string or number type', function () {
    $fields = eventon_test_get_manifest_fields_by_name();
    $location = eventon_test_index_shape($fields['location']['shape']);

    eq($location['lat']['type'], array('string', 'number'));
    eq($location['lon']['type'], array('string', 'number'));
    ok($location['lat']['description'] !== '');
    ok($location['lon']['description'] !== '');
});

test('comma-separated term fields keep their declared shape', function () {
    $fields = eventon_test_get_manifest_fields_by_name();
    $args = eventon_apify_get_event_write_args(false);

    foreach (array('event_type', 'tags') as $name) {
        eq($fields[$name]['type'], 'array');
        // Names are strings; a numeric item is read as a term ID.
        eq($fields[$name]['items']['type'], array('string', 'integer'));
        eq($fields[$name]['also_accepts'], array('comma_separated_string'));
        eq($args[$name]['type'], array('array', 'string'));
    }
});

test('repeat interval item shape lists every key the write accepts', function () {
    $fields = eventon_test_get_manifest_fields_by_name();
    $repeat = eventon_test_index_shape($fields['repeat']['shape']);
    $item_keys = array_keys(eventon_test_index_shape($repeat['intervals']['items']['shape']));

    $accepted = array('start_at', 'end_at', 'start_timestamp', 'end_timestamp', 'start_date', 'start_time', 'end_date', 'end_time');
    foreach ($accepted as $key) {
        ok(in_array($key, $item_keys, true), "repeat.intervals item shape must publish {$key}");
    }

    // Every published key must be one the write actually reads.
    $timezone = 'UTC';
    eq(eventon_apify_normalize_repeat_interval_item(array('start_timestamp' => 100, 'end_timestamp' => 200), $timezone), array(100, 200));
    ok(is_array(eventon_apify_normalize_repeat_interval_item(array('start_at' => '2026-04-01T09:00:00', 'end_at' => '2026-04-01T10:00:00'), $timezone)));
    ok(is_array(eventon_apify_normalize_repeat_interval_item(array('start_date' => '2026-04-01', 'start_time' => '09:00', 'end_date' => '2026-04-01', 'end_time' => '10:00'), $timezone)));
});

test('create example uses only published contract fields inside the wrapper', function () {
    $examples = eventon_apify_get_mcp_contract_examples();
    $published = eventon_apify_get_mcp_contract_field_names();

    foreach (array_keys($examples['create']['fields']) as $field_name) {
        ok(in_array($field_name, $published, true), "create example field {$field_name} must be a published contract field");
    }

    foreach (array_keys($examples['update']['fields']) as $field_name) {
        ok(in_array($field_name, $published, true), "update example field {$field_name} must be a published contract field");
    }
});
