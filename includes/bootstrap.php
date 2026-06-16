<?php

function eventon_apify_load_textdomain() {
    load_plugin_textdomain(
        'eventon-apify',
        false,
        dirname(plugin_basename(EVENTON_APIFY_PLUGIN_FILE)) . '/languages'
    );
}

/**
 * Show an admin notice when PHP is too old for this plugin.
 */
function eventon_apify_php_version_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('EventON APIfy:', 'eventon-apify') . '</strong> ';
    echo esc_html__('This plugin requires PHP 8.0 or higher.', 'eventon-apify') . ' ';
    /* translators: %s: Current PHP version. */
    echo sprintf(esc_html__('You are running PHP %s.', 'eventon-apify'), esc_html(PHP_VERSION)) . ' ';
    echo esc_html__('Please upgrade PHP before activating this plugin.', 'eventon-apify');
    echo '</p></div>';
}

/**
 * Add the plugin settings page.
 */
function eventon_apify_add_settings_page() {
    add_options_page(
        __('EventON APIfy Settings', 'eventon-apify'),
        __('EventON APIfy', 'eventon-apify'),
        'manage_options',
        'eventon-apify-settings',
        'eventon_apify_render_settings_page'
    );
}

/**
 * Add a Settings link on the Plugins screen.
 *
 * @param array<int, string> $links Existing action links.
 * @return array<int, string>
 */
function eventon_apify_add_plugin_action_links($links) {
    $settings_url = admin_url('options-general.php?page=eventon-apify-settings');

    array_unshift(
        $links,
        sprintf(
            '<a href="%s">%s</a>',
            esc_url($settings_url),
            esc_html__('Settings', 'eventon-apify')
        )
    );

    return $links;
}

/**
 * Register plugin settings.
 */
function eventon_apify_register_settings() {
    register_setting(
        'eventon_apify_settings_group',
        EVENTON_APIFY_OPTION_ENABLE_API,
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'eventon_apify_sanitize_checkbox',
            'default' => false,
        )
    );

    register_setting(
        'eventon_apify_settings_group',
        EVENTON_APIFY_OPTION_API_CAPABILITIES,
        array(
            'type' => 'array',
            'sanitize_callback' => 'eventon_apify_sanitize_capabilities',
            'default' => eventon_apify_get_default_api_capabilities(),
        )
    );

    register_setting(
        'eventon_apify_settings_group',
        EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT,
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'eventon_apify_sanitize_checkbox',
            'default' => false,
        )
    );
}

/**
 * Sanitize checkbox-style values into a boolean.
 *
 * @param mixed $value Submitted option value.
 */
function eventon_apify_sanitize_checkbox($value) {
    return !empty($value);
}
