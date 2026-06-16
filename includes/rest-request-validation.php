<?php

/**
 * Validate EventON MCP manifest content type slugs.
 *
 * @param mixed $value Route parameter.
 */
function eventon_apify_validate_content_type_slug($value) {
    return is_string($value) && sanitize_key($value) !== '';
}

/**
 * Resolve a post ID from REST field callbacks.
 *
 * @param array<string, mixed>|object $object REST callback object.
 */
function eventon_apify_get_rest_callback_post_id($object) {
    if (is_array($object)) {
        if (!empty($object['id'])) {
            return absint($object['id']);
        }

        if (!empty($object['ID'])) {
            return absint($object['ID']);
        }
    }

    if (is_object($object)) {
        if (!empty($object->id)) {
            return absint($object->id);
        }

        if (!empty($object->ID)) {
            return absint($object->ID);
        }
    }

    return 0;
}

/**
 * Ensure identifier values are numeric.
 *
 * @param mixed $value Route parameter.
 */
function eventon_apify_validate_numeric_identifier($value) {
    return is_numeric($value);
}

/**
 * Ensure a request parameter is a valid local calendar date.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_validate_local_date($value) {
    if (!is_scalar($value)) {
        return false;
    }

    $value = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    list($year, $month, $day) = array_map('intval', explode('-', $value));

    return checkdate($month, $day, $year);
}

/**
 * Ensure an event date filter is either empty, a local date, or a parseable datetime.
 *
 * @param mixed           $value   Request parameter.
 * @param WP_REST_Request $request Full request object.
 * @param string          $param   Parameter name.
 * @return true|WP_Error
 */
function eventon_apify_validate_event_date_filter($value, $request = null, $param = '') {
    unset($request);

    if (!is_scalar($value)) {
        return new WP_Error(
            'eventon_apify_invalid_event_date_filter',
            sprintf(
                /* translators: %s: Request parameter name. */
                __('%s must be a valid date or datetime string.', 'eventon-apify'),
                $param !== '' ? $param : __('The event date filter', 'eventon-apify')
            ),
            array('status' => 400)
        );
    }

    $value = trim((string) $value);
    if ($value === '') {
        return true;
    }

    if (eventon_apify_validate_local_date($value)) {
        return true;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return new WP_Error(
            'eventon_apify_invalid_event_date_filter',
            sprintf(
                /* translators: %s: Request parameter name. */
                __('%s must be a valid date or datetime string.', 'eventon-apify'),
                $param !== '' ? $param : __('The event date filter', 'eventon-apify')
            ),
            array('status' => 400)
        );
    }

    if (eventon_apify_parse_event_filter_datetime($value) instanceof DateTimeImmutable) {
        return true;
    }

    return new WP_Error(
        'eventon_apify_invalid_event_date_filter',
        sprintf(
            /* translators: %s: Request parameter name. */
            __('%s must be a valid date or datetime string.', 'eventon-apify'),
            $param !== '' ? $param : __('The event date filter', 'eventon-apify')
        ),
        array('status' => 400)
    );
}

/**
 * Ensure the events order value is supported.
 *
 * @param mixed           $value   Request parameter.
 * @param WP_REST_Request $request Full request object.
 * @param string          $param   Parameter name.
 * @return true|WP_Error
 */
function eventon_apify_validate_events_order($value, $request = null, $param = '') {
    unset($request);

    if (!is_scalar($value)) {
        return new WP_Error(
            'eventon_apify_invalid_events_order',
            sprintf(
                /* translators: %s: Request parameter name. */
                __('%s must be either asc or desc.', 'eventon-apify'),
                $param !== '' ? $param : __('order', 'eventon-apify')
            ),
            array('status' => 400)
        );
    }

    $value = strtolower(trim((string) $value));
    if (in_array($value, array('asc', 'desc'), true)) {
        return true;
    }

    return new WP_Error(
        'eventon_apify_invalid_events_order',
        sprintf(
            /* translators: %s: Request parameter name. */
            __('%s must be either asc or desc.', 'eventon-apify'),
            $param !== '' ? $param : __('order', 'eventon-apify')
        ),
        array('status' => 400)
    );
}

/**
 * Ensure the events orderby value is supported.
 *
 * @param mixed           $value   Request parameter.
 * @param WP_REST_Request $request Full request object.
 * @param string          $param   Parameter name.
 * @return true|WP_Error
 */
function eventon_apify_validate_events_orderby($value, $request = null, $param = '') {
    unset($request);

    if (!is_scalar($value)) {
        return new WP_Error(
            'eventon_apify_invalid_events_orderby',
            sprintf(
                /* translators: %s: Request parameter name. */
                __('%s must be one of start_at, created, modified, or title.', 'eventon-apify'),
                $param !== '' ? $param : __('orderby', 'eventon-apify')
            ),
            array('status' => 400)
        );
    }

    $value = strtolower(trim((string) $value));
    if (in_array($value, array('start_at', 'created', 'modified', 'title'), true)) {
        return true;
    }

    return new WP_Error(
        'eventon_apify_invalid_events_orderby',
        sprintf(
            /* translators: %s: Request parameter name. */
            __('%s must be one of start_at, created, modified, or title.', 'eventon-apify'),
            $param !== '' ? $param : __('orderby', 'eventon-apify')
        ),
        array('status' => 400)
    );
}

/**
 * Ensure a request parameter is a valid ISO 8601 datetime string or empty.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_validate_iso8601_datetime($value) {
    if (!is_scalar($value)) {
        return false;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return true;
    }

    return eventon_apify_parse_rsvp_checkpoint_datetime($value) instanceof DateTimeImmutable;
}

/**
 * Ensure a request parameter matches a standard REST boolean value.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_validate_rest_boolean($value) {
    if (is_bool($value)) {
        return true;
    }

    if (is_int($value)) {
        return in_array($value, array(0, 1), true);
    }

    if (!is_scalar($value)) {
        return false;
    }

    return in_array(
        strtolower(trim((string) $value)),
        array('0', '1', 'false', 'true'),
        true
    );
}

/**
 * Sanitize standard REST boolean values.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_sanitize_rest_boolean($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return 1 === $value;
    }

    return in_array(
        strtolower(trim((string) $value)),
        array('1', 'true'),
        true
    );
}

/**
 * Sanitize RSVP list filter values.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_sanitize_rsvp_filter($value) {
    $value = strtolower(trim(sanitize_text_field((string) $value)));

    if (!in_array($value, array('all', 'yes', 'no', 'maybe'), true)) {
        return 'all';
    }

    return $value;
}

/**
 * Sanitize a page number.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_sanitize_page($value) {
    $page = absint($value);
    return $page > 0 ? $page : 1;
}

/**
 * Sanitize a per-page value.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_sanitize_per_page($value) {
    $per_page = absint($value);

    if ($per_page < 1) {
        $per_page = 20;
    }

    return min($per_page, 100);
}

/**
 * Sanitize a slug filter that accepts a comma-separated string or an array.
 *
 * Preserves the array form (?slug[]=a&slug[]=b) instead of collapsing it to an
 * empty string, sanitizes each value as a slug, and caps the number of slugs to
 * keep the resulting post_name__in query bounded.
 *
 * @param mixed $value Request parameter.
 * @return array Sanitized list of slugs.
 */
function eventon_apify_sanitize_slug_filter($value) {
    if (!is_array($value)) {
        $value = explode(',', (string) $value);
    }

    return array_slice(array_filter(array_map('sanitize_title', $value)), 0, EVENTON_APIFY_MAX_SLUG_FILTER);
}

/**
 * Sanitize allowed post statuses.
 *
 * @param mixed $status Submitted post status.
 */
function eventon_apify_get_sanitized_status($status) {
    $allowed = array('publish', 'draft', 'private', 'pending', 'future');
    $status = sanitize_key((string) $status);

    return in_array($status, $allowed, true) ? $status : '';
}

/**
 * Allowed EventON event statuses.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_event_statuses() {
    return array('scheduled', 'cancelled', 'movedonline', 'postponed', 'rescheduled', 'preliminary', 'tentative');
}

/**
 * Allowed EventON attendance modes.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_attendance_modes() {
    return array('offline', 'online', 'mixed');
}

/**
 * Allowed repeat frequencies.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_repeat_frequencies() {
    return array('hourly', 'daily', 'weekly', 'monthly', 'yearly', 'custom');
}

/**
 * Allowed normalized event click interaction modes.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_interaction_modes() {
    return array('do_nothing', 'slide_down_eventcard', 'external_link', 'popup_window', 'open_event_page');
}

/**
 * Validate a URL-like input.
 *
 * @param mixed $value URL input.
 */
function eventon_apify_validate_url($value) {
    if ($value === null || trim((string) $value) === '') {
        return true;
    }

    $url = esc_url_raw((string) $value);

    if ($url === '') {
        return false;
    }

    if (function_exists('wp_http_validate_url')) {
        return (bool) wp_http_validate_url($url);
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate featured image attachment input.
 *
 * @param mixed $value Attachment input.
 * @return true|WP_Error
 */
function eventon_apify_validate_featured_media_input($value) {
    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        return true;
    }

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return new WP_Error(
            'eventon_apify_invalid_featured_media',
            'featured_media must be an attachment ID, or 0 to clear the featured image.',
            array('status' => 400)
        );
    }

    $attachment_id = absint($value);
    $attachment = get_post($attachment_id);

    if (!$attachment instanceof WP_Post || $attachment->post_type !== 'attachment') {
        return new WP_Error(
            'eventon_apify_invalid_featured_media',
            'featured_media must reference an existing WordPress attachment.',
            array('status' => 400)
        );
    }

    if (!wp_attachment_is_image($attachment_id)) {
        return new WP_Error(
            'eventon_apify_invalid_featured_media',
            'featured_media must reference an image attachment.',
            array('status' => 400)
        );
    }

    return true;
}

/**
 * Normalize colors to EventON's stored format (hex without the leading #).
 *
 * @param mixed $value Color input.
 * @return string|null
 */
function eventon_apify_normalize_color_input($value) {
    $value = ltrim(trim((string) $value), '#');

    if ($value === '') {
        return '';
    }

    return preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? strtolower($value) : null;
}
