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

if (!$create_endpoint || empty($create_endpoint['args']['title']['required'])) {
    throw new RuntimeException('Create-event REST schema must require title.');
}

if (empty($create_endpoint['args']['start_date']['required'])) {
    throw new RuntimeException('Create-event REST schema must require start_date.');
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

echo "WordPress 7.0.2 REST integration smoke passed.\n";
