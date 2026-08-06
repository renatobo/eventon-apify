<?php
/**
 * WordPress 7 integration smoke assertions, executed with `wp eval-file`.
 */

if (!defined('ABSPATH')) {
    exit(1);
}

if (get_bloginfo('version') !== '7.0.2') {
    throw new RuntimeException('Expected WordPress 7.0.2, got ' . get_bloginfo('version'));
}

register_post_type('ajde_events', array('public' => false));
register_taxonomy('event_type', 'ajde_events');
register_taxonomy('event_location', 'ajde_events');
register_taxonomy('event_organizer', 'ajde_events');

$administrator = get_user_by('login', 'admin');
if (!$administrator) {
    throw new RuntimeException('Integration administrator fixture is missing.');
}

wp_set_current_user(0);
$permission = eventon_apify_admin_only();
if ($permission !== false) {
    throw new RuntimeException('Anonymous requests must not pass API authorization.');
}

wp_set_current_user($administrator->ID);
if (!eventon_apify_admin_only()) {
    throw new RuntimeException('Administrator must pass API authorization.');
}

$server = rest_get_server();
do_action('rest_api_init', $server);
$routes = $server->get_routes();

foreach (array('/eventonapify/v1/mcp-schema', '/eventonapify/v1/events') as $route) {
    if (!isset($routes[$route])) {
        throw new RuntimeException('Missing REST route: ' . $route);
    }
}

$schema_endpoint = $routes['/eventonapify/v1/mcp-schema'][0];
wp_set_current_user(0);
if (call_user_func($schema_endpoint['permission_callback']) !== false) {
    throw new RuntimeException('Schema discovery must reject anonymous requests.');
}

$event_endpoints = $routes['/eventonapify/v1/events'];
$create_endpoint = null;
foreach ($event_endpoints as $endpoint) {
    if (!empty($endpoint['methods']['POST'])) {
        $create_endpoint = $endpoint;
        break;
    }
}

if (!$create_endpoint) {
    throw new RuntimeException('The events route must expose a create endpoint.');
}

/*
 * Create-required fields are deliberately NOT marked required at the arg layer:
 * a client may send title inside the fields/custom_fields wrapper, and an
 * arg-level requirement would reject that with a 400 before normalization runs.
 * The route publishes the fields; the requirement is asserted below by
 * dispatching real requests, which is where the behavior actually lives.
 */
foreach (array('title', 'start_date') as $field) {
    if (!isset($create_endpoint['args'][$field])) {
        throw new RuntimeException('Create-event REST schema must publish ' . $field . '.');
    }
}

$post_id = wp_insert_post(
    array(
        'post_type' => 'ajde_events',
        'post_status' => 'draft',
        'post_title' => 'Before rollback',
    )
);
update_post_meta($post_id, 'evcal_event_color', '112233');

$rollback_result = EventON_APIfy_Event_Write_Coordinator::persist(
    $post_id,
    array(
        'event_color' => 'abcdef',
        // event_faq is intentionally absent from this fixture, forcing the
        // taxonomy phase to fail after metadata has changed.
        'faq_items' => array(array('question' => 'Rollback?', 'answer' => 'Yes')),
    ),
    false,
    array('ID' => $post_id, 'post_title' => 'Must roll back')
);

if (!is_wp_error($rollback_result)) {
    throw new RuntimeException('Expected the integration write to fail during taxonomy persistence.');
}

if (get_post($post_id)->post_title !== 'Before rollback') {
    throw new RuntimeException('Post fields were not restored after a partial write failure.');
}

if (get_post_meta($post_id, 'evcal_event_color', true) !== '112233') {
    throw new RuntimeException('Post metadata was not restored after a partial write failure.');
}

/*
 * Rollback also has to restore taxonomy assignments and EventON's shared
 * evo_tax_meta option. WordPress gives no transaction across either, so these
 * are the two compensating writes most likely to regress unnoticed. Term
 * creation requires manage_options, so this runs as the administrator.
 */
wp_set_current_user($administrator->ID);

$baseline_type = term_exists('Baseline Type', 'event_type');
if (!$baseline_type) {
    $baseline_type = wp_insert_term('Baseline Type', 'event_type');
}
if (is_wp_error($baseline_type)) {
    throw new RuntimeException('Could not create the baseline event_type fixture.');
}
$baseline_type_id = (int) $baseline_type['term_id'];

$terms_post_id = wp_insert_post(
    array(
        'post_type' => 'ajde_events',
        'post_status' => 'draft',
        'post_title' => 'Terms before rollback',
    )
);
wp_set_object_terms($terms_post_id, array($baseline_type_id), 'event_type', false);

$baseline_tax_meta = array(
    'event_location' => array(
        4242 => array('location_address' => 'Baseline St'),
    ),
);
update_option('evo_tax_meta', $baseline_tax_meta);

// event_type and the location term both persist before faq_items reaches the
// unregistered event_faq taxonomy, so terms and evo_tax_meta have both already
// changed by the time the write fails.
$terms_rollback_result = EventON_APIfy_Event_Write_Coordinator::persist(
    $terms_post_id,
    array(
        'event_type' => array('Replacement Type'),
        'location_name' => 'Rollback Hall',
        'location_address' => '1 Rollback Way',
        'faq_items' => array(array('question' => 'Rollback?', 'answer' => 'Yes')),
    ),
    false,
    array('ID' => $terms_post_id)
);

if (!is_wp_error($terms_rollback_result)) {
    throw new RuntimeException('Expected the taxonomy-phase write to fail.');
}

$restored_types = wp_get_object_terms($terms_post_id, 'event_type', array('fields' => 'ids'));
if (is_wp_error($restored_types) || array_map('intval', $restored_types) !== array($baseline_type_id)) {
    throw new RuntimeException('Taxonomy assignments were not restored after a partial write failure.');
}

if (get_option('evo_tax_meta') !== $baseline_tax_meta) {
    throw new RuntimeException('EventON shared taxonomy metadata was not restored after a partial write failure.');
}

/*
 * Dispatch real requests through the REST server. Route args declare
 * sanitize_callback, validate_callback, and enum constraints that nothing else
 * in the suite exercises: a typo in a callback name registers cleanly and only
 * surfaces on a live request.
 */
update_option('eventon_apify_enable_api', true);

/**
 * Dispatch one eventonapify/v1 request and return its status code.
 *
 * @param array<string, mixed> $query_params Query parameters.
 */
function eventon_smoke_dispatch_status(WP_REST_Server $server, $method, $route, array $query_params = array()) {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query_params);

    return $server->dispatch($request)->get_status();
}

wp_set_current_user(0);
$anonymous_status = eventon_smoke_dispatch_status($server, 'GET', '/eventonapify/v1/events');
if ($anonymous_status !== 401) {
    throw new RuntimeException('Anonymous list requests must be rejected, got status ' . $anonymous_status . '.');
}

wp_set_current_user($administrator->ID);

// A search that matches nothing keeps the response off the EventON-dependent
// formatting path, so this asserts dispatch and arg wiring, nothing more.
$list_request = new WP_REST_Request('GET', '/eventonapify/v1/events');
$list_request->set_query_params(array('search' => '__no_such_event__'));
$list_response = $server->dispatch($list_request);

if ($list_response->get_status() !== 200) {
    throw new RuntimeException('Authorized list requests must succeed, got status ' . $list_response->get_status() . '.');
}

$list_data = $list_response->get_data();
if (!is_array($list_data) || !array_key_exists('events', $list_data) || $list_data['events'] !== array()) {
    throw new RuntimeException('List responses must carry an events collection.');
}

// Args carrying an explicit validate_callback reject. Note that WordPress does
// not apply rest_validate_request_arg to hand-registered args, so an arg with
// only a schema and a sanitize_callback never rejects; see the pagination
// assertions below for the ones that clamp instead.
foreach (
    array(
        'an unknown orderby' => array('orderby' => 'not_a_field'),
        'an unknown order direction' => array('order' => 'sideways'),
        'a malformed date filter' => array('starts_on_or_after' => 'not-a-date'),
        'a malformed upper date bound' => array('starts_before' => 'not-a-date'),
    ) as $label => $query_params
) {
    $status = eventon_smoke_dispatch_status($server, 'GET', '/eventonapify/v1/events', $query_params);
    if ($status !== 400) {
        throw new RuntimeException('Expected 400 for ' . $label . ', got status ' . $status . '.');
    }
}

// per_page and page declare minimum/maximum for documentation but carry no
// validate_callback, so out-of-range values are clamped by their sanitizers
// rather than rejected. Pinning the clamp keeps that contract honest.
foreach (
    array(
        array('query' => array('per_page' => 101), 'key' => 'per_page', 'expected' => 100),
        array('query' => array('per_page' => 0), 'key' => 'per_page', 'expected' => 20),
        array('query' => array('page' => 0), 'key' => 'page', 'expected' => 1),
    ) as $case
) {
    $request = new WP_REST_Request('GET', '/eventonapify/v1/events');
    $request->set_query_params(array_merge(array('search' => '__no_such_event__'), $case['query']));
    $response = $server->dispatch($request);

    if ($response->get_status() !== 200) {
        throw new RuntimeException('Out-of-range pagination must be clamped, not rejected.');
    }

    $data = $response->get_data();
    if (($data[$case['key']] ?? null) !== $case['expected']) {
        throw new RuntimeException(
            'Expected ' . $case['key'] . ' to clamp to ' . $case['expected']
            . ', got ' . var_export($data[$case['key']] ?? null, true) . '.'
        );
    }
}

/*
 * Create-required fields are enforced after wrapper normalization, so both
 * shapes have to be exercised: a bare payload missing title must fail, and the
 * same title supplied inside the fields wrapper must succeed.
 */
$missing_title = new WP_REST_Request('POST', '/eventonapify/v1/events');
$missing_title->set_header('content-type', 'application/json');
$missing_title->set_body(wp_json_encode(array('start_date' => '2030-01-01')));
$missing_title_response = $server->dispatch($missing_title);

if ($missing_title_response->get_status() !== 400) {
    throw new RuntimeException('Creating an event without a title must fail, got status ' . $missing_title_response->get_status() . '.');
}

$missing_title_data = $missing_title_response->get_data();
if (($missing_title_data['code'] ?? '') !== 'eventon_apify_missing_title') {
    throw new RuntimeException('Expected eventon_apify_missing_title, got ' . var_export($missing_title_data['code'] ?? null, true) . '.');
}

$wrapped_create = new WP_REST_Request('POST', '/eventonapify/v1/events');
$wrapped_create->set_header('content-type', 'application/json');
$wrapped_create->set_body(wp_json_encode(array(
    'fields' => array('title' => 'Wrapper Create', 'start_date' => '2030-01-01'),
)));
$wrapped_create_response = $server->dispatch($wrapped_create);

if ($wrapped_create_response->get_status() !== 201) {
    throw new RuntimeException('A fields-wrapper create must succeed, got status ' . $wrapped_create_response->get_status() . '.');
}

// The master switch has to close the route for an otherwise authorized caller.
update_option('eventon_apify_enable_api', false);
$disabled_status = eventon_smoke_dispatch_status($server, 'GET', '/eventonapify/v1/events');
if ($disabled_status !== 403) {
    throw new RuntimeException('A disabled API must refuse authorized requests, got status ' . $disabled_status . '.');
}
update_option('eventon_apify_enable_api', true);

echo "WordPress 7.0.2 REST integration smoke passed.\n";
