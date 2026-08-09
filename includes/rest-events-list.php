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
 * Returns a WP_Error for a rejected status filter. The caller passes the
 * result to rest_ensure_response(), which hands a WP_Error straight back to
 * the client, so the error still surfaces as its declared status code.
 *
 * @return array<string, mixed>|WP_Error
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
    $scan_limit = eventon_apify_get_occurrence_scan_limit();

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
        // already narrowed to in-range base starts plus repeating events, and
        // only IDs are fetched: WP_Query skips post, meta, and term cache
        // priming entirely for an ID-only query, so a 2000-candidate scan does
        // not materialize ~90 meta rows per event for rows it discards.
        $query_args['posts_per_page'] = $scan_limit;
        $query_args['fields'] = 'ids';
        $query_args['no_found_rows'] = true;
        unset($query_args['paged']);
    }

    $order_by_start_at = false;

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
                // Ordered through a single LEFT JOIN (see the posts_clauses
                // filter below) rather than a meta_key orderby: the bare
                // meta_key form produces an INNER JOIN that drops events with
                // no evcal_srow from both results and totals.
                $order_by_start_at = true;
                $query_args['eventon_apify_order_by_start_at'] = true;
            }
            break;
    }

    if ($order_by_start_at) {
        add_filter('posts_clauses', 'eventon_apify_apply_start_at_order_clauses', 10, 2);
    }

    $query = new WP_Query($query_args);

    if ($order_by_start_at) {
        remove_filter('posts_clauses', 'eventon_apify_apply_start_at_order_clauses', 10);
    }

    $posts = $query->posts;
    $total = (int) $query->found_posts;
    $pages = (int) $query->max_num_pages;
    $truncated = false;

    if ($has_date_filter) {
        $matching = eventon_apify_filter_event_ids_by_date_range($posts, $after_timestamp, $before_timestamp);

        $truncated = count($posts) >= $scan_limit;
        $total = count($matching);
        $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;
        $page_ids = array_slice($matching, max(0, ($page - 1) * $per_page), $per_page);

        // Prime caches for just the page being serialized, so format_event's
        // per-event meta reads stay cache hits.
        if (!empty($page_ids)) {
            _prime_post_caches($page_ids);
        }

        $posts = array_filter(array_map('get_post', $page_ids));
    }

    $events = array();
    foreach ($posts as $post) {
        if ($post instanceof WP_Post) {
            $events[] = eventon_apify_format_event($post);
        }
    }

    $response = array(
        'total' => $total,
        'pages' => $pages,
        'page' => $page,
        'per_page' => $per_page,
        'events' => $events,
    );

    // Report a capped scan rather than presenting a partial result as complete.
    if ($truncated) {
        $response['truncated'] = true;
    }

    return $response;
}

/**
 * Order an events query by EventON's start timestamp through a single LEFT
 * JOIN, keeping events that have no stored start timestamp in the result set.
 *
 * @param array<string, string> $clauses SQL clauses.
 * @return array<string, string>
 */
function eventon_apify_apply_start_at_order_clauses($clauses, WP_Query $query) {
    global $wpdb;

    if (!$query->get('eventon_apify_order_by_start_at')) {
        return $clauses;
    }

    $order = strtoupper((string) $query->get('order')) === 'DESC' ? 'DESC' : 'ASC';

    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS eventon_apify_srow"
        . " ON ({$wpdb->posts}.ID = eventon_apify_srow.post_id AND eventon_apify_srow.meta_key = 'evcal_srow')";
    $clauses['orderby'] = "CAST(eventon_apify_srow.meta_value AS SIGNED) {$order}, {$wpdb->posts}.ID {$order}";

    return $clauses;
}

/**
 * Reduce candidate event IDs to those with an occurrence inside the range.
 *
 * Reads the three needed meta keys in one query rather than per event.
 *
 * @param array<int, mixed> $post_ids Candidate event IDs.
 * @param int|null          $after    Inclusive lower bound, wall-as-UTC.
 * @param int|null          $before   Exclusive upper bound, wall-as-UTC.
 * @return array<int, int>
 */
function eventon_apify_filter_event_ids_by_date_range(array $post_ids, $after, $before) {
    $post_ids = array_values(array_filter(array_map('absint', $post_ids)));
    if (empty($post_ids)) {
        return array();
    }

    $meta = eventon_apify_get_occurrence_meta_for_ids($post_ids);
    $matching = array();

    foreach ($post_ids as $post_id) {
        $event_meta = $meta[$post_id] ?? array();

        if (eventon_apify_occurrence_meta_matches_range($event_meta, $after, $before)) {
            $matching[] = $post_id;
        }
    }

    return $matching;
}

/**
 * Fetch only the occurrence-related meta for a set of events.
 *
 * @param array<int, int> $post_ids Event IDs.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_occurrence_meta_for_ids(array $post_ids) {
    global $wpdb;

    $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %d.
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}"
            . " WHERE meta_key IN ('evcal_srow', 'evcal_repeat', 'repeat_intervals')"
            . " AND post_id IN ({$placeholders})",
            $post_ids
        )
    );

    $meta = array();
    foreach ((array) $rows as $row) {
        $meta[(int) $row->post_id][$row->meta_key] = $row->meta_value;
    }

    return $meta;
}

/**
 * Whether an event's base start or any repeat occurrence falls inside the
 * wall-as-UTC range [after, before).
 *
 * @param array<string, mixed> $meta   Occurrence meta for one event.
 * @param int|null             $after  Inclusive lower bound, wall-as-UTC.
 * @param int|null             $before Exclusive upper bound, wall-as-UTC.
 */
function eventon_apify_occurrence_meta_matches_range(array $meta, $after, $before) {
    if (eventon_apify_timestamp_is_in_range(absint($meta['evcal_srow'] ?? 0), $after, $before)) {
        return true;
    }

    if (!eventon_apify_is_yes($meta['evcal_repeat'] ?? '')) {
        return false;
    }

    $intervals = maybe_unserialize($meta['repeat_intervals'] ?? '');
    if (!is_array($intervals)) {
        return false;
    }

    foreach ($intervals as $interval) {
        if (is_array($interval) && eventon_apify_timestamp_is_in_range(absint($interval[0] ?? 0), $after, $before)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a timestamp falls inside [after, before).
 *
 * @param int      $timestamp Timestamp to test.
 * @param int|null $after     Inclusive lower bound.
 * @param int|null $before    Exclusive upper bound.
 */
function eventon_apify_timestamp_is_in_range($timestamp, $after, $before) {
    if (!$timestamp) {
        return false;
    }

    if ($after !== null && $timestamp < $after) {
        return false;
    }

    return !($before !== null && $timestamp >= $before);
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
    $parts = eventon_apify_convert_instant_to_wall_parts($datetime->format('c'), $fallback_timezone->getName());

    return array(
        'raw' => $value,
        'timestamp' => $parts ? eventon_apify_build_wall_utc_timestamp($parts['date'], $parts['time']) : null,
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
    $allowed = eventon_apify_get_allowed_post_statuses();
    $default = eventon_apify_get_default_list_post_statuses();

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
