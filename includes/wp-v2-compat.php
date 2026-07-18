<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determine whether the standard wp/v2 compatibility layer is enabled.
 */
function eventon_apify_is_wp_v2_compatibility_enabled() {
    return (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, false);
}

/**
 * Whether wp/v2 compatibility output should be filtered for the current request.
 *
 * True only when compatibility mode is on and the current user is not an
 * administrator; administrators see the full compatibility surface.
 */
function eventon_apify_should_filter_wp_v2_compatibility_for_request() {
    return eventon_apify_is_wp_v2_compatibility_enabled() && !current_user_can('manage_options');
}

/**
 * Restrict the standard wp/v2 compatibility surface to administrators.
 *
 * @param mixed           $result  Response to replace the requested version with.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed
 */
function eventon_apify_restrict_wp_v2_compatibility_routes($result, $server, WP_REST_Request $request) {
    unset($server);

    if (!eventon_apify_is_wp_v2_compatibility_enabled()) {
        return $result;
    }

    if (!eventon_apify_is_wp_v2_compatibility_route($request->get_route())) {
        return $result;
    }

    if (current_user_can('manage_options')) {
        return $result;
    }

    return new WP_Error(
        'eventon_apify_wp_v2_admin_only',
        __('The EventON wp/v2 compatibility endpoints are restricted to administrators.', 'eventon-apify'),
        array('status' => rest_authorization_required_code())
    );
}

/**
 * Return true when the request route is part of the EventON wp/v2 compatibility surface.
 */
function eventon_apify_is_wp_v2_compatibility_route($route) {
    $route = (string) $route;

    $prefixes = array(
        '/wp/v2/ajde_events',
        '/wp/v2/types/ajde_events',
    );

    foreach (eventon_apify_get_wp_v2_compatibility_taxonomies() as $taxonomy) {
        $prefixes[] = '/wp/v2/' . $taxonomy;
        $prefixes[] = '/wp/v2/taxonomies/' . $taxonomy;
    }

    foreach ($prefixes as $prefix) {
        if ($route === $prefix || str_starts_with($route, $prefix . '/')) {
            return true;
        }
    }

    return false;
}

/**
 * Return the EventON taxonomies exposed through wp/v2 compatibility mode.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_compatibility_taxonomies() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $fallback = array('event_type', 'event_type_2', 'event_location', 'event_organizer');

    if (!post_type_exists('ajde_events')) {
        return $cache = $fallback;
    }

    $taxonomies = get_object_taxonomies('ajde_events', 'names');
    if (!is_array($taxonomies)) {
        return $cache = $fallback;
    }

    $taxonomies = array_values(
        array_filter(
            $taxonomies,
            static function ($taxonomy) {
                return is_string($taxonomy) && str_starts_with($taxonomy, 'event_');
            }
        )
    );

    return $cache = (!empty($taxonomies) ? $taxonomies : $fallback);
}

/**
 * Remove EventON wp/v2 compatibility routes from the REST index for non-admin users.
 *
 * @param array<string, mixed> $endpoints Registered REST endpoints.
 * @return array<string, mixed>
 */
function eventon_apify_filter_wp_v2_compatibility_endpoints($endpoints) {
    if (!eventon_apify_should_filter_wp_v2_compatibility_for_request()) {
        return $endpoints;
    }

    foreach (array_keys($endpoints) as $route) {
        if (eventon_apify_is_wp_v2_compatibility_route($route)) {
            unset($endpoints[$route]);
        }
    }

    return $endpoints;
}

/**
 * Remove EventON compatibility objects from shared wp/v2 collection responses for non-admin users.
 *
 * @param WP_HTTP_Response|mixed $response Response object.
 * @param WP_REST_Server         $server   Server instance.
 * @param WP_REST_Request        $request  Current request.
 * @return WP_HTTP_Response|mixed
 */
function eventon_apify_filter_wp_v2_compatibility_responses($response, $_server, WP_REST_Request $request) {

    if (
        !eventon_apify_should_filter_wp_v2_compatibility_for_request()
        || !($response instanceof WP_HTTP_Response)
    ) {
        return $response;
    }

    $route = (string) $request->get_route();
    $data = $response->get_data();

    if ($route === '/wp/v2/types' && is_array($data)) {
        unset($data['ajde_events']);
        $response->set_data(!empty($data) ? $data : (object) array());
        return $response;
    }

    if ($route === '/wp/v2/taxonomies' && is_array($data)) {
        foreach (eventon_apify_get_wp_v2_compatibility_taxonomies() as $taxonomy) {
            unset($data[$taxonomy]);
        }

        $response->set_data(!empty($data) ? $data : (object) array());
        return $response;
    }

    return $response;
}

/**
 * Exclude EventON posts from shared wp/v2 search queries for non-admin users.
 *
 * @param array<string, mixed> $query_args Search query arguments.
 * @return array<string, mixed>
 */
function eventon_apify_filter_wp_v2_compatibility_post_search_query(array $query_args) {

    if (!eventon_apify_should_filter_wp_v2_compatibility_for_request()) {
        return $query_args;
    }

    if (!isset($query_args['post_type'])) {
        return $query_args;
    }

    $post_types = (array) $query_args['post_type'];
    $post_types = array_values(array_diff($post_types, array('ajde_events')));
    $query_args['post_type'] = !empty($post_types) ? $post_types : array('__eventon_apify_no_results__');

    return $query_args;
}

/**
 * Exclude EventON taxonomies from shared wp/v2 search queries for non-admin users.
 *
 * @param array<string, mixed> $query_args Search query arguments.
 * @return array<string, mixed>
 */
function eventon_apify_filter_wp_v2_compatibility_term_search_query(array $query_args) {

    if (!eventon_apify_should_filter_wp_v2_compatibility_for_request()) {
        return $query_args;
    }

    if (!isset($query_args['taxonomy'])) {
        return $query_args;
    }

    $taxonomies = (array) $query_args['taxonomy'];
    $taxonomies = array_values(array_diff($taxonomies, eventon_apify_get_wp_v2_compatibility_taxonomies()));
    $query_args['taxonomy'] = !empty($taxonomies) ? $taxonomies : array('__eventon_apify_no_results__');

    return $query_args;
}
