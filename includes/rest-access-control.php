<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Restrict API access to administrators.
 */
function eventon_apify_admin_only() {
    return current_user_can('manage_options');
}

/**
 * Verify EventON availability and plugin enablement.
 *
 * @return true|WP_Error
 */
function eventon_apify_assert_api_is_ready() {
    return eventon_apify_assert_api_capability_is_ready('');
}

/**
 * Verify EventON availability, plugin enablement, and route capability.
 *
 * @return true|WP_Error
 */
function eventon_apify_assert_api_capability_is_ready($capability = '') {
    if (!eventon_apify_is_eventon_available()) {
        return new WP_Error(
            'eventon_apify_eventon_missing',
            __('EventON is not active or the ajde_events post type is unavailable.', 'eventon-apify'),
            array('status' => 500)
        );
    }

    if (!get_option(EVENTON_APIFY_OPTION_ENABLE_API, false)) {
        return new WP_Error(
            'eventon_apify_disabled',
            __('The EventON APIfy endpoint is disabled. Enable it in Settings > EventON APIfy.', 'eventon-apify'),
            array('status' => 403)
        );
    }

    if ($capability !== '' && !eventon_apify_is_api_capability_enabled($capability)) {
        $definitions = eventon_apify_get_api_capability_definitions();
        $capability_label = isset($definitions[$capability]['label']) ? $definitions[$capability]['label'] : $capability;

        return new WP_Error(
            'eventon_apify_capability_disabled',
            sprintf(
                /* translators: %s: API capability label. */
                __('%s is disabled in Settings > EventON APIfy.', 'eventon-apify'),
                $capability_label
            ),
            array('status' => 403)
        );
    }

    return true;
}

/**
 * Detect whether EventON's event post type is registered.
 */
function eventon_apify_is_eventon_available() {
    return post_type_exists('ajde_events');
}

/**
 * Detect whether the EventON RSVP addon is active and has registered its post type.
 */
function eventon_apify_is_eventon_rsvp_available() {
    return class_exists('EventON_rsvp') && post_type_exists('evo-rsvp');
}

/**
 * Verify EventON RSVP availability, plugin enablement, and route capability.
 *
 * @return true|WP_Error
 */
function eventon_apify_assert_rsvp_api_capability_is_ready($capability) {
    $ready = eventon_apify_assert_api_capability_is_ready($capability);
    if (is_wp_error($ready)) {
        return $ready;
    }

    if (!eventon_apify_is_eventon_rsvp_available()) {
        return new WP_Error(
            'eventon_apify_rsvp_missing',
            __('The EventON RSVP addon is not active or the evo-rsvp post type is unavailable.', 'eventon-apify'),
            array('status' => 404)
        );
    }

    return true;
}

/**
 * Ensure only administrators can create or mutate shared EventON taxonomy records.
 *
 * @return true|WP_Error
 */
function eventon_apify_assert_can_manage_shared_terms() {
    if (current_user_can('manage_options')) {
        return true;
    }

    return new WP_Error(
        'eventon_apify_term_management_forbidden',
        __('Managing shared EventON taxonomy records requires administrator privileges.', 'eventon-apify'),
        array('status' => rest_authorization_required_code())
    );
}
