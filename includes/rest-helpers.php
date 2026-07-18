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
 * Map EventON's stored interaction codes to normalized API values.
 */
function eventon_apify_map_interaction_code_to_mode($value) {
    $value = trim((string) $value);

    $map = array(
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

    return $map[$value] ?? 'slide_down_eventcard';
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
 * Validate timezone identifiers.
 */
function eventon_apify_is_valid_timezone($timezone_key) {
    return in_array((string) $timezone_key, timezone_identifiers_list(), true);
}

/**
 * Split HH:MM time strings into EventON-compatible pieces.
 *
 * @param string $time Time string.
 * @return array<string, string>|null
 */
function eventon_apify_split_time_string($time) {
    $time = trim($time);

    if ($time === '') {
        return null;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
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
