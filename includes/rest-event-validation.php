<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate that an optional enum field, when present, holds an allowed value.
 *
 * Centralizes the present-check + sanitize_key + allowlist + 400 pattern shared
 * by the event_status, attendance_mode, time_extend_type, and repeat_frequency
 * fields.
 *
 * @param array<string, mixed> $params      Request body parameters.
 * @param string               $key         Parameter name to validate.
 * @param array<int, string>   $allowed     Allowed sanitized values.
 * @param string               $error_code  WP_Error code on failure.
 * @param string               $message     WP_Error message on failure.
 * @param bool                 $allow_empty When true, a blank value is accepted.
 * @return true|WP_Error
 */
function eventon_apify_validate_enum_field(array $params, $key, array $allowed, $error_code, $message, $allow_empty = false) {
    if (!array_key_exists($key, $params)) {
        return true;
    }

    if ($allow_empty && trim((string) $params[$key]) === '') {
        return true;
    }

    if (!in_array(sanitize_key((string) $params[$key]), $allowed, true)) {
        return new WP_Error($error_code, $message, array('status' => 400));
    }

    return true;
}

/**
 * Request keys that participate in datetime resolution. When none are present
 * on an update, the stored datetime meta must be left completely untouched:
 * rewriting it rederives inputs from boundary-extended timestamps and
 * permanently corrupts dl/ml/yl events.
 *
 * @return array<int, string>
 */
function eventon_apify_get_datetime_input_keys() {
    return array(
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'timezone_key',
        'time_extend_type',
        'hide_end_time',
        'span_hidden_end',
        'virtual_end_enabled',
        'virtual_end_date',
        'virtual_end_time',
    );
}

/**
 * Whether the request carries any datetime-affecting input.
 *
 * @param array<string, mixed> $params Normalized request parameters.
 */
function eventon_apify_has_datetime_input(array $params) {
    return eventon_apify_array_has_any($params, eventon_apify_get_datetime_input_keys());
}

/**
 * Validate request payload fields before create/update.
 *
 * @param array<string, mixed> $params    Request body parameters.
 * @param bool                 $is_create Whether this is a create request.
 * @param int                  $post_id   Existing post ID for updates.
 * @return true|WP_Error
 */
function eventon_apify_validate_event_payload(array $params, $is_create, $post_id = 0) {
    if ($is_create && empty($params['title'])) {
        return new WP_Error(
            'eventon_apify_missing_title',
            'Event title is required.',
            array('status' => 400)
        );
    }

    if (array_key_exists('title', $params) && trim((string) $params['title']) === '') {
        return new WP_Error(
            'eventon_apify_invalid_title',
            'title cannot be empty.',
            array('status' => 400)
        );
    }

    if ($is_create && empty($params['start_date'])) {
        return new WP_Error(
            'eventon_apify_missing_start_date',
            'start_date is required when creating an EventON event.',
            array('status' => 400)
        );
    }

    if (array_key_exists('status', $params) && eventon_apify_get_sanitized_status($params['status']) === '') {
        return new WP_Error(
            'eventon_apify_invalid_status',
            'status must be one of: publish, draft, private, pending, future.',
            array('status' => 400)
        );
    }

    if (array_key_exists('featured_media', $params)) {
        $featured_media_validation = eventon_apify_validate_featured_media_input($params['featured_media']);
        if (is_wp_error($featured_media_validation)) {
            return $featured_media_validation;
        }
    }

    foreach (array('event_color', 'event_color_secondary') as $color_key) {
        if (array_key_exists($color_key, $params) && eventon_apify_normalize_color_input($params[$color_key]) === null) {
            return new WP_Error(
                'eventon_apify_invalid_color',
                $color_key . ' must be a valid hex color such as #ff0000 or ff0000.',
                array('status' => 400)
            );
        }
    }

    $event_status_check = eventon_apify_validate_enum_field(
        $params,
        'event_status',
        eventon_apify_get_allowed_event_statuses(),
        'eventon_apify_invalid_event_status',
        'event_status must be one of: ' . implode(', ', eventon_apify_get_allowed_event_statuses()) . '.'
    );
    if (is_wp_error($event_status_check)) {
        return $event_status_check;
    }

    $attendance_mode_check = eventon_apify_validate_enum_field(
        $params,
        'attendance_mode',
        eventon_apify_get_allowed_attendance_modes(),
        'eventon_apify_invalid_attendance_mode',
        'attendance_mode must be one of: offline, online, mixed.'
    );
    if (is_wp_error($attendance_mode_check)) {
        return $attendance_mode_check;
    }

    $time_extend_type_check = eventon_apify_validate_enum_field(
        $params,
        'time_extend_type',
        array('n', 'dl', 'ml', 'yl'),
        'eventon_apify_invalid_time_extend_type',
        'time_extend_type must be one of: n, dl, ml, yl.'
    );
    if (is_wp_error($time_extend_type_check)) {
        return $time_extend_type_check;
    }

    if (array_key_exists('timezone_key', $params) && trim((string) $params['timezone_key']) !== '' && !eventon_apify_is_valid_timezone((string) $params['timezone_key'])) {
        return new WP_Error(
            'eventon_apify_invalid_timezone',
            'timezone_key must be a valid PHP timezone identifier.',
            array('status' => 400)
        );
    }

    if (array_key_exists('gradient_angle', $params) && trim((string) $params['gradient_angle']) !== '' && !is_numeric($params['gradient_angle'])) {
        return new WP_Error(
            'eventon_apify_invalid_gradient_angle',
            'gradient_angle must be numeric.',
            array('status' => 400)
        );
    }

    $coordinate_ranges = array('location_lat' => 90.0, 'location_lon' => 180.0);
    foreach ($coordinate_ranges as $coord_key => $range) {
        if (!array_key_exists($coord_key, $params) || trim((string) $params[$coord_key]) === '') {
            continue;
        }

        if (!is_numeric($params[$coord_key]) || abs((float) $params[$coord_key]) > $range) {
            return new WP_Error(
                'eventon_apify_invalid_location_coordinate',
                $coord_key . ' must be numeric, between -' . $range . ' and ' . $range . '.',
                array('status' => 400)
            );
        }
    }

    if (array_key_exists('virtual_moderator_id', $params) && trim((string) $params['virtual_moderator_id']) !== '') {
        if (!is_numeric($params['virtual_moderator_id']) || (int) $params['virtual_moderator_id'] < 0) {
            return new WP_Error(
                'eventon_apify_invalid_moderator_id',
                'virtual.moderator_id must be a non-negative user ID.',
                array('status' => 400)
            );
        }
    }

    if (array_key_exists('location_email', $params) && trim((string) $params['location_email']) !== '' && !is_email((string) $params['location_email'])) {
        return new WP_Error(
            'eventon_apify_invalid_email',
            'location.email must be a valid email address.',
            array('status' => 400)
        );
    }

    foreach (array('learn_more_link', 'location_link', 'virtual_url') as $url_key) {
        if (array_key_exists($url_key, $params) && !eventon_apify_validate_url($params[$url_key])) {
            return new WP_Error(
                'eventon_apify_invalid_url',
                $url_key . ' must be a valid absolute URL.',
                array('status' => 400)
            );
        }
    }

    if (array_key_exists('interaction_url', $params) && !eventon_apify_validate_url($params['interaction_url'])) {
        return new WP_Error(
            'eventon_apify_invalid_interaction_url',
            'interaction.url must be a valid absolute URL.',
            array('status' => 400)
        );
    }

    if (array_key_exists('interaction_mode', $params) && !eventon_apify_is_known_interaction_mode($params['interaction_mode'])) {
        return new WP_Error(
            'eventon_apify_invalid_interaction_mode',
            'interaction.mode must be one of: ' . implode(', ', eventon_apify_get_allowed_interaction_modes()) . '.',
            array('status' => 400)
        );
    }

    if (array_key_exists('organizers', $params) && is_array($params['organizers'])) {
        foreach ($params['organizers'] as $organizer) {
            if (!is_array($organizer)) {
                continue;
            }

            if (isset($organizer['link']) && !eventon_apify_validate_url($organizer['link'])) {
                return new WP_Error(
                    'eventon_apify_invalid_organizer_url',
                    'organizer.link must be a valid absolute URL.',
                    array('status' => 400)
                );
            }

            if (isset($organizer['email']) && trim((string) $organizer['email']) !== '' && !is_email((string) $organizer['email'])) {
                return new WP_Error(
                    'eventon_apify_invalid_email',
                    'organizer.email must be a valid email address.',
                    array('status' => 400)
                );
            }
        }
    }

    $repeat_frequency_check = eventon_apify_validate_enum_field(
        $params,
        'repeat_frequency',
        eventon_apify_get_allowed_repeat_frequencies(),
        'eventon_apify_invalid_repeat_frequency',
        'repeat.frequency must be one of: ' . implode(', ', eventon_apify_get_allowed_repeat_frequencies()) . '.',
        true
    );
    if (is_wp_error($repeat_frequency_check)) {
        return $repeat_frequency_check;
    }

    if (array_key_exists('repeat_intervals', $params)) {
        $repeat_timezone_state = eventon_apify_resolve_datetime_inputs($params, $post_id);
        $intervals = eventon_apify_normalize_repeat_intervals_input(
            $params['repeat_intervals'],
            (string) ($repeat_timezone_state['timezone_key'] ?? '')
        );

        if (is_wp_error($intervals)) {
            return $intervals;
        }
    }

    $datetime_validation = eventon_apify_validate_datetime_fields($params, $post_id);
    if (is_wp_error($datetime_validation)) {
        return $datetime_validation;
    }

    return true;
}

/**
 * Validate date/time combinations before saving.
 *
 * @param array<string, mixed> $params  Request body parameters.
 * @param int                  $post_id Existing post ID when updating.
 * @return true|WP_Error
 */
function eventon_apify_validate_datetime_fields(array $params, $post_id = 0) {
    if ($post_id && !eventon_apify_has_datetime_input($params)) {
        return true;
    }

    $state = eventon_apify_resolve_datetime_inputs($params, $post_id);
    $timezone_key = $state['timezone_key'];

    if ($state['start_date'] === '') {
        return new WP_Error(
            'eventon_apify_missing_start_date',
            'start_date is required for EventON events.',
            array('status' => 400)
        );
    }

    if ($state['start_time'] !== '' && !eventon_apify_split_time_string($state['start_time'])) {
        return new WP_Error(
            'eventon_apify_invalid_start_time',
            'start_time must use HH:MM in 24-hour format.',
            array('status' => 400)
        );
    }

    $start_timestamp = eventon_apify_build_timestamp($state['start_date'], $state['start_time'], $timezone_key);
    if ($start_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_start_datetime',
            'The start_date/start_time combination could not be parsed.',
            array('status' => 400)
        );
    }

    if ($state['end_time'] !== '' && !eventon_apify_split_time_string($state['end_time'])) {
        return new WP_Error(
            'eventon_apify_invalid_end_time',
            'end_time must use HH:MM in 24-hour format.',
            array('status' => 400)
        );
    }

    $end_timestamp = eventon_apify_build_timestamp($state['end_date'], $state['end_time'], $timezone_key);
    if ($end_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_end_datetime',
            'The end_date/end_time combination could not be parsed.',
            array('status' => 400)
        );
    }

    if ($end_timestamp < $start_timestamp) {
        return new WP_Error(
            'eventon_apify_invalid_datetime_range',
            'The end date/time must be on or after the start date/time.',
            array('status' => 400)
        );
    }

    if ($state['virtual_end_enabled']) {
        if ($state['virtual_end_date'] === '') {
            return new WP_Error(
                'eventon_apify_missing_virtual_end',
                'virtual.end_date is required when virtual end time is enabled.',
                array('status' => 400)
            );
        }

        if ($state['virtual_end_time'] !== '' && !eventon_apify_split_time_string($state['virtual_end_time'])) {
            return new WP_Error(
                'eventon_apify_invalid_virtual_end_time',
                'virtual.end_time must use HH:MM in 24-hour format.',
                array('status' => 400)
            );
        }

        $virtual_end_timestamp = eventon_apify_build_timestamp($state['virtual_end_date'], $state['virtual_end_time'], $timezone_key);
        if ($virtual_end_timestamp === null) {
            return new WP_Error(
                'eventon_apify_invalid_virtual_end_datetime',
                'The virtual end date/time could not be parsed.',
                array('status' => 400)
            );
        }

        if ($virtual_end_timestamp < $start_timestamp) {
            return new WP_Error(
                'eventon_apify_invalid_virtual_end_datetime',
                'The virtual end date/time must be on or after the event start.',
                array('status' => 400)
            );
        }
    }

    return true;
}

/**
 * Build the merged datetime state for a create/update request.
 *
 * @param array<string, mixed> $params Request parameters.
 * @return array<string, mixed>
 */
function eventon_apify_resolve_datetime_inputs(array $params, $post_id = 0) {
    $state = $post_id ? eventon_apify_get_existing_datetime_state($post_id) : array(
        'start_date' => '',
        'start_time' => '',
        'end_date' => '',
        'end_time' => '',
        'virtual_end_date' => '',
        'virtual_end_time' => '',
        'timezone_key' => wp_timezone_string() ?: 'UTC',
        'time_extend_type' => 'n',
        'hide_end_time' => false,
        'span_hidden_end' => false,
        'virtual_end_enabled' => false,
    );

    foreach (array('start_date', 'start_time', 'end_date', 'end_time', 'virtual_end_date', 'virtual_end_time') as $field) {
        if (array_key_exists($field, $params)) {
            $state[$field] = sanitize_text_field((string) $params[$field]);
        }
    }

    if (array_key_exists('timezone_key', $params) && trim((string) $params['timezone_key']) !== '') {
        $state['timezone_key'] = sanitize_text_field((string) $params['timezone_key']);
    }

    if (array_key_exists('time_extend_type', $params)) {
        $state['time_extend_type'] = sanitize_key((string) $params['time_extend_type']);
    }

    foreach (array('hide_end_time', 'span_hidden_end', 'virtual_end_enabled') as $flag) {
        if (array_key_exists($flag, $params)) {
            $state[$flag] = eventon_apify_is_yes($params[$flag]);
        }
    }

    if ($state['end_date'] === '' && $state['start_date'] !== '') {
        $state['end_date'] = $state['start_date'];
    }

    if ($state['start_time'] === '') {
        $state['start_time'] = '00:00';
    }

    if ($state['end_time'] === '' && $state['start_time'] !== '') {
        $state['end_time'] = $state['start_time'];
    }

    if ($state['hide_end_time'] && !$state['span_hidden_end']) {
        $state['end_date'] = $state['start_date'];
        $state['end_time'] = '23:59';
    }

    if ($state['virtual_end_enabled'] && $state['virtual_end_date'] === '') {
        $state['virtual_end_date'] = $state['end_date'];
        $state['virtual_end_time'] = $state['end_time'];
    }

    return $state;
}

/**
 * Read existing datetime configuration from EventON meta.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_existing_datetime_state($post_id) {
    $meta = get_post_meta($post_id);
    $timezone_key = eventon_apify_get_timezone_key_from_meta($meta);

    // evcal_srow/evcal_erow hold the user's base wall clock in EventON's
    // wall-as-UTC space, unaffected by dl/ml/yl boundary extension; reading
    // the extended _unix_*_ev values here would rederive 00:00/23:59 and
    // permanently overwrite the real times on save. gmdate() reads the wall
    // clock back verbatim.
    $start_wall = eventon_apify_get_meta_int($meta, 'evcal_srow');
    $end_wall = eventon_apify_get_meta_int($meta, 'evcal_erow');
    $virtual_end_wall = eventon_apify_get_meta_int($meta, '_evo_virtual_erow');

    $start_timestamp = eventon_apify_get_meta_int($meta, '_unix_start_ev');
    $end_timestamp = eventon_apify_get_meta_int($meta, '_unix_end_ev');
    $virtual_end_timestamp = eventon_apify_get_meta_int($meta, '_unix_vend_ev');

    $resolve = static function ($wall, $real) use ($timezone_key) {
        if ($wall) {
            return array(gmdate('Y-m-d', $wall), gmdate('H:i', $wall));
        }
        if ($real) {
            return array(
                eventon_apify_format_timestamp_for_timezone($real, $timezone_key, 'Y-m-d'),
                eventon_apify_format_timestamp_for_timezone($real, $timezone_key, 'H:i'),
            );
        }
        return array('', '');
    };

    list($start_date, $start_time) = $resolve($start_wall, $start_timestamp);
    list($end_date, $end_time) = $resolve($end_wall, $end_timestamp);
    list($virtual_end_date, $virtual_end_time) = $resolve($virtual_end_wall, $virtual_end_timestamp);

    return array(
        'start_date' => $start_date,
        'start_time' => $start_time,
        'end_date' => $end_date,
        'end_time' => $end_time,
        'virtual_end_date' => $virtual_end_date,
        'virtual_end_time' => $virtual_end_time,
        'timezone_key' => $timezone_key,
        'time_extend_type' => eventon_apify_get_meta_text($meta, '_time_ext_type') ?: 'n',
        'hide_end_time' => eventon_apify_get_yes_no_flag($meta, 'evo_hide_endtime'),
        'span_hidden_end' => eventon_apify_get_yes_no_flag($meta, 'evo_span_hidden_end'),
        'virtual_end_enabled' => eventon_apify_get_yes_no_flag($meta, '_evo_virtual_endtime'),
    );
}
