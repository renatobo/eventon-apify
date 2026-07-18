<?php

if (!defined('ABSPATH')) {
    exit;
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
