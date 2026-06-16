<?php

/**
 * Seed and restore plugin settings so upgrades do not silently disable the API surface.
 */
function eventon_apify_bootstrap_settings() {
    $backup = get_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, array());
    if (!is_array($backup)) {
        $backup = array();
    }

    eventon_apify_restore_or_seed_boolean_option(EVENTON_APIFY_OPTION_ENABLE_API, $backup, 'enable_api');

    $capabilities = get_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, null);
    if (!is_array($capabilities)) {
        if (isset($backup['api_capabilities']) && is_array($backup['api_capabilities'])) {
            update_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, eventon_apify_sanitize_capabilities($backup['api_capabilities']));
        } else {
            add_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, eventon_apify_get_default_api_capabilities());
        }
    }

    eventon_apify_restore_or_seed_boolean_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, $backup, 'enable_wp_v2_compat');

    eventon_apify_sync_settings_backup();
}

/**
 * Seed a boolean option from its backup value, or false when no backup exists.
 *
 * No-op when the option already has a stored value.
 *
 * @param string               $option     Option name.
 * @param array<string, mixed> $backup     Settings backup snapshot.
 * @param string               $backup_key Key within the backup holding this option.
 */
function eventon_apify_restore_or_seed_boolean_option($option, array $backup, $backup_key) {
    if (null !== get_option($option, null)) {
        return;
    }

    if (array_key_exists($backup_key, $backup)) {
        update_option($option, !empty($backup[$backup_key]));
        return;
    }

    add_option($option, false);
}

/**
 * Ensure settings are bootstrapped on activation too.
 */
function eventon_apify_activate() {
    eventon_apify_bootstrap_settings();
}

/**
 * Persist a backup copy of the plugin settings so future upgrades can restore them if needed.
 */
function eventon_apify_sync_settings_backup() {
    $backup = array(
        'version' => EVENTON_APIFY_VERSION,
        'enable_api' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false),
        'api_capabilities' => eventon_apify_get_api_capabilities(),
        'enable_wp_v2_compat' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, false),
    );

    update_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, $backup, false);
    update_option(EVENTON_APIFY_OPTION_INSTALLED_VERSION, EVENTON_APIFY_VERSION, false);
}

/**
 * Refresh the backup snapshot when a tracked option changes.
 *
 * @param string $option    Updated option name.
 * @param mixed  $old_value Previous option value.
 * @param mixed  $value     New option value.
 */
function eventon_apify_sync_settings_backup_on_option_change($option, $old_value, $value) {
    eventon_apify_maybe_sync_settings_backup($option);
}

/**
 * Refresh the backup snapshot when a tracked option is added.
 *
 * @param string $option Option name.
 * @param mixed  $value  Stored option value.
 */
function eventon_apify_sync_settings_backup_on_option_add($option, $value) {
    eventon_apify_maybe_sync_settings_backup($option);
}

/**
 * Refresh the backup snapshot when the changed option is one we track.
 *
 * @param string $option Option name.
 */
function eventon_apify_maybe_sync_settings_backup($option) {
    if (!eventon_apify_is_tracked_settings_option($option)) {
        return;
    }

    eventon_apify_sync_settings_backup();
}

/**
 * Return true when the option should trigger a settings-backup refresh.
 */
function eventon_apify_is_tracked_settings_option($option) {
    return in_array(
        (string) $option,
        array(
            EVENTON_APIFY_OPTION_ENABLE_API,
            EVENTON_APIFY_OPTION_API_CAPABILITIES,
            EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT,
        ),
        true
    );
}
