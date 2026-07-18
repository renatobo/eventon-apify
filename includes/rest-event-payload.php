<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetch JSON/body parameters as an array.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_request_payload(WP_REST_Request $request) {
    $params = $request->get_json_params();

    if (!is_array($params)) {
        $params = $request->get_body_params();
    }

    return is_array($params) ? $params : array();
}

/**
 * Merge values from a source array into the normalized payload using an alias map.
 *
 * For each target key, the first present alias from the source is copied in,
 * unless the target was already set by an explicit top-level value.
 *
 * @param array<string, mixed>              $normalized Normalized payload so far.
 * @param array<string, mixed>              $source     Source sub-array to read aliases from.
 * @param array<string, array<int, string>> $map        Target key => candidate alias keys.
 * @return array<string, mixed>
 */
function eventon_apify_apply_alias_map(array $normalized, array $source, array $map) {
    foreach ($map as $target => $aliases) {
        if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($source, $aliases)) {
            $normalized[$target] = eventon_apify_array_get($source, $aliases);
        }
    }

    return $normalized;
}

/**
 * Normalize flexible API input into the canonical keys used internally.
 *
 * @param array<string, mixed> $params Raw request payload.
 * @return array<string, mixed>
 */
function eventon_apify_normalize_request_payload(array $params) {
    $normalized = eventon_apify_flatten_wrapped_request_payload($params);

    if (!array_key_exists('description', $normalized) && array_key_exists('content', $params)) {
        $normalized['description'] = $params['content'];
    }

    if (!array_key_exists('excerpt', $normalized) && array_key_exists('post_excerpt', $params)) {
        $normalized['excerpt'] = $params['post_excerpt'];
    }

    if (!array_key_exists('featured_media', $normalized) && array_key_exists('featured_image_id', $normalized)) {
        $normalized['featured_media'] = $normalized['featured_image_id'];
    }

    if (!array_key_exists('event_subtitle', $normalized) && array_key_exists('subtitle', $params)) {
        $normalized['event_subtitle'] = $params['subtitle'];
    }

    if (!array_key_exists('event_excerpt', $normalized) && array_key_exists('evo_excerpt', $params)) {
        $normalized['event_excerpt'] = $params['evo_excerpt'];
    }

    if (!array_key_exists('event_type', $normalized) && array_key_exists('event_types', $params)) {
        $normalized['event_type'] = $params['event_types'];
    }

    if (!array_key_exists('tags', $normalized) && array_key_exists('post_tag', $params)) {
        $normalized['tags'] = $params['post_tag'];
    }

    if (!array_key_exists('event_status', $normalized) && array_key_exists('_status', $params)) {
        $normalized['event_status'] = $params['_status'];
    }

    if (array_key_exists('start_at', $params) && !array_key_exists('start_date', $normalized)) {
        $normalized = eventon_apify_apply_datetime_parts_to_payload($normalized, 'start', $params['start_at']);
    }

    if (array_key_exists('end_at', $params) && !array_key_exists('end_date', $normalized)) {
        $normalized = eventon_apify_apply_datetime_parts_to_payload($normalized, 'end', $params['end_at']);
    }

    if (array_key_exists('timezone', $params)) {
        if (is_array($params['timezone'])) {
            if (!array_key_exists('timezone_key', $normalized)) {
                $normalized['timezone_key'] = eventon_apify_array_get($params['timezone'], array('key', 'timezone', 'id', 'value'), '');
            }

            if (!array_key_exists('timezone_text', $normalized)) {
                $normalized['timezone_text'] = eventon_apify_array_get($params['timezone'], array('text', 'label', 'display'), '');
            }
        } elseif (!array_key_exists('timezone_key', $normalized)) {
            $normalized['timezone_key'] = $params['timezone'];
        }
    }

    if (isset($params['eventon']) && is_array($params['eventon'])) {
        $eventon_map = array(
            'event_subtitle' => array('event_subtitle', 'subtitle'),
            'event_excerpt' => array('event_excerpt', 'excerpt'),
            'event_color' => array('event_color', 'color'),
            'event_color_secondary' => array('event_color_secondary', 'secondary_color', 'color_secondary'),
            'gradient_enabled' => array('gradient_enabled'),
            'gradient_angle' => array('gradient_angle'),
            'learn_more_link' => array('learn_more_link', 'link'),
            'learn_more_link_target' => array('learn_more_link_target', 'link_target'),
            'event_status' => array('event_status', 'status'),
            'status_reason' => array('status_reason', 'reason'),
            'attendance_mode' => array('attendance_mode'),
            'time_extend_type' => array('time_extend_type', 'extend_type'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['eventon'], $eventon_map);
    }

    if (isset($params['flags']) && is_array($params['flags'])) {
        $flags_map = array(
            'featured' => array('featured'),
            'completed' => array('completed'),
            'exclude_from_calendar' => array('exclude_from_calendar'),
            'loggedin_only' => array('loggedin_only'),
            'hide_end_time' => array('hide_end_time'),
            'span_hidden_end' => array('span_hidden_end'),
            'hide_location_name' => array('hide_location_name'),
            'hide_organizer_card' => array('hide_organizer_card'),
            'generate_gmap' => array('generate_gmap', 'map_enabled'),
            'open_google_maps_link' => array('open_google_maps_link'),
            'location_access_loggedin_only' => array('location_access_loggedin_only'),
            'location_info_over_image' => array('location_info_over_image'),
            'organizer_as_performer' => array('organizer_as_performer'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['flags'], $flags_map);
    }

    if (array_key_exists('interaction', $params)) {
        if (is_array($params['interaction'])) {
            $interaction_map = array(
                'interaction_mode' => array('mode', 'action'),
                'interaction_url' => array('url', 'link'),
                'interaction_new_window' => array('new_window', 'target'),
            );

            $normalized = eventon_apify_apply_alias_map($normalized, $params['interaction'], $interaction_map);
        } elseif (!array_key_exists('interaction_mode', $normalized)) {
            $normalized['interaction_mode'] = $params['interaction'];
        }
    }

    if (array_key_exists('location', $params)) {
        if (is_string($params['location'])) {
            $normalized['location_name'] = $params['location'];
        } elseif (is_array($params['location'])) {
            $location_map = array(
                'location_term_id' => array('term_id', 'id'),
                'location_name' => array('name', 'title'),
                'location_slug' => array('slug'),
                'location_description' => array('description'),
                'location_type' => array('type'),
                'location_address' => array('address', 'location_address'),
                'location_city' => array('city', 'location_city'),
                'location_state' => array('state', 'location_state'),
                'location_country' => array('country', 'location_country'),
                'location_zip' => array('zip', 'location_zip'),
                'location_lat' => array('lat', 'latitude', 'location_lat'),
                'location_lon' => array('lon', 'lng', 'longitude', 'location_lon'),
                'location_link' => array('link', 'location_link', 'map_url'),
                'location_link_target' => array('link_target'),
                'location_phone' => array('phone', 'loc_phone'),
                'location_email' => array('email', 'loc_email'),
                'location_getdir_latlng' => array('use_latlng_for_directions', 'location_getdir_latlng'),
            );

            $normalized = eventon_apify_apply_alias_map($normalized, $params['location'], $location_map);
        }
    }

    if (!array_key_exists('location_link', $normalized) && array_key_exists('map_url', $params)) {
        $normalized['location_link'] = $params['map_url'];
    }

    if (!array_key_exists('location_lat', $normalized) && array_key_exists('lat', $params)) {
        $normalized['location_lat'] = $params['lat'];
    }

    if (!array_key_exists('location_lon', $normalized) && eventon_apify_array_has_any($params, array('lon', 'lng'))) {
        $normalized['location_lon'] = eventon_apify_array_get($params, array('lon', 'lng'));
    }

    if (array_key_exists('organizers', $params)) {
        $normalized['organizers'] = eventon_apify_normalize_organizer_items($params['organizers']);
    } elseif (array_key_exists('organizer', $params)) {
        $normalized['organizers'] = eventon_apify_normalize_organizer_items($params['organizer']);
    }

    if (isset($params['health']) && is_array($params['health'])) {
        $health_map = array(
            'health_enabled' => array('enabled'),
            'health_mask_required' => array('mask_required'),
            'health_temperature_check' => array('temperature_check'),
            'health_physical_distance' => array('physical_distance'),
            'health_sanitized' => array('sanitized'),
            'health_outdoor' => array('outdoor'),
            'health_vaccination_required' => array('vaccination_required'),
            'health_other' => array('other'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['health'], $health_map);
    }

    if (isset($params['virtual']) && is_array($params['virtual'])) {
        $virtual_map = array(
            'virtual_enabled' => array('enabled'),
            'virtual_type' => array('type'),
            'virtual_url' => array('url'),
            'virtual_password' => array('password'),
            'virtual_embed' => array('embed'),
            'virtual_other' => array('other'),
            'virtual_show' => array('show'),
            'virtual_hide_when_live' => array('hide_when_live'),
            'virtual_disable_redirect_hiding' => array('disable_redirect_hiding'),
            'virtual_after_content' => array('after_content'),
            'virtual_after_content_when' => array('after_content_when'),
            'virtual_moderator_id' => array('moderator_id'),
            'virtual_end_enabled' => array('end_time_enabled', 'end_enabled'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['virtual'], $virtual_map);

        if (eventon_apify_array_has_any($params['virtual'], array('end_at')) && !array_key_exists('virtual_end_date', $normalized)) {
            $normalized = eventon_apify_apply_datetime_parts_to_payload($normalized, 'virtual_end', $params['virtual']['end_at']);
        }

        if (!array_key_exists('virtual_end_date', $normalized) && eventon_apify_array_has_any($params['virtual'], array('end_date'))) {
            $normalized['virtual_end_date'] = eventon_apify_array_get($params['virtual'], array('end_date'));
        }

        if (!array_key_exists('virtual_end_time', $normalized) && eventon_apify_array_has_any($params['virtual'], array('end_time'))) {
            $normalized['virtual_end_time'] = eventon_apify_array_get($params['virtual'], array('end_time'));
        }
    }

    if (isset($params['repeat']) && is_array($params['repeat'])) {
        $repeat_map = array(
            'repeat_enabled' => array('enabled'),
            'repeat_frequency' => array('frequency', 'type'),
            'repeat_gap' => array('gap'),
            'repeat_count' => array('count', 'number'),
            'repeat_series_visible' => array('series_visible'),
            'repeat_intervals' => array('intervals'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['repeat'], $repeat_map);
    }

    if (isset($params['related_events']) && is_array($params['related_events'])) {
        $related_map = array(
            'related_items' => array('items', 'events'),
            'related_hide_image' => array('hide_image'),
            'related_hide_past' => array('hide_past'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['related_events'], $related_map);

        if (!array_key_exists('related_items', $normalized)) {
            $normalized['related_items'] = eventon_apify_array_get($params['related_events'], array('items', 'events'), array());
        }
    }

    if (isset($params['seo']) && is_array($params['seo'])) {
        $seo_map = array(
            'seo_offer_price' => array('offer_price', 'price'),
            'seo_offer_currency' => array('offer_currency', 'currency'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['seo'], $seo_map);
    }

    if (array_key_exists('faqs', $params)) {
        if (is_array($params['faqs'])) {
            if (!array_key_exists('faq_subheader', $normalized) && eventon_apify_array_has_any($params['faqs'], array('subheader'))) {
                $normalized['faq_subheader'] = eventon_apify_array_get($params['faqs'], array('subheader'));
            }

            if (!array_key_exists('faq_items', $normalized)) {
                $normalized['faq_items'] = eventon_apify_array_get($params['faqs'], array('items'), array());
            }
        } elseif (!array_key_exists('faq_items', $normalized)) {
            $normalized['faq_items'] = $params['faqs'];
        }
    }

    if (isset($params['rsvp']) && is_array($params['rsvp'])) {
        $rsvp_map = array(
            'rsvp_enabled' => array('enabled'),
            'rsvp_show_count' => array('show_count'),
            'rsvp_show_whos_coming' => array('show_whos_coming'),
            'rsvp_only_loggedin' => array('only_loggedin'),
            'rsvp_capacity_enabled' => array('capacity_enabled'),
            'rsvp_capacity_count' => array('capacity_count'),
            'rsvp_capacity_show_remaining' => array('capacity_show_remaining'),
            'rsvp_show_bars' => array('show_bars'),
            'rsvp_max_active' => array('max_active'),
            'rsvp_max_count' => array('max_count'),
            'rsvp_min_capacity_active' => array('min_capacity_active'),
            'rsvp_min_count' => array('min_count'),
            'rsvp_close_before_minutes' => array('close_before_minutes'),
            'rsvp_additional_emails' => array('additional_emails'),
            'rsvp_manage_repeat_capacity' => array('manage_repeat_capacity'),
            'rsvp_repeat_capacities' => array('repeat_capacities'),
        );

        $normalized = eventon_apify_apply_alias_map($normalized, $params['rsvp'], $rsvp_map);
    }

    return $normalized;
}

/**
 * Flatten generic wrapper payloads into the top-level request body while preserving explicit top-level values.
 *
 * @param array<string, mixed> $params Raw request payload.
 * @return array<string, mixed>
 */
function eventon_apify_flatten_wrapped_request_payload(array $params) {
    $flattened = $params;

    foreach (eventon_apify_get_wp_v2_wrapper_field_names() as $wrapper_key) {
        if (!isset($params[$wrapper_key]) || !is_array($params[$wrapper_key])) {
            continue;
        }

        foreach ($params[$wrapper_key] as $key => $value) {
            if (!array_key_exists($key, $flattened)) {
                $flattened[$key] = $value;
            }
        }
    }

    return $flattened;
}

/**
 * Apply an ISO-like datetime string to date/time payload keys.
 *
 * @param array<string, mixed> $payload Existing payload.
 * @return array<string, mixed>
 */
function eventon_apify_apply_datetime_parts_to_payload(array $payload, $prefix, $value) {
    $parts = eventon_apify_extract_datetime_parts($value);

    if (!$parts) {
        return $payload;
    }

    $payload[$prefix . '_date'] = $parts['date'];
    $payload[$prefix . '_time'] = $parts['time'];

    if (!array_key_exists('timezone_key', $payload) && $parts['timezone'] !== '' && eventon_apify_is_valid_timezone($parts['timezone'])) {
        $payload['timezone_key'] = $parts['timezone'];
    }

    return $payload;
}

/**
 * Parse datetime strings commonly returned by the API.
 *
 * @return array<string, string>|null
 */
function eventon_apify_extract_datetime_parts($value) {
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        $datetime = new DateTimeImmutable($value);
    } catch (Exception $exception) {
        return null;
    }

    return array(
        'date' => $datetime->format('Y-m-d'),
        'time' => $datetime->format('H:i'),
        'timezone' => $datetime->getTimezone()->getName(),
    );
}

/**
 * Normalize organizer inputs into a consistent array of organizer objects.
 *
 * @param mixed $value Organizer input value.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_normalize_organizer_items($value) {
    if ($value === null || $value === '') {
        return array();
    }

    if (is_string($value) || is_numeric($value)) {
        return array(eventon_apify_normalize_organizer_item($value));
    }

    if (!is_array($value)) {
        return array();
    }

    if (eventon_apify_array_has_any($value, array('term_id', 'id', 'name', 'title', 'email', 'contact', 'phone'))) {
        return array(eventon_apify_normalize_organizer_item($value));
    }

    $items = array();
    foreach ($value as $item) {
        $items[] = eventon_apify_normalize_organizer_item($item);
    }

    return $items;
}

/**
 * Normalize one organizer item into a keyed array.
 *
 * @param mixed $value Organizer input value.
 * @return array<string, mixed>
 */
function eventon_apify_normalize_organizer_item($value) {
    if (is_string($value) || is_numeric($value)) {
        return array(
            'name' => (string) $value,
        );
    }

    if (!is_array($value)) {
        return array();
    }

    $normalized = array();
    $field_map = array(
        'term_id' => array('term_id', 'id'),
        'name' => array('name', 'title'),
        'slug' => array('slug'),
        'description' => array('description'),
        'contact' => array('contact', 'organizer_contact'),
        'email' => array('email', 'contact_email'),
        'phone' => array('phone', 'contact_phone'),
        'address' => array('address', 'organizer_address'),
        'link' => array('link', 'organizer_link'),
        'link_target' => array('link_target', 'organizer_link_target'),
        'excerpt' => array('excerpt'),
    );

    foreach ($field_map as $target => $aliases) {
        if (eventon_apify_array_has_any($value, $aliases)) {
            $normalized[$target] = eventon_apify_array_get($value, $aliases);
        }
    }

    return $normalized;
}
