<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Merge MCP availability flags, exposing the admin-only set only to
 * authenticated administrators.
 *
 * Centralizes the visibility policy so individual manifest builders do not each
 * re-check the capability and risk leaking sensitive flags by default.
 *
 * @param array<string, mixed> $public     Flags safe for anonymous callers.
 * @param array<string, mixed> $admin_only Flags restricted to administrators.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_availability(array $public, array $admin_only) {
    if (current_user_can('manage_options')) {
        return array_merge($public, $admin_only);
    }

    return $public;
}

/**
 * Base runtime availability flags shared across MCP manifest sections.
 *
 * @return array<string, bool>
 */
function eventon_apify_get_mcp_availability_flags() {
    return array(
        'eventon_available' => eventon_apify_is_eventon_available(),
        'eventon_rsvp_available' => eventon_apify_is_eventon_rsvp_available(),
        'custom_event_api_enabled' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false),
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

    return eventon_apify_build_mcp_availability(
        array_merge(
            eventon_apify_get_mcp_availability_flags(),
            array(
                'wp_v2_compatibility_enabled' => $wp_v2_enabled,
                'preferred_mcp_ready' => $eventon_available && $wp_v2_enabled,
            )
        ),
        // The granular capability matrix reveals exactly which write/RSVP routes
        // are open, so it is restricted to authenticated administrators.
        array(
            'custom_event_api_capabilities' => eventon_apify_get_api_capabilities(),
        )
    );
}
