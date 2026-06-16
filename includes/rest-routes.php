<?php

function eventon_apify_register_routes() {
    register_rest_route(
        EVENTON_APIFY_NAMESPACE,
        '/mcp-schema',
        array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => 'eventon_apify_get_mcp_schema',
                'permission_callback' => '__return_true',
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
                'permission_callback' => '__return_true',
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
                        'default' => 20,
                        'sanitize_callback' => 'eventon_apify_sanitize_per_page',
                    ),
                    'page' => array(
                        'default' => 1,
                        'sanitize_callback' => 'eventon_apify_sanitize_page',
                    ),
                    'search' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'slug' => array(
                        'default' => '',
                        'sanitize_callback' => 'eventon_apify_sanitize_slug_filter',
                        'description' => 'Limit results to events matching one or more exact slugs (comma-separated string or array).',
                    ),
                    'status' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'starts_on_or_after' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_event_date_filter',
                    ),
                    'starts_before' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_event_date_filter',
                    ),
                    'upcoming' => array(
                        'sanitize_callback' => 'eventon_apify_sanitize_rest_boolean',
                        'validate_callback' => 'eventon_apify_validate_rest_boolean',
                    ),
                    'order' => array(
                        'default' => 'asc',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => 'eventon_apify_validate_events_order',
                    ),
                    'orderby' => array(
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
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => 'eventon_apify_update_event',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'id' => array(
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => 'eventon_apify_delete_event',
                'permission_callback' => 'eventon_apify_admin_only',
                'args' => array(
                    'id' => array(
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
                        'validate_callback' => 'eventon_apify_validate_numeric_identifier',
                    ),
                    'per_page' => array(
                        'default' => 50,
                        'sanitize_callback' => 'eventon_apify_sanitize_per_page',
                    ),
                    'page' => array(
                        'default' => 1,
                        'sanitize_callback' => 'eventon_apify_sanitize_page',
                    ),
                    'search' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'rsvp' => array(
                        'default' => 'all',
                        'sanitize_callback' => 'eventon_apify_sanitize_rsvp_filter',
                    ),
                    'status' => array(
                        'default' => 'all',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'updated_after' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'updated_after_id' => array(
                        'default' => 0,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        )
    );
}
