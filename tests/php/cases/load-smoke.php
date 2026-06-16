<?php
/**
 * Smoke test: every plugin module loads (bootstrap requires them all) and a
 * representative function from each is defined. Guards against a refactor or
 * file split dropping or double-declaring a function.
 */

test('a representative function from every module is defined', function () {
    $representatives = array(
        'eventon_apify_load_textdomain',                 // bootstrap.php
        'eventon_apify_get_api_capabilities',            // capabilities.php
        'eventon_apify_bootstrap_settings',              // settings-backup.php
        'eventon_apify_is_wp_v2_compatibility_enabled',  // wp-v2-compat.php
        'eventon_apify_render_settings_page',            // admin.php
        'eventon_apify_get_contract_field_definitions',  // mcp-field-definitions.php
        'eventon_apify_get_mcp_contract_field_names',    // mcp-field-metadata.php
        'eventon_apify_get_mcp_contract_fields',         // mcp-contract-builder.php
        'eventon_apify_get_mcp_validation_rules',        // mcp-validation.php
        'eventon_apify_get_mcp_contract_examples',       // mcp-examples.php
        'eventon_apify_build_mcp_availability',          // mcp-availability.php
        'eventon_apify_get_mcp_rsvp_content_type_manifest', // mcp-rsvp.php
        'eventon_apify_get_mcp_manifest',                // mcp-manifest.php
        'eventon_apify_register_routes',                 // rest-routes.php
        'eventon_apify_format_wp_v2_event',              // rest-wp-v2-compat.php
        'eventon_apify_sanitize_slug_filter',            // rest-request-validation.php
        'eventon_apify_admin_only',                      // rest-access-control.php
        'eventon_apify_format_event',                    // rest-events-read.php
        'eventon_apify_get_events',                      // rest-events-list.php
        'eventon_apify_create_event',                    // rest-events-write.php
        'eventon_apify_get_event_rsvps',                 // rest-rsvp.php
        'eventon_apify_normalize_request_payload',       // rest-event-payload.php
        'eventon_apify_validate_event_payload',          // rest-event-validation.php
        'eventon_apify_save_event_meta',                 // rest-event-meta.php
        'eventon_apify_save_event_terms',                // rest-event-terms.php
        'eventon_apify_array_get',                       // rest-helpers.php
    );

    foreach ($representatives as $fn) {
        ok(function_exists($fn), "missing function: {$fn}");
    }
});
