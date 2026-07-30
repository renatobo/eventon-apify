<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize an API interaction mode value.
 */
function eventon_apify_normalize_interaction_mode($value) {
    return eventon_apify_map_interaction_code_to_mode((string) $value);
}

/**
 * Return every accepted interaction mode input, keyed by EventON's stored
 * codes and the normalized API names, mapped to the normalized name.
 *
 * @return array<string, string>
 */
function eventon_apify_get_interaction_mode_map() {
    return array(
        'X' => 'do_nothing',
        '1' => 'slide_down_eventcard',
        '2' => 'external_link',
        '3' => 'popup_window',
        '4' => 'open_event_page',
        'do_nothing' => 'do_nothing',
        'slide_down_eventcard' => 'slide_down_eventcard',
        'external_link' => 'external_link',
        'popup_window' => 'popup_window',
        'open_event_page' => 'open_event_page',
    );
}

/**
 * Map EventON's stored interaction codes to normalized API values.
 */
function eventon_apify_map_interaction_code_to_mode($value) {
    $map = eventon_apify_get_interaction_mode_map();

    return $map[trim((string) $value)] ?? 'slide_down_eventcard';
}

/**
 * Map normalized API interaction values back to EventON's stored codes.
 */
function eventon_apify_map_interaction_mode_to_code($value) {
    $mode = eventon_apify_normalize_interaction_mode($value);

    $map = array(
        'do_nothing' => 'X',
        'slide_down_eventcard' => '1',
        'external_link' => '2',
        'popup_window' => '3',
        'open_event_page' => '4',
    );

    return $map[$mode] ?? '1';
}

/**
 * Return the first matching key from an array.
 *
 * @param array<string, mixed> $source Source array.
 * @param array<int, string>   $keys   Candidate keys.
 * @return mixed
 */
function eventon_apify_array_get(array $source, array $keys, $default = null) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $source)) {
            return $source[$key];
        }
    }

    return $default;
}

/**
 * Determine whether any of the provided keys exist in the array.
 *
 * @param array<string, mixed> $source Source array.
 * @param array<int, string>   $keys   Candidate keys.
 */
function eventon_apify_array_has_any(array $source, array $keys) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $source)) {
            return true;
        }
    }

    return false;
}

/**
 * Check whether an EventON yes/no style value means yes.
 *
 * @param mixed $value Yes/no style input.
 */
function eventon_apify_is_yes($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (int) $value === 1;
    }

    return in_array(strtolower(trim((string) $value)), array('yes', 'y', '1', 'true', 'on'), true);
}

/**
 * Convert a truthy value into EventON's yes/no string format.
 *
 * @param mixed $value Yes/no style input.
 */
function eventon_apify_to_yes_no($value) {
    return eventon_apify_is_yes($value) ? 'yes' : 'no';
}

/**
 * Sanitize an optional email address, preserving a deliberately blank value.
 *
 * Malformed addresses are rejected by validation before this runs, so an
 * empty result here can only mean the caller intends to clear the value.
 *
 * @param mixed $value Email input.
 */
function eventon_apify_sanitize_optional_email($value) {
    return trim((string) $value) === '' ? '' : sanitize_email((string) $value);
}

/**
 * Validate timezone identifiers.
 */
function eventon_apify_is_valid_timezone($timezone_key) {
    return in_array((string) $timezone_key, timezone_identifiers_list(), true);
}

/**
 * Determine whether an interaction mode input is one of the known
 * modes or stored codes, before normalization coerces it to a default.
 *
 * @param mixed $value Raw interaction mode input.
 */
function eventon_apify_is_known_interaction_mode($value) {
    return is_scalar($value)
        && array_key_exists(trim((string) $value), eventon_apify_get_interaction_mode_map());
}

/**
 * Split HH:MM (optionally HH:MM:SS; seconds are dropped) time strings into
 * EventON-compatible pieces.
 *
 * @param string $time Time string.
 * @return array<string, string>|null
 */
function eventon_apify_split_time_string($time) {
    $time = trim($time);

    if ($time === '') {
        return null;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches)) {
        return null;
    }

    $hour = (int) $matches[1];
    $minute = (int) $matches[2];

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        return null;
    }

    return array(
        'hour' => (string) $hour,
        'minute' => str_pad((string) $minute, 2, '0', STR_PAD_LEFT),
    );
}

/**
 * Build a site-timezone timestamp from date/time inputs.
 *
 * @param string $date Date string.
 * @param string $time Optional time string.
 * @return int|null
 */
function eventon_apify_build_timestamp($date, $time = '', $timezone_key = '') {
    $date = trim($date);
    $time = trim($time);

    if ($date === '') {
        return null;
    }

    // Reject impossible calendar dates instead of letting PHP roll them over
    // (2026-02-31 must not silently become 2026-03-03).
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $date_parts)) {
        if (!checkdate((int) $date_parts[2], (int) $date_parts[3], (int) $date_parts[1])) {
            return null;
        }
    } else {
        return null;
    }

    try {
        $timezone = $timezone_key !== '' ? new DateTimeZone($timezone_key) : wp_timezone();
    } catch (Exception $exception) {
        $timezone = wp_timezone();
    }

    $datetime_string = $date . ' ' . ($time !== '' ? $time : '00:00');

    try {
        $datetime = new DateTimeImmutable($datetime_string, $timezone);
    } catch (Exception $exception) {
        return null;
    }

    return $datetime->getTimestamp();
}

/**
 * Build a timestamp in EventON's storage coordinate space: the wall-clock
 * date/time interpreted as UTC ("wall-as-UTC"), the convention used by
 * evcal_srow/evcal_erow and repeat_intervals.
 *
 * @return int|null
 */
function eventon_apify_build_wall_utc_timestamp($date, $time = '') {
    return eventon_apify_build_timestamp($date, $time, 'UTC');
}

/**
 * Convert an absolute instant into its wall-clock parts in a target timezone.
 *
 * Shared by every path that has to turn an offset-bearing input (an ISO string
 * with Z or +05:00, a parsed filter datetime) into the wall clock EventON
 * stores. Returns null when the value cannot be parsed.
 *
 * @param mixed  $value        Datetime input parseable by DateTimeImmutable.
 * @param string $timezone_key Target timezone identifier or offset.
 * @return array<string, string>|null Keys: date (Y-m-d), time (H:i).
 */
function eventon_apify_convert_instant_to_wall_parts($value, $timezone_key) {
    if (!is_scalar($value)) {
        return null;
    }

    try {
        $timezone = trim((string) $timezone_key) !== '' ? new DateTimeZone((string) $timezone_key) : wp_timezone();
        $converted = (new DateTimeImmutable((string) $value))->setTimezone($timezone);
    } catch (Exception $exception) {
        return null;
    }

    return array(
        'date' => $converted->format('Y-m-d'),
        'time' => $converted->format('H:i'),
    );
}

/**
 * Whether a post meta key has no stored value yet, i.e. this event has never
 * had the field written (which the write paths treat as "create").
 */
function eventon_apify_meta_is_unset($post_id, $meta_key) {
    return (string) get_post_meta($post_id, $meta_key, true) === '';
}

/**
 * Format a wall-as-UTC timestamp (EventON's evcal_srow/repeat_intervals
 * space) as a real datetime in the given timezone: the stored wall clock is
 * read back in UTC, then re-interpreted in the event timezone so ISO output
 * carries the correct offset.
 */
function eventon_apify_format_wall_timestamp($timestamp, $timezone_key, $format) {
    $wall = gmdate('Y-m-d H:i:s', (int) $timestamp);

    try {
        $timezone = $timezone_key !== '' ? new DateTimeZone((string) $timezone_key) : wp_timezone();
    } catch (Exception $exception) {
        $timezone = new DateTimeZone('UTC');
    }

    try {
        $datetime = new DateTimeImmutable($wall, $timezone);
    } catch (Exception $exception) {
        return '';
    }

    return $datetime->format($format);
}
