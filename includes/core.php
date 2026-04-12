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
 * Seed and restore plugin settings so upgrades do not silently disable the API surface.
 */
function eventon_apify_bootstrap_settings() {
    $backup = get_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, array());
    if (!is_array($backup)) {
        $backup = array();
    }

    $api_enabled = get_option(EVENTON_APIFY_OPTION_ENABLE_API, null);
    if (null === $api_enabled) {
        if (array_key_exists('enable_api', $backup)) {
            update_option(EVENTON_APIFY_OPTION_ENABLE_API, !empty($backup['enable_api']));
        } else {
            add_option(EVENTON_APIFY_OPTION_ENABLE_API, false);
        }
    }

    $capabilities = get_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, null);
    if (!is_array($capabilities)) {
        if (isset($backup['api_capabilities']) && is_array($backup['api_capabilities'])) {
            update_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, eventon_apify_sanitize_capabilities($backup['api_capabilities']));
        } else {
            add_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, eventon_apify_get_default_api_capabilities());
        }
    }

    $wp_v2_compat = get_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, null);
    if (null === $wp_v2_compat) {
        if (array_key_exists('enable_wp_v2_compat', $backup)) {
            update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, !empty($backup['enable_wp_v2_compat']));
        } else {
            add_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, false);
        }
    }

    eventon_apify_sync_settings_backup();
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
    unset($old_value, $value);

    if (!eventon_apify_is_tracked_settings_option($option)) {
        return;
    }

    eventon_apify_sync_settings_backup();
}

/**
 * Refresh the backup snapshot when a tracked option is added.
 *
 * @param string $option Option name.
 * @param mixed  $value  Stored option value.
 */
function eventon_apify_sync_settings_backup_on_option_add($option, $value) {
    unset($value);

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

/**
 * Sanitize checkbox-style values into a boolean.
 *
 * @param mixed $value Submitted option value.
 */
function eventon_apify_sanitize_checkbox($value) {
    return !empty($value);
}

/**
 * Return the default enabled API capabilities.
 *
 * @return array<string, bool>
 */
function eventon_apify_get_default_api_capabilities() {
    return array(
        'list' => true,
        'read' => true,
        'create' => true,
        'update' => true,
        'delete' => true,
        'rsvp_counts' => false,
        'rsvp_attendees' => false,
    );
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
        ),
        'read' => array(
            'label' => __('Read single event', 'eventon-apify'),
            'description' => __('Allow GET requests for an individual event.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events/<id>',
        ),
        'create' => array(
            'label' => __('Create events', 'eventon-apify'),
            'description' => __('Allow POST requests that create ajde_events records.', 'eventon-apify'),
            'methods' => 'POST',
            'route' => '/events',
        ),
        'update' => array(
            'label' => __('Update events', 'eventon-apify'),
            'description' => __('Allow PUT and PATCH requests for existing events.', 'eventon-apify'),
            'methods' => 'PUT, PATCH',
            'route' => '/events/<id>',
        ),
        'delete' => array(
            'label' => __('Delete events', 'eventon-apify'),
            'description' => __('Allow DELETE requests that trash events.', 'eventon-apify'),
            'methods' => 'DELETE',
            'route' => '/events/<id>',
        ),
        'rsvp_counts' => array(
            'label' => __('Read RSVP summary', 'eventon-apify'),
            'description' => __('Allow GET requests for the yes-only RSVP summary of an event.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events/<id>/rsvps/summary',
        ),
        'rsvp_attendees' => array(
            'label' => __('List RSVP attendees', 'eventon-apify'),
            'description' => __('Allow GET requests for RSVP attendee records and contact details.', 'eventon-apify'),
            'methods' => 'GET',
            'route' => '/events/<id>/rsvps',
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

/**
 * Determine whether the standard wp/v2 compatibility layer is enabled.
 */
function eventon_apify_is_wp_v2_compatibility_enabled() {
    return (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, false);
}

/**
 * Restrict the standard wp/v2 compatibility surface to administrators.
 *
 * @param mixed           $result  Response to replace the requested version with.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed
 */
function eventon_apify_restrict_wp_v2_compatibility_routes($result, $server, WP_REST_Request $request) {
    unset($server);

    if (!eventon_apify_is_wp_v2_compatibility_enabled()) {
        return $result;
    }

    if (!eventon_apify_is_wp_v2_compatibility_route($request->get_route())) {
        return $result;
    }

    if (current_user_can('manage_options')) {
        return $result;
    }

    return new WP_Error(
        'eventon_apify_wp_v2_admin_only',
        __('The EventON wp/v2 compatibility endpoints are restricted to administrators.', 'eventon-apify'),
        array('status' => rest_authorization_required_code())
    );
}

/**
 * Return true when the request route is part of the EventON wp/v2 compatibility surface.
 */
function eventon_apify_is_wp_v2_compatibility_route($route) {
    $route = (string) $route;

    $prefixes = array(
        '/wp/v2/ajde_events',
        '/wp/v2/types/ajde_events',
    );

    foreach (eventon_apify_get_wp_v2_compatibility_taxonomies() as $taxonomy) {
        $prefixes[] = '/wp/v2/' . $taxonomy;
        $prefixes[] = '/wp/v2/taxonomies/' . $taxonomy;
    }

    foreach ($prefixes as $prefix) {
        if ($route === $prefix || str_starts_with($route, $prefix . '/')) {
            return true;
        }
    }

    return false;
}

/**
 * Return the EventON taxonomies exposed through wp/v2 compatibility mode.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_compatibility_taxonomies() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $fallback = array('event_type', 'event_type_2', 'event_location', 'event_organizer');

    if (!post_type_exists('ajde_events')) {
        return $cache = $fallback;
    }

    $taxonomies = get_object_taxonomies('ajde_events', 'names');
    if (!is_array($taxonomies)) {
        return $fallback;
    }

    $taxonomies = array_values(
        array_filter(
            $taxonomies,
            static function ($taxonomy) {
                return is_string($taxonomy) && str_starts_with($taxonomy, 'event_');
            }
        )
    );

    return $cache = (!empty($taxonomies) ? $taxonomies : $fallback);
}

/**
 * Remove EventON wp/v2 compatibility routes from the REST index for non-admin users.
 *
 * @param array<string, mixed> $endpoints Registered REST endpoints.
 * @return array<string, mixed>
 */
function eventon_apify_filter_wp_v2_compatibility_endpoints($endpoints) {
    if (!eventon_apify_is_wp_v2_compatibility_enabled() || current_user_can('manage_options')) {
        return $endpoints;
    }

    foreach (array_keys($endpoints) as $route) {
        if (eventon_apify_is_wp_v2_compatibility_route($route)) {
            unset($endpoints[$route]);
        }
    }

    return $endpoints;
}

/**
 * Remove EventON compatibility objects from shared wp/v2 collection responses for non-admin users.
 *
 * @param WP_HTTP_Response|mixed $response Response object.
 * @param WP_REST_Server         $server   Server instance.
 * @param WP_REST_Request        $request  Current request.
 * @return WP_HTTP_Response|mixed
 */
function eventon_apify_filter_wp_v2_compatibility_responses($response, $_server, WP_REST_Request $request) {

    if (
        !eventon_apify_is_wp_v2_compatibility_enabled()
        || current_user_can('manage_options')
        || !($response instanceof WP_HTTP_Response)
    ) {
        return $response;
    }

    $route = (string) $request->get_route();
    $data = $response->get_data();

    if ($route === '/wp/v2/types' && is_array($data)) {
        unset($data['ajde_events']);
        $response->set_data(!empty($data) ? $data : (object) array());
        return $response;
    }

    if ($route === '/wp/v2/taxonomies' && is_array($data)) {
        foreach (eventon_apify_get_wp_v2_compatibility_taxonomies() as $taxonomy) {
            unset($data[$taxonomy]);
        }

        $response->set_data(!empty($data) ? $data : (object) array());
        return $response;
    }

    return $response;
}

/**
 * Exclude EventON posts from shared wp/v2 search queries for non-admin users.
 *
 * @param array<string, mixed> $query_args Search query arguments.
 * @return array<string, mixed>
 */
function eventon_apify_filter_wp_v2_compatibility_post_search_query(array $query_args) {

    if (!eventon_apify_is_wp_v2_compatibility_enabled() || current_user_can('manage_options')) {
        return $query_args;
    }

    if (!isset($query_args['post_type'])) {
        return $query_args;
    }

    $post_types = (array) $query_args['post_type'];
    $post_types = array_values(array_diff($post_types, array('ajde_events')));
    $query_args['post_type'] = !empty($post_types) ? $post_types : array('__eventon_apify_no_results__');

    return $query_args;
}

/**
 * Exclude EventON taxonomies from shared wp/v2 search queries for non-admin users.
 *
 * @param array<string, mixed> $query_args Search query arguments.
 * @return array<string, mixed>
 */
function eventon_apify_filter_wp_v2_compatibility_term_search_query(array $query_args) {

    if (!eventon_apify_is_wp_v2_compatibility_enabled() || current_user_can('manage_options')) {
        return $query_args;
    }

    if (!isset($query_args['taxonomy'])) {
        return $query_args;
    }

    $taxonomies = (array) $query_args['taxonomy'];
    $taxonomies = array_values(array_diff($taxonomies, eventon_apify_get_wp_v2_compatibility_taxonomies()));
    $query_args['taxonomy'] = !empty($taxonomies) ? $taxonomies : array('__eventon_apify_no_results__');

    return $query_args;
}

/**
 * Render the plugin settings page.
 */
