<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Record a canonical change timestamp whenever an RSVP post itself is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Saved post object.
 * @param bool    $update  Whether this is an update.
 */
function eventon_apify_touch_rsvp_post_on_save($post_id, $post) {

    if (!($post instanceof WP_Post) || $post->post_type !== 'evo-rsvp') {
        return;
    }

    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    eventon_apify_touch_rsvp_post($post_id);
}

/**
 * Record a canonical change timestamp whenever RSVP meta is added, updated, or deleted.
 *
 * @param int|string $meta_id    Meta row ID.
 * @param int|string $post_id    Post ID.
 * @param string     $meta_key   Meta key.
 * @param mixed      $meta_value Meta value.
 */
function eventon_apify_touch_rsvp_post_on_meta_change($_meta_id, $post_id, $meta_key) {

    $post_id = absint($post_id);
    if ($post_id < 1 || $meta_key === EVENTON_APIFY_RSVP_UPDATED_AT_META) {
        return;
    }

    if (get_post_type($post_id) !== 'evo-rsvp') {
        return;
    }

    eventon_apify_touch_rsvp_post($post_id);
}

/**
 * Persist the canonical RSVP change timestamp in GMT.
 */
function eventon_apify_touch_rsvp_post($post_id) {
    $now = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)), new DateTimeZone('UTC'));
    if (!$now instanceof DateTimeImmutable) {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    update_post_meta($post_id, EVENTON_APIFY_RSVP_UPDATED_AT_META, $now->format('Y-m-d H:i:s.u'));
}

/**
 * Return the yes-only RSVP summary for an EventON event.
 */
function eventon_apify_get_event_rsvp_summary(WP_REST_Request $request) {
    $ready = eventon_apify_assert_rsvp_api_capability_is_ready('rsvp_counts');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $event = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($event)) {
        return $event;
    }

    $attendees = eventon_apify_get_event_rsvp_attendees($event->ID);
    if (is_wp_error($attendees)) {
        return $attendees;
    }

    $yes_submissions = 0;
    $yes_attendees_total = 0;

    foreach ($attendees as $attendee) {
        if (($attendee['rsvp'] ?? '') !== 'yes') {
            continue;
        }

        $yes_submissions++;
        $yes_attendees_total += max(1, absint($attendee['count'] ?? 1));
    }

    return rest_ensure_response(
        array(
            'event_id' => $event->ID,
            'event_title' => $event->post_title,
            'yes_submissions' => $yes_submissions,
            'yes_attendees_total' => $yes_attendees_total,
            'yes_additional_attendees' => max(0, $yes_attendees_total - $yes_submissions),
        )
    );
}

/**
 * Apply the rsvp / status / search list filters to RSVP attendees.
 *
 * @param array<int, array<string, mixed>> $attendees     Attendee records.
 * @param string                           $rsvp_filter   'all' or an exact rsvp value.
 * @param string                           $status_filter '', 'all', or a lowercased status.
 * @param string                           $search        Lowercased search term, or ''.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_filter_rsvp_attendees(array $attendees, $rsvp_filter, $status_filter, $search) {
    if ($rsvp_filter !== 'all') {
        $attendees = array_values(
            array_filter(
                $attendees,
                static function (array $attendee) use ($rsvp_filter) {
                    return ($attendee['rsvp'] ?? '') === $rsvp_filter;
                }
            )
        );
    }

    if ($status_filter !== '' && $status_filter !== 'all') {
        $attendees = array_values(
            array_filter(
                $attendees,
                static function (array $attendee) use ($status_filter) {
                    return strtolower((string) ($attendee['status'] ?? '')) === $status_filter;
                }
            )
        );
    }

    if ($search !== '') {
        $attendees = array_values(
            array_filter(
                $attendees,
                static function (array $attendee) use ($search) {
                    return eventon_apify_rsvp_attendee_matches_search($attendee, $search);
                }
            )
        );
    }

    return $attendees;
}

/**
 * Paginate a list, returning the requested page slice plus collection metadata.
 *
 * @param array<int, mixed> $items    Items to paginate.
 * @param int               $page     1-based page number.
 * @param int               $per_page Page size.
 * @return array<string, mixed>
 */
function eventon_apify_paginate_list(array $items, $page, $per_page) {
    $total = count($items);
    $pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
    $offset = max(0, ($page - 1) * $per_page);

    return array(
        'total' => $total,
        'pages' => $pages,
        'page' => $page,
        'per_page' => $per_page,
        'items' => array_slice($items, $offset, $per_page),
    );
}

/**
 * List RSVP attendee records for an EventON event.
 */
function eventon_apify_get_event_rsvps(WP_REST_Request $request) {
    $ready = eventon_apify_assert_rsvp_api_capability_is_ready('rsvp_attendees');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $event = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($event)) {
        return $event;
    }

    $attendees = eventon_apify_get_event_rsvp_attendees($event->ID);
    if (is_wp_error($attendees)) {
        return $attendees;
    }

    $rsvp_filter = eventon_apify_sanitize_rsvp_filter($request->get_param('rsvp'));
    $status_filter = strtolower(trim((string) $request->get_param('status')));
    $search = strtolower(trim((string) $request->get_param('search')));
    $updated_after = eventon_apify_parse_rsvp_checkpoint_datetime($request->get_param('updated_after'));
    $updated_after_id = absint($request->get_param('updated_after_id'));

    if (false === $updated_after) {
        return new WP_Error(
            'eventon_apify_invalid_updated_after',
            'The updated_after parameter must be a valid ISO 8601 datetime.',
            array('status' => 400)
        );
    }

    if ($updated_after instanceof DateTimeImmutable && eventon_apify_rsvp_delta_filters_conflict($rsvp_filter, $status_filter, $search)) {
        return new WP_Error(
            'eventon_apify_invalid_rsvp_delta_filters',
            'The updated_after parameter cannot be combined with rsvp, status, or search filters.',
            array('status' => 400)
        );
    }

    $attendees = eventon_apify_filter_rsvp_attendees($attendees, $rsvp_filter, $status_filter, $search);

    if ($updated_after instanceof DateTimeImmutable) {
        $checkpoint_timestamp = eventon_apify_get_rsvp_datetime_sort_key($updated_after);
        $attendees = array_values(
            array_filter(
                $attendees,
                static function (array $attendee) use ($checkpoint_timestamp, $updated_after_id) {
                    return eventon_apify_rsvp_attendee_is_after_checkpoint($attendee, $checkpoint_timestamp, $updated_after_id);
                }
            )
        );

        // Schwartzian transform: compute sort key once per attendee instead of O(N log N) times.
        $keyed = array_map(
            static fn(array $a) => array('key' => eventon_apify_get_rsvp_datetime_sort_key($a['updated_at'] ?? ''), 'data' => $a),
            $attendees
        );
        usort($keyed, static fn($l, $r) => $l['key'] <=> $r['key'] ?: (int) ($l['data']['id'] ?? 0) <=> (int) ($r['data']['id'] ?? 0));
        $attendees = array_column($keyed, 'data');
    }

    $pagination = eventon_apify_paginate_list(
        $attendees,
        (int) $request->get_param('page'),
        (int) $request->get_param('per_page')
    );
    $paged_attendees = $pagination['items'];
    $pages = $pagination['pages'];
    $page = $pagination['page'];

    $response = array(
        'total' => $pagination['total'],
        'pages' => $pages,
        'page' => $page,
        'per_page' => $pagination['per_page'],
        'attendees' => $paged_attendees,
    );

    if ($updated_after instanceof DateTimeImmutable) {
        $response['has_more'] = $page < $pages;
        $response['sync_checkpoint'] = eventon_apify_get_rsvp_sync_checkpoint(
            $paged_attendees,
            $updated_after->format('c'),
            $updated_after_id
        );
    }

    return rest_ensure_response(
        $response
    );
}

/**
 * Return normalized RSVP attendee records for an EventON event.
 *
 * @return array<int, array<string, mixed>>|WP_Error
 */
function eventon_apify_get_event_rsvp_attendees($event_id) {
    $repository = new \EventON_APIfy\RSVP_Attendee_Repository(
        new \EventON_APIfy\RSVP_Attendee_Formatter()
    );
    return $repository->find_by_event($event_id);
}

/**
 * Format an RSVP attendee record into a stable API payload.
 *
 * @return array<string, mixed>
 */
function eventon_apify_format_rsvp_attendee(WP_Post $post) {
    $formatter = new \EventON_APIfy\RSVP_Attendee_Formatter();
    return $formatter->format($post);
}

/**
 * Read a normalized RSVP field from the addon object or raw post meta.
 *
 * @param object|null                      $rsvp_object RSVP addon object.
 * @param array<string, array<int, mixed>> $meta        Raw post meta.
 * @param array<int, string>               $methods     Candidate method names.
 * @param array<int, string>               $keys        Candidate property/meta keys.
 * @return mixed
 */
function eventon_apify_get_rsvp_field_value($rsvp_object, array $meta, array $methods, array $keys) {
    if (is_object($rsvp_object)) {
        foreach ($methods as $method) {
            if (!method_exists($rsvp_object, $method)) {
                continue;
            }

            $value = $rsvp_object->{$method}();
            if ($value !== null && $value !== '' && $value !== array()) {
                return $value;
            }
        }

        foreach ($keys as $key) {
            if (method_exists($rsvp_object, 'get_prop')) {
                $value = $rsvp_object->get_prop($key);
                if ($value !== null && $value !== '' && $value !== array()) {
                    return $value;
                }
            }

            if (method_exists($rsvp_object, 'get_prop_')) {
                $value = $rsvp_object->get_prop_($key);
                if ($value !== null && $value !== '' && $value !== array()) {
                    return $value;
                }
            }
        }
    }

    foreach ($keys as $key) {
        if (!isset($meta[$key][0])) {
            continue;
        }

        $value = maybe_unserialize($meta[$key][0]);
        if ($value !== null && $value !== '' && $value !== array()) {
            return $value;
        }
    }

    return '';
}

/**
 * Parse an RSVP delta-sync datetime parameter.
 *
 * @param mixed $value Raw request value.
 * @return DateTimeImmutable|false|null
 */
function eventon_apify_parse_rsvp_checkpoint_datetime($value) {
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $value = trim($value);
    $formats = array(
        'Y-m-d\TH:i:s\Z',
        'Y-m-d\TH:i:sP',
        'Y-m-d\TH:i:s.u\Z',
        'Y-m-d\TH:i:s.uP',
    );

    foreach ($formats as $format) {
        $datetime = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));
        if (!$datetime instanceof DateTimeImmutable) {
            continue;
        }

        $last_errors = DateTimeImmutable::getLastErrors();
        if (
            is_array($last_errors)
            && ((int) ($last_errors['warning_count'] ?? 0) > 0 || (int) ($last_errors['error_count'] ?? 0) > 0)
        ) {
            continue;
        }

        return $datetime->setTimezone(new DateTimeZone('UTC'));
    }

    return false;
}

/**
 * Return true when RSVP delta sync is combined with stateful filters.
 */
function eventon_apify_rsvp_delta_filters_conflict($rsvp_filter, $status_filter, $search) {
    return $rsvp_filter !== 'all'
        || ($status_filter !== '' && $status_filter !== 'all')
        || $search !== '';
}

/**
 * Wrap a Unix timestamp in a UTC DateTimeImmutable.
 */
function eventon_apify_timestamp_to_utc_datetime(int $timestamp) {
    return (new DateTimeImmutable())->setTimestamp($timestamp)->setTimezone(new DateTimeZone('UTC'));
}

/**
 * Return the RSVP creation timestamp in ISO 8601 UTC.
 */
function eventon_apify_get_post_created_at_iso8601(WP_Post $post) {
    $timestamp = (int) get_post_time('U', true, $post);

    if ($timestamp < 1) {
        $timestamp = (int) strtotime((string) $post->post_date_gmt . ' UTC');
    }

    if ($timestamp < 1) {
        return '';
    }

    return eventon_apify_format_datetime_iso8601_utc(eventon_apify_timestamp_to_utc_datetime($timestamp));
}

/**
 * Return the canonical RSVP updated timestamp in ISO 8601 UTC.
 */
function eventon_apify_get_rsvp_updated_at_iso8601(WP_Post $post) {
    $stored = get_post_meta($post->ID, EVENTON_APIFY_RSVP_UPDATED_AT_META, true);
    $stored_datetime = eventon_apify_parse_datetime_to_utc($stored);

    if ($stored_datetime instanceof DateTimeImmutable) {
        return eventon_apify_format_datetime_iso8601_utc($stored_datetime);
    }

    $timestamp = (int) get_post_modified_time('U', true, $post);

    if ($timestamp < 1) {
        $timestamp = (int) get_post_time('U', true, $post);
    }

    if ($timestamp < 1) {
        return '';
    }

    return eventon_apify_format_datetime_iso8601_utc(eventon_apify_timestamp_to_utc_datetime($timestamp));
}

/**
 * Return true when an RSVP attendee sorts after the stored checkpoint pair.
 *
 * @param string $checkpoint_timestamp Pre-computed sort key from eventon_apify_get_rsvp_datetime_sort_key.
 */
function eventon_apify_rsvp_attendee_is_after_checkpoint(array $attendee, string $checkpoint_timestamp, $updated_after_id) {
    $attendee_timestamp = eventon_apify_get_rsvp_datetime_sort_key($attendee['updated_at'] ?? '');
    if ($attendee_timestamp === '') {
        return false;
    }

    if ($attendee_timestamp > $checkpoint_timestamp) {
        return true;
    }

    if ($attendee_timestamp < $checkpoint_timestamp) {
        return false;
    }

    return (int) ($attendee['id'] ?? 0) > absint($updated_after_id);
}

/**
 * Return the checkpoint pair for the current RSVP page.
 *
 * @param array<int, array<string, mixed>> $attendees Paginated attendee slice.
 * @return array<string, mixed>
 */
function eventon_apify_get_rsvp_sync_checkpoint(array $attendees, $fallback_updated_at, $fallback_id) {
    $last_attendee = !empty($attendees) ? end($attendees) : null;

    if (is_array($last_attendee) && !empty($last_attendee['updated_at'])) {
        return array(
            'updated_at' => (string) $last_attendee['updated_at'],
            'id' => (int) ($last_attendee['id'] ?? 0),
        );
    }

    return array(
        'updated_at' => (string) $fallback_updated_at,
        'id' => absint($fallback_id),
    );
}

/**
 * Parse a supported datetime value and normalize it to UTC.
 *
 * @param mixed $value Raw datetime value.
 * @return DateTimeImmutable|null
 */
function eventon_apify_parse_datetime_to_utc($value) {
    if ($value instanceof DateTimeImmutable) {
        return $value->setTimezone(new DateTimeZone('UTC'));
    }

    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'));
    }

    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable(trim($value)))->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception) {
        return null;
    }
}

/**
 * Format a UTC datetime for API responses and sync checkpoints.
 */
function eventon_apify_format_datetime_iso8601_utc(DateTimeImmutable $datetime) {
    return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
}

/**
 * Return a lexicographically sortable UTC datetime key with microsecond precision.
 *
 * @param mixed $value Raw datetime value.
 */
function eventon_apify_get_rsvp_datetime_sort_key($value) {
    $datetime = eventon_apify_parse_datetime_to_utc($value);
    return $datetime instanceof DateTimeImmutable ? $datetime->format('Y-m-d H:i:s.u') : '';
}

/**
 * Return the formatted event time string shown by the RSVP CSV export.
 */
function eventon_apify_get_rsvp_event_time($event_id, $repeat_interval) {
    if (!$event_id || !class_exists('EVORS_Event')) {
        return '';
    }

    $event = new EVORS_Event($event_id, $repeat_interval);

    if (!isset($event->event) || !is_object($event->event) || !method_exists($event->event, 'get_formatted_smart_time')) {
        return '';
    }

    return (string) $event->event->get_formatted_smart_time($repeat_interval);
}

/**
 * Normalize EventON RSVP values to yes/no/maybe.
 */
function eventon_apify_normalize_rsvp_response($value) {
    $value = strtolower(trim((string) $value));

    if (in_array($value, array('y', 'yes'), true)) {
        return 'yes';
    }

    if (in_array($value, array('n', 'no'), true)) {
        return 'no';
    }

    if (in_array($value, array('m', 'maybe'), true)) {
        return 'maybe';
    }

    return $value;
}

/**
 * Normalize stored other-attendee values into a flat string array.
 *
 * @param mixed $value Raw stored value.
 * @return array<int, string>
 */
function eventon_apify_normalize_rsvp_other_attendees($value) {
    if (is_string($value)) {
        $parts = preg_split('/[\r\n,]+/', $value);
    } elseif (is_array($value)) {
        $parts = $value;
    } else {
        $parts = array();
    }

    $items = array();
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $items[] = $part;
        }
    }

    return array_values(array_unique($items));
}

/**
 * Return additional RSVP form fields without exposing internal system keys.
 *
 * @param array<string, array<int, mixed>> $meta Raw post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_rsvp_custom_fields(array $meta) {
    $custom_fields = array();
    $excluded_keys = array(
        'e_id',
        'event_id',
        'evors_event_id',
        'count',
        'qty',
        'rsvp',
        'evors_rsvp',
        'response',
        'status',
        'checkin_status',
        'evors_status',
        'type',
        'rsvp_type',
        'names',
        'other_attendees',
        'attendees',
        'fname',
        'first_name',
        'firstname',
        'lname',
        'last_name',
        'lastname',
        'email',
        'evors_email',
        'email_address',
        'phone',
        'evors_phone',
        'updates',
        'email_updates',
        'optin',
        'subscribe',
    );

    foreach ($meta as $key => $values) {
        if (str_starts_with((string) $key, '_') || in_array($key, $excluded_keys, true)) {
            continue;
        }

        if (!isset($values[0])) {
            continue;
        }

        $value = maybe_unserialize($values[0]);

        if (is_array($value)) {
            $custom_fields[$key] = array_values(
                array_map(
                    static function ($item) {
                        return is_scalar($item) ? sanitize_text_field((string) $item) : wp_json_encode($item);
                    },
                    $value
                )
            );
            continue;
        }

        if (is_scalar($value) && trim((string) $value) !== '') {
            $custom_fields[$key] = sanitize_text_field((string) $value);
        }
    }

    ksort($custom_fields);

    return $custom_fields;
}

/**
 * Determine whether an RSVP attendee row matches a text search.
 */
function eventon_apify_rsvp_attendee_matches_search(array $attendee, $search) {
    $haystacks = array(
        $attendee['first_name'] ?? '',
        $attendee['last_name'] ?? '',
        $attendee['full_name'] ?? '',
        $attendee['email'] ?? '',
        $attendee['phone'] ?? '',
        $attendee['rsvp'] ?? '',
        $attendee['status'] ?? '',
        $attendee['rsvp_type'] ?? '',
        implode(' ', is_array($attendee['other_attendees'] ?? null) ? $attendee['other_attendees'] : array()),
    );

    if (!empty($attendee['custom_fields']) && is_array($attendee['custom_fields'])) {
        foreach ($attendee['custom_fields'] as $value) {
            if (is_array($value)) {
                $haystacks[] = implode(' ', array_map('strval', $value));
            } else {
                $haystacks[] = (string) $value;
            }
        }
    }

    $combined = strtolower(implode(' ', $haystacks));

    return $combined !== '' && str_contains($combined, $search);
}
