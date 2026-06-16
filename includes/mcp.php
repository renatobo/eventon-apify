<?php
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
 * Export raw field definitions into the executable MCP contract shape.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_contract_fields() {
    $fields = array();
    $definitions = eventon_apify_get_contract_field_definitions();

    foreach (eventon_apify_get_mcp_contract_field_names() as $field_name) {
        if (!isset($definitions[$field_name])) {
            continue;
        }

        $fields[] = eventon_apify_build_mcp_contract_field_definition($field_name, $definitions[$field_name]);
    }

    return $fields;
}

/**
 * Convert one raw internal field definition into the manifest contract format.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_contract_field_definition($field_name, array $definition) {
    $field = array(
        'name' => $field_name,
        'label' => eventon_apify_format_contract_field_label($field_name),
        'description' => $definition['description'] ?? '',
        'type' => eventon_apify_map_contract_field_type($definition),
        'write_key' => eventon_apify_get_mcp_contract_write_key($field_name),
        'operations' => array('create', 'update'),
    );

    if (!empty($definition['required_on_create'])) {
        $field['required_on'] = array('create');
    }

    if (!empty($definition['allowed_values']) && is_array($definition['allowed_values'])) {
        $field['enum'] = array_values($definition['allowed_values']);
    }

    if (!empty($definition['guidance']) && is_string($definition['guidance'])) {
        $field['guidance'] = $definition['guidance'];
    }

    $aliases = eventon_apify_get_mcp_contract_field_aliases($field_name);
    if (!empty($aliases)) {
        $field['aliases'] = $aliases;
    }

    $coerce = eventon_apify_get_mcp_contract_field_coerce($field_name);
    if (!empty($coerce)) {
        $field['coerce'] = $coerce;
    }

    if (!empty($definition['shape']) && is_array($definition['shape'])) {
        $field['shape'] = eventon_apify_export_contract_shape_definitions($definition['shape']);
    }

    if ($definition['type'] === 'array') {
        $items = eventon_apify_build_mcp_contract_items_definition($field_name, $definition);
        if (!empty($items)) {
            $field['items'] = $items;
        }
    }

    return $field;
}

/**
 * Convert nested raw field definitions into contract field objects.
 *
 * @param array<string, array<string, mixed>> $shape Raw nested field definitions.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_export_contract_shape_definitions(array $shape) {
    $fields = array();

    foreach ($shape as $field_name => $definition) {
        $item = array(
            'name' => $field_name,
            'label' => eventon_apify_format_contract_field_label($field_name),
            'description' => $definition['description'] ?? '',
            'type' => eventon_apify_map_contract_field_type($definition),
        );

        if (!empty($definition['allowed_values']) && is_array($definition['allowed_values'])) {
            $item['enum'] = array_values($definition['allowed_values']);
        }

        if (!empty($definition['guidance']) && is_string($definition['guidance'])) {
            $item['guidance'] = $definition['guidance'];
        }

        if (!empty($definition['shape']) && is_array($definition['shape'])) {
            $item['shape'] = eventon_apify_export_contract_shape_definitions($definition['shape']);
        }

        if (($definition['type'] ?? '') === 'array') {
            $nested_items = eventon_apify_build_mcp_contract_items_definition($field_name, $definition);
            if (!empty($nested_items)) {
                $item['items'] = $nested_items;
            }
        }

        $fields[] = $item;
    }

    return $fields;
}

/**
 * Build the manifest `items` definition for array fields.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_contract_items_definition($field_name, array $definition) {
    $item_type = $definition['item_type'] ?? '';

    if (!is_string($item_type) || $item_type === '') {
        return array();
    }

    $items = array(
        'name' => eventon_apify_get_mcp_contract_array_item_name($field_name),
        'type' => $item_type,
    );

    if (!empty($definition['item_shape']) && is_array($definition['item_shape'])) {
        $items['shape'] = eventon_apify_export_contract_shape_definitions($definition['item_shape']);
    }

    return $items;
}

/**
 * Map the internal field type metadata to the external contract type vocabulary.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 */
function eventon_apify_map_contract_field_type(array $definition) {
    $format = $definition['format'] ?? '';
    if ($format === 'date') {
        return 'date';
    }

    if ($format === 'time') {
        return 'time';
    }

    return $definition['type'] ?? 'string';
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

/**
 * Build a readable field label from the canonical field key.
 */
function eventon_apify_format_contract_field_label($field_name) {
    return ucwords(str_replace('_', ' ', $field_name));
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

/**
 * Return normalized contract examples for create/update flows.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_mcp_contract_examples() {
    return array(
        'list' => array(
            'query' => array(
                'after' => '2025-12-31',
                'before' => '2027-01-01',
                'per_page' => 100,
                'order' => 'asc',
                'orderby' => 'start_at',
            ),
        ),
        'create' => array(
            'title' => 'Ride to Big Bear',
            'status' => 'publish',
            'content' => 'Optional HTML content',
            'excerpt' => 'Ride to Big Bear is a one-day ride in Big Bear Lake on April 1, 2026 from 9:00 to 17:00 PT.',
            'fields' => eventon_apify_get_mcp_example_create_payload(),
        ),
        'update' => array(
            'content' => 'Updated event description',
            'fields' => eventon_apify_get_mcp_example_update_payload(),
        ),
    );
}

/**
 * Return EventON field groups published in the MCP manifest.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_field_groups() {
    return array(
        array(
            'key' => 'core',
            'label' => 'Core content',
            'description' => 'Standard WordPress post fields used for EventON events.',
            'fields' => array('title', 'description', 'excerpt', 'featured_media', 'status'),
        ),
        array(
            'key' => 'taxonomy',
            'label' => 'Taxonomy',
            'description' => 'EventON taxonomy inputs.',
            'fields' => array('tags', 'event_type'),
        ),
        array(
            'key' => 'timing',
            'label' => 'Timing',
            'description' => 'Start, end, and timezone data.',
            'fields' => array('start_at', 'start_date', 'start_time', 'end_at', 'end_date', 'end_time', 'timezone', 'start_timestamp', 'end_timestamp', 'event_start_timestamp', 'event_end_timestamp'),
        ),
        array(
            'key' => 'eventon',
            'label' => 'EventON state',
            'description' => 'EventON-specific status and subtitle fields.',
            'fields' => array('event_subtitle', 'event_excerpt', 'event_status', 'status_reason', 'attendance_mode'),
        ),
        array(
            'key' => 'location',
            'label' => 'Location',
            'description' => 'Nested venue and map details.',
            'fields' => array('location'),
        ),
        array(
            'key' => 'organizers',
            'label' => 'Organizers',
            'description' => 'Organizer aliases and nested organizer payloads.',
            'fields' => array('organizer', 'organizers'),
        ),
        array(
            'key' => 'presentation',
            'label' => 'Presentation',
            'description' => 'Color styling fields.',
            'fields' => array('event_color', 'event_color_secondary'),
        ),
        array(
            'key' => 'flags',
            'label' => 'Flags',
            'description' => 'Boolean EventON display and access toggles.',
            'fields' => array('flags'),
        ),
        array(
            'key' => 'interaction',
            'label' => 'Interaction',
            'description' => 'Event click interaction settings.',
            'fields' => array('interaction'),
        ),
        array(
            'key' => 'health',
            'label' => 'Health',
            'description' => 'Health guideline settings.',
            'fields' => array('health'),
        ),
        array(
            'key' => 'virtual',
            'label' => 'Virtual event',
            'description' => 'Virtual event settings and end-of-event behavior.',
            'fields' => array('virtual'),
        ),
        array(
            'key' => 'repeat',
            'label' => 'Repeat',
            'description' => 'Repeat interval rules.',
            'fields' => array('repeat'),
        ),
        array(
            'key' => 'related',
            'label' => 'Related events',
            'description' => 'Related event references and display flags.',
            'fields' => array('related_events'),
        ),
        array(
            'key' => 'seo',
            'label' => 'SEO',
            'description' => 'Extra schema offer fields.',
            'fields' => array('seo'),
        ),
        array(
            'key' => 'faqs',
            'label' => 'FAQs',
            'description' => 'FAQ taxonomy assignments and subtitle.',
            'fields' => array('faqs'),
        ),
        array(
            'key' => 'rsvp',
            'label' => 'RSVP',
            'description' => 'RSVP addon settings.',
            'fields' => array('rsvp'),
        ),
        array(
            'key' => 'read_only',
            'label' => 'Read-only output',
            'description' => 'Compatibility fields returned by the API but not writable.',
            'fields' => array('link', 'featured_image', 'created', 'modified'),
        ),
    );
}

/**
 * Return the example EventON payload used in documentation and MCP discovery.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_example_create_payload() {
    return array(
        'featured_media' => 456,
        'tags' => array('bike night', 'ducati'),
        'event_type' => array('Rides', 'Featured'),
        'start_date' => '2026-04-01',
        'start_time' => '09:00',
        'end_date' => '2026-04-01',
        'end_time' => '17:00',
        'timezone' => array(
            'key' => 'America/Los_Angeles',
            'text' => 'PT',
        ),
        'time_extend_type' => 'n',
        'event_status' => 'scheduled',
        'attendance_mode' => 'offline',
        'event_subtitle' => 'High-altitude ride day',
        'event_excerpt' => 'Ride to Big Bear is a one-day ride in Big Bear Lake on April 1, 2026 from 9:00 to 17:00 PT.',
        'location' => array(
            'name' => 'Big Bear Lake',
            'address' => '123 Main St',
            'city' => 'Big Bear Lake',
            'state' => 'CA',
            'country' => 'US',
            'link' => 'https://maps.google.com/?q=Big+Bear+Lake',
            'link_target' => true,
        ),
        'organizers' => array(
            array(
                'name' => 'EventON APIfy',
                'email' => 'events@example.com',
            ),
        ),
        'event_color' => '#ff0000',
        'learn_more_link' => 'https://example.com/rides/big-bear',
        'learn_more_link_target' => true,
        'flags' => array(
            'featured' => true,
            'generate_gmap' => true,
            'open_google_maps_link' => true,
            'organizer_as_performer' => true,
            'gradient_enabled' => true,
        ),
        'interaction' => array(
            'mode' => 'external_link',
            'url' => 'https://example.com/rides/big-bear/details',
            'new_window' => true,
        ),
        'health' => array(
            'enabled' => true,
            'outdoor' => true,
        ),
        'gradient_angle' => 90,
        'virtual' => array(
            'enabled' => false,
        ),
        'seo' => array(
            'offer_price' => '0.00',
            'offer_currency' => 'USD',
        ),
        'rsvp' => array(
            'enabled' => true,
            'capacity_enabled' => true,
            'capacity_count' => 75,
        ),
        'faqs' => array(
            'subheader' => 'Know before you go',
            'items' => array(
                array(
                    'question' => 'Is parking available?',
                    'answer' => 'Yes, free parking is available on site.',
                ),
            ),
        ),
    );
}

/**
 * Return a partial update example for the MCP manifest.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_example_update_payload() {
    return array(
        'featured_media' => 0,
        'tags' => array('track day'),
        'event_status' => 'rescheduled',
        'status_reason' => 'Weather moved the ride to next week.',
        'start_date' => '2026-04-08',
        'start_time' => '10:00',
        'end_date' => '2026-04-08',
        'end_time' => '18:00',
        'timezone' => array(
            'key' => 'America/Los_Angeles',
            'text' => 'PT',
        ),
        'location' => array(
            'name' => 'Lake Arrowhead',
            'address' => '456 Summit Rd',
            'city' => 'Lake Arrowhead',
            'state' => 'CA',
            'country' => 'US',
        ),
        'flags' => array(
            'featured' => false,
            'generate_gmap' => true,
            'hide_organizer_card' => true,
        ),
        'interaction' => array(
            'mode' => 'slide_down_eventcard',
        ),
        'related_events' => array(
            'hide_past' => true,
        ),
        'virtual' => array(
            'enabled' => true,
            'url' => 'https://example.com/live/ride',
        ),
    );
}

/**
 * Return the current runtime availability flags relevant to MCP clients.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_availability_state() {
    $eventon_available = eventon_apify_is_eventon_available();
    $wp_v2_enabled = eventon_apify_is_wp_v2_compatibility_enabled();

    return array(
        'eventon_available' => $eventon_available,
        'eventon_rsvp_available' => eventon_apify_is_eventon_rsvp_available(),
        'custom_event_api_enabled' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false),
        'custom_event_api_capabilities' => eventon_apify_get_api_capabilities(),
        'wp_v2_compatibility_enabled' => $wp_v2_enabled,
        'preferred_mcp_ready' => $eventon_available && $wp_v2_enabled,
    );
}

/**
 * Return authoring guidance for MCP agents that prepare EventON payloads.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_agent_guidance() {
    return array(
        'autofill_recommended_fields' => array(
            'excerpt',
            'event_excerpt',
            'tags',
            'location',
            'organizers',
            'flags',
            'interaction',
            'virtual',
            'related_events',
            'seo',
            'faqs',
        ),
        'field_strategies' => array(
            array(
                'field' => 'event_excerpt',
                'strategy' => 'Generate a short summary from the event title, location, and start date/time when the field is empty.',
            ),
            array(
                'field' => 'excerpt',
                'strategy' => 'Use a concise plain-text summary. It may mirror event_excerpt when no distinct editorial excerpt is provided.',
            ),
            array(
                'field' => 'tags',
                'strategy' => 'When creating an event and tags are empty, propose or generate recommended tags from the title, location, organizer, event format, and obvious topics. If cloning an existing event, preserve source tags when available; only generate replacements when source tags are absent.',
            ),
            array(
                'field' => 'location',
                'strategy' => 'Fill the most specific known venue details available, prioritizing name, address, city, state, country, and map link.',
            ),
            array(
                'field' => 'organizers',
                'strategy' => 'Include organizer records whenever the source material names a host, organizer, venue operator, or promoter.',
            ),
        ),
    );
}

/**
 * Build the EventON MCP manifest envelope.
 *
 * @param string $content_type Optional content type filter.
 * @return array<string, mixed>|WP_Error
 */
function eventon_apify_get_mcp_manifest($content_type = '') {
    $content_types = array();
    $rsvp_available = eventon_apify_is_eventon_rsvp_available();

    if ($content_type === '') {
        $content_types['ajde_events'] = eventon_apify_get_mcp_content_type_manifest();

        if ($rsvp_available) {
            $content_types['event_rsvps'] = eventon_apify_get_mcp_rsvp_content_type_manifest();
        }
    } elseif ($content_type === 'ajde_events') {
        $content_types['ajde_events'] = eventon_apify_get_mcp_content_type_manifest();
    } elseif ($content_type === 'event_rsvps') {
        if (!$rsvp_available) {
            return new WP_Error(
                'eventon_apify_unknown_content_type',
                'The event_rsvps content type is published only when the EventON RSVP addon is active.',
                array('status' => 404)
            );
        }

        $content_types['event_rsvps'] = eventon_apify_get_mcp_rsvp_content_type_manifest();
    } else {
        return new WP_Error(
            'eventon_apify_unknown_content_type',
            $rsvp_available
                ? 'Only the ajde_events and event_rsvps content types are currently published in the EventON APIfy MCP manifest.'
                : 'Only the ajde_events content type is currently published in the EventON APIfy MCP manifest.',
            array('status' => 404)
        );
    }

    return array(
        'schema_version' => '1.0.0',
        'provider' => 'eventon-apify',
        'provider_version' => EVENTON_APIFY_VERSION,
        'generated_at' => gmdate('c'),
        'site_url' => untrailingslashit(get_site_url()),
        'namespace' => EVENTON_APIFY_NAMESPACE,
        'discovery' => array(
            'manifest' => rest_url(EVENTON_APIFY_NAMESPACE . '/mcp-schema'),
            'content_type_template' => rest_url(EVENTON_APIFY_NAMESPACE . '/mcp-schema/{content_type}'),
        ),
        'agent_guidance' => eventon_apify_get_mcp_agent_guidance(),
        'availability' => eventon_apify_get_mcp_availability_state(),
        'content_types' => $content_types,
    );
}

/**
 * Build the ajde_events contract published in the MCP manifest.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_content_type_manifest() {
    return array(
        'slug' => 'ajde_events',
        'label' => 'EventON Event',
        'description' => 'EventON events stored as ajde_events posts and exposed through the EventON APIfy read API plus wp/v2-compatible writes.',
        'preferred_endpoint' => 'wp/v2/ajde_events',
        'read_endpoint' => 'eventonapify/v1/events',
        'preferred_write_mode' => 'fields',
        'supported_operations' => array('list', 'get', 'create', 'update'),
        'read_contract' => array(
            'resource_shape' => 'collection',
            'primary_date_field' => 'start_at',
            'default_orderby' => 'start_at',
            'default_order' => 'asc',
            'filters' => array(
                'after' => array(
                    'maps_to' => 'starts_on_or_after',
                    'field' => 'start_at',
                    'inclusive' => true,
                    'format' => 'date_or_datetime',
                ),
                'before' => array(
                    'maps_to' => 'starts_before',
                    'field' => 'start_at',
                    'inclusive' => false,
                    'format' => 'date_or_datetime',
                ),
                'status' => array(
                    'maps_to' => 'status',
                ),
                'slug' => array(
                    'maps_to' => 'slug',
                    'field' => 'post_name',
                    'format' => 'slug_or_slug_list',
                ),
                'search' => array(
                    'maps_to' => 'search',
                ),
                'page' => array(
                    'maps_to' => 'page',
                ),
                'per_page' => array(
                    'maps_to' => 'per_page',
                ),
                'order' => array(
                    'maps_to' => 'order',
                    'enum' => array('asc', 'desc'),
                ),
                'orderby' => array(
                    'maps_to' => 'orderby',
                    'enum' => array('start_at', 'created', 'modified', 'title'),
                ),
            ),
        ),
        'fields' => eventon_apify_get_mcp_contract_fields(),
        'validation_rules' => eventon_apify_get_mcp_validation_rules(),
        'examples' => eventon_apify_get_mcp_contract_examples(),
        'validation_notes' => eventon_apify_get_mcp_validation_notes(),
        'availability' => array(
            'eventon_available' => eventon_apify_is_eventon_available(),
            'wp_v2_compatibility_enabled' => eventon_apify_is_wp_v2_compatibility_enabled(),
        ),
    );
}

/**
 * Build the event_rsvps contract published in the MCP manifest.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_rsvp_content_type_manifest() {
    return array(
        'slug' => 'event_rsvps',
        'label' => 'EventON RSVP Attendee',
        'description' => 'Read-only RSVP attendee records exposed through EventON APIfy nested event routes when the EventON RSVP addon is active.',
        'preferred_endpoint' => 'eventonapify/v1/events/{event_id}/rsvps',
        'preferred_write_mode' => 'read_only',
        'supported_operations' => array('list'),
        'fields' => eventon_apify_get_mcp_rsvp_contract_fields(),
        'examples' => eventon_apify_get_mcp_rsvp_contract_examples(),
        'availability' => array(
            'eventon_available' => eventon_apify_is_eventon_available(),
            'eventon_rsvp_available' => eventon_apify_is_eventon_rsvp_available(),
            'custom_event_api_enabled' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false),
            'rsvp_attendees_enabled' => eventon_apify_is_api_capability_enabled('rsvp_attendees'),
            'rsvp_counts_enabled' => eventon_apify_is_api_capability_enabled('rsvp_counts'),
        ),
        'parent_context' => array(
            'content_type' => 'ajde_events',
            'id_param' => 'event_id',
        ),
        'related_endpoints' => array(
            array(
                'name' => 'summary',
                'endpoint' => 'eventonapify/v1/events/{event_id}/rsvps/summary',
                'description' => 'Yes-only RSVP attendance summary for the same event.',
            ),
        ),
    );
}

/**
 * Return the RSVP attendee fields published in the MCP manifest.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_rsvp_contract_fields() {
    return array(
        array(
            'name' => 'id',
            'label' => 'ID',
            'description' => 'RSVP post ID.',
            'type' => 'integer',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'created_at',
            'label' => 'Created At',
            'description' => 'RSVP creation timestamp in ISO 8601 UTC.',
            'type' => 'string',
            'format' => 'date-time',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'updated_at',
            'label' => 'Updated At',
            'description' => 'Canonical RSVP change timestamp in ISO 8601 UTC.',
            'type' => 'string',
            'format' => 'date-time',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'first_name',
            'label' => 'First Name',
            'description' => 'RSVP attendee first name.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'last_name',
            'label' => 'Last Name',
            'description' => 'RSVP attendee last name.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'full_name',
            'label' => 'Full Name',
            'description' => 'Combined RSVP attendee display name.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'email',
            'label' => 'Email',
            'description' => 'RSVP attendee email address.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'phone',
            'label' => 'Phone',
            'description' => 'RSVP attendee phone number.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'email_updates',
            'label' => 'Email Updates',
            'description' => 'Whether the attendee opted in to email updates.',
            'type' => 'boolean',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'rsvp',
            'label' => 'RSVP',
            'description' => 'Normalized RSVP response.',
            'type' => 'string',
            'enum' => array('yes', 'no', 'maybe'),
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'status',
            'label' => 'Status',
            'description' => 'Check-in status value from EventON RSVP.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'rsvp_type',
            'label' => 'RSVP Type',
            'description' => 'RSVP type such as normal, invitee, or waitlist.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'count',
            'label' => 'Count',
            'description' => 'Total party size for the RSVP, including the submitter.',
            'type' => 'integer',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'event_time',
            'label' => 'Event Time',
            'description' => 'Formatted event time string used in the RSVP CSV export.',
            'type' => 'string',
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'other_attendees',
            'label' => 'Other Attendees',
            'description' => 'Additional guest names captured with the RSVP.',
            'type' => 'array',
            'items' => array(
                'name' => 'other_attendee',
                'type' => 'string',
            ),
            'operations' => array('list'),
            'read_only' => true,
        ),
        array(
            'name' => 'custom_fields',
            'label' => 'Custom Fields',
            'description' => 'Additional RSVP form fields stored on the attendee record.',
            'type' => 'object',
            'operations' => array('list'),
            'read_only' => true,
        ),
    );
}

/**
 * Return RSVP MCP examples for list and summary reads.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_mcp_rsvp_contract_examples() {
    return array(
        'list' => array(
            'event_id' => 123,
            'query' => array(
                'per_page' => 50,
                'page' => 1,
                'rsvp' => 'yes',
                'updated_after' => '2026-04-08T18:00:00Z',
                'updated_after_id' => 980,
            ),
        ),
        'summary' => array(
            'event_id' => 123,
            'endpoint' => 'eventonapify/v1/events/{event_id}/rsvps/summary',
            'response_fields' => array(
                'event_id',
                'event_title',
                'yes_submissions',
                'yes_attendees_total',
                'yes_additional_attendees',
            ),
        ),
    );
}

/**
 * Serve the MCP manifest endpoints.
 *
 * @return WP_REST_Response|WP_Error
 */
function eventon_apify_get_mcp_schema(WP_REST_Request $request) {
    $content_type = sanitize_key((string) $request->get_param('content_type'));
    $manifest = eventon_apify_get_mcp_manifest($content_type);

    if (is_wp_error($manifest)) {
        return $manifest;
    }

    return rest_ensure_response($manifest);
}
