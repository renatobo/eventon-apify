<?php

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
    $query_args = array(
        'post_type' => 'ajde_events',
        'post_status' => eventon_apify_get_requested_statuses($request->get_param('status')),
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
    }

    $meta_query = array();

    if (is_array($context['after'] ?? null) && isset($context['after']['timestamp'])) {
        $meta_query[] = array(
            'key' => 'evcal_srow',
            'value' => (int) $context['after']['timestamp'],
            'compare' => '>=',
            'type' => 'NUMERIC',
        );
    }

    if (is_array($context['before'] ?? null) && isset($context['before']['timestamp'])) {
        $meta_query[] = array(
            'key' => 'evcal_srow',
            'value' => (int) $context['before']['timestamp'],
            'compare' => '<',
            'type' => 'NUMERIC',
        );
    }

    if (!empty($meta_query)) {
        if (count($meta_query) > 1) {
            $query_args['meta_query'] = array_merge(array('relation' => 'AND'), $meta_query);
        } else {
            $query_args['meta_query'] = $meta_query;
        }
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
            $query_args['orderby'] = 'meta_value_num';
            $query_args['meta_key'] = 'evcal_srow';
            break;
    }

    $query = new WP_Query($query_args);
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

    if (eventon_apify_validate_local_date($value)) {
        return array(
            'raw' => $value,
            'date' => $value,
            'timestamp' => eventon_apify_build_timestamp($value, '00:00', $fallback_timezone->getName()),
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

    return array(
        'raw' => $value,
        'timestamp' => $datetime->getTimestamp(),
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
 * @return array<int, string>
 */
function eventon_apify_get_requested_statuses($status_param) {
    $allowed = array('publish', 'draft', 'private', 'pending', 'future');
    $default = array('publish', 'draft', 'private');

    if (!is_string($status_param) || trim($status_param) === '') {
        return $default;
    }

    $statuses = array_map('trim', explode(',', $status_param));
    $statuses = array_values(array_intersect($allowed, $statuses));

    return !empty($statuses) ? $statuses : $default;
}
