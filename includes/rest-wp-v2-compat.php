<?php

/**
 * Expose EventON's event post type on the standard wp/v2 REST API when compatibility mode is enabled.
 *
 * @param array<string, mixed> $args      Registered post type args.
 * @param string               $post_type Post type key.
 * @return array<string, mixed>
 */
function eventon_apify_filter_post_type_args_for_wp_v2_compat(array $args, $post_type) {
    if (!eventon_apify_is_wp_v2_compatibility_enabled() || $post_type !== 'ajde_events') {
        return $args;
    }

    if (!current_user_can('manage_options')) {
        $args['show_in_rest'] = false;
        return $args;
    }

    $args['show_in_rest'] = true;
    $args['rest_base'] = 'ajde_events';

    if (empty($args['rest_controller_class'])) {
        $args['rest_controller_class'] = 'WP_REST_Posts_Controller';
    }

    return $args;
}

/**
 * Expose the main EventON taxonomies on the standard wp/v2 REST API when compatibility mode is enabled.
 *
 * @param array<string, mixed> $args     Registered taxonomy args.
 * @param string               $taxonomy Taxonomy key.
 * @return array<string, mixed>
 */
function eventon_apify_filter_taxonomy_args_for_wp_v2_compat(array $args, $taxonomy) {
    if (!eventon_apify_is_wp_v2_compatibility_enabled()) {
        return $args;
    }

    if (!in_array($taxonomy, array('event_type', 'event_location', 'event_organizer'), true)) {
        return $args;
    }

    if (!current_user_can('manage_options')) {
        $args['show_in_rest'] = false;
        return $args;
    }

    $args['show_in_rest'] = true;
    $args['rest_base'] = $taxonomy;

    if (empty($args['rest_controller_class'])) {
        $args['rest_controller_class'] = 'WP_REST_Terms_Controller';
    }

    return $args;
}

/**
 * Register additional wp/v2 fields so generic WordPress clients can work with EventON data.
 */
function eventon_apify_register_wp_v2_compatibility_fields() {
    if (!eventon_apify_is_wp_v2_compatibility_enabled() || !current_user_can('manage_options')) {
        return;
    }

    $read_only_fields = eventon_apify_get_wp_v2_read_only_field_names();

    foreach ($read_only_fields as $field_name) {
        register_rest_field(
            'ajde_events',
            $field_name,
            array(
                'get_callback' => 'eventon_apify_get_wp_v2_rest_field',
            )
        );
    }

    foreach (eventon_apify_get_wp_v2_mutable_field_names() as $field_name) {
        register_rest_field(
            'ajde_events',
            $field_name,
            array(
                'get_callback' => 'eventon_apify_get_wp_v2_rest_field',
                'update_callback' => 'eventon_apify_update_wp_v2_rest_field',
            )
        );
    }

    foreach (eventon_apify_get_wp_v2_wrapper_field_names() as $field_name) {
        register_rest_field(
            'ajde_events',
            $field_name,
            array(
                'update_callback' => 'eventon_apify_update_wp_v2_wrapped_rest_field',
            )
        );
    }
}

/**
 * Return the shared EventON contract fields used by wp/v2 compatibility and MCP discovery.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_contract_field_names_for_wp_v2_mode($mode) {
    $field_names = array();

    foreach (eventon_apify_get_contract_field_definitions() as $field_name => $definition) {
        if (($definition['wp_v2_field_mode'] ?? '') === $mode) {
            $field_names[] = $field_name;
        }
    }

    return $field_names;
}

/**
 * Return read-only EventON fields exposed on wp/v2 ajde_events.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_read_only_field_names() {
    return eventon_apify_get_contract_field_names_for_wp_v2_mode('read_only');
}

/**
 * Return mutable EventON fields exposed on wp/v2 ajde_events.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_mutable_field_names() {
    return eventon_apify_get_contract_field_names_for_wp_v2_mode('additional');
}

/**
 * Return wrapper field names that generic wp/v2 clients may use for EventON payloads.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_wrapper_field_names() {
    return array('custom_fields', 'fields');
}

/**
 * Return an EventON payload safe for the shared wp/v2 compatibility surface.
 *
 * @return array<string, mixed>
 */
function eventon_apify_format_wp_v2_event(WP_Post $post) {
    $event = eventon_apify_format_event($post);

    if (isset($event['location']) && is_array($event['location'])) {
        unset($event['location']['email'], $event['location']['phone']);
    }

    if (isset($event['organizers']) && is_array($event['organizers'])) {
        foreach ($event['organizers'] as $index => $organizer) {
            if (!is_array($organizer)) {
                continue;
            }

            unset(
                $event['organizers'][$index]['email'],
                $event['organizers'][$index]['phone'],
                $event['organizers'][$index]['address']
            );
        }
    }

    if (isset($event['virtual']) && is_array($event['virtual'])) {
        unset(
            $event['virtual']['url'],
            $event['virtual']['password'],
            $event['virtual']['embed'],
            $event['virtual']['other'],
            $event['virtual']['moderator_id'],
            $event['virtual']['after_content']
        );
    }

    if (isset($event['rsvp']) && is_array($event['rsvp'])) {
        unset($event['rsvp']['additional_emails']);
    }

    return $event;
}

/**
 * Read an EventON compatibility field from the standard wp/v2 endpoint.
 *
 * @param array<string, mixed>|object $object REST callback object.
 * @return mixed
 */
function eventon_apify_get_wp_v2_rest_field($object, $field_name) {
    if (!current_user_can('manage_options')) {
        return null;
    }

    $post_id = eventon_apify_get_rest_callback_post_id($object);

    if (!$post_id) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'ajde_events') {
        return null;
    }

    $event = eventon_apify_format_wp_v2_event($post);

    return array_key_exists($field_name, $event) ? $event[$field_name] : null;
}

/**
 * Update an EventON compatibility field from the standard wp/v2 endpoint.
 *
 * @param mixed                       $value      Submitted field value.
 * @param array<string, mixed>|object $object     REST callback object.
 * @param string                      $field_name Field name.
 * @param WP_REST_Request|null        $request    Current REST request.
 * @return true|WP_Error
 */
function eventon_apify_update_wp_v2_rest_field($value, $object, $field_name, $request = null) {
    if (!current_user_can('manage_options')) {
        return new WP_Error(
            'eventon_apify_wp_v2_admin_only',
            'The EventON wp/v2 compatibility endpoints are restricted to administrators.',
            array('status' => rest_authorization_required_code())
        );
    }

    $post_id = eventon_apify_get_rest_callback_post_id($object);

    if (!$post_id) {
        return new WP_Error(
            'eventon_apify_missing_rest_post',
            'Unable to resolve the EventON event being updated.',
            array('status' => 400)
        );
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'ajde_events') {
        return new WP_Error(
            'eventon_apify_invalid_rest_post',
            'The standard wp/v2 compatibility field can only be used with ajde_events.',
            array('status' => 400)
        );
    }

    $params = array($field_name => $value);

    if ($request instanceof WP_REST_Request) {
        $params = eventon_apify_extract_wp_v2_event_request_payload($request, $params);
    }

    $params = eventon_apify_normalize_request_payload($params);
    $validation = eventon_apify_validate_event_payload($params, false, $post_id);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $meta_result = eventon_apify_save_event_meta($post_id, $params);
    if (is_wp_error($meta_result)) {
        return $meta_result;
    }

    $term_result = eventon_apify_save_event_terms($post_id, $params);
    if (is_wp_error($term_result)) {
        return $term_result;
    }

    return true;
}

/**
 * Update EventON compatibility data when a client sends wrapper objects such as custom_fields or fields.
 *
 * @param mixed                       $value      Submitted wrapper object.
 * @param array<string, mixed>|object $object     REST callback object.
 * @param string                      $field_name Field name.
 * @param WP_REST_Request|null        $request    Current REST request.
 * @return true|WP_Error
 */
function eventon_apify_update_wp_v2_wrapped_rest_field($value, $object, $field_name, $request = null) {
    if (!is_array($value)) {
        return new WP_Error(
            'eventon_apify_invalid_wrapped_fields',
            $field_name . ' must be an object of EventON field values.',
            array('status' => 400)
        );
    }

    return eventon_apify_update_wp_v2_rest_field($value, $object, $field_name, $request instanceof WP_REST_Request ? $request : null);
}

/**
 * Extract all EventON-specific wp/v2 fields present in the request.
 *
 * @param array<string, mixed> $fallback Single-field fallback payload.
 * @return array<string, mixed>
 */
function eventon_apify_extract_wp_v2_event_request_payload(WP_REST_Request $request, array $fallback = array()) {
    $payload = array();

    foreach (eventon_apify_get_wp_v2_mutable_field_names() as $field_name) {
        if ($request->has_param($field_name)) {
            $payload[$field_name] = $request->get_param($field_name);
        }
    }

    return !empty($payload) ? $payload : $fallback;
}
