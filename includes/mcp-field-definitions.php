<?php

if (!defined('ABSPATH')) {
    exit;
}

function eventon_apify_get_contract_field_definitions() {
    return array(
        'title' => array(
            'type' => 'string',
            'group' => 'core',
            'required_on_create' => true,
            'description' => 'WordPress post title for the EventON event.',
            'aliases' => array('post_title'),
            'transport' => array(
                'custom_namespace' => 'title',
                'wp_v2' => 'title',
            ),
        ),
        'slug' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'Desired URL slug, sanitized and stored as the WordPress post_name. Omit to derive it from the title; WordPress may append a suffix to keep it unique.',
            'aliases' => array('post_name'),
            'transport' => array(
                'custom_namespace' => 'slug',
                'wp_v2' => 'slug',
            ),
        ),
        'description' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'Main event description or HTML body content.',
            'aliases' => array('content'),
            'transport' => array(
                'custom_namespace' => 'description',
                'wp_v2' => 'content',
            ),
        ),
        'excerpt' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'WordPress post excerpt. When absent, agents should generate a short plain-text summary of the event.',
            'guidance' => 'Autogenerate when missing. Keep it concise and plain text. Summarize the event title, date/time, and location in one or two short sentences.',
            'aliases' => array('post_excerpt'),
            'transport' => array(
                'custom_namespace' => 'excerpt',
                'wp_v2' => 'excerpt',
            ),
        ),
        'featured_media' => array(
            'type' => 'integer',
            'group' => 'core',
            'description' => 'WordPress attachment ID to set as the featured image. Send 0 to clear the featured image.',
            'aliases' => array('featured_image_id'),
            'transport' => array(
                'custom_namespace' => 'featured_media',
                'wp_v2' => 'featured_media',
            ),
        ),
        'status' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'WordPress post status for the EventON event.',
            'allowed_values' => array('publish', 'draft', 'private', 'pending', 'future'),
            'transport' => array(
                'custom_namespace' => 'status',
                'wp_v2' => 'status',
            ),
        ),
        'event_type' => array(
            'type' => 'array',
            'item_type' => 'string',
            'group' => 'taxonomy',
            'description' => 'EventON event_type terms as names or slugs.',
            'also_accepts' => array('comma_separated_string'),
            'aliases' => array('event_types'),
            'transport' => array(
                'custom_namespace' => 'event_type',
                'wp_v2' => 'event_type',
            ),
        ),
        'tags' => array(
            'type' => 'array',
            'item_type' => 'string',
            'group' => 'taxonomy',
            'description' => 'Standard WordPress post_tag terms attached to the EventON event.',
            'guidance' => 'Populate recommended tags on create when tags are missing. If the event is cloned and no source tags were carried over, generate a short set of relevant tags from the title, venue/location, organizer/brand, format, and notable themes. Prefer 2 to 6 concise lowercase tags.',
            'also_accepts' => array('comma_separated_string'),
            'aliases' => array('post_tag'),
            'transport' => array(
                'custom_namespace' => 'tags',
                'wp_v2' => 'tags',
            ),
        ),
        'start_at' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'timing',
            'description' => 'ISO 8601 alias that can be split into start_date and start_time automatically.',
            'transport' => array(
                'custom_namespace' => 'start_at',
                'wp_v2' => 'start_at',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'start_date' => array(
            'type' => 'string',
            'format' => 'date',
            'group' => 'timing',
            'required_on_create' => true,
            'description' => 'Event start date in YYYY-MM-DD.',
            'transport' => array(
                'custom_namespace' => 'start_date',
                'wp_v2' => 'start_date',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'start_time' => array(
            'type' => 'string',
            'format' => 'time',
            'group' => 'timing',
            'description' => 'Event start time in HH:MM 24-hour format.',
            'transport' => array(
                'custom_namespace' => 'start_time',
                'wp_v2' => 'start_time',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'end_at' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'timing',
            'description' => 'ISO 8601 alias that can be split into end_date and end_time automatically.',
            'transport' => array(
                'custom_namespace' => 'end_at',
                'wp_v2' => 'end_at',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'end_date' => array(
            'type' => 'string',
            'format' => 'date',
            'group' => 'timing',
            'description' => 'Event end date in YYYY-MM-DD.',
            'transport' => array(
                'custom_namespace' => 'end_date',
                'wp_v2' => 'end_date',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'end_time' => array(
            'type' => 'string',
            'format' => 'time',
            'group' => 'timing',
            'description' => 'Event end time in HH:MM 24-hour format.',
            'transport' => array(
                'custom_namespace' => 'end_time',
                'wp_v2' => 'end_time',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'timezone' => array(
            'type' => 'object',
            'group' => 'timing',
            'description' => 'Timezone payload used by EventON.',
            'shape' => array(
                'key' => array(
                    'type' => 'string',
                    'description' => 'Valid PHP timezone identifier such as America/Los_Angeles.',
                ),
                'text' => array(
                    'type' => 'string',
                    'description' => 'Optional human-readable label such as PT.',
                ),
            ),
            'aliases' => array('timezone_key', 'timezone_text'),
            'transport' => array(
                'custom_namespace' => 'timezone',
                'wp_v2' => 'timezone',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_subtitle' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'EventON subtitle stored in evcal_subtitle.',
            'aliases' => array('subtitle'),
            'transport' => array(
                'custom_namespace' => 'event_subtitle',
                'wp_v2' => 'event_subtitle',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_excerpt' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'EventON excerpt stored separately from the WordPress excerpt. Agents should usually fill this with a shorter event summary for EventON displays.',
            'guidance' => 'Recommended when creating events. Generate a short, natural-language summary that includes the event title, location, and start date/time. Keep it shorter than the main description and avoid HTML unless needed.',
            'aliases' => array('evo_excerpt'),
            'transport' => array(
                'custom_namespace' => 'event_excerpt',
                'wp_v2' => 'event_excerpt',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_status' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'EventON event status value.',
            'allowed_values' => eventon_apify_get_allowed_event_statuses(),
            'aliases' => array('_status'),
            'transport' => array(
                'custom_namespace' => 'event_status',
                'wp_v2' => 'event_status',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'status_reason' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'Human-readable reason for the current non-scheduled EventON status.',
            'transport' => array(
                'custom_namespace' => 'status_reason',
                'wp_v2' => 'status_reason',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'attendance_mode' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'Attendance mode for the event.',
            'allowed_values' => eventon_apify_get_allowed_attendance_modes(),
            'transport' => array(
                'custom_namespace' => 'attendance_mode',
                'wp_v2' => 'attendance_mode',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'time_extend_type' => array(
            'type' => 'string',
            'group' => 'timing',
            'description' => 'EventON time extension mode.',
            'allowed_values' => array('n', 'dl', 'ml', 'yl'),
            'aliases' => array('extend_type'),
            'transport' => array(
                'custom_namespace' => 'time_extend_type',
                'wp_v2' => 'time_extend_type',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'location' => array(
            'type' => 'object',
            'group' => 'location',
            'description' => 'Nested EventON event_location term payload.',
            'shape' => eventon_apify_get_location_contract_shape(),
            'aliases' => array(
                'location_name',
                'location_address',
                'location_city',
                'location_state',
                'location_country',
                'location_zip',
                'location_lat',
                'location_lon',
                'location_link',
                'map_url',
            ),
            'transport' => array(
                'custom_namespace' => 'location',
                'wp_v2' => 'location',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'organizer' => array(
            'type' => 'string',
            'group' => 'organizers',
            'description' => 'Legacy single-organizer alias. Prefer organizers[].',
            'transport' => array(
                'custom_namespace' => 'organizer',
                'wp_v2' => 'organizer',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'organizers' => array(
            'type' => 'array',
            'item_type' => 'object',
            'group' => 'organizers',
            'description' => 'Nested EventON event_organizer term payloads.',
            'item_shape' => eventon_apify_get_organizer_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'organizers',
                'wp_v2' => 'organizers',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_color' => array(
            'type' => 'string',
            'format' => 'color',
            'group' => 'presentation',
            'description' => 'Primary EventON event color in hex format.',
            'transport' => array(
                'custom_namespace' => 'event_color',
                'wp_v2' => 'event_color',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_color_secondary' => array(
            'type' => 'string',
            'format' => 'color',
            'group' => 'presentation',
            'description' => 'Secondary EventON event color in hex format.',
            'transport' => array(
                'custom_namespace' => 'event_color_secondary',
                'wp_v2' => 'event_color_secondary',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'gradient_angle' => array(
            'type' => 'number',
            'group' => 'presentation',
            'description' => 'Optional EventON gradient angle when gradient colors are enabled.',
            'transport' => array(
                'custom_namespace' => 'gradient_angle',
                'wp_v2' => 'gradient_angle',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'learn_more_link' => array(
            'type' => 'string',
            'format' => 'url',
            'group' => 'eventon',
            'description' => 'Optional EventON learn more URL.',
            'aliases' => array('link'),
            'transport' => array(
                'custom_namespace' => 'learn_more_link',
                'wp_v2' => 'learn_more_link',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'learn_more_link_target' => array(
            'type' => 'boolean',
            'group' => 'eventon',
            'description' => 'Open the learn more link in a new tab.',
            'aliases' => array('link_target'),
            'transport' => array(
                'custom_namespace' => 'learn_more_link_target',
                'wp_v2' => 'learn_more_link_target',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'interaction' => array(
            'type' => 'object',
            'group' => 'interaction',
            'description' => 'Event click interaction settings such as event card behavior or external links.',
            'shape' => eventon_apify_get_interaction_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'interaction',
                'wp_v2' => 'interaction',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'flags' => array(
            'type' => 'object',
            'group' => 'flags',
            'description' => 'Boolean EventON switches controlling featured state, maps, and visibility.',
            'shape' => eventon_apify_get_flags_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'flags',
                'wp_v2' => 'flags',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'health' => array(
            'type' => 'object',
            'group' => 'health',
            'description' => 'Health guideline settings shown in the EventON event card.',
            'shape' => eventon_apify_get_health_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'health',
                'wp_v2' => 'health',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'virtual' => array(
            'type' => 'object',
            'group' => 'virtual',
            'description' => 'Virtual event settings such as URL, password, embed, and visible end.',
            'shape' => eventon_apify_get_virtual_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'virtual',
                'wp_v2' => 'virtual',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'repeat' => array(
            'type' => 'object',
            'group' => 'repeat',
            'description' => 'Repeat rules and custom repeat intervals.',
            'shape' => eventon_apify_get_repeat_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'repeat',
                'wp_v2' => 'repeat',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'rsvp' => array(
            'type' => 'object',
            'group' => 'rsvp',
            'description' => 'EventON RSVP addon settings.',
            'shape' => eventon_apify_get_rsvp_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'rsvp',
                'wp_v2' => 'rsvp',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'related_events' => array(
            'type' => 'object',
            'group' => 'related',
            'description' => 'Related event references and display flags.',
            'shape' => eventon_apify_get_related_events_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'related_events',
                'wp_v2' => 'related_events',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'seo' => array(
            'type' => 'object',
            'group' => 'seo',
            'description' => 'Extra EventON SEO offer fields used in schema output.',
            'shape' => eventon_apify_get_seo_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'seo',
                'wp_v2' => 'seo',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'faqs' => array(
            'type' => 'object',
            'group' => 'faqs',
            'description' => 'Event FAQ taxonomy assignments and section subtitle.',
            'shape' => eventon_apify_get_faq_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'faqs',
                'wp_v2' => 'faqs',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'start_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Saved EventON collection start timestamp.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'end_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Saved EventON collection end timestamp.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'event_start_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Resolved event start timestamp after EventON normalization.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'event_end_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Resolved event end timestamp after EventON normalization.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'link' => array(
            'type' => 'string',
            'format' => 'url',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Public permalink for the event.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'featured_image' => array(
            'type' => 'string',
            'format' => 'url',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Full-size featured image URL.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'created' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Created timestamp in ISO 8601 format.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'modified' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Last modified timestamp in ISO 8601 format.',
            'wp_v2_field_mode' => 'read_only',
        ),
    );
}

/**
 * Return the nested location shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_location_contract_shape() {
    return array(
        'term_id' => array('type' => 'integer', 'description' => 'Existing event_location term ID to reuse.'),
        'name' => array('type' => 'string', 'description' => 'Location name or title.'),
        'slug' => array('type' => 'string', 'description' => 'Optional slug for term lookup or creation.'),
        'description' => array('type' => 'string', 'description' => 'Location description.'),
        'type' => array('type' => 'string', 'description' => 'EventON location type.'),
        'address' => array('type' => 'string', 'description' => 'Street address.'),
        'city' => array('type' => 'string', 'description' => 'City.'),
        'state' => array('type' => 'string', 'description' => 'State or region.'),
        'country' => array('type' => 'string', 'description' => 'Country code or text.'),
        'zip' => array('type' => 'string', 'description' => 'Postal code.'),
        'lat' => array('type' => 'number', 'description' => 'Latitude.'),
        'lon' => array('type' => 'number', 'description' => 'Longitude.'),
        'link' => array('type' => 'string', 'format' => 'url', 'description' => 'External map or venue URL.'),
        'link_target' => array('type' => 'boolean', 'description' => 'Open the location link in a new tab.'),
        'phone' => array('type' => 'string', 'description' => 'Venue phone number.'),
        'email' => array('type' => 'string', 'description' => 'Venue email address.'),
        'use_latlng_for_directions' => array('type' => 'boolean', 'description' => 'Use coordinates when building map directions.'),
    );
}

/**
 * Return the nested organizer shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_organizer_contract_shape() {
    return array(
        'term_id' => array('type' => 'integer', 'description' => 'Existing event_organizer term ID to reuse.'),
        'name' => array('type' => 'string', 'description' => 'Organizer display name.'),
        'slug' => array('type' => 'string', 'description' => 'Optional slug for term lookup or creation.'),
        'description' => array('type' => 'string', 'description' => 'Organizer description.'),
        'contact' => array('type' => 'string', 'description' => 'Primary contact person or label.'),
        'email' => array('type' => 'string', 'description' => 'Organizer email address.'),
        'phone' => array('type' => 'string', 'description' => 'Organizer phone number.'),
        'address' => array('type' => 'string', 'description' => 'Organizer address.'),
        'link' => array('type' => 'string', 'format' => 'url', 'description' => 'Organizer external link.'),
        'link_target' => array('type' => 'boolean', 'description' => 'Open the organizer link in a new tab.'),
        'excerpt' => array('type' => 'string', 'description' => 'Organizer excerpt.'),
    );
}

/**
 * Return the nested flags shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_flags_contract_shape() {
    return array(
        'featured' => array('type' => 'boolean', 'description' => 'Feature the event in EventON.'),
        'completed' => array('type' => 'boolean', 'description' => 'Mark the event as completed.'),
        'exclude_from_calendar' => array('type' => 'boolean', 'description' => 'Exclude the event from calendar listings.'),
        'loggedin_only' => array('type' => 'boolean', 'description' => 'Restrict the event to logged-in users.'),
        'hide_end_time' => array('type' => 'boolean', 'description' => 'Hide the event end time.'),
        'span_hidden_end' => array('type' => 'boolean', 'description' => 'Allow the event to span beyond the hidden end time.'),
        'hide_location_name' => array('type' => 'boolean', 'description' => 'Hide the location name from the event card.'),
        'hide_organizer_card' => array('type' => 'boolean', 'description' => 'Hide the organizer field from the event card.'),
        'generate_gmap' => array('type' => 'boolean', 'description' => 'Generate a Google map for the location.'),
        'open_google_maps_link' => array('type' => 'boolean', 'description' => 'Link the event location to Google Maps.'),
        'location_access_loggedin_only' => array('type' => 'boolean', 'description' => 'Restrict location details to logged-in users.'),
        'location_info_over_image' => array('type' => 'boolean', 'description' => 'Display location info over the event image.'),
        'organizer_as_performer' => array('type' => 'boolean', 'description' => 'Use organizer information to also populate performer schema data for the event.'),
        'gradient_enabled' => array('type' => 'boolean', 'description' => 'Enable EventON gradient colors.'),
    );
}

/**
 * Return the nested interaction shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_interaction_contract_shape() {
    return array(
        'mode' => array(
            'type' => 'string',
            'description' => 'How EventON should react when the event is clicked.',
            'allowed_values' => eventon_apify_get_allowed_interaction_modes(),
        ),
        'url' => array('type' => 'string', 'format' => 'url', 'description' => 'External URL used for external-link or popup interaction modes.'),
        'new_window' => array('type' => 'boolean', 'description' => 'Open the interaction URL in a new window when applicable.'),
    );
}

/**
 * Return the nested virtual shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_virtual_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable virtual event mode.'),
        'type' => array('type' => 'string', 'description' => 'EventON virtual event type key.'),
        'url' => array('type' => 'string', 'format' => 'url', 'description' => 'Virtual event URL.'),
        'password' => array('type' => 'string', 'description' => 'Virtual event password.'),
        'embed' => array('type' => 'string', 'description' => 'Embed markup or shortcode.'),
        'other' => array('type' => 'string', 'description' => 'Additional virtual event content.'),
        'show' => array('type' => 'string', 'description' => 'EventON virtual visibility mode.'),
        'hide_when_live' => array('type' => 'boolean', 'description' => 'Hide the virtual link while the event is live.'),
        'disable_redirect_hiding' => array('type' => 'boolean', 'description' => 'Disable EventON redirect hiding.'),
        'moderator_id' => array('type' => 'integer', 'description' => 'Optional moderator user ID.'),
        'end_time_enabled' => array('type' => 'boolean', 'description' => 'Enable a separate virtual event end time.'),
        'end_date' => array('type' => 'string', 'format' => 'date', 'description' => 'Virtual event end date in YYYY-MM-DD.'),
        'end_time' => array('type' => 'string', 'format' => 'time', 'description' => 'Virtual event end time in HH:MM 24-hour format.'),
        'end_at' => array('type' => 'string', 'format' => 'date-time', 'description' => 'ISO 8601 alias for the virtual end datetime.'),
        'after_content' => array('type' => 'string', 'description' => 'Content shown after the virtual event ends.'),
        'after_content_when' => array('type' => 'string', 'description' => 'Condition for showing after-content text.'),
    );
}

/**
 * Return the nested repeat shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_repeat_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable repeating EventON intervals.'),
        'frequency' => array(
            'type' => 'string',
            'description' => 'Repeat frequency key.',
            'allowed_values' => eventon_apify_get_allowed_repeat_frequencies(),
        ),
        'gap' => array('type' => 'integer', 'description' => 'Gap between repeat occurrences.'),
        'count' => array('type' => 'integer', 'description' => 'Number of repeat occurrences.'),
        'series_visible' => array('type' => 'boolean', 'description' => 'Show the repeat series in EventON.'),
        'intervals' => array(
            'type' => 'array',
            'item_type' => 'object',
            'description' => 'Explicit repeat interval overrides.',
            'item_shape' => array(
                'start_at' => array('type' => 'string', 'format' => 'date-time', 'description' => 'Interval start in ISO 8601 format.'),
                'end_at' => array('type' => 'string', 'format' => 'date-time', 'description' => 'Interval end in ISO 8601 format.'),
            ),
        ),
    );
}

/**
 * Return the nested health shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_health_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable health guidelines for the event.'),
        'mask_required' => array('type' => 'boolean', 'description' => 'Face masks are required.'),
        'temperature_check' => array('type' => 'boolean', 'description' => 'Temperature is checked at the entrance.'),
        'physical_distance' => array('type' => 'boolean', 'description' => 'Physical distance is maintained.'),
        'sanitized' => array('type' => 'boolean', 'description' => 'The event area is sanitized before the event.'),
        'outdoor' => array('type' => 'boolean', 'description' => 'The event is held outside.'),
        'vaccination_required' => array('type' => 'boolean', 'description' => 'Vaccination is required.'),
        'other' => array('type' => 'string', 'description' => 'Other additional health guidelines.'),
    );
}

/**
 * Return the nested related events shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_related_events_contract_shape() {
    return array(
        'items' => array(
            'type' => 'array',
            'description' => 'Related event references.',
            'item_type' => 'object',
            'item_shape' => array(
                'event_id' => array('type' => 'integer', 'description' => 'Related EventON event post ID.'),
                'repeat_interval' => array('type' => 'integer', 'description' => 'Optional repeat interval for the related event.'),
                'title' => array('type' => 'string', 'description' => 'Resolved title of the related event.'),
            ),
        ),
        'hide_image' => array('type' => 'boolean', 'description' => 'Hide related event images in the event card.'),
        'hide_past' => array('type' => 'boolean', 'description' => 'Hide past related events from the event card.'),
    );
}

/**
 * Return the nested SEO shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_seo_contract_shape() {
    return array(
        'offer_price' => array('type' => 'string', 'description' => 'Offer price used in schema output.'),
        'offer_currency' => array('type' => 'string', 'description' => 'Offer currency code or symbol used in schema output.'),
    );
}

/**
 * Return the nested FAQ shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_faq_contract_shape() {
    return array(
        'subheader' => array('type' => 'string', 'description' => 'Optional FAQ section subtitle text.'),
        'items' => array(
            'type' => 'array',
            'description' => 'FAQ items stored in the evo_faq taxonomy.',
            'item_type' => 'object',
            'item_shape' => array(
                'term_id' => array('type' => 'integer', 'description' => 'Existing FAQ term ID to reuse.'),
                'question' => array('type' => 'string', 'description' => 'FAQ question stored as the term name.'),
                'slug' => array('type' => 'string', 'description' => 'Optional FAQ slug for lookup or creation.'),
                'answer' => array('type' => 'string', 'description' => 'FAQ answer stored as the term description.'),
            ),
        ),
    );
}

/**
 * Return the nested RSVP shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_rsvp_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable EventON RSVP for the event.'),
        'show_count' => array('type' => 'boolean', 'description' => 'Show RSVP counts.'),
        'show_whos_coming' => array('type' => 'boolean', 'description' => 'Show who is coming.'),
        'only_loggedin' => array('type' => 'boolean', 'description' => 'Restrict RSVP to logged-in users.'),
        'capacity_enabled' => array('type' => 'boolean', 'description' => 'Enable capacity limits.'),
        'capacity_count' => array('type' => 'integer', 'description' => 'Maximum RSVP capacity.'),
        'capacity_show_remaining' => array('type' => 'boolean', 'description' => 'Show remaining capacity.'),
        'show_bars' => array('type' => 'boolean', 'description' => 'Display RSVP progress bars.'),
        'max_active' => array('type' => 'boolean', 'description' => 'Enable per-user maximum RSVP count.'),
        'max_count' => array('type' => 'integer', 'description' => 'Maximum RSVP count per user.'),
        'min_capacity_active' => array('type' => 'boolean', 'description' => 'Enable minimum RSVP capacity.'),
        'min_count' => array('type' => 'integer', 'description' => 'Minimum RSVP count.'),
        'close_before_minutes' => array('type' => 'integer', 'description' => 'Minutes before start when RSVP closes.'),
        'additional_emails' => array('type' => 'string', 'description' => 'Comma-separated email recipients for RSVP notifications.'),
        'manage_repeat_capacity' => array('type' => 'boolean', 'description' => 'Manage capacity separately for repeat intervals.'),
        'repeat_capacities' => array(
            'type' => 'array',
            'item_type' => 'integer',
            'description' => 'Capacity values for repeat intervals.',
        ),
    );
}
