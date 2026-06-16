<?php

/**
 * Save event taxonomy terms.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_event_terms($post_id, array $params) {
    if (array_key_exists('tags', $params)) {
        $tags_result = eventon_apify_sync_simple_terms($post_id, 'post_tag', $params['tags']);
        if (is_wp_error($tags_result)) {
            return $tags_result;
        }
    }

    if (array_key_exists('event_type', $params)) {
        $event_type_result = eventon_apify_sync_simple_terms($post_id, 'event_type', $params['event_type']);
        if (is_wp_error($event_type_result)) {
            return $event_type_result;
        }
    }

    if (eventon_apify_array_has_any(
        $params,
        array(
            'location',
            'location_term_id',
            'location_name',
            'location_slug',
            'location_description',
            'location_type',
            'location_address',
            'location_city',
            'location_state',
            'location_country',
            'location_zip',
            'location_lat',
            'location_lon',
            'location_link',
            'location_link_target',
            'location_phone',
            'location_email',
            'location_getdir_latlng',
        )
    )) {
        $location_result = eventon_apify_sync_location_term($post_id, $params);
        if (is_wp_error($location_result)) {
            return $location_result;
        }
    }

    if (array_key_exists('organizers', $params) || array_key_exists('organizer', $params)) {
        $organizer_result = eventon_apify_sync_organizer_terms($post_id, $params);
        if (is_wp_error($organizer_result)) {
            return $organizer_result;
        }
    }

    if (array_key_exists('faq_items', $params)) {
        $faq_result = eventon_apify_sync_faq_terms($post_id, $params['faq_items']);
        if (is_wp_error($faq_result)) {
            return $faq_result;
        }
    }

    return true;
}

/**
 * Sync simple taxonomy assignments such as event_type.
 *
 * @param mixed $terms Raw term input.
 * @return true|WP_Error
 */
function eventon_apify_sync_simple_terms($post_id, $taxonomy, $terms) {
    if (is_string($terms)) {
        $terms = array_filter(array_map('trim', explode(',', $terms)));
    } elseif (!is_array($terms)) {
        $terms = array();
    }

    $term_ids = array();
    foreach ($terms as $term_input) {
        if (is_scalar($term_input) && !is_array($term_input)) {
            if (is_numeric($term_input)) {
                $term = get_term(absint($term_input), $taxonomy);
                if (!$term || is_wp_error($term)) {
                    return new WP_Error(
                        'eventon_apify_invalid_term',
                        'A requested ' . $taxonomy . ' term does not exist.',
                        array('status' => 400)
                    );
                }
            } else {
                $term = eventon_apify_resolve_taxonomy_term($taxonomy, array('name' => (string) $term_input));
            }
        } elseif (is_array($term_input)) {
            $term = eventon_apify_resolve_taxonomy_term($taxonomy, $term_input);
        } else {
            continue;
        }

        if (is_wp_error($term)) {
            return $term;
        }

        $term_ids[] = (int) $term->term_id;

        if ($taxonomy === 'event_type' && is_array($term_input)) {
            $term_color = eventon_apify_array_get($term_input, array('et_color', 'color'), '');
            $normalized_color = eventon_apify_normalize_color_input($term_color);

            if ($normalized_color !== null && $normalized_color !== '') {
                $term_meta_result = eventon_apify_save_term_meta_payload('event_type', (int) $term->term_id, array('et_color' => $normalized_color));
                if (is_wp_error($term_meta_result)) {
                    return $term_meta_result;
                }
            }
        }
    }

    $result = wp_set_post_terms($post_id, $term_ids, $taxonomy, false);

    return is_wp_error($result) ? $result : true;
}

/**
 * Sync the single EventON location term attached to an event.
 *
 * @param array<string, mixed> $params Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_sync_location_term($post_id, array $params) {
    $explicit_clear = array_key_exists('location_name', $params)
        && trim((string) $params['location_name']) === ''
        && absint($params['location_term_id'] ?? 0) === 0;

    if ($explicit_clear || (array_key_exists('location', $params) && empty($params['location']))) {
        $result = wp_set_post_terms($post_id, array(), 'event_location', false);
        if (is_wp_error($result)) {
            return $result;
        }

        eventon_apify_clear_legacy_location_meta($post_id);
        return true;
    }

    $term_item = array();
    if (array_key_exists('location_term_id', $params)) {
        $term_item['term_id'] = absint($params['location_term_id']);
    }
    if (array_key_exists('location_name', $params)) {
        $term_item['name'] = sanitize_text_field((string) $params['location_name']);
    }
    if (array_key_exists('location_slug', $params)) {
        $term_item['slug'] = sanitize_title((string) $params['location_slug']);
    }
    if (array_key_exists('location_description', $params)) {
        $term_item['description'] = wp_kses_post((string) $params['location_description']);
    }

    $term = null;
    if (!empty($term_item['term_id'])) {
        $term = eventon_apify_resolve_taxonomy_term('event_location', $term_item, false);
    } elseif (!empty($term_item['name'])) {
        $term = eventon_apify_resolve_taxonomy_term('event_location', $term_item);
    } else {
        $existing_terms = wp_get_post_terms($post_id, 'event_location');
        if (!is_wp_error($existing_terms) && !empty($existing_terms)) {
            $term = eventon_apify_resolve_taxonomy_term(
                'event_location',
                array_merge($term_item, array('term_id' => (int) $existing_terms[0]->term_id)),
                false
            );
        }
    }

    if (is_wp_error($term)) {
        return $term;
    }

    if (!$term || !($term instanceof WP_Term)) {
        return new WP_Error(
            'eventon_apify_missing_location_term',
            'location.name or location.term_id is required when setting location details.',
            array('status' => 400)
        );
    }

    $result = wp_set_post_terms($post_id, array((int) $term->term_id), 'event_location', false);
    if (is_wp_error($result)) {
        return $result;
    }

    $term_meta = array();
    $text_term_meta_map = array(
        'location_address' => 'location_address',
        'location_city' => 'location_city',
        'location_state' => 'location_state',
        'location_country' => 'location_country',
        'location_zip' => 'location_zip',
        'location_lat' => 'location_lat',
        'location_lon' => 'location_lon',
        'location_type' => 'location_type',
        'location_phone' => 'loc_phone',
    );

    foreach ($text_term_meta_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            $term_meta[$meta_key] = sanitize_text_field((string) $params[$request_key]);
        }
    }

    if (array_key_exists('location_email', $params)) {
        $term_meta['loc_email'] = sanitize_email((string) $params['location_email']);
    }

    if (array_key_exists('location_link', $params)) {
        $location_link = esc_url_raw((string) $params['location_link']);
        $term_meta['location_link'] = $location_link;
        $term_meta['evcal_location_link'] = $location_link;
    }

    if (array_key_exists('location_link_target', $params)) {
        $term_meta['evcal_location_link_target'] = eventon_apify_to_yes_no($params['location_link_target']);
    }

    if (array_key_exists('location_getdir_latlng', $params)) {
        $term_meta['location_getdir_latlng'] = eventon_apify_to_yes_no($params['location_getdir_latlng']);
    }

    if (!empty($term_meta)) {
        $term_meta_result = eventon_apify_save_term_meta_payload('event_location', (int) $term->term_id, $term_meta);
        if (is_wp_error($term_meta_result)) {
            return $term_meta_result;
        }
    }
    eventon_apify_clear_legacy_location_meta($post_id);

    return true;
}

/**
 * Sync EventON organizer terms attached to an event.
 *
 * @param array<string, mixed> $params Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_sync_organizer_terms($post_id, array $params) {
    $organizers = $params['organizers'] ?? array();

    if (!is_array($organizers) || empty($organizers)) {
        $result = wp_set_post_terms($post_id, array(), 'event_organizer', false);
        if (is_wp_error($result)) {
            return $result;
        }

        delete_post_meta($post_id, '_evotax_order_event_organizer');
        eventon_apify_clear_legacy_organizer_meta($post_id);
        return true;
    }

    $term_ids = array();
    foreach ($organizers as $organizer) {
        if (!is_array($organizer)) {
            continue;
        }

        $term = eventon_apify_resolve_taxonomy_term('event_organizer', $organizer);
        if (is_wp_error($term)) {
            return $term;
        }

        if (!$term || !($term instanceof WP_Term)) {
            return new WP_Error(
                'eventon_apify_invalid_organizer',
                'Each organizer must include a valid name or term_id.',
                array('status' => 400)
            );
        }

        $term_ids[] = (int) $term->term_id;

        $term_meta = array();
        $organizer_meta_map = array(
            'contact' => 'evcal_org_contact',
            'phone' => 'evcal_org_contact_phone',
            'address' => 'evcal_org_address',
        );

        foreach ($organizer_meta_map as $request_key => $meta_key) {
            if (array_key_exists($request_key, $organizer)) {
                $term_meta[$meta_key] = sanitize_text_field((string) $organizer[$request_key]);
            }
        }

        if (array_key_exists('email', $organizer)) {
            $term_meta['evcal_org_contact_e'] = sanitize_email((string) $organizer['email']);
        }

        if (array_key_exists('link', $organizer)) {
            $term_meta['evcal_org_exlink'] = esc_url_raw((string) $organizer['link']);
        }

        if (array_key_exists('link_target', $organizer)) {
            $term_meta['_evocal_org_exlink_target'] = eventon_apify_to_yes_no($organizer['link_target']);
        }

        if (array_key_exists('excerpt', $organizer)) {
            $term_meta['excerpt'] = sanitize_textarea_field((string) $organizer['excerpt']);
        }

        if (!empty($term_meta)) {
            $term_meta_result = eventon_apify_save_term_meta_payload('event_organizer', (int) $term->term_id, $term_meta);
            if (is_wp_error($term_meta_result)) {
                return $term_meta_result;
            }
        }
    }

    $result = wp_set_post_terms($post_id, $term_ids, 'event_organizer', false);
    if (is_wp_error($result)) {
        return $result;
    }

    if (!empty($term_ids)) {
        update_post_meta($post_id, '_evotax_order_event_organizer', implode(',', $term_ids));
    } else {
        delete_post_meta($post_id, '_evotax_order_event_organizer');
    }

    eventon_apify_clear_legacy_organizer_meta($post_id);

    return true;
}

/**
 * Sync EventON FAQ terms attached to an event.
 *
 * @param mixed $items Raw FAQ payload.
 * @return true|WP_Error
 */
function eventon_apify_sync_faq_terms($post_id, $items) {
    if (!taxonomy_exists('evo_faq')) {
        return new WP_Error(
            'eventon_apify_faq_taxonomy_unavailable',
            'The EventON FAQ taxonomy is not available on this site.',
            array('status' => 400)
        );
    }

    if (!is_array($items) || empty($items)) {
        $result = wp_set_post_terms($post_id, array(), 'evo_faq', false);
        return is_wp_error($result) ? $result : true;
    }

    $term_ids = array();
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $term_payload = array();

        if (array_key_exists('term_id', $item) || array_key_exists('id', $item)) {
            $term_payload['term_id'] = absint(eventon_apify_array_get($item, array('term_id', 'id'), 0));
        }

        if (eventon_apify_array_has_any($item, array('question', 'name', 'title'))) {
            $term_payload['name'] = sanitize_text_field((string) eventon_apify_array_get($item, array('question', 'name', 'title'), ''));
        }

        if (array_key_exists('slug', $item)) {
            $term_payload['slug'] = sanitize_title((string) $item['slug']);
        }

        if (eventon_apify_array_has_any($item, array('answer', 'description'))) {
            $term_payload['description'] = wp_kses_post((string) eventon_apify_array_get($item, array('answer', 'description'), ''));
        }

        $term = eventon_apify_resolve_taxonomy_term('evo_faq', $term_payload);
        if (is_wp_error($term)) {
            return $term;
        }

        if (!$term || !($term instanceof WP_Term)) {
            return new WP_Error(
                'eventon_apify_invalid_faq',
                'Each FAQ must include a valid question or term_id.',
                array('status' => 400)
            );
        }

        $term_ids[] = (int) $term->term_id;
    }

    $result = wp_set_post_terms($post_id, $term_ids, 'evo_faq', false);

    return is_wp_error($result) ? $result : true;
}

/**
 * Return true when the term payload carries mutable core term attributes.
 */
function eventon_apify_term_payload_has_term_changes(array $item) {
    foreach (array('name', 'title', 'label', 'slug', 'description') as $key) {
        if (!array_key_exists($key, $item)) {
            continue;
        }

        if ($key === 'description') {
            return true;
        }

        if (trim((string) $item[$key]) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Resolve or create a term for one of EventON's taxonomies.
 *
 * @param array<string, mixed> $item Term input.
 * @return WP_Term|WP_Error
 */
function eventon_apify_resolve_taxonomy_term($taxonomy, array $item, $create_if_missing = true) {
    $term_id = absint(eventon_apify_array_get($item, array('term_id', 'id'), 0));

    if ($term_id) {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return new WP_Error(
                'eventon_apify_invalid_term_reference',
                'The provided ' . $taxonomy . ' term_id does not exist.',
                array('status' => 400)
            );
        }

        if (eventon_apify_term_payload_has_term_changes($item)) {
            $authorization = eventon_apify_assert_can_manage_shared_terms();
            if (is_wp_error($authorization)) {
                return $authorization;
            }
        }

        return eventon_apify_update_taxonomy_term($taxonomy, $term, $item);
    }

    $slug = sanitize_title((string) eventon_apify_array_get($item, array('slug'), ''));
    if ($slug !== '') {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term instanceof WP_Term) {
            if (eventon_apify_term_payload_has_term_changes($item)) {
                $authorization = eventon_apify_assert_can_manage_shared_terms();
                if (is_wp_error($authorization)) {
                    return $authorization;
                }
            }

            return eventon_apify_update_taxonomy_term($taxonomy, $term, $item);
        }
    }

    $name = sanitize_text_field((string) eventon_apify_array_get($item, array('name', 'title', 'label'), ''));
    if ($name === '') {
        return new WP_Error(
            'eventon_apify_missing_term_name',
            'A taxonomy item must include a name or term_id.',
            array('status' => 400)
        );
    }

    $existing = term_exists($name, $taxonomy);
    if ($existing) {
        $existing_term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
        $term = get_term($existing_term_id, $taxonomy);
        if ($term instanceof WP_Term) {
            if (eventon_apify_term_payload_has_term_changes($item) && count($item) > 1) {
                $authorization = eventon_apify_assert_can_manage_shared_terms();
                if (is_wp_error($authorization)) {
                    return $authorization;
                }
            }

            return eventon_apify_update_taxonomy_term($taxonomy, $term, $item);
        }
    }

    if (!$create_if_missing) {
        return new WP_Error(
            'eventon_apify_missing_term',
            'The referenced ' . $taxonomy . ' term does not exist.',
            array('status' => 400)
        );
    }

    $authorization = eventon_apify_assert_can_manage_shared_terms();
    if (is_wp_error($authorization)) {
        return $authorization;
    }

    $inserted = wp_insert_term(
        $name,
        $taxonomy,
        array(
            'slug' => $slug ?: sanitize_title($name),
            'description' => array_key_exists('description', $item) ? wp_kses_post((string) $item['description']) : '',
        )
    );

    if (is_wp_error($inserted)) {
        return $inserted;
    }

    $term = get_term((int) $inserted['term_id'], $taxonomy);

    return $term instanceof WP_Term ? $term : new WP_Error(
        'eventon_apify_term_creation_failed',
        'The ' . $taxonomy . ' term could not be loaded after creation.',
        array('status' => 500)
    );
}

/**
 * Update a term's core data when the payload explicitly supplies it.
 *
 * @param array<string, mixed> $item Term payload.
 * @return WP_Term|WP_Error
 */
function eventon_apify_update_taxonomy_term($taxonomy, WP_Term $term, array $item) {
    $update_args = array();

    if (eventon_apify_array_has_any($item, array('name', 'title', 'label'))) {
        $name = sanitize_text_field((string) eventon_apify_array_get($item, array('name', 'title', 'label'), ''));
        if ($name !== '') {
            $update_args['name'] = $name;
        }
    }

    if (array_key_exists('slug', $item)) {
        $update_args['slug'] = sanitize_title((string) $item['slug']);
    }

    if (array_key_exists('description', $item)) {
        $update_args['description'] = wp_kses_post((string) $item['description']);
    }

    if (!empty($update_args)) {
        $authorization = eventon_apify_assert_can_manage_shared_terms();
        if (is_wp_error($authorization)) {
            return $authorization;
        }

        $updated = wp_update_term($term->term_id, $taxonomy, $update_args);
        if (is_wp_error($updated)) {
            return $updated;
        }

        $term = get_term($term->term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return new WP_Error(
                'eventon_apify_term_update_failed',
                'The ' . $taxonomy . ' term could not be loaded after update.',
                array('status' => 500)
            );
        }
    }

    return $term;
}

/**
 * Persist EventON taxonomy meta into the shared evo_tax_meta option store.
 *
 * @param array<string, scalar|null> $term_meta Term meta to save.
 * @return true|WP_Error
 */
function eventon_apify_save_term_meta_payload($taxonomy, $term_id, array $term_meta) {
    $authorization = eventon_apify_assert_can_manage_shared_terms();
    if (is_wp_error($authorization)) {
        return $authorization;
    }

    $payload = array();
    foreach ($term_meta as $meta_key => $meta_value) {
        if (is_bool($meta_value)) {
            $payload[$meta_key] = eventon_apify_to_yes_no($meta_value);
            continue;
        }

        if ($meta_value === null) {
            $payload[$meta_key] = '';
            continue;
        }

        $payload[$meta_key] = (string) $meta_value;
    }

    if (function_exists('evo_save_term_metas')) {
        evo_save_term_metas($taxonomy, $term_id, $payload);
        return true;
    }

    $all_term_meta = get_option('evo_tax_meta', array());
    if (!is_array($all_term_meta)) {
        $all_term_meta = array();
    }

    $existing = $all_term_meta[$taxonomy][$term_id] ?? array();
    if (!is_array($existing)) {
        $existing = array();
    }

    $merged = array_merge($existing, $payload);
    foreach ($merged as $key => $value) {
        if ($value === '') {
            unset($merged[$key]);
        }
    }

    $all_term_meta[$taxonomy][$term_id] = $merged;
    update_option('evo_tax_meta', $all_term_meta);

    return true;
}

/**
 * Remove legacy location meta from earlier plugin revisions.
 */
function eventon_apify_clear_legacy_location_meta($post_id) {
    foreach (array(
        'evcal_location_name_t',
        'evcal_location_addr',
        'evcal_location_city',
        'evcal_location_state',
        'evcal_location_country',
    ) as $meta_key) {
        delete_post_meta($post_id, $meta_key);
    }
}

/**
 * Remove legacy organizer meta from earlier plugin revisions.
 */
function eventon_apify_clear_legacy_organizer_meta($post_id) {
    delete_post_meta($post_id, 'evcal_organizer_name');
}
