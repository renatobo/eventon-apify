<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return a single EventON event.
 */
function eventon_apify_get_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('read');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $post = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($post)) {
        return $post;
    }

    return rest_ensure_response(eventon_apify_format_event($post));
}

/**
 * Apply a requested slug to a post array as post_name.
 *
 * A non-empty sanitized slug sets post_name; an absent or blank slug leaves the
 * array untouched so WordPress keeps the existing or auto-generated slug.
 *
 * @param array<string, mixed> $postarr WordPress post array for insert/update.
 * @param array<string, mixed> $params  Normalized request parameters.
 * @return array<string, mixed>
 */
function eventon_apify_apply_requested_slug(array $postarr, array $params) {
    if (!array_key_exists('slug', $params)) {
        return $postarr;
    }

    $slug = sanitize_title((string) $params['slug']);
    if ($slug !== '') {
        $postarr['post_name'] = $slug;
    }

    return $postarr;
}

/**
 * Create a new EventON event.
 */
function eventon_apify_create_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('create');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $params = eventon_apify_normalize_request_payload(eventon_apify_get_request_payload($request));
    $validation = eventon_apify_validate_event_payload($params, true);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $postarr = eventon_apify_apply_requested_slug(
        array(
            'post_type' => 'ajde_events',
            'post_title' => sanitize_text_field((string) $params['title']),
            'post_content' => wp_kses_post($params['description'] ?? ''),
            'post_excerpt' => sanitize_textarea_field((string) ($params['excerpt'] ?? '')),
            'post_status' => eventon_apify_get_sanitized_status($params['status'] ?? 'draft'),
        ),
        $params
    );

    $post_id = wp_insert_post($postarr, true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $write_result = EventON_APIfy_Event_Write_Coordinator::persist($post_id, $params, true);
    if (is_wp_error($write_result)) {
        return $write_result;
    }

    $response = rest_ensure_response(eventon_apify_format_event(get_post($post_id)));
    $response->set_status(201);

    return $response;
}

/**
 * Update an existing EventON event.
 */
function eventon_apify_update_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('update');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $post = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($post)) {
        return $post;
    }

    $params = eventon_apify_normalize_request_payload(eventon_apify_get_request_payload($request));
    $validation = eventon_apify_validate_event_payload($params, false, $post->ID);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $updates = array('ID' => $post->ID);

    if (array_key_exists('title', $params)) {
        $updates['post_title'] = sanitize_text_field((string) $params['title']);
    }

    if (array_key_exists('description', $params)) {
        $updates['post_content'] = wp_kses_post((string) $params['description']);
    }

    if (array_key_exists('excerpt', $params)) {
        $updates['post_excerpt'] = sanitize_textarea_field((string) $params['excerpt']);
    }

    if (array_key_exists('status', $params)) {
        $updates['post_status'] = eventon_apify_get_sanitized_status($params['status']);
    }

    $updates = eventon_apify_apply_requested_slug($updates, $params);

    $write_result = EventON_APIfy_Event_Write_Coordinator::persist($post->ID, $params, false, $updates);
    if (is_wp_error($write_result)) {
        return $write_result;
    }

    return rest_ensure_response(eventon_apify_format_event(get_post($post->ID)));
}

/**
 * Trash an EventON event.
 */
function eventon_apify_delete_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('delete');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $post = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($post)) {
        return $post;
    }

    $deleted = wp_trash_post($post->ID);
    if (!$deleted) {
        return new WP_Error(
            'eventon_apify_delete_failed',
            'The event could not be moved to the trash.',
            array('status' => 500)
        );
    }

    return rest_ensure_response(
        array(
            'deleted' => true,
            'id' => $post->ID,
            'title' => $post->post_title,
        )
    );
}

/**
 * Retrieve an EventON event post or return a 404 error.
 *
 * @return WP_Post|WP_Error
 */
function eventon_apify_get_event_post($post_id) {
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'ajde_events') {
        return new WP_Error(
            'eventon_apify_not_found',
            'Event not found.',
            array('status' => 404)
        );
    }

    return $post;
}
