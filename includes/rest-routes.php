<?php

if (!defined('ABSPATH')) {
    exit;
}

function eventon_apify_register_routes() {
    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/mcp-schema',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_mcp_schema',
                'permission_callback' => 'eventon_apify_admin_only',
            ),
        )
    );

    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/mcp-schema/(?P<content_type>[a-z0-9_-]+)',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_mcp_schema',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'content_type' => array(
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => 'eventon_apify_validate_content_type_slug',
                    ),
                ),
            ),
        )
    );

    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/events',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_events',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'per_page' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 100,
                        'default' => 20,
                        'sanitize_callback' => 'eventon_apify_sanitize_per_page',
                    ),
                    'page' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'default' => 1,
                        'sanitize_callback' => 'eventon_apify_sanitize_page',
                    ),
                    'search' => array(
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'slug' => array(
                        'type' => array('string', 'array'),
                        'default' => '',
                        'sanitize_callback' => 'eventon_apify_sanitize_slug_filter',
                        'description' => 'Limit results to events matching one or more exact slugs (comma-separated string or array).',
                    ),
                    'status' => array(
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'starts_on_or_after' => array(
                        'type' => 'string',
                        'format' => 'date-time',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_event_date_filter',
                    ),
                    'starts_before' => array(
                        'type' => 'string',
                        'format' => 'date-time',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_event_date_filter',
                    ),
                    'upcoming' => array(
                        'type' => 'boolean',
                        'sanitize_callback' => 'eventon_apify_sanitize_rest_boolean',
                        'validate_callback' => 'eventon_apify_validate_rest_boolean',
                    ),
                    'order' => array(
                        'type' => 'string',
                        'default' => 'asc',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_events_order',
                    ),
                    'orderby' => array(
                        'type' => 'string',
                        'enum' => array('start_at', 'created', 'modified', 'title'),
                        'default' => 'start_at',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_events_orderby',
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => 'eventon_apify_create_event',
                'permission_callback' => 'eventon_apify_admin_only',
                // Required create fields are checked after compatibility payloads
                // have been normalized into their canonical form.
                'args' => eventon_apify_get_event_write_args(false),
            ),
        )
    );

    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/events/(?P<id>\d+)',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_event',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'id' => array(
                        'type' => 'integer',
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => 'eventon_apify_update_event',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array_merge(
                    eventon_apify_get_event_write_args(false),
                    array(
                        'id' => array(
                            'type' => 'integer',
                            'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                        ),
                    )
                ),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => 'eventon_apify_delete_event',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'id' => array(
                        'type' => 'integer',
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                ),
            ),
        )
    );

    if (!eventon_apify_is_eventon_rsvp_available()) {
        return;
    }

    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/events/(?P<id>\d+)/rsvps/summary',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_event_rsvp_summary',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'id' => array(
                        'type' => 'integer',
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                ),
            ),
        )
    );

    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/events/(?P<id>\d+)/rsvps',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_event_rsvps',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'id' => array(
                        'type' => 'integer',
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                    'per_page' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 100,
                        'default' => 50,
                        'sanitize_callback' => 'eventon_apify_sanitize_per_page',
                    ),
                    'page' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'default' => 1,
                        'sanitize_callback' => 'eventon_apify_sanitize_page',
                    ),
                    'search' => array(
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'rsvp' => array(
                        'type' => 'string',
                        'enum' => array('all', 'yes', 'no', 'maybe'),
                        'default' => 'all',
                        'sanitize_callback' => 'eventon_apify_sanitize_rsvp_filter',
                    ),
                    'status' => array(
                        'type' => 'string',
                        'default' => 'all',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'updated_after' => array(
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_iso8601_datetime',
                    ),
                    'updated_after_id' => array(
                        'type' => 'integer',
                        'minimum' => 0,
                        'default' => 0,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        )
    );
}
