<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the default enabled API capabilities.
 *
 * @return array<string, bool>
 */
function eventon_apify_get_default_api_capabilities() {
    $defaults = array();

    foreach (eventon_apify_get_api_capability_definitions() as $capability => $definition) {
        $defaults[$capability] = !empty($definition['default']);
    }

    return $defaults;
}

/**
 * Return API capability metadata used by settings and gating.
 *
 * @return array<string, array<string, string>>
 */
function eventon_apify_get_api_capability_definitions() {
    return array(
        'list' => array(
            'label' => __('List events', 'eventon-apify'),
            'description' => __('Allow GET requests to the events collection endpoint.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events',
            'default' => true,
        ),
        'read' => array(
            'label' => __('Read single event', 'eventon-apify'),
            'description' => __('Allow GET requests for an individual event.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events/<id>',
            'default' => true,
        ),
        'create' => array(
            'label' => __('Create events', 'eventon-apify'),
            'description' => __('Allow POST requests that create ajde_events records.', 'eventon-apify'),
            'methods' => 'POST',
            'route' => '/events',
            'default' => true,
        ),
        'update' => array(
            'label' => __('Update events', 'eventon-apify'),
            'description' => __('Allow PUT and PATCH requests for existing events.', 'eventon-apify'),
            'methods' => 'PUT, PATCH',
            'route' => '/events/<id>',
            'default' => true,
        ),
        'delete' => array(
            'label' => __('Delete events', 'eventon-apify'),
            'description' => __('Allow DELETE requests that trash events.', 'eventon-apify'),
            'methods' => 'DELETE',
            'route' => '/events/<id>',
            'default' => true,
        ),
        'rsvp_counts' => array(
            'label' => __('Read RSVP summary', 'eventon-apify'),
            'description' => __('Allow GET requests for the yes-only RSVP summary of an event.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events/<id>/rsvps/summary',
            'default' => false,
        ),
        'rsvp_attendees' => array(
            'label' => __('List RSVP attendees', 'eventon-apify'),
            'description' => __('Allow GET requests for RSVP attendee records and contact details.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events/<id>/rsvps',
            'default' => false,
        ),
    );
}

/**
 * Sanitize the saved API capability map.
 *
 * @param mixed $value Submitted option value.
 * @return array<string, bool>
 */
function eventon_apify_sanitize_capabilities($value) {
    $defaults = eventon_apify_get_default_api_capabilities();
    $sanitized = array();

    foreach ($defaults as $capability => $enabled_by_default) {
        $sanitized[$capability] = is_array($value) && !empty($value[$capability]);
    }

    return $sanitized;
}

/**
 * Return the saved API capability map with defaults applied.
 *
 * @return array<string, bool>
 */
function eventon_apify_get_api_capabilities() {
    $saved = get_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, array());

    if (!is_array($saved)) {
        $saved = array();
    }

    return array_merge(eventon_apify_get_default_api_capabilities(), $saved);
}

/**
 * Determine whether a specific API capability is enabled.
 */
function eventon_apify_is_api_capability_enabled($capability) {
    $capabilities = eventon_apify_get_api_capabilities();

    return !empty($capabilities[$capability]);
}
