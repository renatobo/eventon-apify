<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * WordPress abilities: a read-only adapter over the eventonapify/v1 REST API.
 *
 * Each event ability dispatches the existing route in-process with
 * rest_do_request() (no HTTP round trip), so arguments are sanitized by the
 * same route args, and the handler runs the same readiness assert, per-route
 * capability toggle, queries, and errors as a REST client gets. The abilities
 * only add bounded input schemas and a narrower output mapping.
 *
 * Authorization matches the REST API: manage_options for execution, and the
 * abilities are hidden from non-administrators in discovery too, as the MCP
 * schema manifest is.
 */

/** Ability names share this prefix; it also scopes the discovery guards. */
const EVENTON_APIFY_ABILITY_PREFIX = 'eventon-apify/';

/**
 * Register the ability category. Hooked on wp_abilities_api_categories_init.
 */
function eventon_apify_register_ability_category() {
    wp_register_ability_category(
        'eventon-apify',
        array(
            'label' => __('EventON events', 'eventon-apify'),
            'description' => __('Read EventON events and the status of the EventON APIfy API.', 'eventon-apify'),
        )
    );
}

/**
 * Register the abilities. Hooked on wp_abilities_api_init.
 */
function eventon_apify_register_abilities() {
    $meta = array(
        'annotations' => array(
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ),
        'public' => true,
    );

    wp_register_ability(
        EVENTON_APIFY_ABILITY_PREFIX . 'get-status',
        array(
            'label' => __('Get EventON API status', 'eventon-apify'),
            'description' => __('Reports whether EventON and its RSVP addon are active, whether the EventON APIfy API and wp/v2 compatibility are enabled, and which API operations are switched on. Returns no event data, and works while the API is disabled.', 'eventon-apify'),
            'category' => 'eventon-apify',
            'output_schema' => eventon_apify_get_status_ability_output_schema(),
            'execute_callback' => 'eventon_apify_execute_status_ability',
            'permission_callback' => 'eventon_apify_admin_only',
            'meta' => $meta,
        )
    );

    wp_register_ability(
        EVENTON_APIFY_ABILITY_PREFIX . 'search-events',
        array(
            'label' => __('Search EventON events', 'eventon-apify'),
            'description' => __('Searches EventON events with pagination and returns a summary of each: id, title, status, start and end, timezone, location name, and event types. Use get-event for full details. Requires the API and its List events operation to be enabled.', 'eventon-apify'),
            'category' => 'eventon-apify',
            'input_schema' => eventon_apify_get_search_events_ability_input_schema(),
            'output_schema' => eventon_apify_get_search_events_ability_output_schema(),
            'execute_callback' => 'eventon_apify_execute_search_events_ability',
            'permission_callback' => 'eventon_apify_admin_only',
            'meta' => $meta,
        )
    );

    wp_register_ability(
        EVENTON_APIFY_ABILITY_PREFIX . 'get-event',
        array(
            'label' => __('Get EventON event', 'eventon-apify'),
            'description' => __('Returns one EventON event by ID with dates, location, organizers, event types, repeat, virtual, and RSVP settings. Contact details, virtual access secrets, and notification emails are removed. Requires the API and its Read single event operation to be enabled.', 'eventon-apify'),
            'category' => 'eventon-apify',
            'input_schema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'description' => __('Event post ID.', 'eventon-apify'),
                        'required' => true,
                    ),
                ),
                'additionalProperties' => false,
            ),
            'output_schema' => array(
                'type' => 'object',
                'description' => __('Event details, in the eventonapify/v1 event shape without contact details or access secrets.', 'eventon-apify'),
                'properties' => array(
                    'id' => array('type' => 'integer'),
                    'title' => array('type' => 'string'),
                ),
                'additionalProperties' => true,
            ),
            'execute_callback' => 'eventon_apify_execute_get_event_ability',
            'permission_callback' => 'eventon_apify_admin_only',
            'meta' => $meta,
        )
    );
}

/**
 * @return array<string, mixed>
 */
function eventon_apify_get_status_ability_output_schema() {
    $capabilities = array();
    foreach (array_keys(eventon_apify_get_api_capability_definitions()) as $capability) {
        $capabilities[$capability] = array('type' => 'boolean');
    }

    return array(
        'type' => 'object',
        'properties' => array(
            'plugin_version' => array('type' => 'string'),
            'eventon_available' => array('type' => 'boolean'),
            'eventon_rsvp_available' => array('type' => 'boolean'),
            'custom_event_api_enabled' => array('type' => 'boolean'),
            'wp_v2_compatibility_enabled' => array('type' => 'boolean'),
            'custom_event_api_capabilities' => array(
                'type' => 'object',
                'description' => __('Saved state of each API operation toggle. An operation is usable only while custom_event_api_enabled is also true.', 'eventon-apify'),
                'properties' => $capabilities,
                'additionalProperties' => false,
            ),
        ),
        'additionalProperties' => false,
    );
}

/**
 * @return array<string, mixed>
 */
function eventon_apify_get_search_events_ability_input_schema() {
    return array(
        'type' => 'object',
        'properties' => array(
            'search' => array(
                'type' => 'string',
                'maxLength' => 200,
                'description' => __('Free-text search over event titles and content.', 'eventon-apify'),
            ),
            'page' => array(
                'type' => 'integer',
                'minimum' => 1,
                'description' => __('1-based page number. Default 1.', 'eventon-apify'),
            ),
            'per_page' => array(
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'description' => __('Events per page, 1 to 100. Default 20.', 'eventon-apify'),
            ),
            'status' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'string',
                    'enum' => eventon_apify_get_allowed_post_statuses(),
                ),
                'maxItems' => count(eventon_apify_get_allowed_post_statuses()),
                'description' => __('Post statuses to include. Default: every status the list endpoint returns by default.', 'eventon-apify'),
            ),
            'starts_on_or_after' => array(
                'type' => 'string',
                'maxLength' => 40,
                'description' => __('Only events with an occurrence starting at or after this date or ISO 8601 date-time.', 'eventon-apify'),
            ),
            'starts_before' => array(
                'type' => 'string',
                'maxLength' => 40,
                'description' => __('Only events with an occurrence starting before this date or ISO 8601 date-time.', 'eventon-apify'),
            ),
            'upcoming' => array(
                'type' => 'boolean',
                'description' => __('Only events starting from now on.', 'eventon-apify'),
            ),
            'order' => array(
                'type' => 'string',
                'enum' => array('asc', 'desc'),
            ),
            'orderby' => array(
                'type' => 'string',
                'enum' => array('start_at', 'created', 'modified', 'title'),
            ),
        ),
        'additionalProperties' => false,
        'default' => array(),
    );
}

/**
 * @return array<string, mixed>
 */
function eventon_apify_get_search_events_ability_output_schema() {
    return array(
        'type' => 'object',
        'properties' => array(
            'total' => array('type' => 'integer'),
            'pages' => array('type' => 'integer'),
            'page' => array('type' => 'integer'),
            'per_page' => array('type' => 'integer'),
            'truncated' => array(
                'type' => 'boolean',
                'description' => __('Present and true when a date filter hit the candidate scan limit, so the result may be incomplete.', 'eventon-apify'),
            ),
            'events' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'id' => array('type' => 'integer'),
                        'title' => array('type' => 'string'),
                        'slug' => array('type' => 'string'),
                        'status' => array('type' => 'string'),
                        'link' => array('type' => 'string'),
                        'start_at' => array('type' => 'string'),
                        'end_at' => array('type' => 'string'),
                        'timezone' => array(
                            'type' => 'string',
                            'description' => __('IANA timezone identifier of the event.', 'eventon-apify'),
                        ),
                        'event_status' => array('type' => 'string'),
                        'attendance_mode' => array('type' => 'string'),
                        'location_name' => array('type' => 'string'),
                        'event_type' => array(
                            'type' => 'array',
                            'items' => array('type' => 'string'),
                        ),
                    ),
                    'additionalProperties' => false,
                ),
            ),
        ),
        'additionalProperties' => false,
    );
}

/**
 * Report plugin and dependency status. Deliberately skips the readiness
 * assert: saying that the API is disabled is the point of this ability.
 *
 * @return array<string, mixed>
 */
function eventon_apify_execute_status_ability() {
    return array(
        'plugin_version' => EVENTON_APIFY_VERSION,
        'eventon_available' => eventon_apify_is_eventon_available(),
        'eventon_rsvp_available' => eventon_apify_is_eventon_rsvp_available(),
        'custom_event_api_enabled' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false),
        'wp_v2_compatibility_enabled' => eventon_apify_is_wp_v2_compatibility_enabled(),
        'custom_event_api_capabilities' => array_map('boolval', eventon_apify_get_api_capabilities()),
    );
}

/**
 * Search events through GET /eventonapify/v1/events.
 *
 * @param mixed $input Validated ability input.
 * @return array<string, mixed>|WP_Error
 */
function eventon_apify_execute_search_events_ability($input = array()) {
    $input = is_array($input) ? $input : array();
    $params = array_intersect_key(
        $input,
        array_flip(array('search', 'page', 'per_page', 'starts_on_or_after', 'starts_before', 'upcoming', 'order', 'orderby'))
    );

    if (!empty($input['status']) && is_array($input['status'])) {
        $params['status'] = implode(',', $input['status']);
    }

    $data = eventon_apify_dispatch_rest_for_ability('GET', '/events', $params);
    if (is_wp_error($data)) {
        return $data;
    }

    $result = array(
        'total' => (int) ($data['total'] ?? 0),
        'pages' => (int) ($data['pages'] ?? 0),
        'page' => (int) ($data['page'] ?? 1),
        'per_page' => (int) ($data['per_page'] ?? 0),
        'events' => array_map('eventon_apify_get_event_summary', array_values((array) ($data['events'] ?? array()))),
    );

    if (!empty($data['truncated'])) {
        $result['truncated'] = true;
    }

    return $result;
}

/**
 * Read one event through GET /eventonapify/v1/events/<id>.
 *
 * @param mixed $input Validated ability input.
 * @return array<string, mixed>|WP_Error
 */
function eventon_apify_execute_get_event_ability($input = array()) {
    $event_id = is_array($input) ? absint($input['id'] ?? 0) : 0;

    $data = eventon_apify_dispatch_rest_for_ability('GET', '/events/' . $event_id, array());
    if (is_wp_error($data)) {
        return $data;
    }

    return eventon_apify_redact_event_payload((array) $data);
}

/**
 * Dispatch an eventonapify/v1 route in-process and unwrap the result.
 *
 * @param array<string, mixed> $params Query parameters.
 * @return mixed|WP_Error Response data, or the route's WP_Error with its code, message, and status intact.
 */
function eventon_apify_dispatch_rest_for_ability($method, $route, array $params) {
    $request = new WP_REST_Request($method, '/' . EVENTON_APIFY_NAMESPACE . $route);
    $request->set_query_params($params);

    $response = rest_do_request($request);
    if ($response->is_error()) {
        return $response->as_error();
    }

    return $response->get_data();
}

/**
 * Reduce a formatted event to the fields search results expose.
 *
 * @param mixed $event Formatted event payload.
 * @return array<string, mixed>
 */
function eventon_apify_get_event_summary($event) {
    $event = is_array($event) ? $event : array();
    $location = is_array($event['location'] ?? null) ? $event['location'] : array();
    $timezone = is_array($event['timezone'] ?? null) ? $event['timezone'] : array();

    return array(
        'id' => (int) ($event['id'] ?? 0),
        'title' => (string) ($event['title'] ?? ''),
        'slug' => (string) ($event['slug'] ?? ''),
        'status' => (string) ($event['status'] ?? ''),
        'link' => is_string($event['link'] ?? null) ? $event['link'] : '',
        'start_at' => (string) ($event['start_at'] ?? ''),
        'end_at' => (string) ($event['end_at'] ?? ''),
        'timezone' => (string) ($timezone['key'] ?? ''),
        'event_status' => (string) ($event['event_status'] ?? ''),
        'attendance_mode' => (string) ($event['attendance_mode'] ?? ''),
        'location_name' => (string) ($location['name'] ?? ''),
        'event_type' => array_values(array_map('strval', array_filter((array) ($event['event_type'] ?? array()), 'is_scalar'))),
    );
}

/**
 * Whether an ability belongs to this plugin.
 */
function eventon_apify_is_own_ability_name($name) {
    return str_starts_with(strtolower((string) $name), EVENTON_APIFY_ABILITY_PREFIX);
}

/**
 * Hide this plugin's abilities from non-administrators in every
 * wp_get_abilities() listing, including core's /wp-abilities/v1/abilities.
 *
 * Core lets any user with `read` list REST-exposed abilities. Execution is
 * still checked, but the schema manifest this plugin already publishes is
 * administrator-only, so ability discovery matches it.
 *
 * @param bool       $include Whether the ability is included.
 * @param WP_Ability $ability Ability being considered.
 */
function eventon_apify_filter_ability_visibility($include, $ability) {
    if ($include && $ability instanceof WP_Ability && eventon_apify_is_own_ability_name($ability->get_name())) {
        return current_user_can('manage_options');
    }

    return (bool) $include;
}

/**
 * Refuse the single-ability routes for non-administrators.
 *
 * GET /wp-abilities/v1/abilities/<name> reads the ability directly rather
 * than through wp_get_abilities(), so the listing filter does not cover it.
 *
 * @param mixed           $result  Response to replace the requested version with.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed
 */
function eventon_apify_restrict_ability_routes($result, $server, WP_REST_Request $request) {
    unset($server);

    // Case-folded: core matches routes case-insensitively.
    $route = strtolower((string) $request->get_route());
    if (!str_starts_with($route, '/wp-abilities/v1/abilities/' . EVENTON_APIFY_ABILITY_PREFIX)) {
        return $result;
    }

    if (current_user_can('manage_options')) {
        return $result;
    }

    return new WP_Error(
        'eventon_apify_ability_admin_only',
        __('EventON APIfy abilities are restricted to administrators.', 'eventon-apify'),
        array('status' => rest_authorization_required_code())
    );
}
