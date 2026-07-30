<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * List EventON events.
 */
function eventon_apify_get_events(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('list');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $page = (int) $request->get_param('page');
    $per_page = (int) $request->get_param('per_page');
    $list_context = eventon_apify_get_events_list_context($request);
    if (is_wp_error($list_context)) {
        return $list_context;
    }
    return rest_ensure_response(eventon_apify_get_events_database_response($request, $page, $per_page, $list_context));
}

/**
 * Build the database-backed paginated events response.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_events_database_response(WP_REST_Request $request, $page, $per_page, array $context) {
    $statuses = eventon_apify_get_requested_statuses($request->get_param('status'));
    if (is_wp_error($statuses)) {
        return $statuses;
    }

    $query_args = array(
        'post_type' => 'ajde_events',
        'post_status' => $statuses,
        'posts_per_page' => $per_page,
        'paged' => $page,
        's' => (string) $request->get_param('search'),
        'order' => strtoupper((string) $context['order']),
    );

    // The slug arg is normalized and capped by eventon_apify_sanitize_slug_filter
    // (its registered sanitize_callback), so the value is already a clean list.
    $slugs = $request->get_param('slug');
    if (!empty($slugs)) {
        $query_args['post_name__in'] = $slugs;
    } elseif ($request->has_param('slug') && eventon_apify_raw_slug_filter_is_nonempty($request)) {
        // An explicit slug filter whose every value sanitized away must match
        // nothing, not silently return the full list.
        $query_args['post__in'] = array(0);
    }

    $after_timestamp = is_array($context['after'] ?? null) && isset($context['after']['timestamp'])
        ? (int) $context['after']['timestamp']
        : null;
    $before_timestamp = is_array($context['before'] ?? null) && isset($context['before']['timestamp'])
        ? (int) $context['before']['timestamp']
        : null;
    $has_date_filter = $after_timestamp !== null || $before_timestamp !== null;

    if ($has_date_filter) {
        // Repeating events must match on any occurrence, not only the base
        // start, so the SQL filter widens to include repeat-enabled events
        // and the occurrence check plus pagination happen in PHP.
        $range_clauses = array('relation' => 'AND');
        if ($after_timestamp !== null) {
            $range_clauses[] = array(
                'key' => 'evcal_srow',
                'value' => $after_timestamp,
                'compare' => '>=',
                'type' => 'NUMERIC',
            );
        }
        if ($before_timestamp !== null) {
            $range_clauses[] = array(
                'key' => 'evcal_srow',
                'value' => $before_timestamp,
                'compare' => '<',
                'type' => 'NUMERIC',
            );
        }

        $query_args['meta_query'] = array(
            'relation' => 'OR',
            $range_clauses,
            array(
                'key' => 'evcal_repeat',
                'value' => 'yes',
            ),
        );
        // Occurrence matching happens in PHP, so the candidate set is bounded
        // to keep a large calendar from exhausting memory. Candidates are
        // already narrowed to in-range base starts plus repeating events.
        $query_args['posts_per_page'] = eventon_apify_get_occurrence_scan_limit();
        $query_args['no_found_rows'] = true;
        unset($query_args['paged']);
    }

    switch ((string) $context['orderby']) {
        case 'created':
            $query_args['orderby'] = 'date';
            break;

        case 'modified':
            $query_args['orderby'] = 'modified';
            break;

        case 'title':
            $query_args['orderby'] = 'title';
            break;

        case 'start_at':
        default:
            if ($has_date_filter) {
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = 'evcal_srow';
            } else {
                // OR-ing EXISTS with NOT EXISTS keeps events that have no
                // evcal_srow meta in the result (and in the totals) instead
                // of the INNER JOIN a bare meta_key orderby produces.
                $query_args['meta_query'] = array(
                    'relation' => 'OR',
                    'start_at_exists' => array(
                        'key' => 'evcal_srow',
                        'compare' => 'EXISTS',
                        'type' => 'NUMERIC',
                    ),
                    'start_at_missing' => array(
                        'key' => 'evcal_srow',
                        'compare' => 'NOT EXISTS',
                    ),
                );
                $query_args['orderby'] = 'start_at_exists';
            }
            break;
    }

    $query = new WP_Query($query_args);

    if ($has_date_filter) {
        $matching = array();
        foreach ($query->posts as $post) {
            if ($post instanceof WP_Post && eventon_apify_event_matches_date_range($post->ID, $after_timestamp, $before_timestamp)) {
                $matching[] = $post;
            }
        }

        $total = count($matching);
        $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;
        $page_posts = array_slice($matching, max(0, ($page - 1) * $per_page), $per_page);

        $events = array();
        foreach ($page_posts as $post) {
            $events[] = eventon_apify_format_event($post);
        }

        $response = array(
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'per_page' => $per_page,
            'events' => $events,
        );

        // Report a capped scan rather than presenting a partial result as
        // complete.
        if (count($query->posts) >= eventon_apify_get_occurrence_scan_limit()) {
            $response['truncated'] = true;
        }

        return $response;
    }

    $events = array();
    foreach ($query->posts as $post) {
        if ($post instanceof WP_Post) {
            $events[] = eventon_apify_format_event($post);
        }
    }

    return array(
        'total' => (int) $query->found_posts,
        'pages' => (int) $query->max_num_pages,
        'page' => $page,
        'per_page' => $per_page,
        'events' => $events,
    );
}

/**
 * Maximum number of candidate events scanned for occurrence matches when a
 * date-range filter is active. Filterable for unusually large calendars.
 */
function eventon_apify_get_occurrence_scan_limit() {
    $limit = (int) apply_filters('eventon_apify_occurrence_scan_limit', 2000);

    return $limit > 0 ? $limit : 2000;
}

/**
 * Whether an event's base start or any repeat occurrence falls inside the
 * wall-as-UTC range [after, before).
 *
 * @param int      $post_id Event post ID.
 * @param int|null $after   Inclusive lower bound, wall-as-UTC.
 * @param int|null $before  Exclusive upper bound, wall-as-UTC.
 */
function eventon_apify_event_matches_date_range($post_id, $after, $before) {
    $in_range = static function ($timestamp) use ($after, $before) {
        if (!$timestamp) {
            return false;
        }
        if ($after !== null && $timestamp < $after) {
            return false;
        }
        if ($before !== null && $timestamp >= $before) {
            return false;
        }
        return true;
    };

    if ($in_range(absint(get_post_meta($post_id, 'evcal_srow', true)))) {
        return true;
    }

    if (!eventon_apify_is_yes(get_post_meta($post_id, 'evcal_repeat', true))) {
        return false;
    }

    $intervals = maybe_unserialize(get_post_meta($post_id, 'repeat_intervals', true));
    if (!is_array($intervals)) {
        return false;
    }

    foreach ($intervals as $interval) {
        if (is_array($interval) && $in_range(absint($interval[0] ?? 0))) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the raw (pre-sanitization) slug request value was non-empty.
 */
function eventon_apify_raw_slug_filter_is_nonempty(WP_REST_Request $request) {
    $raw = $request->get_query_params()['slug'] ?? ($request->get_body_params()['slug'] ?? '');

    if (is_array($raw)) {
        return !empty(array_filter(array_map('trim', array_map('strval', $raw)), 'strlen'));
    }

    return trim((string) $raw) !== '';
}

/**
 * Normalize and validate list-events query parameters.
 */
function eventon_apify_get_events_list_context(WP_REST_Request $request) {
    $site_timezone = wp_timezone();
    $after = '';
    $before = '';

    if ($request->has_param('starts_on_or_after')) {
        $after = trim((string) $request->get_param('starts_on_or_after'));
    }

    if ($request->has_param('starts_before')) {
        $before = trim((string) $request->get_param('starts_before'));
    }

    if ($after === '' && $request->has_param('upcoming') && eventon_apify_sanitize_rest_boolean($request->get_param('upcoming'))) {
        $after = wp_date('Y-m-d', null, $site_timezone);
    }

    $order = strtolower(trim((string) $request->get_param('order')));
    if ($order === '') {
        $order = 'asc';
    }

    if (!in_array($order, array('asc', 'desc'), true)) {
        return new WP_Error(
            'eventon_apify_invalid_events_order',
            __('order must be either asc or desc.', 'eventon-apify'),
            array('status' => 400)
        );
    }

    $orderby = strtolower(trim((string) $request->get_param('orderby')));
    if ($orderby === '') {
        $orderby = 'start_at';
    }

    if (!in_array($orderby, array('start_at', 'created', 'modified', 'title'), true)) {
        return new WP_Error(
            'eventon_apify_invalid_events_orderby',
            __('orderby must be one of start_at, created, modified, or title.', 'eventon-apify'),
            array('status' => 400)
        );
    }

    $after_filter = eventon_apify_normalize_event_date_filter($after, $site_timezone);
    if (is_wp_error($after_filter)) {
        return $after_filter;
    }

    $before_filter = eventon_apify_normalize_event_date_filter($before, $site_timezone);
    if (is_wp_error($before_filter)) {
        return $before_filter;
    }

    if (
        is_array($after_filter)
        && is_array($before_filter)
        && isset($after_filter['timestamp'], $before_filter['timestamp'])
        && (int) $before_filter['timestamp'] <= (int) $after_filter['timestamp']
    ) {
        return new WP_Error(
            'invalid_event_date_range',
            __('starts_before must be later than starts_on_or_after.', 'eventon-apify'),
            array('status' => 400)
        );
    }

    return array(
        'after' => is_array($after_filter) ? $after_filter : null,
        'before' => is_array($before_filter) ? $before_filter : null,
        'order' => $order,
        'orderby' => $orderby,
    );
}

/**
 * Parse a request date/datetime into a normalized event filter definition.
 * Date-only values are normalized in the site timezone for SQL filtering.
 *
 * @return array<string, mixed>|null|WP_Error
 */
function eventon_apify_normalize_event_date_filter($value, DateTimeZone $fallback_timezone) {
    if (!is_scalar($value)) {
        return new WP_Error(
            'eventon_apify_invalid_event_date_filter',
            __('Event date filters must be valid date or datetime strings.', 'eventon-apify'),
            array('status' => 400)
        );
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    // Filter bounds are compared against evcal_srow/repeat_intervals, which
    // live in EventON's wall-as-UTC space, so bounds are built there too.
    if (eventon_apify_validate_local_date($value)) {
        return array(
            'raw' => $value,
            'date' => $value,
            'timestamp' => eventon_apify_build_wall_utc_timestamp($value, '00:00'),
            'is_date_only' => true,
        );
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return new WP_Error(
            'eventon_apify_invalid_event_date_filter',
            __('Event date filters must be valid date or datetime strings.', 'eventon-apify'),
            array('status' => 400)
        );
    }

    $datetime = eventon_apify_parse_event_filter_datetime($value, $fallback_timezone);
    if (!($datetime instanceof DateTimeImmutable)) {
        return new WP_Error(
            'eventon_apify_invalid_event_date_filter',
            __('Event date filters must be valid date or datetime strings.', 'eventon-apify'),
            array('status' => 400)
        );
    }

    // A datetime bound is a real instant: convert it to the site wall clock,
    // then into wall-as-UTC to match the stored coordinate space.
    $local = $datetime->setTimezone($fallback_timezone);

    return array(
        'raw' => $value,
        'timestamp' => eventon_apify_build_wall_utc_timestamp($local->format('Y-m-d'), $local->format('H:i')),
        'is_date_only' => false,
    );
}

/**
 * Parse a request date/datetime string for event filtering.
 *
 * @return DateTimeImmutable|null
 */
function eventon_apify_parse_event_filter_datetime($value, ?DateTimeZone $fallback_timezone = null) {
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if ($fallback_timezone === null) {
        $fallback_timezone = wp_timezone();
    }

    try {
        $datetime = new DateTimeImmutable($value, $fallback_timezone);
    } catch (Exception $exception) {
        unset($exception);
        return null;
    }

    $parse_errors = DateTimeImmutable::getLastErrors();
    if (
        is_array($parse_errors)
        && (
            !empty($parse_errors['warning_count'])
            || !empty($parse_errors['error_count'])
        )
    ) {
        return null;
    }

    return $datetime;
}

/**
 * Convert a CSV-like status value into allowed post statuses.
 *
 * @param mixed $status_param Raw request value.
 * @return array<int, string>|WP_Error
 */
function eventon_apify_get_requested_statuses($status_param) {
    $allowed = array('publish', 'draft', 'private', 'pending', 'future');
    $default = array('publish', 'draft', 'private');

    if (!is_string($status_param) || trim($status_param) === '') {
        return $default;
    }

    $statuses = array_values(array_filter(array_map('trim', explode(',', $status_param)), 'strlen'));
    $unknown = array_diff($statuses, $allowed);

    if (!empty($unknown)) {
        return new WP_Error(
            'eventon_apify_invalid_events_status',
            'status must be a comma-separated list of: ' . implode(', ', $allowed) . '.',
            array('status' => 400)
        );
    }

    $statuses = array_values(array_intersect($allowed, $statuses));

    return !empty($statuses) ? $statuses : $default;
}
