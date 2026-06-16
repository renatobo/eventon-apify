<?php

/**
 * Return the normalized plugin-specific fields exported in the MCP contract.
 *
 * @return array<int, string>
 */
function eventon_apify_get_mcp_contract_field_names() {
    return array(
        'excerpt',
        'featured_media',
        'tags',
        'event_type',
        'start_at',
        'start_date',
        'start_time',
        'end_at',
        'end_date',
        'end_time',
        'timezone',
        'time_extend_type',
        'event_subtitle',
        'event_excerpt',
        'event_status',
        'status_reason',
        'attendance_mode',
        'location',
        'organizers',
        'event_color',
        'event_color_secondary',
        'gradient_angle',
        'learn_more_link',
        'learn_more_link_target',
        'interaction',
        'flags',
        'health',
        'virtual',
        'repeat',
        'related_events',
        'seo',
        'faqs',
        'rsvp',
    );
}

/**
 * Return the canonical wp/v2 payload key for a manifest field.
 */
function eventon_apify_get_mcp_contract_write_key($field_name) {
    $custom_keys = array(
        'time_extend_type' => 'time_extend_type',
        'learn_more_link' => 'learn_more_link',
        'learn_more_link_target' => 'learn_more_link_target',
    );

    return $custom_keys[$field_name] ?? $field_name;
}

/**
 * Return the executable alias list for a manifest field.
 *
 * @return array<int, string>
 */
function eventon_apify_get_mcp_contract_field_aliases($field_name) {
    $aliases = array(
        'featured_media' => array('featured_image_id'),
        'tags' => array('post_tag'),
        'event_type' => array('event_types'),
        'event_subtitle' => array('subtitle'),
        'event_excerpt' => array('evo_excerpt'),
        'time_extend_type' => array('extend_type'),
    );

    return $aliases[$field_name] ?? array();
}

/**
 * Return an optional executable coercion rule for a manifest field.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_contract_field_coerce($field_name) {
    $coercions = array(
        'timezone' => array(
            'type' => 'string_to_object',
            'key' => 'key',
        ),
        'location' => array(
            'type' => 'string_to_object',
            'key' => 'name',
        ),
        'organizers' => array(
            'type' => 'array_string_to_object_array',
            'key' => 'name',
        ),
    );

    return $coercions[$field_name] ?? array();
}

/**
 * Return the item name used in array field definitions.
 */
function eventon_apify_get_mcp_contract_array_item_name($field_name) {
    $names = array(
        'event_type' => 'event_type',
        'organizers' => 'organizer',
        'intervals' => 'repeat_interval',
        'repeat_capacities' => 'repeat_capacity',
    );

    if (isset($names[$field_name])) {
        return $names[$field_name];
    }

    if (substr($field_name, -1) === 's') {
        return substr($field_name, 0, -1);
    }

    return $field_name . '_item';
}
