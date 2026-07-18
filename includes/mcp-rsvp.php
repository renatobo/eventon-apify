<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build the event_rsvps contract published in the MCP manifest.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_rsvp_content_type_manifest() {
    $availability = eventon_apify_build_mcp_availability(
        eventon_apify_get_mcp_availability_flags(),
        // Per-capability RSVP flags reveal which attendee/count routes are open,
        // so they are restricted to authenticated administrators.
        array(
            'rsvp_attendees_enabled' => eventon_apify_is_api_capability_enabled('rsvp_attendees'),
            'rsvp_counts_enabled' => eventon_apify_is_api_capability_enabled('rsvp_counts'),
        )
    );

    return array(
        'slug' => 'event_rsvps',
        'label' => 'EventON RSVP Attendee',
        'description' => 'Read-only RSVP attendee records exposed through EventON APIfy nested event routes when the EventON RSVP addon is active.',
        'preferred_endpoint' => 'eventonapify/v1/events/{event_id}/rsvps',
        'preferred_write_mode' => 'read_only',
        'supported_operations' => array('list'),
        'fields' => eventon_apify_get_mcp_rsvp_contract_fields(),
        'examples' => eventon_apify_get_mcp_rsvp_contract_examples(),
        'availability' => $availability,
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
