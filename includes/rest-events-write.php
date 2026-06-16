<?php

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

    $post_id = wp_insert_post(
        array(
            'post_type' => 'ajde_events',
            'post_title' => sanitize_text_field((string) $params['title']),
            'post_content' => wp_kses_post($params['description'] ?? ''),
            'post_excerpt' => sanitize_textarea_field((string) ($params['excerpt'] ?? '')),
            'post_status' => eventon_apify_get_sanitized_status($params['status'] ?? 'draft'),
        ),
        true
    );

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $meta_result = eventon_apify_save_event_meta($post_id, $params);
    if (is_wp_error($meta_result)) {
        wp_delete_post($post_id, true);
        return $meta_result;
    }

    $term_result = eventon_apify_save_event_terms($post_id, $params);
    if (is_wp_error($term_result)) {
        wp_delete_post($post_id, true);
        return $term_result;
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

    if (count($updates) > 1) {
        $result = wp_update_post($updates, true);
        if (is_wp_error($result)) {
            return $result;
        }
    }

    $meta_result = eventon_apify_save_event_meta($post->ID, $params);
    if (is_wp_error($meta_result)) {
        return $meta_result;
    }

    $term_result = eventon_apify_save_event_terms($post->ID, $params);
    if (is_wp_error($term_result)) {
        return $term_result;
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
