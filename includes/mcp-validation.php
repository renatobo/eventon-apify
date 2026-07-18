<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the executable validation rules supported by the generic contract interpreter.
 *
 * @return array<string, array<int, mixed>>
 */
function eventon_apify_get_mcp_validation_rules() {
    return array(
        'required_for_create' => array('start_date'),
        'required_for_update' => array(),
        'required_together' => array(),
        'one_of_required' => array(),
    );
}

/**
 * Return additional validation notes that are enforced by the plugin runtime but not by the generic interpreter.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_validation_notes() {
    return array(
        array(
            'id' => 'title_required_on_create',
            'level' => 'error',
            'when' => 'create',
            'fields' => array('title'),
            'message' => 'title is required when creating an EventON event.',
        ),
        array(
            'id' => 'start_date_required',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('start_date'),
            'message' => 'start_date is required for EventON events.',
        ),
        array(
            'id' => 'datetime_range',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('start_date', 'start_time', 'end_date', 'end_time'),
            'message' => 'The end date/time must be on or after the start date/time.',
        ),
        array(
            'id' => 'timezone_key_must_be_valid',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('timezone'),
            'message' => 'timezone.key must be a valid PHP timezone identifier.',
        ),
        array(
            'id' => 'event_status_enum',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('event_status'),
            'message' => 'event_status must be one of the published enum values.',
        ),
        array(
            'id' => 'attendance_mode_enum',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('attendance_mode'),
            'message' => 'attendance_mode must be one of offline, online, or mixed.',
        ),
        array(
            'id' => 'color_format',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('event_color', 'event_color_secondary'),
            'message' => 'Event colors must use 6-character hex notation with or without a leading #.',
        ),
        array(
            'id' => 'absolute_urls_only',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('location', 'organizers', 'interaction', 'virtual', 'learn_more_link'),
            'message' => 'Location, organizer, interaction, virtual, and learn more links must be valid absolute URLs when provided.',
        ),
        array(
            'id' => 'featured_media_image_attachment',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('featured_media'),
            'message' => 'featured_media must reference an existing WordPress image attachment, or 0 to clear the featured image.',
        ),
        array(
            'id' => 'numeric_coordinates',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('location'),
            'message' => 'location.lat and location.lon must be numeric when provided.',
        ),
        array(
            'id' => 'repeat_frequency_enum',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('repeat'),
            'message' => 'repeat.frequency must be one of the published enum values.',
        ),
        array(
            'id' => 'virtual_end_requires_datetime',
            'level' => 'error',
            'when' => 'virtual.end_time_enabled',
            'fields' => array('virtual'),
            'message' => 'virtual.end_date is required when virtual end time is enabled.',
        ),
    );
}
