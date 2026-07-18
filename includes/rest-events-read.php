<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format an EventON post into an API response object.
 */
function eventon_apify_format_event(WP_Post $post) {
    $meta = get_post_meta($post->ID);

    $start_timestamp = eventon_apify_get_meta_int($meta, 'evcal_srow');
    $end_timestamp = eventon_apify_get_meta_int($meta, 'evcal_erow');
    $event_start_timestamp = eventon_apify_get_event_effective_start_timestamp($meta);
    $event_end_timestamp = eventon_apify_get_meta_int($meta, '_unix_end_ev') ?: $end_timestamp;
    $virtual_end_timestamp = eventon_apify_get_meta_int($meta, '_unix_vend_ev') ?: eventon_apify_get_meta_int($meta, '_evo_virtual_erow');
    $timezone_key = eventon_apify_get_timezone_key_from_meta($meta);
    $event_type_terms_raw = wp_get_post_terms($post->ID, 'event_type');
    $tag_terms_raw = wp_get_post_terms($post->ID, 'post_tag');

    if (is_wp_error($event_type_terms_raw)) {
        $event_type_terms_raw = array();
    }

    if (is_wp_error($tag_terms_raw)) {
        $tag_terms_raw = array();
    }

    $event_types = wp_list_pluck($event_type_terms_raw, 'name');
    $tags = wp_list_pluck($tag_terms_raw, 'name');
    $event_type_terms = eventon_apify_format_term_objects($event_type_terms_raw);
    $tag_terms = eventon_apify_format_term_objects($tag_terms_raw);
    $location = eventon_apify_get_location_payload($post->ID, $meta);
    $organizers = eventon_apify_get_organizer_payload($post->ID, $meta);
    $event_status = eventon_apify_get_event_status_from_meta($meta);
    $status_reason = eventon_apify_get_event_status_reason_from_meta($meta, $event_status);
    $gradient_angle = eventon_apify_get_meta_text($meta, '_evo_event_grad_ang');
    $health = eventon_apify_get_health_payload($post->ID);

    return array(
        'id' => $post->ID,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'slug' => $post->post_name,
        'description' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'tags' => $tags,
        'tag_terms' => $tag_terms,
        'event_subtitle' => eventon_apify_get_meta_text($meta, 'evcal_subtitle'),
        'event_excerpt' => eventon_apify_get_meta_text($meta, 'evo_excerpt'),
        'link' => get_permalink($post->ID),
        'start_timestamp' => $start_timestamp,
        'start_at' => $event_start_timestamp ? eventon_apify_format_timestamp_for_timezone($event_start_timestamp, $timezone_key, 'c') : '',
        'start_date' => $event_start_timestamp ? eventon_apify_format_timestamp_for_timezone($event_start_timestamp, $timezone_key, 'Y-m-d') : '',
        'start_time' => $event_start_timestamp ? eventon_apify_format_timestamp_for_timezone($event_start_timestamp, $timezone_key, 'H:i') : '',
        'event_start_timestamp' => $event_start_timestamp,
        'end_timestamp' => $end_timestamp,
        'end_at' => $event_end_timestamp ? eventon_apify_format_timestamp_for_timezone($event_end_timestamp, $timezone_key, 'c') : '',
        'end_date' => $event_end_timestamp ? eventon_apify_format_timestamp_for_timezone($event_end_timestamp, $timezone_key, 'Y-m-d') : '',
        'end_time' => $event_end_timestamp ? eventon_apify_format_timestamp_for_timezone($event_end_timestamp, $timezone_key, 'H:i') : '',
        'event_end_timestamp' => $event_end_timestamp,
        'location' => $location,
        'organizer' => !empty($organizers) ? $organizers[0]['name'] : eventon_apify_get_meta_text($meta, 'evcal_organizer_name'),
        'organizers' => $organizers,
        'event_color' => eventon_apify_format_color_output(eventon_apify_get_meta_text($meta, 'evcal_event_color')),
        'event_color_secondary' => eventon_apify_format_color_output(eventon_apify_get_meta_text($meta, 'evcal_event_color2')),
        'event_type' => $event_types,
        'event_type_terms' => $event_type_terms,
        'event_status' => $event_status,
        'status_reason' => $status_reason,
        'attendance_mode' => eventon_apify_get_attendance_mode_from_meta($meta, $event_status),
        'time_extend_type' => eventon_apify_get_meta_text($meta, '_time_ext_type') ?: 'n',
        'timezone' => array(
            'key' => $timezone_key,
            'text' => eventon_apify_get_meta_text($meta, 'evo_event_timezone'),
        ),
        'learn_more_link' => eventon_apify_get_meta_text($meta, 'evcal_lmlink'),
        'learn_more_link_target' => eventon_apify_get_yes_no_flag($meta, 'evcal_lmlink_target'),
        'interaction' => eventon_apify_get_interaction_payload($post->ID, $meta),
        'flags' => array(
            'featured' => eventon_apify_get_yes_no_flag($meta, '_featured'),
            'completed' => eventon_apify_get_yes_no_flag($meta, '_completed'),
            'exclude_from_calendar' => eventon_apify_get_yes_no_flag($meta, 'evo_exclude_ev'),
            'loggedin_only' => eventon_apify_get_yes_no_flag($meta, '_onlyloggedin'),
            'hide_end_time' => eventon_apify_get_yes_no_flag($meta, 'evo_hide_endtime'),
            'span_hidden_end' => eventon_apify_get_yes_no_flag($meta, 'evo_span_hidden_end'),
            'hide_location_name' => eventon_apify_get_yes_no_flag($meta, 'evcal_hide_locname'),
            'hide_organizer_card' => eventon_apify_get_yes_no_flag($meta, 'evo_evcrd_field_org'),
            'generate_gmap' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_gen'),
            'open_google_maps_link' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_link'),
            'location_access_loggedin_only' => eventon_apify_get_yes_no_flag($meta, 'evo_access_control_location'),
            'location_info_over_image' => eventon_apify_get_yes_no_flag($meta, 'evcal_name_over_img'),
            'organizer_as_performer' => eventon_apify_get_yes_no_flag($meta, 'evo_event_org_as_perf'),
            'gradient_enabled' => eventon_apify_get_yes_no_flag($meta, '_evo_event_grad_colors'),
        ),
        'health' => $health,
        'gradient_angle' => is_numeric($gradient_angle) ? (0 + $gradient_angle) : null,
        'virtual' => eventon_apify_get_virtual_payload($meta, $timezone_key, $virtual_end_timestamp),
        'repeat' => eventon_apify_get_repeat_payload($meta, $timezone_key),
        'related_events' => eventon_apify_get_related_events_payload($post->ID, $meta),
        'seo' => eventon_apify_get_seo_payload($meta),
        'faqs' => eventon_apify_get_faq_payload($post->ID),
        'rsvp' => eventon_apify_get_rsvp_payload($meta),
        'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: '',
        'created' => $post->post_date_gmt ? get_date_from_gmt($post->post_date_gmt, 'c') : '',
        'modified' => $post->post_modified_gmt ? get_date_from_gmt($post->post_modified_gmt, 'c') : '',
    );
}

/**
 * Format a list of WP_Term objects into identifier-bearing payload entries.
 *
 * Mirrors the organizers payload shape so consumers can resolve taxonomy terms
 * to their term_id and slug, which the label-only `event_type`/`tags` arrays omit.
 *
 * @param WP_Term[] $terms
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_format_term_objects($terms) {
    if (is_wp_error($terms) || !is_array($terms)) {
        return array();
    }

    $payload = array();

    foreach ($terms as $term) {
        if (!($term instanceof WP_Term)) {
            continue;
        }

        $payload[] = array(
            'term_id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }

    return $payload;
}

/**
 * Return a text meta value from the raw post meta array.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @param string                           $key  Meta key.
 */
function eventon_apify_get_meta_text(array $meta, $key) {
    if (!isset($meta[$key][0])) {
        return '';
    }

    return (string) $meta[$key][0];
}

/**
 * Return an integer meta value from the raw post meta array.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @param string                           $key  Meta key.
 */
function eventon_apify_get_meta_int(array $meta, $key) {
    if (!isset($meta[$key][0]) || $meta[$key][0] === '') {
        return 0;
    }

    return absint($meta[$key][0]);
}

/**
 * Return the canonical EventON start timestamp used by the API payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_event_effective_start_timestamp(array $meta) {
    return eventon_apify_get_meta_int($meta, '_unix_start_ev') ?: eventon_apify_get_meta_int($meta, 'evcal_srow');
}

/**
 * Resolve EventON timezone key from saved meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_timezone_key_from_meta(array $meta) {
    $timezone_key = eventon_apify_get_meta_text($meta, '_evo_tz');

    if ($timezone_key !== '' && eventon_apify_is_valid_timezone($timezone_key)) {
        return $timezone_key;
    }

    $wp_timezone = wp_timezone_string();
    return $wp_timezone !== '' ? $wp_timezone : 'UTC';
}

/**
 * Return true when a saved meta flag is yes.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_yes_no_flag(array $meta, $key) {
    return eventon_apify_is_yes(eventon_apify_get_meta_text($meta, $key));
}

/**
 * Return a normalized interaction payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_interaction_payload($post_id, array $meta) {
    $mode = eventon_apify_map_interaction_code_to_mode(eventon_apify_get_meta_text($meta, '_evcal_exlink_option'));
    $url = eventon_apify_get_meta_text($meta, 'evcal_exlink');

    if ($mode === 'open_event_page') {
        $url = get_permalink($post_id) ?: '';
    }

    return array(
        'mode' => $mode,
        'url' => $url,
        'new_window' => eventon_apify_get_yes_no_flag($meta, '_evcal_exlink_target'),
    );
}

/**
 * Return a normalized health payload.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_health_payload($post_id) {
    $edata = eventon_apify_get_event_edata($post_id);

    return array(
        'enabled' => eventon_apify_is_yes(get_post_meta($post_id, '_health', true)),
        'mask_required' => eventon_apify_is_yes($edata['_health_mask'] ?? ''),
        'temperature_check' => eventon_apify_is_yes($edata['_health_temp'] ?? ''),
        'physical_distance' => eventon_apify_is_yes($edata['_health_pdis'] ?? ''),
        'sanitized' => eventon_apify_is_yes($edata['_health_san'] ?? ''),
        'outdoor' => eventon_apify_is_yes($edata['_health_out'] ?? ''),
        'vaccination_required' => eventon_apify_is_yes($edata['_health_vac'] ?? ''),
        'other' => isset($edata['_health_other']) ? (string) $edata['_health_other'] : '',
    );
}

/**
 * Return a normalized related events payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_related_events_payload($post_id, array $meta) {
    unset($post_id);

    $items = array();
    $raw = eventon_apify_get_meta_text($meta, 'ev_releated');

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $title) {
                $parts = explode('-', (string) $key, 2);
                $event_id = absint($parts[0] ?? 0);
                $repeat_interval = isset($parts[1]) ? absint($parts[1]) : 0;
                $resolved_title = $event_id ? get_the_title($event_id) : '';

                $items[] = array(
                    'event_id' => $event_id,
                    'repeat_interval' => $repeat_interval,
                    'title' => $resolved_title !== '' ? $resolved_title : sanitize_text_field((string) $title),
                );
            }
        }
    }

    return array(
        'items' => $items,
        'hide_image' => eventon_apify_get_yes_no_flag($meta, '_evo_relevs_hide_img'),
        'hide_past' => eventon_apify_get_yes_no_flag($meta, '_evo_relevs_hide_past'),
    );
}

/**
 * Return a normalized SEO payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_seo_payload(array $meta) {
    return array(
        'offer_price' => eventon_apify_get_meta_text($meta, '_seo_offer_price'),
        'offer_currency' => eventon_apify_get_meta_text($meta, '_seo_offer_currency'),
    );
}

/**
 * Return a normalized FAQ payload.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_faq_payload($post_id) {
    $items = array();
    $terms = taxonomy_exists('evo_faq') ? wp_get_post_terms($post_id, 'evo_faq') : array();

    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $items[] = array(
                'term_id' => (int) $term->term_id,
                'question' => $term->name,
                'slug' => $term->slug,
                'answer' => $term->description,
            );
        }
    }

    return array(
        'subheader' => (string) get_post_meta($post_id, '_evo_faq_subheader', true),
        'items' => $items,
    );
}

/**
 * Format timestamps in the provided timezone.
 */
function eventon_apify_format_timestamp_for_timezone($timestamp, $timezone_key, $format) {
    if (!$timestamp) {
        return '';
    }

    try {
        $timezone = new DateTimeZone(eventon_apify_is_valid_timezone($timezone_key) ? $timezone_key : 'UTC');
    } catch (Exception $exception) {
        $timezone = new DateTimeZone('UTC');
    }

    return wp_date($format, $timestamp, $timezone);
}

/**
 * Normalize EventON color storage to an API-friendly format.
 */
function eventon_apify_format_color_output($value) {
    $value = ltrim(trim((string) $value), '#');

    return $value !== '' ? '#' . $value : '';
}

/**
 * Read EventON event status from meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_event_status_from_meta(array $meta) {
    $status = eventon_apify_get_meta_text($meta, '_status');

    return $status !== '' ? $status : 'scheduled';
}

/**
 * Read the current EventON status reason from meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_event_status_reason_from_meta(array $meta, $event_status) {
    if ($event_status === '') {
        return '';
    }

    return eventon_apify_get_meta_text($meta, '_' . $event_status . '_reason');
}

/**
 * Read EventON attendance mode from meta, respecting moved-online status.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_attendance_mode_from_meta(array $meta, $event_status = '') {
    if ($event_status === 'movedonline') {
        return 'online';
    }

    $attendance_mode = eventon_apify_get_meta_text($meta, '_attendance_mode');

    return $attendance_mode !== '' ? $attendance_mode : 'offline';
}

/**
 * Build the location payload from EventON taxonomies.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_location_payload($post_id, array $meta) {
    $terms = wp_get_post_terms($post_id, 'event_location');
    $location = array(
        'term_id' => 0,
        'name' => '',
        'slug' => '',
        'archive_url' => '',
        'type' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'country' => '',
        'zip' => '',
        'lat' => '',
        'lon' => '',
        'latlng' => '',
        'link' => '',
        'link_target' => false,
        'phone' => '',
        'email' => '',
        'description' => '',
        'use_latlng_for_directions' => false,
        'map_enabled' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_gen'),
        'open_google_maps_link' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_link'),
        'map_url' => '',
    );

    if ($terms && !is_wp_error($terms)) {
        $term = $terms[0];
        $term_meta = eventon_apify_get_term_meta_payload('event_location', $term->term_id);

        $location['term_id'] = (int) $term->term_id;
        $location['name'] = $term->name;
        $location['slug'] = $term->slug;
        $archive_url = get_term_link($term, 'event_location');
        $location['archive_url'] = is_wp_error($archive_url) ? '' : $archive_url;
        $location['type'] = $term_meta['location_type'] ?? '';
        $location['address'] = $term_meta['location_address'] ?? '';
        $location['city'] = $term_meta['location_city'] ?? '';
        $location['state'] = $term_meta['location_state'] ?? '';
        $location['country'] = $term_meta['location_country'] ?? '';
        $location['zip'] = $term_meta['location_zip'] ?? '';
        $location['lat'] = $term_meta['location_lat'] ?? '';
        $location['lon'] = $term_meta['location_lon'] ?? '';
        $location['link'] = $term_meta['location_link'] ?? ($term_meta['evcal_location_link'] ?? '');
        $location['link_target'] = eventon_apify_is_yes($term_meta['evcal_location_link_target'] ?? '');
        $location['phone'] = $term_meta['loc_phone'] ?? '';
        $location['email'] = $term_meta['loc_email'] ?? '';
        $location['use_latlng_for_directions'] = eventon_apify_is_yes($term_meta['location_getdir_latlng'] ?? '');
        $location['description'] = $term->description;
    } else {
        // Fallback for events created before this compatibility pass.
        $location['name'] = eventon_apify_get_meta_text($meta, 'evcal_location_name_t');
        $location['address'] = eventon_apify_get_meta_text($meta, 'evcal_location_addr');
        $location['city'] = eventon_apify_get_meta_text($meta, 'evcal_location_city');
        $location['state'] = eventon_apify_get_meta_text($meta, 'evcal_location_state');
        $location['country'] = eventon_apify_get_meta_text($meta, 'evcal_location_country');
    }

    if ($location['lat'] !== '' && $location['lon'] !== '') {
        $location['latlng'] = $location['lat'] . ',' . $location['lon'];
    }

    $location['map_url'] = eventon_apify_build_google_maps_url(
        $location['address'],
        $location['lat'],
        $location['lon']
    );

    return $location;
}

/**
 * Build organizer payload from EventON organizer terms.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_organizer_payload($post_id, array $meta) {
    $terms = wp_get_post_terms($post_id, 'event_organizer');
    $organizers = array();

    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $term_meta = eventon_apify_get_term_meta_payload('event_organizer', $term->term_id);
            $archive_url = get_term_link($term, 'event_organizer');

            $organizers[] = array(
                'term_id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'archive_url' => is_wp_error($archive_url) ? '' : $archive_url,
                'description' => $term->description,
                'contact' => $term_meta['evcal_org_contact'] ?? '',
                'email' => $term_meta['evcal_org_contact_e'] ?? '',
                'phone' => $term_meta['evcal_org_contact_phone'] ?? '',
                'address' => $term_meta['evcal_org_address'] ?? '',
                'link' => $term_meta['evcal_org_exlink'] ?? '',
                'link_target' => eventon_apify_is_yes($term_meta['_evocal_org_exlink_target'] ?? ''),
                'excerpt' => $term_meta['excerpt'] ?? '',
            );
        }
    } elseif (eventon_apify_get_meta_text($meta, 'evcal_organizer_name') !== '') {
        $organizers[] = array(
            'term_id' => 0,
            'name' => eventon_apify_get_meta_text($meta, 'evcal_organizer_name'),
            'slug' => '',
            'archive_url' => '',
            'description' => '',
            'contact' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'link' => '',
            'link_target' => false,
            'excerpt' => '',
        );
    }

    return $organizers;
}

/**
 * Build virtual event payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_virtual_payload(array $meta, $timezone_key, $virtual_end_timestamp) {
    return array(
        'enabled' => eventon_apify_get_yes_no_flag($meta, '_virtual'),
        'type' => eventon_apify_get_meta_text($meta, '_virtual_type'),
        'url' => eventon_apify_get_meta_text($meta, '_vir_url'),
        'password' => eventon_apify_get_meta_text($meta, '_vir_pass'),
        'embed' => eventon_apify_get_meta_text($meta, '_vir_embed'),
        'other' => eventon_apify_get_meta_text($meta, '_vir_other'),
        'show' => eventon_apify_get_meta_text($meta, '_vir_show'),
        'hide_when_live' => eventon_apify_get_yes_no_flag($meta, '_vir_hide'),
        'disable_redirect_hiding' => eventon_apify_get_yes_no_flag($meta, '_vir_nohiding'),
        'moderator_id' => eventon_apify_get_meta_int($meta, '_mod'),
        'end_time_enabled' => eventon_apify_get_yes_no_flag($meta, '_evo_virtual_endtime'),
        'end_timestamp' => $virtual_end_timestamp,
        'end_at' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'c') : '',
        'end_date' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'Y-m-d') : '',
        'end_time' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'H:i') : '',
        'after_content' => eventon_apify_get_meta_text($meta, '_vir_after_content'),
        'after_content_when' => eventon_apify_get_meta_text($meta, '_vir_after_content_when'),
    );
}

/**
 * Build repeat payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_repeat_payload(array $meta, $timezone_key) {
    $intervals = maybe_unserialize($meta['repeat_intervals'][0] ?? array());
    $items = array();

    if (is_array($intervals)) {
        foreach ($intervals as $index => $interval) {
            if (!is_array($interval) || count($interval) < 2) {
                continue;
            }

            $items[] = array(
                'index' => (int) $index,
                'start_timestamp' => (int) $interval[0],
                'start_at' => eventon_apify_format_timestamp_for_timezone((int) $interval[0], $timezone_key, 'c'),
                'end_timestamp' => (int) $interval[1],
                'end_at' => eventon_apify_format_timestamp_for_timezone((int) $interval[1], $timezone_key, 'c'),
            );
        }
    }

    return array(
        'enabled' => eventon_apify_get_yes_no_flag($meta, 'evcal_repeat'),
        'frequency' => eventon_apify_get_meta_text($meta, 'evcal_rep_freq'),
        'gap' => eventon_apify_get_meta_int($meta, 'evcal_rep_gap'),
        'count' => eventon_apify_get_meta_int($meta, 'evcal_rep_num'),
        'series_visible' => eventon_apify_get_yes_no_flag($meta, '_evcal_rep_series'),
        'intervals' => $items,
    );
}

/**
 * Build EventON RSVP payload from event meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_rsvp_payload(array $meta) {
    $repeat_capacities = maybe_unserialize($meta['ri_capacity_rs'][0] ?? array());

    if (!is_array($repeat_capacities)) {
        $repeat_capacities = array();
    }

    return array(
        'enabled' => eventon_apify_get_yes_no_flag($meta, 'evors_rsvp'),
        'show_count' => eventon_apify_get_yes_no_flag($meta, 'evors_show_rsvp'),
        'show_whos_coming' => eventon_apify_get_yes_no_flag($meta, 'evors_show_whos_coming'),
        'only_loggedin' => eventon_apify_get_yes_no_flag($meta, 'evors_only_loggedin'),
        'capacity_enabled' => eventon_apify_get_yes_no_flag($meta, 'evors_capacity'),
        'capacity_count' => eventon_apify_get_meta_int($meta, 'evors_capacity_count'),
        'capacity_show_remaining' => eventon_apify_get_yes_no_flag($meta, 'evors_capacity_show'),
        'show_bars' => eventon_apify_get_yes_no_flag($meta, 'evors_show_bars'),
        'max_active' => eventon_apify_get_yes_no_flag($meta, 'evors_max_active'),
        'max_count' => eventon_apify_get_meta_int($meta, 'evors_max_count'),
        'min_capacity_active' => eventon_apify_get_yes_no_flag($meta, 'evors_min_cap'),
        'min_count' => eventon_apify_get_meta_int($meta, 'evors_min_count'),
        'close_before_minutes' => eventon_apify_get_meta_int($meta, 'evors_close_time'),
        'additional_emails' => eventon_apify_get_meta_text($meta, 'evors_add_emails'),
        'manage_repeat_capacity' => eventon_apify_get_yes_no_flag($meta, '_manage_repeat_cap_rs'),
        'repeat_capacities' => array_map('absint', $repeat_capacities),
    );
}

/**
 * Read EventON term meta from the shared option store.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_term_meta_payload($taxonomy, $term_id) {
    if (!$term_id) {
        return array();
    }

    if (function_exists('evo_get_term_meta')) {
        $meta = evo_get_term_meta($taxonomy, $term_id, true);
        return is_array($meta) ? $meta : array();
    }

    $all_term_meta = get_option('evo_tax_meta', array());
    if (isset($all_term_meta[$taxonomy][$term_id]) && is_array($all_term_meta[$taxonomy][$term_id])) {
        return $all_term_meta[$taxonomy][$term_id];
    }

    $legacy = get_option('taxonomy_' . $term_id, array());
    return is_array($legacy) ? $legacy : array();
}

/**
 * Build a Google Maps URL from saved location details.
 */
function eventon_apify_build_google_maps_url($address, $lat = '', $lon = '') {
    if ($lat !== '' && $lon !== '') {
        return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lon);
    }

    if (trim((string) $address) !== '') {
        return 'https://www.google.com/maps?q=' . rawurlencode($address);
    }

    return '';
}

/**
 * Read EventON's serialized _edata payload as an array.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_event_edata($post_id) {
    $edata = get_post_meta($post_id, '_edata', true);
    return is_array($edata) ? $edata : array();
}
