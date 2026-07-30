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

test('executable create rules require title and start_date', function () {
    $rules = eventon_apify_get_mcp_validation_rules();

    ok(in_array('title', $rules['required_for_create'], true));
    ok(in_array('start_date', $rules['required_for_create'], true));
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
