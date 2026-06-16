<?php

/**
 * Persist the plain-text EventON meta fields from request parameters.
 *
 * URL-bearing keys are escaped with esc_url_raw; all others are run through
 * sanitize_text_field. Empty values delete the meta key.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request body parameters.
 */
function eventon_apify_save_event_text_meta($post_id, array $params) {
    $text_meta_map = array(
        'event_subtitle' => 'evcal_subtitle',
        'event_excerpt' => 'evo_excerpt',
        'timezone_text' => 'evo_event_timezone',
        'learn_more_link' => 'evcal_lmlink',
        'interaction_url' => 'evcal_exlink',
        'virtual_type' => '_virtual_type',
        'virtual_password' => '_vir_pass',
        'virtual_other' => '_vir_other',
        'virtual_show' => '_vir_show',
        'virtual_after_content_when' => '_vir_after_content_when',
        'seo_offer_price' => '_seo_offer_price',
        'seo_offer_currency' => '_seo_offer_currency',
        'faq_subheader' => '_evo_faq_subheader',
    );

    foreach ($text_meta_map as $request_key => $meta_key) {
        if (!array_key_exists($request_key, $params)) {
            continue;
        }

        $value = (string) $params[$request_key];
        if (in_array($request_key, array('learn_more_link', 'interaction_url'), true)) {
            $value = esc_url_raw($value);
        } else {
            $value = sanitize_text_field($value);
        }

        eventon_apify_update_or_delete_meta($post_id, $meta_key, $value);
    }
}

/**
 * Persist the EventON yes/no flag meta fields from request parameters.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request body parameters.
 */
function eventon_apify_save_event_flag_meta($post_id, array $params) {
    $flag_meta_map = array(
        'featured' => '_featured',
        'completed' => '_completed',
        'exclude_from_calendar' => 'evo_exclude_ev',
        'loggedin_only' => '_onlyloggedin',
        'hide_end_time' => 'evo_hide_endtime',
        'span_hidden_end' => 'evo_span_hidden_end',
        'hide_location_name' => 'evcal_hide_locname',
        'hide_organizer_card' => 'evo_evcrd_field_org',
        'generate_gmap' => 'evcal_gmap_gen',
        'open_google_maps_link' => 'evcal_gmap_link',
        'location_access_loggedin_only' => 'evo_access_control_location',
        'location_info_over_image' => 'evcal_name_over_img',
        'organizer_as_performer' => 'evo_event_org_as_perf',
        'virtual_enabled' => '_virtual',
        'virtual_hide_when_live' => '_vir_hide',
        'virtual_disable_redirect_hiding' => '_vir_nohiding',
        'virtual_end_enabled' => '_evo_virtual_endtime',
    );

    foreach ($flag_meta_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            eventon_apify_update_yes_no_meta($post_id, $meta_key, $params[$request_key]);
        }
    }
}

/**
 * Save EventON meta fields from request parameters.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request body parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_event_meta($post_id, array $params) {
    $datetime_result = eventon_apify_save_datetime_meta($post_id, $params);
    if (is_wp_error($datetime_result)) {
        return $datetime_result;
    }

    if (array_key_exists('featured_media', $params)) {
        $featured_media_result = eventon_apify_save_featured_media($post_id, $params['featured_media']);
        if (is_wp_error($featured_media_result)) {
            return $featured_media_result;
        }
    }

    eventon_apify_save_event_text_meta($post_id, $params);

    if (array_key_exists('event_status', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_status', sanitize_key((string) $params['event_status']));
    } elseif ('' === get_post_meta($post_id, '_status', true)) {
        update_post_meta($post_id, '_status', 'scheduled');
    }

    $current_event_status = sanitize_key((string) get_post_meta($post_id, '_status', true));
    $current_event_status = in_array($current_event_status, eventon_apify_get_allowed_event_statuses(), true) ? $current_event_status : 'scheduled';

    if (array_key_exists('status_reason', $params) || array_key_exists('event_status', $params)) {
        foreach (eventon_apify_get_allowed_event_statuses() as $status_key) {
            $reason_meta_key = '_' . $status_key . '_reason';

            if ($status_key === $current_event_status && array_key_exists('status_reason', $params) && $current_event_status !== 'scheduled') {
                eventon_apify_update_or_delete_meta($post_id, $reason_meta_key, sanitize_textarea_field((string) $params['status_reason']));
                continue;
            }

            delete_post_meta($post_id, $reason_meta_key);
        }
    }

    if (array_key_exists('attendance_mode', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_attendance_mode', sanitize_key((string) $params['attendance_mode']));
    }

    if (array_key_exists('time_extend_type', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_time_ext_type', sanitize_key((string) $params['time_extend_type']));
    }

    if (array_key_exists('timezone_key', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_evo_tz', sanitize_text_field((string) $params['timezone_key']));
    }

    if (array_key_exists('event_color', $params)) {
        $color = eventon_apify_normalize_color_input($params['event_color']);
        eventon_apify_update_or_delete_meta($post_id, 'evcal_event_color', $color ?: '');
    }

    if (array_key_exists('event_color_secondary', $params)) {
        $color = eventon_apify_normalize_color_input($params['event_color_secondary']);
        eventon_apify_update_or_delete_meta($post_id, 'evcal_event_color2', $color ?: '');
    }

    if (array_key_exists('gradient_enabled', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evo_event_grad_colors', $params['gradient_enabled']);
    }

    if (array_key_exists('gradient_angle', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_evo_event_grad_ang', sanitize_text_field((string) $params['gradient_angle']));
    }

    if (array_key_exists('learn_more_link_target', $params)) {
        eventon_apify_update_yes_no_meta($post_id, 'evcal_lmlink_target', $params['learn_more_link_target']);
    }

    if (array_key_exists('interaction_mode', $params)) {
        $interaction_mode = eventon_apify_normalize_interaction_mode($params['interaction_mode']);
        eventon_apify_update_or_delete_meta($post_id, '_evcal_exlink_option', eventon_apify_map_interaction_mode_to_code($interaction_mode));

        if ($interaction_mode === 'open_event_page' && !array_key_exists('interaction_url', $params)) {
            eventon_apify_update_or_delete_meta($post_id, 'evcal_exlink', get_permalink($post_id) ?: '');
        }

        if (in_array($interaction_mode, array('do_nothing', 'slide_down_eventcard'), true) && !array_key_exists('interaction_url', $params)) {
            delete_post_meta($post_id, 'evcal_exlink');
        }
    }

    eventon_apify_save_event_flag_meta($post_id, $params);

    if (array_key_exists('interaction_new_window', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evcal_exlink_target', $params['interaction_new_window']);
    }

    $health_result = eventon_apify_save_health_meta($post_id, $params);
    if (is_wp_error($health_result)) {
        return $health_result;
    }

    $related_result = eventon_apify_save_related_events_meta($post_id, $params);
    if (is_wp_error($related_result)) {
        return $related_result;
    }

    if (array_key_exists('virtual_url', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_vir_url', esc_url_raw((string) $params['virtual_url']));
    }

    if (array_key_exists('virtual_embed', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_vir_embed', wp_kses_post((string) $params['virtual_embed']));
    }

    if (array_key_exists('virtual_after_content', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_vir_after_content', wp_kses_post((string) $params['virtual_after_content']));
    }

    if (array_key_exists('virtual_moderator_id', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_mod', (string) absint($params['virtual_moderator_id']));
    }

    $repeat_result = eventon_apify_save_repeat_meta($post_id, $params);
    if (is_wp_error($repeat_result)) {
        return $repeat_result;
    }

    $rsvp_result = eventon_apify_save_rsvp_meta($post_id, $params);
    if (is_wp_error($rsvp_result)) {
        return $rsvp_result;
    }

    return true;
}

/**
 * Save EventON health guideline meta.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_health_meta($post_id, array $params) {
    $edata_map = array(
        'health_mask_required' => '_health_mask',
        'health_temperature_check' => '_health_temp',
        'health_physical_distance' => '_health_pdis',
        'health_sanitized' => '_health_san',
        'health_outdoor' => '_health_out',
        'health_vaccination_required' => '_health_vac',
    );

    $existing_edata = eventon_apify_get_event_edata($post_id);
    $updated = false;

    if (array_key_exists('health_enabled', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_health', $params['health_enabled']);
    }

    foreach ($edata_map as $request_key => $edata_key) {
        if (!array_key_exists($request_key, $params)) {
            continue;
        }

        $existing_edata[$edata_key] = eventon_apify_to_yes_no($params[$request_key]);
        $updated = true;
    }

    if (array_key_exists('health_other', $params)) {
        $existing_edata['_health_other'] = sanitize_textarea_field((string) $params['health_other']);
        $updated = true;
    }

    if ($updated) {
        update_post_meta($post_id, '_edata', $existing_edata);
    }

    return true;
}

/**
 * Save EventON related events meta.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_related_events_meta($post_id, array $params) {
    if (array_key_exists('related_hide_image', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evo_relevs_hide_img', $params['related_hide_image']);
    }

    if (array_key_exists('related_hide_past', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evo_relevs_hide_past', $params['related_hide_past']);
    }

    if (!array_key_exists('related_items', $params)) {
        return true;
    }

    if (!is_array($params['related_items']) || empty($params['related_items'])) {
        delete_post_meta($post_id, 'ev_releated');
        return true;
    }

    $payload = array();
    foreach ($params['related_items'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        $event_id = absint(eventon_apify_array_get($item, array('event_id', 'id'), 0));
        if ($event_id <= 0) {
            return new WP_Error(
                'eventon_apify_invalid_related_event',
                'Each related event must include a valid event_id.',
                array('status' => 400)
            );
        }

        $related_post = get_post($event_id);
        if (!$related_post instanceof WP_Post || $related_post->post_type !== 'ajde_events') {
            return new WP_Error(
                'eventon_apify_invalid_related_event',
                'related_events.items[].event_id must reference an ajde_events post.',
                array('status' => 400)
            );
        }

        $repeat_interval = absint(eventon_apify_array_get($item, array('repeat_interval', 'ri'), 0));
        $title = sanitize_text_field((string) eventon_apify_array_get($item, array('title', 'name'), $related_post->post_title));
        $payload[$event_id . '-' . $repeat_interval] = $title;
    }

    eventon_apify_update_or_delete_meta($post_id, 'ev_releated', wp_json_encode($payload));

    return true;
}

/**
 * Save EventON's primary date/time meta using the same conventions as the plugin itself.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_datetime_meta($post_id, array $params) {
    $state = eventon_apify_resolve_datetime_inputs($params, $post_id);
    $calculated = eventon_apify_calculate_datetime_meta($state);

    if (is_wp_error($calculated)) {
        return $calculated;
    }

    update_post_meta($post_id, 'evcal_srow', absint($calculated['unix_start']));
    update_post_meta($post_id, 'evcal_erow', absint($calculated['unix_end']));
    update_post_meta($post_id, '_unix_start_ev', absint($calculated['unix_start_ev']));
    update_post_meta($post_id, '_unix_end_ev', absint($calculated['unix_end_ev']));
    update_post_meta($post_id, '_evo_tz', sanitize_text_field((string) $state['timezone_key']));
    update_post_meta($post_id, '_time_ext_type', sanitize_key((string) $state['time_extend_type']));

    if ($state['virtual_end_enabled']) {
        update_post_meta($post_id, '_evo_virtual_erow', absint($calculated['unix_vir_end']));
        update_post_meta($post_id, '_unix_vend_ev', absint($calculated['unix_vend_ev']));
    } else {
        delete_post_meta($post_id, '_evo_virtual_erow');
        delete_post_meta($post_id, '_unix_vend_ev');
    }

    // Remove legacy non-EventON time keys from previous plugin revisions.
    delete_post_meta($post_id, 'evcal_st');
    delete_post_meta($post_id, 'evcal_et');

    return true;
}

/**
 * Calculate EventON timestamp values, using EventON helpers when available.
 *
 * @param array<string, mixed> $state Resolved datetime state.
 * @return array<string, int>|WP_Error
 */
function eventon_apify_calculate_datetime_meta(array $state) {
    $payload = eventon_apify_build_eventon_datetime_payload($state);

    if (function_exists('eventon_get_unix_time')) {
        try {
            $timezone = new DateTimeZone($state['timezone_key']);
        } catch (Exception $exception) {
            $timezone = wp_timezone();
        }

        $calculated = eventon_get_unix_time($payload, 'Y/m/d', '24h', $timezone);

        if (is_array($calculated) && !empty($calculated['unix_start']) && !empty($calculated['unix_end'])) {
            return array(
                'unix_start' => absint($calculated['unix_start']),
                'unix_end' => absint($calculated['unix_end']),
                'unix_vir_end' => absint($calculated['unix_vir_end'] ?? 0),
                'unix_start_ev' => absint($calculated['unix_start_ev'] ?? $calculated['unix_start']),
                'unix_end_ev' => absint($calculated['unix_end_ev'] ?? $calculated['unix_end']),
                'unix_vend_ev' => absint($calculated['unix_vend_ev'] ?? ($calculated['unix_vir_end'] ?? 0)),
            );
        }
    }

    $start_timestamp = eventon_apify_build_timestamp($state['start_date'], $state['start_time'], $state['timezone_key']);
    $end_timestamp = eventon_apify_build_timestamp($state['end_date'], $state['end_time'], $state['timezone_key']);

    if ($start_timestamp === null || $end_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_datetime',
            'Event dates could not be converted into timestamps.',
            array('status' => 400)
        );
    }

    $virtual_end_timestamp = 0;
    if ($state['virtual_end_enabled']) {
        $virtual_end_timestamp = eventon_apify_build_timestamp($state['virtual_end_date'], $state['virtual_end_time'], $state['timezone_key']) ?: 0;
    }

    return array(
        'unix_start' => $start_timestamp,
        'unix_end' => $end_timestamp,
        'unix_vir_end' => $virtual_end_timestamp,
        'unix_start_ev' => $start_timestamp,
        'unix_end_ev' => $end_timestamp,
        'unix_vend_ev' => $virtual_end_timestamp,
    );
}

/**
 * Convert a resolved datetime state into the POST-like structure EventON expects.
 *
 * @param array<string, mixed> $state Resolved datetime state.
 * @return array<string, string>
 */
function eventon_apify_build_eventon_datetime_payload(array $state) {
    $payload = array(
        'evcal_start_date' => str_replace('-', '/', (string) $state['start_date']),
        'evcal_end_date' => str_replace('-', '/', (string) $state['end_date']),
        '_evo_date_format' => 'Y/m/d',
        '_evo_time_format' => '24h',
        'extend_type' => (string) $state['time_extend_type'],
    );

    $start_time = eventon_apify_split_time_string((string) $state['start_time']);
    $end_time = eventon_apify_split_time_string((string) $state['end_time']);

    if ($start_time) {
        $payload['evcal_start_time_hour'] = $start_time['hour'];
        $payload['evcal_start_time_min'] = $start_time['minute'];
    }

    if ($end_time) {
        $payload['evcal_end_time_hour'] = $end_time['hour'];
        $payload['evcal_end_time_min'] = $end_time['minute'];
    }

    if ($state['virtual_end_enabled']) {
        $virtual_time = eventon_apify_split_time_string((string) $state['virtual_end_time']);
        $payload['event_vir_date_x'] = str_replace('-', '/', (string) $state['virtual_end_date']);

        if ($virtual_time) {
            $payload['_vir_hour'] = $virtual_time['hour'];
            $payload['_vir_minute'] = $virtual_time['minute'];
        }
    }

    return $payload;
}

/**
 * Save EventON repeat-event fields.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_repeat_meta($post_id, array $params) {
    $has_repeat_payload = eventon_apify_array_has_any(
        $params,
        array('repeat_enabled', 'repeat_frequency', 'repeat_gap', 'repeat_count', 'repeat_series_visible', 'repeat_intervals')
    );

    if (!$has_repeat_payload) {
        return true;
    }

    $enabled = array_key_exists('repeat_enabled', $params)
        ? eventon_apify_is_yes($params['repeat_enabled'])
        : (
            eventon_apify_array_has_any($params, array('repeat_frequency', 'repeat_gap', 'repeat_count', 'repeat_intervals'))
                ? true
                : eventon_apify_is_yes(get_post_meta($post_id, 'evcal_repeat', true))
        );

    eventon_apify_update_yes_no_meta($post_id, 'evcal_repeat', $enabled);

    if (!$enabled) {
        delete_post_meta($post_id, 'repeat_intervals');
        delete_post_meta($post_id, 'evcal_rep_freq');
        delete_post_meta($post_id, 'evcal_rep_gap');
        delete_post_meta($post_id, 'evcal_rep_num');
        delete_post_meta($post_id, '_evcal_rep_series');
        return true;
    }

    $repeat_frequency = array_key_exists('repeat_frequency', $params)
        ? sanitize_key((string) $params['repeat_frequency'])
        : sanitize_key((string) get_post_meta($post_id, 'evcal_rep_freq', true));
    $repeat_gap = array_key_exists('repeat_gap', $params)
        ? absint($params['repeat_gap'])
        : absint(get_post_meta($post_id, 'evcal_rep_gap', true));
    $repeat_count = array_key_exists('repeat_count', $params)
        ? absint($params['repeat_count'])
        : absint(get_post_meta($post_id, 'evcal_rep_num', true));

    $repeat_frequency = $repeat_frequency !== '' ? $repeat_frequency : 'daily';
    $repeat_gap = $repeat_gap > 0 ? $repeat_gap : 1;
    $repeat_count = $repeat_count > 0 ? $repeat_count : 1;

    update_post_meta($post_id, 'evcal_rep_freq', $repeat_frequency);
    update_post_meta($post_id, 'evcal_rep_gap', $repeat_gap);
    update_post_meta($post_id, 'evcal_rep_num', $repeat_count);

    if (array_key_exists('repeat_series_visible', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evcal_rep_series', $params['repeat_series_visible']);
    }

    $intervals = eventon_apify_generate_repeat_intervals($post_id, $params, $repeat_frequency, $repeat_gap, $repeat_count);
    if (is_wp_error($intervals)) {
        return $intervals;
    }

    if (!empty($intervals)) {
        update_post_meta($post_id, 'repeat_intervals', $intervals);
    } else {
        delete_post_meta($post_id, 'repeat_intervals');
    }

    return true;
}

/**
 * Generate the repeat_intervals structure EventON expects.
 *
 * @param int                  $post_id         Event post ID.
 * @param array<string, mixed> $params          Request parameters.
 * @param string               $repeat_frequency Repeat frequency.
 * @return array<int, array<int, int>>|WP_Error
 */
function eventon_apify_generate_repeat_intervals($post_id, array $params, $repeat_frequency, $repeat_gap, $repeat_count) {
    $base_start = absint(get_post_meta($post_id, 'evcal_srow', true));
    $base_end = absint(get_post_meta($post_id, 'evcal_erow', true));
    $timezone_key = sanitize_text_field((string) (array_key_exists('timezone_key', $params) ? $params['timezone_key'] : get_post_meta($post_id, '_evo_tz', true)));

    if ($repeat_frequency === 'custom' && !array_key_exists('repeat_intervals', $params)) {
        $existing_intervals = get_post_meta($post_id, 'repeat_intervals', true);
        return is_array($existing_intervals) ? $existing_intervals : array();
    }

    $custom_intervals = array();
    if (array_key_exists('repeat_intervals', $params)) {
        $custom_intervals = eventon_apify_normalize_repeat_intervals_input($params['repeat_intervals'], $timezone_key);
        if (is_wp_error($custom_intervals)) {
            return $custom_intervals;
        }
    }

    if (empty($base_start) || empty($base_end)) {
        return array();
    }

    if (function_exists('eventon_get_repeat_intervals')) {
        $repeat_payload = array(
            'evcal_rep_freq' => $repeat_frequency,
            'evcal_rep_gap' => $repeat_gap,
            'evcal_rep_num' => $repeat_count,
            'repeat_intervals' => $custom_intervals,
            '_evo_date_format' => 'Y/m/d',
            '_evo_time_format' => '24h',
        );

        $intervals = eventon_get_repeat_intervals($base_start, $base_end, $repeat_payload);
        if (is_array($intervals)) {
            return $intervals;
        }
    }

    if ($repeat_frequency === 'custom') {
        if (empty($custom_intervals)) {
            return new WP_Error(
                'eventon_apify_missing_repeat_intervals',
                'repeat.intervals is required when repeat.frequency is custom.',
                array('status' => 400)
            );
        }

        return $custom_intervals;
    }

    return array(array($base_start, $base_end));
}

/**
 * Normalize repeat interval input into the storage format EventON uses.
 *
 * @param mixed  $intervals    Repeat interval input.
 * @param string $timezone_key Event timezone.
 * @return array<int, array<int, int>>|WP_Error
 */
function eventon_apify_normalize_repeat_intervals_input($intervals, $timezone_key) {
    if ($intervals === null || $intervals === '') {
        return array();
    }

    if (!is_array($intervals)) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_intervals',
            'repeat.intervals must be an array.',
            array('status' => 400)
        );
    }

    $normalized = array();
    foreach ($intervals as $interval) {
        $normalized_interval = eventon_apify_normalize_repeat_interval_item($interval, $timezone_key);

        if (is_wp_error($normalized_interval)) {
            return $normalized_interval;
        }

        if ($normalized_interval) {
            $normalized[] = $normalized_interval;
        }
    }

    usort(
        $normalized,
        static function (array $left, array $right) {
            return $left[0] <=> $right[0];
        }
    );

    $unique = array_map('unserialize', array_unique(array_map('serialize', $normalized)));

    return array_values($unique);
}

/**
 * Normalize one repeat interval item.
 *
 * @param mixed  $interval     One interval item.
 * @param string $timezone_key Event timezone.
 * @return array<int, int>|false|WP_Error
 */
function eventon_apify_normalize_repeat_interval_item($interval, $timezone_key) {
    if (!is_array($interval)) {
        return false;
    }

    if (isset($interval[0], $interval[1]) && is_numeric($interval[0]) && is_numeric($interval[1])) {
        $start = absint($interval[0]);
        $end = absint($interval[1]);

        if ($end < $start) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_interval',
                'Each repeat interval end must be on or after its start.',
                array('status' => 400)
            );
        }

        return array($start, $end);
    }

    $start_timestamp = eventon_apify_array_has_any($interval, array('start_timestamp'))
        ? absint($interval['start_timestamp'])
        : 0;
    $end_timestamp = eventon_apify_array_has_any($interval, array('end_timestamp'))
        ? absint($interval['end_timestamp'])
        : 0;

    if ($start_timestamp && $end_timestamp) {
        if ($end_timestamp < $start_timestamp) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_interval',
                'Each repeat interval end must be on or after its start.',
                array('status' => 400)
            );
        }

        return array($start_timestamp, $end_timestamp);
    }

    $start_at = eventon_apify_array_get($interval, array('start_at'), '');
    $end_at = eventon_apify_array_get($interval, array('end_at'), '');

    if ($start_at !== '' && $end_at !== '') {
        $start_parts = eventon_apify_extract_datetime_parts($start_at);
        $end_parts = eventon_apify_extract_datetime_parts($end_at);

        if (!$start_parts || !$end_parts) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_interval',
                'repeat.intervals start_at/end_at could not be parsed.',
                array('status' => 400)
            );
        }

        $start_timestamp = eventon_apify_build_timestamp($start_parts['date'], $start_parts['time'], $start_parts['timezone'] ?: $timezone_key);
        $end_timestamp = eventon_apify_build_timestamp($end_parts['date'], $end_parts['time'], $end_parts['timezone'] ?: $timezone_key);
    } else {
        $start_date = sanitize_text_field((string) eventon_apify_array_get($interval, array('start_date'), ''));
        $start_time = sanitize_text_field((string) eventon_apify_array_get($interval, array('start_time'), '00:00'));
        $end_date = sanitize_text_field((string) eventon_apify_array_get($interval, array('end_date'), $start_date));
        $end_time = sanitize_text_field((string) eventon_apify_array_get($interval, array('end_time'), $start_time));

        if ($start_date === '') {
            return false;
        }

        $start_timestamp = eventon_apify_build_timestamp($start_date, $start_time, $timezone_key);
        $end_timestamp = eventon_apify_build_timestamp($end_date, $end_time, $timezone_key);
    }

    if ($start_timestamp === null || $end_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_interval',
            'repeat.intervals contains an invalid date/time.',
            array('status' => 400)
        );
    }

    if ($end_timestamp < $start_timestamp) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_interval',
            'Each repeat interval end must be on or after its start.',
            array('status' => 400)
        );
    }

    return array($start_timestamp, $end_timestamp);
}

/**
 * Save EventON RSVP addon meta when present.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_rsvp_meta($post_id, array $params) {
    $has_rsvp_payload = eventon_apify_array_has_any(
        $params,
        array(
            'rsvp_enabled',
            'rsvp_show_count',
            'rsvp_show_whos_coming',
            'rsvp_only_loggedin',
            'rsvp_capacity_enabled',
            'rsvp_capacity_count',
            'rsvp_capacity_show_remaining',
            'rsvp_show_bars',
            'rsvp_max_active',
            'rsvp_max_count',
            'rsvp_min_capacity_active',
            'rsvp_min_count',
            'rsvp_close_before_minutes',
            'rsvp_additional_emails',
            'rsvp_manage_repeat_capacity',
            'rsvp_repeat_capacities',
        )
    );

    if (!$has_rsvp_payload) {
        return true;
    }

    if (!array_key_exists('rsvp_enabled', $params)) {
        eventon_apify_update_yes_no_meta($post_id, 'evors_rsvp', true);
    }

    $boolean_map = array(
        'rsvp_enabled' => 'evors_rsvp',
        'rsvp_show_count' => 'evors_show_rsvp',
        'rsvp_show_whos_coming' => 'evors_show_whos_coming',
        'rsvp_only_loggedin' => 'evors_only_loggedin',
        'rsvp_capacity_enabled' => 'evors_capacity',
        'rsvp_capacity_show_remaining' => 'evors_capacity_show',
        'rsvp_show_bars' => 'evors_show_bars',
        'rsvp_max_active' => 'evors_max_active',
        'rsvp_min_capacity_active' => 'evors_min_cap',
        'rsvp_manage_repeat_capacity' => '_manage_repeat_cap_rs',
    );

    foreach ($boolean_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            eventon_apify_update_yes_no_meta($post_id, $meta_key, $params[$request_key]);
        }
    }

    if (array_key_exists('rsvp_capacity_count', $params) && !array_key_exists('rsvp_capacity_enabled', $params) && absint($params['rsvp_capacity_count']) > 0) {
        eventon_apify_update_yes_no_meta($post_id, 'evors_capacity', true);
    }

    $integer_map = array(
        'rsvp_capacity_count' => 'evors_capacity_count',
        'rsvp_max_count' => 'evors_max_count',
        'rsvp_min_count' => 'evors_min_count',
        'rsvp_close_before_minutes' => 'evors_close_time',
    );

    foreach ($integer_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            eventon_apify_update_or_delete_meta($post_id, $meta_key, (string) absint($params[$request_key]));
        }
    }

    if (array_key_exists('rsvp_additional_emails', $params)) {
        eventon_apify_update_or_delete_meta($post_id, 'evors_add_emails', sanitize_text_field((string) $params['rsvp_additional_emails']));
    }

    if (array_key_exists('rsvp_repeat_capacities', $params)) {
        if (!is_array($params['rsvp_repeat_capacities'])) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_capacities',
                'rsvp.repeat_capacities must be an array of integers.',
                array('status' => 400)
            );
        }

        $capacities = array_map('absint', $params['rsvp_repeat_capacities']);
        $capacities = array_values($capacities);

        if (!array_key_exists('rsvp_manage_repeat_capacity', $params) && !empty($capacities)) {
            eventon_apify_update_yes_no_meta($post_id, '_manage_repeat_cap_rs', true);
        }

        if (!empty($capacities)) {
            update_post_meta($post_id, 'ri_capacity_rs', $capacities);
        } else {
            delete_post_meta($post_id, 'ri_capacity_rs');
        }
    } elseif (array_key_exists('rsvp_manage_repeat_capacity', $params) && !eventon_apify_is_yes($params['rsvp_manage_repeat_capacity'])) {
        delete_post_meta($post_id, 'ri_capacity_rs');
    }

    return true;
}

/**
 * Update a meta key when a non-empty value is present, otherwise delete it.
 */
function eventon_apify_update_or_delete_meta($post_id, $meta_key, $value) {
    if ($value === '') {
        delete_post_meta($post_id, $meta_key);
        return;
    }

    update_post_meta($post_id, $meta_key, $value);
}

/**
 * Update a yes/no EventON meta flag.
 */
function eventon_apify_update_yes_no_meta($post_id, $meta_key, $value) {
    update_post_meta($post_id, $meta_key, eventon_apify_to_yes_no($value));
}

/**
 * Save or clear the featured image for an event.
 *
 * @param int   $post_id Event post ID.
 * @param mixed $value   Attachment input.
 * @return true|WP_Error
 */
function eventon_apify_save_featured_media($post_id, $value) {
    $validation = eventon_apify_validate_featured_media_input($value);
    if (is_wp_error($validation)) {
        return $validation;
    }

    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        delete_post_thumbnail($post_id);
        return true;
    }

    set_post_thumbnail($post_id, absint($value));

    return true;
}

/**
 * Get an existing EventON timestamp meta field as Y-m-d.
 */
function eventon_apify_get_existing_meta_date($post_id, $meta_key) {
    $timestamp = absint(get_post_meta($post_id, $meta_key, true));

    return $timestamp ? wp_date('Y-m-d', $timestamp) : '';
}
