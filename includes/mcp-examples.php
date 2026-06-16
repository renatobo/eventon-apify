<?php

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
