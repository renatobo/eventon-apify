<?php
/**
 * Plugin Name:       EventON APIfy
 * Plugin URI:        https://github.com/renatobo/eventon-apify
 * Description:       Protected REST API endpoints for EventON events with pagination, CRUD operations, and administrator-only access.
 * Version:           1.2.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Renato Bonomini
 * Author URI:        https://github.com/renatobo
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eventon-apify
 *
 * GitHub Plugin URI: https://github.com/renatobo/eventon-apify
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package EventON_APIfy
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EVENTON_APIFY_VERSION', '1.2.3');
define('EVENTON_APIFY_NAMESPACE', 'eventonapify/v1');
define('EVENTON_APIFY_OPTION_ENABLE_API', 'eventon_apify_enable_api');
define('EVENTON_APIFY_OPTION_API_CAPABILITIES', 'eventon_apify_api_capabilities');
define('EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT', 'eventon_apify_enable_wp_v2_compat');

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    add_action('admin_notices', 'eventon_apify_php_version_notice');
    return;
}

add_action('admin_menu', 'eventon_apify_add_settings_page');
add_action('admin_init', 'eventon_apify_register_settings');
add_action('rest_api_init', 'eventon_apify_register_routes');
add_action('rest_api_init', 'eventon_apify_register_wp_v2_compatibility_fields');
add_filter('register_post_type_args', 'eventon_apify_filter_post_type_args_for_wp_v2_compat', 10, 2);
add_filter('register_taxonomy_args', 'eventon_apify_filter_taxonomy_args_for_wp_v2_compat', 10, 2);
add_filter('rest_pre_dispatch', 'eventon_apify_restrict_wp_v2_compatibility_routes', 10, 3);

/**
 * Show an admin notice when PHP is too old for this plugin.
 */
function eventon_apify_php_version_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>EventON APIfy:</strong> This plugin requires PHP 8.0 or higher. ';
    echo 'You are running PHP ' . esc_html(PHP_VERSION) . '. ';
    echo 'Please upgrade PHP before activating this plugin.';
    echo '</p></div>';
}

/**
 * Add the plugin settings page.
 */
function eventon_apify_add_settings_page() {
    add_options_page(
        'EventON APIfy Settings',
        'EventON APIfy',
        'manage_options',
        'eventon-apify-settings',
        'eventon_apify_render_settings_page'
    );
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
            'label' => 'List events',
            'description' => 'Allow GET requests to the events collection endpoint.',
            'methods' => 'GET',
            'route' => '/events',
        ),
        'read' => array(
            'label' => 'Read single event',
            'description' => 'Allow GET requests for an individual event.',
            'methods' => 'GET',
            'route' => '/events/<id>',
        ),
        'create' => array(
            'label' => 'Create events',
            'description' => 'Allow POST requests that create ajde_events records.',
            'methods' => 'POST',
            'route' => '/events',
        ),
        'update' => array(
            'label' => 'Update events',
            'description' => 'Allow PUT and PATCH requests for existing events.',
            'methods' => 'PUT, PATCH',
            'route' => '/events/<id>',
        ),
        'delete' => array(
            'label' => 'Delete events',
            'description' => 'Allow DELETE requests that trash events.',
            'methods' => 'DELETE',
            'route' => '/events/<id>',
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
        'The EventON wp/v2 compatibility endpoints are restricted to administrators.',
        array('status' => rest_authorization_required_code())
    );
}

/**
 * Return true when the request route is part of the EventON wp/v2 compatibility surface.
 */
function eventon_apify_is_wp_v2_compatibility_route($route) {
    $route = (string) $route;

    foreach (array(
        '/wp/v2/ajde_events',
        '/wp/v2/types/ajde_events',
        '/wp/v2/event_type',
        '/wp/v2/event_location',
        '/wp/v2/event_organizer',
        '/wp/v2/taxonomies/event_type',
        '/wp/v2/taxonomies/event_location',
        '/wp/v2/taxonomies/event_organizer',
    ) as $prefix) {
        if ($route === $prefix || str_starts_with($route, $prefix . '/')) {
            return true;
        }
    }

    return false;
}

/**
 * Render the plugin settings page.
 */
function eventon_apify_render_settings_page() {
    $site_url = untrailingslashit(get_site_url());
    $api_enabled = (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false);
    $capabilities = eventon_apify_get_api_capabilities();
    $definitions = eventon_apify_get_api_capability_definitions();
    $wp_v2_compat_enabled = eventon_apify_is_wp_v2_compatibility_enabled();
    $manifest_collection_url = $site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/mcp-schema';
    $manifest_type_url = $site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/mcp-schema/ajde_events';
    $project_url = 'https://github.com/renatobo/eventon-apify';
    $author_url = 'https://github.com/renatobo';
    $git_updater_url = 'https://github.com/afragen/git-updater';
    $banner_url = plugins_url('assets/eventon-apify-settings-banner.svg', __FILE__);
    ?>
    <div class="wrap">
        <div class="eventon-apify-admin">
            <div class="eventon-apify-hero">
                <img
                    src="<?php echo esc_url($banner_url); ?>"
                    alt="EventON APIfy settings banner"
                    class="eventon-apify-hero-image"
                />
            </div>

            <div class="eventon-apify-meta">
                <a href="<?php echo esc_url($project_url); ?>" target="_blank" rel="noopener noreferrer">
                    Plugin Repository
                </a>
                <span>Version <?php echo esc_html(EVENTON_APIFY_VERSION); ?></span>
                <a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="noopener noreferrer">
                    Renato Bonomini on GitHub
                </a>
                <a href="<?php echo esc_url($git_updater_url); ?>" target="_blank" rel="noopener noreferrer">
                    Updates via Git Updater
                </a>
            </div>

            <div class="eventon-apify-headline">
                <h1>EventON APIfy Settings</h1>
                <p class="eventon-apify-intro">
                Control the availability of the custom EventON REST API surface, the standard <code>wp/v2</code>
                compatibility layer, and the discovery docs that compatible clients use to build correct requests.
                </p>
                <p class="eventon-apify-intro eventon-apify-intro-secondary">
                    This plugin enables using MCP, with an extended MCP server available at
                    <a href="https://github.com/renatobo/mcp-wp-cpt" target="_blank" rel="noopener noreferrer">renatobo/mcp-wp-cpt</a>.
                </p>
            </div>

            <?php settings_errors(); ?>

            <?php if (!eventon_apify_is_eventon_available()) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong>EventON not detected.</strong>
                        Activate EventON so the <code>ajde_events</code> post type is available before using these endpoints.
                    </p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper eventon-apify-tabs" role="tablist" aria-label="EventON APIfy sections">
                <a href="#api" class="nav-tab eventon-apify-tab nav-tab-active" role="tab" aria-selected="true" data-panel="api">
                    Event API
                </a>
                <a href="#compat" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="compat">
                    WP v2 compatibility
                </a>
                <a href="#manifest" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="manifest">
                    MCP schema manifest
                </a>
                <a href="#fields" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="fields">
                    Request fields
                </a>
                <a href="#passwords" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="passwords">
                    Application Passwords
                </a>
            </nav>

            <form method="post" action="options.php" class="eventon-apify-shell">
                <?php settings_fields('eventon_apify_settings_group'); ?>
                <?php do_settings_sections('eventon_apify_settings_group'); ?>

                <section class="eventon-apify-panel is-active" id="api" data-panel="api" role="tabpanel">
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2>Event API and capability toggles</h2>
                            <p>
                                Gate the custom <code><?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?></code> REST
                                surface without changing authentication requirements.
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card eventon-apify-card-accent">
                        <div class="eventon-apify-switch-row">
                            <div>
                                <h3>Event API</h3>
                                <p>
                                    Enable or disable the protected REST endpoints under
                                    <code>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?></code>.
                                </p>
                            </div>
                            <label class="eventon-apify-toggle">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(EVENTON_APIFY_OPTION_ENABLE_API); ?>"
                                    value="1"
                                    <?php checked(true, $api_enabled, true); ?>
                                />
                                <span>Enable EventON events API</span>
                            </label>
                        </div>

                        <div class="eventon-apify-grid eventon-apify-grid-two">
                            <div class="eventon-apify-code-card">
                                <strong>Namespace</strong>
                                <code>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?></code>
                            </div>
                            <div class="eventon-apify-code-card">
                                <strong>Authentication</strong>
                                <span>Administrator access using WordPress credentials or Application Passwords.</span>
                            </div>
                        </div>

                        <div class="eventon-apify-route-list">
                            <strong>Routes</strong>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;</code>
                            <code>POST <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events</code>
                            <code>PUT/PATCH <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;</code>
                            <code>DELETE <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;</code>
                        </div>

                        <div class="eventon-apify-example-grid">
                            <div class="eventon-apify-example">
                                <strong>Collection example</strong>
                                <code id="eventon-apify-example-get"><?php echo esc_html($site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/events?per_page=10&page=1'); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-example-get'); return false;">Copy</button>
                            </div>
                            <div class="eventon-apify-example">
                                <strong>Authenticated curl example</strong>
                                <code id="eventon-apify-example-curl">curl -u your_username:your_app_password "<?php echo esc_html($site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/events?search=ride'); ?>"</code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-example-curl'); return false;">Copy</button>
                            </div>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-panel-copy">
                            <h3>API capabilities</h3>
                            <p>
                                Disable specific REST operations without turning off the entire API. Requests still
                                require administrator authentication, and disabled capabilities return <code>403</code>.
                            </p>
                        </div>

                        <fieldset class="eventon-apify-capabilities">
                            <legend class="screen-reader-text">API capabilities</legend>
                            <?php foreach ($definitions as $capability => $definition) : ?>
                                <?php
                                $saved_enabled = !empty($capabilities[$capability]);
                                $effective_enabled = $api_enabled && $saved_enabled;
                                ?>
                                <label class="eventon-apify-capability-row">
                                    <span class="eventon-apify-capability-main">
                                        <input
                                            type="checkbox"
                                            name="<?php echo esc_attr(EVENTON_APIFY_OPTION_API_CAPABILITIES . '[' . $capability . ']'); ?>"
                                            value="1"
                                            <?php checked(true, $saved_enabled, true); ?>
                                        />
                                        <span class="eventon-apify-capability-label"><?php echo esc_html($definition['label']); ?></span>
                                        <span class="eventon-apify-capability-badge <?php echo $effective_enabled ? 'is-enabled' : 'is-disabled'; ?>">
                                            <?php echo $effective_enabled ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </span>
                                    <span class="eventon-apify-capability-meta">
                                        <code><?php echo esc_html($definition['methods'] . ' ' . EVENTON_APIFY_NAMESPACE . $definition['route']); ?></code>
                                        <span><?php echo esc_html($definition['description']); ?></span>
                                        <?php if (!$api_enabled && $saved_enabled) : ?>
                                            <em>Saved as enabled, but inactive while the Event API switch is off.</em>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="compat" data-panel="compat" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2>WP v2 compatibility</h2>
                            <p>
                                Expose EventON content through the standard WordPress REST API so generic WordPress
                                tools can discover and operate on it.
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-switch-row">
                            <div>
                                <h3>Standard <code>wp/v2</code> compatibility</h3>
                                <p>
                                    Publish <code>ajde_events</code> and related taxonomies under the standard
                                    WordPress REST namespace while preserving EventON APIfy's custom namespace.
                                </p>
                            </div>
                            <label class="eventon-apify-toggle">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT); ?>"
                                    value="1"
                                    <?php checked(true, $wp_v2_compat_enabled, true); ?>
                                />
                                <span>Enable <code>wp/v2</code> compatibility</span>
                            </label>
                        </div>

                        <div class="eventon-apify-route-list">
                            <strong>Compatibility routes</strong>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/types/ajde_events</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/ajde_events</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/event_location</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/event_organizer</code>
                        </div>

                        <p class="eventon-apify-note">
                            Intended for generic WordPress clients such as
                            <a href="https://github.com/InstaWP/mcp-wp" target="_blank" rel="noopener noreferrer">InstaWP mcp-wp</a>.
                            These routes remain administrator-only, and compatibility responses redact sensitive
                            fields like virtual access secrets and notification email metadata.
                        </p>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="manifest" data-panel="manifest" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2>MCP schema manifest</h2>
                            <p>
                                Publish the executable EventON field contract for compatible MCP servers and other
                                structured clients.
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-example-grid">
                            <div class="eventon-apify-example">
                                <strong>Manifest collection</strong>
                                <code id="eventon-apify-manifest-collection"><?php echo esc_html($manifest_collection_url); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-manifest-collection'); return false;">Copy</button>
                            </div>
                            <div class="eventon-apify-example">
                                <strong>Content type detail</strong>
                                <code id="eventon-apify-manifest-type"><?php echo esc_html($manifest_type_url); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-manifest-type'); return false;">Copy</button>
                            </div>
                        </div>

                        <p>
                            The manifest is read-only and safe to expose. It describes the executable EventON field
                            contract using <code>preferred_endpoint</code>, <code>preferred_write_mode</code>,
                            structured <code>fields</code>, executable <code>validation_rules</code>, and normalized
                            <code>examples.create</code> and <code>examples.update</code> payloads for
                            <code>ajde_events</code>.
                        </p>
                        <p class="eventon-apify-note">
                            The manifest does not replace <code>wp/v2</code> writes. It exists to inform compatible
                            clients how to use <code>/wp-json/wp/v2/ajde_events</code> correctly.
                        </p>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="fields" data-panel="fields" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2>Request fields</h2>
                            <p>
                                Preferred JSON payloads use EventON-style nested objects. Legacy flat aliases such as
                                <code>location_name</code> are still accepted for backward compatibility.
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <pre>{
  "title": "Ride to Big Bear",
  "description": "Optional HTML content",
  "excerpt": "Short summary",
  "status": "publish",
  "start_date": "2026-04-01",
  "start_time": "09:00",
  "end_date": "2026-04-01",
  "end_time": "17:00",
  "timezone": {
    "key": "America/Los_Angeles",
    "text": "PT"
  },
  "event_status": "scheduled",
  "attendance_mode": "offline",
  "location": {
    "name": "Big Bear Lake",
    "address": "123 Main St",
    "city": "Big Bear Lake",
    "state": "CA",
    "country": "US",
    "link": "https://maps.google.com/?q=Big+Bear+Lake",
    "link_target": true
  },
  "organizers": [
    {
      "name": "EventON APIfy",
      "email": "events@example.com"
    }
  ],
  "event_color": "#FF0000",
  "event_type": ["Rides", "Featured"],
  "flags": {
    "featured": true,
    "generate_gmap": true,
    "open_google_maps_link": true
  },
  "rsvp": {
    "enabled": true,
    "capacity_enabled": true,
    "capacity_count": 75
  }
}</pre>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="passwords" data-panel="passwords" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2>How to set up an Application Password</h2>
                            <p>
                                Use WordPress Application Passwords for administrator-authenticated requests to the
                                custom EventON API or the compatibility routes.
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <ol class="eventon-apify-steps">
                            <li>Log in to your WordPress Admin Dashboard.</li>
                            <li>Go to <strong>Users -> Profile</strong>.</li>
                            <li>Scroll down to the <strong>Application Passwords</strong> section.</li>
                            <li>Enter a name like <em>EventON API Access</em> and click <strong>Add New Application Password</strong>.</li>
                            <li>Copy the generated password.</li>
                            <li>Use it with your WordPress username in Basic Auth requests.</li>
                        </ol>
                        <p class="eventon-apify-note">
                            Your site should use HTTPS for Application Passwords. Store the generated password once,
                            because WordPress will not show it again.
                        </p>
                    </div>
                </section>

                <div class="eventon-apify-footer">
                    <?php submit_button('Save settings', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>

        <style>
            .eventon-apify-admin {
                max-width: 1120px;
                margin-top: 18px;
            }

            .eventon-apify-hero {
                margin: 0 0 16px;
                border: 1px solid #c8ccd0;
                background: #f6f7f7;
            }

            .eventon-apify-hero-image {
                display: block;
                width: 100%;
                height: auto;
            }

            .eventon-apify-headline {
                margin: 8px 0 20px;
            }

            .eventon-apify-headline h1 {
                margin: 0 0 8px;
                font-size: 42px;
                line-height: 1.1;
                color: #0f172a;
                font-weight: 400;
            }

            .eventon-apify-intro,
            .eventon-apify-panel-header p,
            .eventon-apify-panel-copy p,
            .eventon-apify-note,
            .eventon-apify-switch-row p {
                margin: 0;
                color: #475569;
                font-size: 14px;
                line-height: 1.65;
            }

            .eventon-apify-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 16px 0 10px;
            }

            .eventon-apify-meta a,
            .eventon-apify-meta span {
                display: inline-flex;
                align-items: center;
                min-height: 36px;
                padding: 0 14px;
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                color: #0f172a;
                text-decoration: none;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
            }

            .eventon-apify-meta a:hover {
                border-color: #2271b1;
                color: #2271b1;
            }

            .eventon-apify-intro {
                margin-bottom: 20px;
                max-width: 76ch;
            }

            .eventon-apify-intro-secondary {
                margin-top: -8px;
            }

            .eventon-apify-tabs {
                margin: 24px 0 0;
            }

            .eventon-apify-tabs .eventon-apify-tab {
                display: inline-block;
                float: none;
            }

            .eventon-apify-tabs .eventon-apify-tab:focus {
                box-shadow: 0 0 0 1px #2271b1;
            }

            .eventon-apify-shell {
                display: grid;
                gap: 18px;
                padding-top: 20px;
            }

            .eventon-apify-panel {
                display: grid;
                gap: 18px;
            }

            .eventon-apify-panel[hidden] {
                display: none;
            }

            .eventon-apify-panel-header h2,
            .eventon-apify-panel-copy h3,
            .eventon-apify-switch-row h3 {
                margin: 0 0 8px;
                color: #0f172a;
            }

            .eventon-apify-card {
                padding: 22px;
                border: 1px solid #c3c4c7;
                background: #ffffff;
            }

            .eventon-apify-card-accent {
                border-left: 4px solid #72aee6;
                background: #f6f7f7;
            }

            .eventon-apify-switch-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 18px;
                margin-bottom: 18px;
            }

            .eventon-apify-toggle {
                display: inline-flex;
                gap: 10px;
                align-items: center;
                background: #ffffff;
                border: 1px solid #c3c4c7;
                padding: 12px 14px;
                font-weight: 600;
                color: #0f172a;
            }

            .eventon-apify-grid {
                display: grid;
                gap: 14px;
            }

            .eventon-apify-grid-two,
            .eventon-apify-example-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .eventon-apify-code-card,
            .eventon-apify-example {
                display: grid;
                gap: 8px;
                padding: 14px;
                border: 1px solid #dcdcde;
                background: #ffffff;
            }

            .eventon-apify-example .button {
                width: fit-content;
            }

            .eventon-apify-route-list {
                display: grid;
                gap: 8px;
                margin: 18px 0;
            }

            .eventon-apify-capabilities {
                display: grid;
                gap: 12px;
                margin: 0;
            }

            .eventon-apify-capability-row {
                display: grid;
                gap: 4px;
                padding: 12px;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                background: #fff;
            }

            .eventon-apify-capability-main {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .eventon-apify-capability-label {
                font-weight: 600;
            }

            .eventon-apify-capability-badge {
                display: inline-flex;
                align-items: center;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.6;
            }

            .eventon-apify-capability-badge.is-enabled {
                background: #edf7ed;
                color: #116329;
            }

            .eventon-apify-capability-badge.is-disabled {
                background: #fcf0f1;
                color: #8a2424;
            }

            .eventon-apify-capability-meta {
                display: grid;
                gap: 4px;
                margin-left: 26px;
                color: #50575e;
            }

            .eventon-apify-footer {
                display: flex;
                justify-content: flex-start;
            }

            .eventon-apify-steps {
                margin: 0;
                padding-left: 18px;
                color: #1e293b;
            }

            .eventon-apify-steps li + li {
                margin-top: 8px;
            }

            code,
            pre {
                background: #f1f1f1;
                border-radius: 4px;
            }

            code {
                padding: 2px 6px;
            }

            pre {
                padding: 12px;
                overflow-x: auto;
            }

            @media (max-width: 960px) {
                .eventon-apify-hero,
                .eventon-apify-grid-two,
                .eventon-apify-example-grid,
                .eventon-apify-switch-row {
                    grid-template-columns: 1fr;
                    display: grid;
                }

                .eventon-apify-switch-row {
                    justify-content: stretch;
                }
            }
        </style>
        <script>
        function eventonApifyCopy(elementId) {
            const source = document.getElementById(elementId);

            if (!source) {
                return;
            }

            navigator.clipboard.writeText(source.textContent);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.eventon-apify-tab');
            const panels = document.querySelectorAll('.eventon-apify-panel');

            function activateTab(targetPanel, updateHash) {
                let hasMatch = false;

                tabs.forEach(function (item) {
                    const isTarget = item.getAttribute('data-panel') === targetPanel;
                    item.classList.toggle('nav-tab-active', isTarget);
                    item.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                    hasMatch = hasMatch || isTarget;
                });

                panels.forEach(function (panel) {
                    const isTarget = panel.getAttribute('data-panel') === targetPanel;
                    panel.classList.toggle('is-active', isTarget);
                    panel.hidden = !isTarget;
                });

                if (hasMatch && updateHash) {
                    window.location.hash = targetPanel;
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function (event) {
                    event.preventDefault();
                    activateTab(tab.getAttribute('data-panel'), true);
                });
            });

            const initialPanel = window.location.hash ? window.location.hash.replace('#', '') : 'api';
            activateTab(initialPanel, false);

            window.addEventListener('hashchange', function () {
                const hashPanel = window.location.hash ? window.location.hash.replace('#', '') : 'api';
                activateTab(hashPanel, false);
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Register REST routes.
 */
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
                    'status' => array(
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
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
}

/**
 * Validate EventON MCP manifest content type slugs.
 *
 * @param mixed $value Route parameter.
 */
function eventon_apify_validate_content_type_slug($value) {
    return is_string($value) && sanitize_key($value) !== '';
}

/**
 * Expose EventON's event post type on the standard wp/v2 REST API when compatibility mode is enabled.
 *
 * @param array<string, mixed> $args      Registered post type args.
 * @param string               $post_type Post type key.
 * @return array<string, mixed>
 */
function eventon_apify_filter_post_type_args_for_wp_v2_compat(array $args, $post_type) {
    if (!eventon_apify_is_wp_v2_compatibility_enabled() || $post_type !== 'ajde_events') {
        return $args;
    }

    $args['show_in_rest'] = true;
    $args['rest_base'] = 'ajde_events';

    if (empty($args['rest_controller_class'])) {
        $args['rest_controller_class'] = 'WP_REST_Posts_Controller';
    }

    return $args;
}

/**
 * Expose the main EventON taxonomies on the standard wp/v2 REST API when compatibility mode is enabled.
 *
 * @param array<string, mixed> $args     Registered taxonomy args.
 * @param string               $taxonomy Taxonomy key.
 * @return array<string, mixed>
 */
function eventon_apify_filter_taxonomy_args_for_wp_v2_compat(array $args, $taxonomy) {
    if (!eventon_apify_is_wp_v2_compatibility_enabled()) {
        return $args;
    }

    if (!in_array($taxonomy, array('event_type', 'event_location', 'event_organizer'), true)) {
        return $args;
    }

    $args['show_in_rest'] = true;
    $args['rest_base'] = $taxonomy;

    if (empty($args['rest_controller_class'])) {
        $args['rest_controller_class'] = 'WP_REST_Terms_Controller';
    }

    return $args;
}

/**
 * Register additional wp/v2 fields so generic WordPress clients can work with EventON data.
 */
function eventon_apify_register_wp_v2_compatibility_fields() {
    if (!eventon_apify_is_wp_v2_compatibility_enabled()) {
        return;
    }

    $read_only_fields = eventon_apify_get_wp_v2_read_only_field_names();

    foreach ($read_only_fields as $field_name) {
        register_rest_field(
            'ajde_events',
            $field_name,
            array(
                'get_callback' => 'eventon_apify_get_wp_v2_rest_field',
            )
        );
    }

    foreach (eventon_apify_get_wp_v2_mutable_field_names() as $field_name) {
        register_rest_field(
            'ajde_events',
            $field_name,
            array(
                'get_callback' => 'eventon_apify_get_wp_v2_rest_field',
                'update_callback' => 'eventon_apify_update_wp_v2_rest_field',
            )
        );
    }
}

/**
 * Return the shared EventON contract fields used by wp/v2 compatibility and MCP discovery.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_contract_field_definitions() {
    return array(
        'title' => array(
            'type' => 'string',
            'group' => 'core',
            'required_on_create' => true,
            'description' => 'WordPress post title for the EventON event.',
            'aliases' => array('post_title'),
            'transport' => array(
                'custom_namespace' => 'title',
                'wp_v2' => 'title',
            ),
        ),
        'description' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'Main event description or HTML body content.',
            'aliases' => array('content'),
            'transport' => array(
                'custom_namespace' => 'description',
                'wp_v2' => 'content',
            ),
        ),
        'excerpt' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'WordPress post excerpt.',
            'aliases' => array('post_excerpt'),
            'transport' => array(
                'custom_namespace' => 'excerpt',
                'wp_v2' => 'excerpt',
            ),
        ),
        'status' => array(
            'type' => 'string',
            'group' => 'core',
            'description' => 'WordPress post status for the EventON event.',
            'allowed_values' => array('publish', 'draft', 'private', 'pending', 'future'),
            'transport' => array(
                'custom_namespace' => 'status',
                'wp_v2' => 'status',
            ),
        ),
        'event_type' => array(
            'type' => 'array',
            'item_type' => 'string',
            'group' => 'taxonomy',
            'description' => 'EventON event_type terms as names or slugs.',
            'also_accepts' => array('comma_separated_string'),
            'aliases' => array('event_types'),
            'transport' => array(
                'custom_namespace' => 'event_type',
                'wp_v2' => 'event_type',
            ),
        ),
        'start_at' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'timing',
            'description' => 'ISO 8601 alias that can be split into start_date and start_time automatically.',
            'transport' => array(
                'custom_namespace' => 'start_at',
                'wp_v2' => 'start_at',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'start_date' => array(
            'type' => 'string',
            'format' => 'date',
            'group' => 'timing',
            'required_on_create' => true,
            'description' => 'Event start date in YYYY-MM-DD.',
            'transport' => array(
                'custom_namespace' => 'start_date',
                'wp_v2' => 'start_date',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'start_time' => array(
            'type' => 'string',
            'format' => 'time',
            'group' => 'timing',
            'description' => 'Event start time in HH:MM 24-hour format.',
            'transport' => array(
                'custom_namespace' => 'start_time',
                'wp_v2' => 'start_time',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'end_at' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'timing',
            'description' => 'ISO 8601 alias that can be split into end_date and end_time automatically.',
            'transport' => array(
                'custom_namespace' => 'end_at',
                'wp_v2' => 'end_at',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'end_date' => array(
            'type' => 'string',
            'format' => 'date',
            'group' => 'timing',
            'description' => 'Event end date in YYYY-MM-DD.',
            'transport' => array(
                'custom_namespace' => 'end_date',
                'wp_v2' => 'end_date',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'end_time' => array(
            'type' => 'string',
            'format' => 'time',
            'group' => 'timing',
            'description' => 'Event end time in HH:MM 24-hour format.',
            'transport' => array(
                'custom_namespace' => 'end_time',
                'wp_v2' => 'end_time',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'timezone' => array(
            'type' => 'object',
            'group' => 'timing',
            'description' => 'Timezone payload used by EventON.',
            'shape' => array(
                'key' => array(
                    'type' => 'string',
                    'description' => 'Valid PHP timezone identifier such as America/Los_Angeles.',
                ),
                'text' => array(
                    'type' => 'string',
                    'description' => 'Optional human-readable label such as PT.',
                ),
            ),
            'aliases' => array('timezone_key', 'timezone_text'),
            'transport' => array(
                'custom_namespace' => 'timezone',
                'wp_v2' => 'timezone',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_subtitle' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'EventON subtitle stored in evcal_subtitle.',
            'aliases' => array('subtitle'),
            'transport' => array(
                'custom_namespace' => 'event_subtitle',
                'wp_v2' => 'event_subtitle',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_excerpt' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'EventON excerpt stored separately from the WordPress excerpt.',
            'aliases' => array('evo_excerpt'),
            'transport' => array(
                'custom_namespace' => 'event_excerpt',
                'wp_v2' => 'event_excerpt',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_status' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'EventON event status value.',
            'allowed_values' => eventon_apify_get_allowed_event_statuses(),
            'aliases' => array('_status'),
            'transport' => array(
                'custom_namespace' => 'event_status',
                'wp_v2' => 'event_status',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'status_reason' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'Human-readable reason for the current non-scheduled EventON status.',
            'transport' => array(
                'custom_namespace' => 'status_reason',
                'wp_v2' => 'status_reason',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'attendance_mode' => array(
            'type' => 'string',
            'group' => 'eventon',
            'description' => 'Attendance mode for the event.',
            'allowed_values' => eventon_apify_get_allowed_attendance_modes(),
            'transport' => array(
                'custom_namespace' => 'attendance_mode',
                'wp_v2' => 'attendance_mode',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'time_extend_type' => array(
            'type' => 'string',
            'group' => 'timing',
            'description' => 'EventON time extension mode.',
            'allowed_values' => array('n', 'dl', 'ml', 'yl'),
            'aliases' => array('extend_type'),
            'transport' => array(
                'custom_namespace' => 'time_extend_type',
                'wp_v2' => 'time_extend_type',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'location' => array(
            'type' => 'object',
            'group' => 'location',
            'description' => 'Nested EventON event_location term payload.',
            'shape' => eventon_apify_get_location_contract_shape(),
            'aliases' => array(
                'location_name',
                'location_address',
                'location_city',
                'location_state',
                'location_country',
                'location_zip',
                'location_lat',
                'location_lon',
                'location_link',
                'map_url',
            ),
            'transport' => array(
                'custom_namespace' => 'location',
                'wp_v2' => 'location',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'organizer' => array(
            'type' => 'string',
            'group' => 'organizers',
            'description' => 'Legacy single-organizer alias. Prefer organizers[].',
            'transport' => array(
                'custom_namespace' => 'organizer',
                'wp_v2' => 'organizer',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'organizers' => array(
            'type' => 'array',
            'item_type' => 'object',
            'group' => 'organizers',
            'description' => 'Nested EventON event_organizer term payloads.',
            'item_shape' => eventon_apify_get_organizer_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'organizers',
                'wp_v2' => 'organizers',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_color' => array(
            'type' => 'string',
            'format' => 'color',
            'group' => 'presentation',
            'description' => 'Primary EventON event color in hex format.',
            'transport' => array(
                'custom_namespace' => 'event_color',
                'wp_v2' => 'event_color',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'event_color_secondary' => array(
            'type' => 'string',
            'format' => 'color',
            'group' => 'presentation',
            'description' => 'Secondary EventON event color in hex format.',
            'transport' => array(
                'custom_namespace' => 'event_color_secondary',
                'wp_v2' => 'event_color_secondary',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'gradient_angle' => array(
            'type' => 'number',
            'group' => 'presentation',
            'description' => 'Optional EventON gradient angle when gradient colors are enabled.',
            'transport' => array(
                'custom_namespace' => 'gradient_angle',
                'wp_v2' => 'gradient_angle',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'learn_more_link' => array(
            'type' => 'string',
            'format' => 'url',
            'group' => 'eventon',
            'description' => 'Optional EventON learn more URL.',
            'aliases' => array('link'),
            'transport' => array(
                'custom_namespace' => 'learn_more_link',
                'wp_v2' => 'learn_more_link',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'learn_more_link_target' => array(
            'type' => 'boolean',
            'group' => 'eventon',
            'description' => 'Open the learn more link in a new tab.',
            'aliases' => array('link_target'),
            'transport' => array(
                'custom_namespace' => 'learn_more_link_target',
                'wp_v2' => 'learn_more_link_target',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'flags' => array(
            'type' => 'object',
            'group' => 'flags',
            'description' => 'Boolean EventON switches controlling featured state, maps, and visibility.',
            'shape' => eventon_apify_get_flags_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'flags',
                'wp_v2' => 'flags',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'virtual' => array(
            'type' => 'object',
            'group' => 'virtual',
            'description' => 'Virtual event settings such as URL, password, embed, and visible end.',
            'shape' => eventon_apify_get_virtual_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'virtual',
                'wp_v2' => 'virtual',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'repeat' => array(
            'type' => 'object',
            'group' => 'repeat',
            'description' => 'Repeat rules and custom repeat intervals.',
            'shape' => eventon_apify_get_repeat_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'repeat',
                'wp_v2' => 'repeat',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'rsvp' => array(
            'type' => 'object',
            'group' => 'rsvp',
            'description' => 'EventON RSVP addon settings.',
            'shape' => eventon_apify_get_rsvp_contract_shape(),
            'transport' => array(
                'custom_namespace' => 'rsvp',
                'wp_v2' => 'rsvp',
            ),
            'wp_v2_field_mode' => 'additional',
        ),
        'start_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Saved EventON collection start timestamp.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'end_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Saved EventON collection end timestamp.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'event_start_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Resolved event start timestamp after EventON normalization.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'event_end_timestamp' => array(
            'type' => 'integer',
            'group' => 'timing',
            'read_only' => true,
            'description' => 'Resolved event end timestamp after EventON normalization.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'link' => array(
            'type' => 'string',
            'format' => 'url',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Public permalink for the event.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'featured_image' => array(
            'type' => 'string',
            'format' => 'url',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Full-size featured image URL.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'created' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Created timestamp in ISO 8601 format.',
            'wp_v2_field_mode' => 'read_only',
        ),
        'modified' => array(
            'type' => 'string',
            'format' => 'date-time',
            'group' => 'read_only',
            'read_only' => true,
            'description' => 'Last modified timestamp in ISO 8601 format.',
            'wp_v2_field_mode' => 'read_only',
        ),
    );
}

/**
 * Return the nested location shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_location_contract_shape() {
    return array(
        'term_id' => array('type' => 'integer', 'description' => 'Existing event_location term ID to reuse.'),
        'name' => array('type' => 'string', 'description' => 'Location name or title.'),
        'slug' => array('type' => 'string', 'description' => 'Optional slug for term lookup or creation.'),
        'description' => array('type' => 'string', 'description' => 'Location description.'),
        'type' => array('type' => 'string', 'description' => 'EventON location type.'),
        'address' => array('type' => 'string', 'description' => 'Street address.'),
        'city' => array('type' => 'string', 'description' => 'City.'),
        'state' => array('type' => 'string', 'description' => 'State or region.'),
        'country' => array('type' => 'string', 'description' => 'Country code or text.'),
        'zip' => array('type' => 'string', 'description' => 'Postal code.'),
        'lat' => array('type' => 'number', 'description' => 'Latitude.'),
        'lon' => array('type' => 'number', 'description' => 'Longitude.'),
        'link' => array('type' => 'string', 'format' => 'url', 'description' => 'External map or venue URL.'),
        'link_target' => array('type' => 'boolean', 'description' => 'Open the location link in a new tab.'),
        'phone' => array('type' => 'string', 'description' => 'Venue phone number.'),
        'email' => array('type' => 'string', 'description' => 'Venue email address.'),
        'use_latlng_for_directions' => array('type' => 'boolean', 'description' => 'Use coordinates when building map directions.'),
    );
}

/**
 * Return the nested organizer shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_organizer_contract_shape() {
    return array(
        'term_id' => array('type' => 'integer', 'description' => 'Existing event_organizer term ID to reuse.'),
        'name' => array('type' => 'string', 'description' => 'Organizer display name.'),
        'slug' => array('type' => 'string', 'description' => 'Optional slug for term lookup or creation.'),
        'description' => array('type' => 'string', 'description' => 'Organizer description.'),
        'contact' => array('type' => 'string', 'description' => 'Primary contact person or label.'),
        'email' => array('type' => 'string', 'description' => 'Organizer email address.'),
        'phone' => array('type' => 'string', 'description' => 'Organizer phone number.'),
        'address' => array('type' => 'string', 'description' => 'Organizer address.'),
        'link' => array('type' => 'string', 'format' => 'url', 'description' => 'Organizer external link.'),
        'link_target' => array('type' => 'boolean', 'description' => 'Open the organizer link in a new tab.'),
        'excerpt' => array('type' => 'string', 'description' => 'Organizer excerpt.'),
    );
}

/**
 * Return the nested flags shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_flags_contract_shape() {
    return array(
        'featured' => array('type' => 'boolean', 'description' => 'Feature the event in EventON.'),
        'completed' => array('type' => 'boolean', 'description' => 'Mark the event as completed.'),
        'exclude_from_calendar' => array('type' => 'boolean', 'description' => 'Exclude the event from calendar listings.'),
        'loggedin_only' => array('type' => 'boolean', 'description' => 'Restrict the event to logged-in users.'),
        'hide_end_time' => array('type' => 'boolean', 'description' => 'Hide the event end time.'),
        'span_hidden_end' => array('type' => 'boolean', 'description' => 'Allow the event to span beyond the hidden end time.'),
        'hide_location_name' => array('type' => 'boolean', 'description' => 'Hide the location name from the event card.'),
        'generate_gmap' => array('type' => 'boolean', 'description' => 'Generate a Google map for the location.'),
        'open_google_maps_link' => array('type' => 'boolean', 'description' => 'Link the event location to Google Maps.'),
        'location_access_loggedin_only' => array('type' => 'boolean', 'description' => 'Restrict location details to logged-in users.'),
        'location_info_over_image' => array('type' => 'boolean', 'description' => 'Display location info over the event image.'),
        'gradient_enabled' => array('type' => 'boolean', 'description' => 'Enable EventON gradient colors.'),
    );
}

/**
 * Return the nested virtual shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_virtual_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable virtual event mode.'),
        'type' => array('type' => 'string', 'description' => 'EventON virtual event type key.'),
        'url' => array('type' => 'string', 'format' => 'url', 'description' => 'Virtual event URL.'),
        'password' => array('type' => 'string', 'description' => 'Virtual event password.'),
        'embed' => array('type' => 'string', 'description' => 'Embed markup or shortcode.'),
        'other' => array('type' => 'string', 'description' => 'Additional virtual event content.'),
        'show' => array('type' => 'string', 'description' => 'EventON virtual visibility mode.'),
        'hide_when_live' => array('type' => 'boolean', 'description' => 'Hide the virtual link while the event is live.'),
        'disable_redirect_hiding' => array('type' => 'boolean', 'description' => 'Disable EventON redirect hiding.'),
        'moderator_id' => array('type' => 'integer', 'description' => 'Optional moderator user ID.'),
        'end_time_enabled' => array('type' => 'boolean', 'description' => 'Enable a separate virtual event end time.'),
        'end_date' => array('type' => 'string', 'format' => 'date', 'description' => 'Virtual event end date in YYYY-MM-DD.'),
        'end_time' => array('type' => 'string', 'format' => 'time', 'description' => 'Virtual event end time in HH:MM 24-hour format.'),
        'end_at' => array('type' => 'string', 'format' => 'date-time', 'description' => 'ISO 8601 alias for the virtual end datetime.'),
        'after_content' => array('type' => 'string', 'description' => 'Content shown after the virtual event ends.'),
        'after_content_when' => array('type' => 'string', 'description' => 'Condition for showing after-content text.'),
    );
}

/**
 * Return the nested repeat shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_repeat_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable repeating EventON intervals.'),
        'frequency' => array(
            'type' => 'string',
            'description' => 'Repeat frequency key.',
            'allowed_values' => eventon_apify_get_allowed_repeat_frequencies(),
        ),
        'gap' => array('type' => 'integer', 'description' => 'Gap between repeat occurrences.'),
        'count' => array('type' => 'integer', 'description' => 'Number of repeat occurrences.'),
        'series_visible' => array('type' => 'boolean', 'description' => 'Show the repeat series in EventON.'),
        'intervals' => array(
            'type' => 'array',
            'item_type' => 'object',
            'description' => 'Explicit repeat interval overrides.',
            'item_shape' => array(
                'start_at' => array('type' => 'string', 'format' => 'date-time', 'description' => 'Interval start in ISO 8601 format.'),
                'end_at' => array('type' => 'string', 'format' => 'date-time', 'description' => 'Interval end in ISO 8601 format.'),
            ),
        ),
    );
}

/**
 * Return the nested RSVP shape published in the MCP manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_rsvp_contract_shape() {
    return array(
        'enabled' => array('type' => 'boolean', 'description' => 'Enable EventON RSVP for the event.'),
        'show_count' => array('type' => 'boolean', 'description' => 'Show RSVP counts.'),
        'show_whos_coming' => array('type' => 'boolean', 'description' => 'Show who is coming.'),
        'only_loggedin' => array('type' => 'boolean', 'description' => 'Restrict RSVP to logged-in users.'),
        'capacity_enabled' => array('type' => 'boolean', 'description' => 'Enable capacity limits.'),
        'capacity_count' => array('type' => 'integer', 'description' => 'Maximum RSVP capacity.'),
        'capacity_show_remaining' => array('type' => 'boolean', 'description' => 'Show remaining capacity.'),
        'show_bars' => array('type' => 'boolean', 'description' => 'Display RSVP progress bars.'),
        'max_active' => array('type' => 'boolean', 'description' => 'Enable per-user maximum RSVP count.'),
        'max_count' => array('type' => 'integer', 'description' => 'Maximum RSVP count per user.'),
        'min_capacity_active' => array('type' => 'boolean', 'description' => 'Enable minimum RSVP capacity.'),
        'min_count' => array('type' => 'integer', 'description' => 'Minimum RSVP count.'),
        'close_before_minutes' => array('type' => 'integer', 'description' => 'Minutes before start when RSVP closes.'),
        'additional_emails' => array('type' => 'string', 'description' => 'Comma-separated email recipients for RSVP notifications.'),
        'manage_repeat_capacity' => array('type' => 'boolean', 'description' => 'Manage capacity separately for repeat intervals.'),
        'repeat_capacities' => array(
            'type' => 'array',
            'item_type' => 'integer',
            'description' => 'Capacity values for repeat intervals.',
        ),
    );
}

/**
 * Return the normalized plugin-specific fields exported in the MCP contract.
 *
 * @return array<int, string>
 */
function eventon_apify_get_mcp_contract_field_names() {
    return array(
        'event_type',
        'start_at',
        'start_date',
        'start_time',
        'end_at',
        'end_date',
        'end_time',
        'timezone',
        'time_extend_type',
        'event_subtitle',
        'event_excerpt',
        'event_status',
        'status_reason',
        'attendance_mode',
        'location',
        'organizers',
        'event_color',
        'event_color_secondary',
        'gradient_angle',
        'learn_more_link',
        'learn_more_link_target',
        'flags',
        'virtual',
        'repeat',
        'rsvp',
    );
}

/**
 * Export raw field definitions into the executable MCP contract shape.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_contract_fields() {
    $fields = array();
    $definitions = eventon_apify_get_contract_field_definitions();

    foreach (eventon_apify_get_mcp_contract_field_names() as $field_name) {
        if (!isset($definitions[$field_name])) {
            continue;
        }

        $fields[] = eventon_apify_build_mcp_contract_field_definition($field_name, $definitions[$field_name]);
    }

    return $fields;
}

/**
 * Convert one raw internal field definition into the manifest contract format.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_contract_field_definition($field_name, array $definition) {
    $field = array(
        'name' => $field_name,
        'label' => eventon_apify_format_contract_field_label($field_name),
        'description' => $definition['description'] ?? '',
        'type' => eventon_apify_map_contract_field_type($definition),
        'write_key' => eventon_apify_get_mcp_contract_write_key($field_name),
        'operations' => array('create', 'update'),
    );

    if (!empty($definition['required_on_create'])) {
        $field['required_on'] = array('create');
    }

    if (!empty($definition['allowed_values']) && is_array($definition['allowed_values'])) {
        $field['enum'] = array_values($definition['allowed_values']);
    }

    $aliases = eventon_apify_get_mcp_contract_field_aliases($field_name);
    if (!empty($aliases)) {
        $field['aliases'] = $aliases;
    }

    $coerce = eventon_apify_get_mcp_contract_field_coerce($field_name);
    if (!empty($coerce)) {
        $field['coerce'] = $coerce;
    }

    if (!empty($definition['shape']) && is_array($definition['shape'])) {
        $field['shape'] = eventon_apify_export_contract_shape_definitions($definition['shape']);
    }

    if ($definition['type'] === 'array') {
        $items = eventon_apify_build_mcp_contract_items_definition($field_name, $definition);
        if (!empty($items)) {
            $field['items'] = $items;
        }
    }

    return $field;
}

/**
 * Convert nested raw field definitions into contract field objects.
 *
 * @param array<string, array<string, mixed>> $shape Raw nested field definitions.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_export_contract_shape_definitions(array $shape) {
    $fields = array();

    foreach ($shape as $field_name => $definition) {
        $item = array(
            'name' => $field_name,
            'label' => eventon_apify_format_contract_field_label($field_name),
            'description' => $definition['description'] ?? '',
            'type' => eventon_apify_map_contract_field_type($definition),
        );

        if (!empty($definition['allowed_values']) && is_array($definition['allowed_values'])) {
            $item['enum'] = array_values($definition['allowed_values']);
        }

        if (!empty($definition['shape']) && is_array($definition['shape'])) {
            $item['shape'] = eventon_apify_export_contract_shape_definitions($definition['shape']);
        }

        if (($definition['type'] ?? '') === 'array') {
            $nested_items = eventon_apify_build_mcp_contract_items_definition($field_name, $definition);
            if (!empty($nested_items)) {
                $item['items'] = $nested_items;
            }
        }

        $fields[] = $item;
    }

    return $fields;
}

/**
 * Build the manifest `items` definition for array fields.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_contract_items_definition($field_name, array $definition) {
    $item_type = $definition['item_type'] ?? '';

    if (!is_string($item_type) || $item_type === '') {
        return array();
    }

    $items = array(
        'name' => eventon_apify_get_mcp_contract_array_item_name($field_name),
        'type' => $item_type,
    );

    if (!empty($definition['item_shape']) && is_array($definition['item_shape'])) {
        $items['shape'] = eventon_apify_export_contract_shape_definitions($definition['item_shape']);
    }

    return $items;
}

/**
 * Map the internal field type metadata to the external contract type vocabulary.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 */
function eventon_apify_map_contract_field_type(array $definition) {
    $format = $definition['format'] ?? '';
    if ($format === 'date') {
        return 'date';
    }

    if ($format === 'time') {
        return 'time';
    }

    return $definition['type'] ?? 'string';
}

/**
 * Return the canonical wp/v2 payload key for a manifest field.
 */
function eventon_apify_get_mcp_contract_write_key($field_name) {
    $custom_keys = array(
        'time_extend_type' => 'time_extend_type',
        'learn_more_link' => 'learn_more_link',
        'learn_more_link_target' => 'learn_more_link_target',
    );

    return $custom_keys[$field_name] ?? $field_name;
}

/**
 * Return the executable alias list for a manifest field.
 *
 * @return array<int, string>
 */
function eventon_apify_get_mcp_contract_field_aliases($field_name) {
    $aliases = array(
        'event_type' => array('event_types'),
        'event_subtitle' => array('subtitle'),
        'event_excerpt' => array('evo_excerpt'),
        'time_extend_type' => array('extend_type'),
    );

    return $aliases[$field_name] ?? array();
}

/**
 * Return an optional executable coercion rule for a manifest field.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_contract_field_coerce($field_name) {
    $coercions = array(
        'timezone' => array(
            'type' => 'string_to_object',
            'key' => 'key',
        ),
        'location' => array(
            'type' => 'string_to_object',
            'key' => 'name',
        ),
        'organizers' => array(
            'type' => 'array_string_to_object_array',
            'key' => 'name',
        ),
    );

    return $coercions[$field_name] ?? array();
}

/**
 * Return the item name used in array field definitions.
 */
function eventon_apify_get_mcp_contract_array_item_name($field_name) {
    $names = array(
        'event_type' => 'event_type',
        'organizers' => 'organizer',
        'intervals' => 'repeat_interval',
        'repeat_capacities' => 'repeat_capacity',
    );

    if (isset($names[$field_name])) {
        return $names[$field_name];
    }

    if (substr($field_name, -1) === 's') {
        return substr($field_name, 0, -1);
    }

    return $field_name . '_item';
}

/**
 * Build a readable field label from the canonical field key.
 */
function eventon_apify_format_contract_field_label($field_name) {
    return ucwords(str_replace('_', ' ', $field_name));
}

/**
 * Return the executable validation rules supported by the generic contract interpreter.
 *
 * @return array<string, array<int, mixed>>
 */
function eventon_apify_get_mcp_validation_rules() {
    return array(
        'required_for_create' => array('start_date'),
        'required_for_update' => array(),
        'required_together' => array(),
        'one_of_required' => array(),
    );
}

/**
 * Return additional validation notes that are enforced by the plugin runtime but not by the generic interpreter.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_validation_notes() {
    return array(
        array(
            'id' => 'title_required_on_create',
            'level' => 'error',
            'when' => 'create',
            'fields' => array('title'),
            'message' => 'title is required when creating an EventON event.',
        ),
        array(
            'id' => 'start_date_required',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('start_date'),
            'message' => 'start_date is required for EventON events.',
        ),
        array(
            'id' => 'datetime_range',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('start_date', 'start_time', 'end_date', 'end_time'),
            'message' => 'The end date/time must be on or after the start date/time.',
        ),
        array(
            'id' => 'timezone_key_must_be_valid',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('timezone'),
            'message' => 'timezone.key must be a valid PHP timezone identifier.',
        ),
        array(
            'id' => 'event_status_enum',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('event_status'),
            'message' => 'event_status must be one of the published enum values.',
        ),
        array(
            'id' => 'attendance_mode_enum',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('attendance_mode'),
            'message' => 'attendance_mode must be one of offline, online, or mixed.',
        ),
        array(
            'id' => 'color_format',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('event_color', 'event_color_secondary'),
            'message' => 'Event colors must use 6-character hex notation with or without a leading #.',
        ),
        array(
            'id' => 'absolute_urls_only',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('location', 'organizers', 'virtual', 'learn_more_link'),
            'message' => 'Location, organizer, virtual, and learn more links must be valid absolute URLs when provided.',
        ),
        array(
            'id' => 'numeric_coordinates',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('location'),
            'message' => 'location.lat and location.lon must be numeric when provided.',
        ),
        array(
            'id' => 'repeat_frequency_enum',
            'level' => 'error',
            'when' => 'create_or_update',
            'fields' => array('repeat'),
            'message' => 'repeat.frequency must be one of the published enum values.',
        ),
        array(
            'id' => 'virtual_end_requires_datetime',
            'level' => 'error',
            'when' => 'virtual.end_time_enabled',
            'fields' => array('virtual'),
            'message' => 'virtual.end_date is required when virtual end time is enabled.',
        ),
    );
}

/**
 * Return normalized contract examples for create/update flows.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_mcp_contract_examples() {
    return array(
        'create' => array(
            'title' => 'Ride to Big Bear',
            'status' => 'publish',
            'content' => 'Optional HTML content',
            'excerpt' => 'Short summary',
            'fields' => eventon_apify_get_mcp_example_create_payload(),
        ),
        'update' => array(
            'content' => 'Updated event description',
            'fields' => eventon_apify_get_mcp_example_update_payload(),
        ),
    );
}

/**
 * Return EventON field groups published in the MCP manifest.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_field_groups() {
    return array(
        array(
            'key' => 'core',
            'label' => 'Core content',
            'description' => 'Standard WordPress post fields used for EventON events.',
            'fields' => array('title', 'description', 'excerpt', 'status'),
        ),
        array(
            'key' => 'taxonomy',
            'label' => 'Taxonomy',
            'description' => 'EventON taxonomy inputs.',
            'fields' => array('event_type'),
        ),
        array(
            'key' => 'timing',
            'label' => 'Timing',
            'description' => 'Start, end, and timezone data.',
            'fields' => array('start_at', 'start_date', 'start_time', 'end_at', 'end_date', 'end_time', 'timezone', 'start_timestamp', 'end_timestamp', 'event_start_timestamp', 'event_end_timestamp'),
        ),
        array(
            'key' => 'eventon',
            'label' => 'EventON state',
            'description' => 'EventON-specific status and subtitle fields.',
            'fields' => array('event_subtitle', 'event_excerpt', 'event_status', 'status_reason', 'attendance_mode'),
        ),
        array(
            'key' => 'location',
            'label' => 'Location',
            'description' => 'Nested venue and map details.',
            'fields' => array('location'),
        ),
        array(
            'key' => 'organizers',
            'label' => 'Organizers',
            'description' => 'Organizer aliases and nested organizer payloads.',
            'fields' => array('organizer', 'organizers'),
        ),
        array(
            'key' => 'presentation',
            'label' => 'Presentation',
            'description' => 'Color styling fields.',
            'fields' => array('event_color', 'event_color_secondary'),
        ),
        array(
            'key' => 'flags',
            'label' => 'Flags',
            'description' => 'Boolean EventON display and access toggles.',
            'fields' => array('flags'),
        ),
        array(
            'key' => 'virtual',
            'label' => 'Virtual event',
            'description' => 'Virtual event settings and end-of-event behavior.',
            'fields' => array('virtual'),
        ),
        array(
            'key' => 'repeat',
            'label' => 'Repeat',
            'description' => 'Repeat interval rules.',
            'fields' => array('repeat'),
        ),
        array(
            'key' => 'rsvp',
            'label' => 'RSVP',
            'description' => 'RSVP addon settings.',
            'fields' => array('rsvp'),
        ),
        array(
            'key' => 'read_only',
            'label' => 'Read-only output',
            'description' => 'Compatibility fields returned by the API but not writable.',
            'fields' => array('link', 'featured_image', 'created', 'modified'),
        ),
    );
}

/**
 * Return the example EventON payload used in documentation and MCP discovery.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_example_create_payload() {
    return array(
        'event_type' => array('Rides', 'Featured'),
        'start_date' => '2026-04-01',
        'start_time' => '09:00',
        'end_date' => '2026-04-01',
        'end_time' => '17:00',
        'timezone' => array(
            'key' => 'America/Los_Angeles',
            'text' => 'PT',
        ),
        'time_extend_type' => 'n',
        'event_status' => 'scheduled',
        'attendance_mode' => 'offline',
        'event_subtitle' => 'High-altitude ride day',
        'location' => array(
            'name' => 'Big Bear Lake',
            'address' => '123 Main St',
            'city' => 'Big Bear Lake',
            'state' => 'CA',
            'country' => 'US',
            'link' => 'https://maps.google.com/?q=Big+Bear+Lake',
            'link_target' => true,
        ),
        'organizers' => array(
            array(
                'name' => 'EventON APIfy',
                'email' => 'events@example.com',
            ),
        ),
        'event_color' => '#ff0000',
        'learn_more_link' => 'https://example.com/rides/big-bear',
        'learn_more_link_target' => true,
        'flags' => array(
            'featured' => true,
            'generate_gmap' => true,
            'open_google_maps_link' => true,
            'gradient_enabled' => true,
        ),
        'gradient_angle' => 90,
        'virtual' => array(
            'enabled' => false,
        ),
        'rsvp' => array(
            'enabled' => true,
            'capacity_enabled' => true,
            'capacity_count' => 75,
        )
    );
}

/**
 * Return a partial update example for the MCP manifest.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_mcp_example_update_payload() {
    return array(
        'event_status' => 'rescheduled',
        'status_reason' => 'Weather moved the ride to next week.',
        'start_date' => '2026-04-08',
        'start_time' => '10:00',
        'end_date' => '2026-04-08',
        'end_time' => '18:00',
        'timezone' => array(
            'key' => 'America/Los_Angeles',
            'text' => 'PT',
        ),
        'location' => array(
            'name' => 'Lake Arrowhead',
            'address' => '456 Summit Rd',
            'city' => 'Lake Arrowhead',
            'state' => 'CA',
            'country' => 'US',
        ),
        'flags' => array(
            'featured' => false,
            'generate_gmap' => true,
        ),
        'virtual' => array(
            'enabled' => true,
            'url' => 'https://example.com/live/ride',
        ),
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

    return array(
        'eventon_available' => $eventon_available,
        'custom_event_api_enabled' => (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false),
        'custom_event_api_capabilities' => eventon_apify_get_api_capabilities(),
        'wp_v2_compatibility_enabled' => $wp_v2_enabled,
        'preferred_mcp_ready' => $eventon_available && $wp_v2_enabled,
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

    if ($content_type === '') {
        $content_types['ajde_events'] = eventon_apify_get_mcp_content_type_manifest();
    } elseif ($content_type === 'ajde_events') {
        $content_types['ajde_events'] = eventon_apify_get_mcp_content_type_manifest();
    } else {
        return new WP_Error(
            'eventon_apify_unknown_content_type',
            'Only the ajde_events content type is currently published in the EventON APIfy MCP manifest.',
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
        'description' => 'EventON events stored as ajde_events posts and exposed through wp/v2 compatibility plus the custom EventON APIfy namespace.',
        'preferred_endpoint' => 'wp/v2/ajde_events',
        'preferred_write_mode' => 'fields',
        'supported_operations' => array('create', 'update'),
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

/**
 * Return field names for the requested wp/v2 compatibility mode.
 *
 * @return array<int, string>
 */
function eventon_apify_get_contract_field_names_for_wp_v2_mode($mode) {
    $field_names = array();

    foreach (eventon_apify_get_contract_field_definitions() as $field_name => $definition) {
        if (($definition['wp_v2_field_mode'] ?? '') === $mode) {
            $field_names[] = $field_name;
        }
    }

    return $field_names;
}

/**
 * Return read-only EventON fields exposed on wp/v2 ajde_events.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_read_only_field_names() {
    return eventon_apify_get_contract_field_names_for_wp_v2_mode('read_only');
}

/**
 * Return mutable EventON fields exposed on wp/v2 ajde_events.
 *
 * @return array<int, string>
 */
function eventon_apify_get_wp_v2_mutable_field_names() {
    return eventon_apify_get_contract_field_names_for_wp_v2_mode('additional');
}

/**
 * Return an EventON payload safe for the shared wp/v2 compatibility surface.
 *
 * @return array<string, mixed>
 */
function eventon_apify_format_wp_v2_event(WP_Post $post) {
    $event = eventon_apify_format_event($post);

    if (isset($event['location']) && is_array($event['location'])) {
        unset($event['location']['email'], $event['location']['phone']);
    }

    if (isset($event['organizers']) && is_array($event['organizers'])) {
        foreach ($event['organizers'] as $index => $organizer) {
            if (!is_array($organizer)) {
                continue;
            }

            unset(
                $event['organizers'][$index]['email'],
                $event['organizers'][$index]['phone'],
                $event['organizers'][$index]['address']
            );
        }
    }

    if (isset($event['virtual']) && is_array($event['virtual'])) {
        unset(
            $event['virtual']['url'],
            $event['virtual']['password'],
            $event['virtual']['embed'],
            $event['virtual']['other'],
            $event['virtual']['moderator_id'],
            $event['virtual']['after_content']
        );
    }

    if (isset($event['rsvp']) && is_array($event['rsvp'])) {
        unset($event['rsvp']['additional_emails']);
    }

    return $event;
}

/**
 * Read an EventON compatibility field from the standard wp/v2 endpoint.
 *
 * @param array<string, mixed>|object $object REST callback object.
 * @return mixed
 */
function eventon_apify_get_wp_v2_rest_field($object, $field_name) {
    if (!current_user_can('manage_options')) {
        return null;
    }

    $post_id = eventon_apify_get_rest_callback_post_id($object);

    if (!$post_id) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'ajde_events') {
        return null;
    }

    $event = eventon_apify_format_wp_v2_event($post);

    return array_key_exists($field_name, $event) ? $event[$field_name] : null;
}

/**
 * Update an EventON compatibility field from the standard wp/v2 endpoint.
 *
 * @param mixed                       $value      Submitted field value.
 * @param array<string, mixed>|object $object     REST callback object.
 * @param string                      $field_name Field name.
 * @param WP_REST_Request|null        $request    Current REST request.
 * @return true|WP_Error
 */
function eventon_apify_update_wp_v2_rest_field($value, $object, $field_name, $request = null) {
    if (!current_user_can('manage_options')) {
        return new WP_Error(
            'eventon_apify_wp_v2_admin_only',
            'The EventON wp/v2 compatibility endpoints are restricted to administrators.',
            array('status' => rest_authorization_required_code())
        );
    }

    $post_id = eventon_apify_get_rest_callback_post_id($object);

    if (!$post_id) {
        return new WP_Error(
            'eventon_apify_missing_rest_post',
            'Unable to resolve the EventON event being updated.',
            array('status' => 400)
        );
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'ajde_events') {
        return new WP_Error(
            'eventon_apify_invalid_rest_post',
            'The standard wp/v2 compatibility field can only be used with ajde_events.',
            array('status' => 400)
        );
    }

    $params = array($field_name => $value);

    if ($request instanceof WP_REST_Request) {
        $params = eventon_apify_extract_wp_v2_event_request_payload($request, $params);
    }

    $params = eventon_apify_normalize_request_payload($params);
    $validation = eventon_apify_validate_event_payload($params, false, $post_id);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $meta_result = eventon_apify_save_event_meta($post_id, $params);
    if (is_wp_error($meta_result)) {
        return $meta_result;
    }

    $term_result = eventon_apify_save_event_terms($post_id, $params);
    if (is_wp_error($term_result)) {
        return $term_result;
    }

    return true;
}

/**
 * Extract all EventON-specific wp/v2 fields present in the request.
 *
 * @param array<string, mixed> $fallback Single-field fallback payload.
 * @return array<string, mixed>
 */
function eventon_apify_extract_wp_v2_event_request_payload(WP_REST_Request $request, array $fallback = array()) {
    $payload = array();

    foreach (eventon_apify_get_wp_v2_mutable_field_names() as $field_name) {
        if ($request->has_param($field_name)) {
            $payload[$field_name] = $request->get_param($field_name);
        }
    }

    return !empty($payload) ? $payload : $fallback;
}

/**
 * Resolve a post ID from REST field callbacks.
 *
 * @param array<string, mixed>|object $object REST callback object.
 */
function eventon_apify_get_rest_callback_post_id($object) {
    if (is_array($object)) {
        if (!empty($object['id'])) {
            return absint($object['id']);
        }

        if (!empty($object['ID'])) {
            return absint($object['ID']);
        }
    }

    if (is_object($object)) {
        if (!empty($object->id)) {
            return absint($object->id);
        }

        if (!empty($object->ID)) {
            return absint($object->ID);
        }
    }

    return 0;
}

/**
 * Ensure identifier values are numeric.
 *
 * @param mixed $value Route parameter.
 */
function eventon_apify_validate_numeric_identifier($value) {
    return is_numeric($value);
}

/**
 * Restrict API access to administrators.
 */
function eventon_apify_admin_only() {
    return current_user_can('manage_options');
}

/**
 * Sanitize a page number.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_sanitize_page($value) {
    $page = absint($value);
    return $page > 0 ? $page : 1;
}

/**
 * Sanitize a per-page value.
 *
 * @param mixed $value Request parameter.
 */
function eventon_apify_sanitize_per_page($value) {
    $per_page = absint($value);

    if ($per_page < 1) {
        $per_page = 20;
    }

    return min($per_page, 100);
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
            'EventON is not active or the ajde_events post type is unavailable.',
            array('status' => 500)
        );
    }

    if (!get_option(EVENTON_APIFY_OPTION_ENABLE_API, false)) {
        return new WP_Error(
            'eventon_apify_disabled',
            'The EventON APIfy endpoint is disabled. Enable it in Settings > EventON APIfy.',
            array('status' => 403)
        );
    }

    if ($capability !== '' && !eventon_apify_is_api_capability_enabled($capability)) {
        $definitions = eventon_apify_get_api_capability_definitions();
        $capability_label = isset($definitions[$capability]['label']) ? $definitions[$capability]['label'] : $capability;

        return new WP_Error(
            'eventon_apify_capability_disabled',
            $capability_label . ' is disabled in Settings > EventON APIfy.',
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
 * Format an EventON post into an API response object.
 */
function eventon_apify_format_event(WP_Post $post) {
    $meta = get_post_meta($post->ID);

    $start_timestamp = eventon_apify_get_meta_int($meta, 'evcal_srow');
    $end_timestamp = eventon_apify_get_meta_int($meta, 'evcal_erow');
    $event_start_timestamp = eventon_apify_get_meta_int($meta, '_unix_start_ev') ?: $start_timestamp;
    $event_end_timestamp = eventon_apify_get_meta_int($meta, '_unix_end_ev') ?: $end_timestamp;
    $virtual_end_timestamp = eventon_apify_get_meta_int($meta, '_unix_vend_ev') ?: eventon_apify_get_meta_int($meta, '_evo_virtual_erow');
    $timezone_key = eventon_apify_get_timezone_key_from_meta($meta);
    $event_types = wp_get_post_terms($post->ID, 'event_type', array('fields' => 'names'));
    $location = eventon_apify_get_location_payload($post->ID, $meta);
    $organizers = eventon_apify_get_organizer_payload($post->ID, $meta);
    $event_status = eventon_apify_get_event_status_from_meta($meta);
    $status_reason = eventon_apify_get_event_status_reason_from_meta($meta, $event_status);
    $gradient_angle = eventon_apify_get_meta_text($meta, '_evo_event_grad_ang');

    if (is_wp_error($event_types)) {
        $event_types = array();
    }

    return array(
        'id' => $post->ID,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'slug' => $post->post_name,
        'description' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'event_subtitle' => eventon_apify_get_meta_text($meta, 'evcal_subtitle'),
        'event_excerpt' => eventon_apify_get_meta_text($meta, 'evo_excerpt'),
        'link' => get_permalink($post->ID),
        'start_timestamp' => $start_timestamp,
        'start_at' => $event_start_timestamp ? eventon_apify_format_timestamp_for_timezone($event_start_timestamp, $timezone_key, 'c') : '',
        'start_date' => $event_start_timestamp ? eventon_apify_format_timestamp_for_timezone($event_start_timestamp, $timezone_key, 'Y-m-d') : '',
        'start_time' => $event_start_timestamp ? eventon_apify_format_timestamp_for_timezone($event_start_timestamp, $timezone_key, 'H:i') : '',
        'event_start_timestamp' => $event_start_timestamp,
        'end_timestamp' => $end_timestamp,
        'end_at' => $event_end_timestamp ? eventon_apify_format_timestamp_for_timezone($event_end_timestamp, $timezone_key, 'c') : '',
        'end_date' => $event_end_timestamp ? eventon_apify_format_timestamp_for_timezone($event_end_timestamp, $timezone_key, 'Y-m-d') : '',
        'end_time' => $event_end_timestamp ? eventon_apify_format_timestamp_for_timezone($event_end_timestamp, $timezone_key, 'H:i') : '',
        'event_end_timestamp' => $event_end_timestamp,
        'location' => $location,
        'organizer' => !empty($organizers) ? $organizers[0]['name'] : eventon_apify_get_meta_text($meta, 'evcal_organizer_name'),
        'organizers' => $organizers,
        'event_color' => eventon_apify_format_color_output(eventon_apify_get_meta_text($meta, 'evcal_event_color')),
        'event_color_secondary' => eventon_apify_format_color_output(eventon_apify_get_meta_text($meta, 'evcal_event_color2')),
        'event_type' => $event_types,
        'event_status' => $event_status,
        'status_reason' => $status_reason,
        'attendance_mode' => eventon_apify_get_attendance_mode_from_meta($meta, $event_status),
        'time_extend_type' => eventon_apify_get_meta_text($meta, '_time_ext_type') ?: 'n',
        'timezone' => array(
            'key' => $timezone_key,
            'text' => eventon_apify_get_meta_text($meta, 'evo_event_timezone'),
        ),
        'learn_more_link' => eventon_apify_get_meta_text($meta, 'evcal_lmlink'),
        'learn_more_link_target' => eventon_apify_get_yes_no_flag($meta, 'evcal_lmlink_target'),
        'flags' => array(
            'featured' => eventon_apify_get_yes_no_flag($meta, '_featured'),
            'completed' => eventon_apify_get_yes_no_flag($meta, '_completed'),
            'exclude_from_calendar' => eventon_apify_get_yes_no_flag($meta, 'evo_exclude_ev'),
            'loggedin_only' => eventon_apify_get_yes_no_flag($meta, '_onlyloggedin'),
            'hide_end_time' => eventon_apify_get_yes_no_flag($meta, 'evo_hide_endtime'),
            'span_hidden_end' => eventon_apify_get_yes_no_flag($meta, 'evo_span_hidden_end'),
            'hide_location_name' => eventon_apify_get_yes_no_flag($meta, 'evcal_hide_locname'),
            'generate_gmap' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_gen'),
            'open_google_maps_link' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_link'),
            'location_access_loggedin_only' => eventon_apify_get_yes_no_flag($meta, 'evo_access_control_location'),
            'location_info_over_image' => eventon_apify_get_yes_no_flag($meta, 'evcal_name_over_img'),
            'gradient_enabled' => eventon_apify_get_yes_no_flag($meta, '_evo_event_grad_colors'),
        ),
        'gradient_angle' => is_numeric($gradient_angle) ? (0 + $gradient_angle) : null,
        'virtual' => eventon_apify_get_virtual_payload($meta, $timezone_key, $virtual_end_timestamp),
        'repeat' => eventon_apify_get_repeat_payload($meta, $timezone_key),
        'rsvp' => eventon_apify_get_rsvp_payload($meta),
        'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: '',
        'created' => $post->post_date_gmt ? get_date_from_gmt($post->post_date_gmt, 'c') : '',
        'modified' => $post->post_modified_gmt ? get_date_from_gmt($post->post_modified_gmt, 'c') : '',
    );
}

/**
 * Return a text meta value from the raw post meta array.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @param string                           $key  Meta key.
 */
function eventon_apify_get_meta_text(array $meta, $key) {
    if (!isset($meta[$key][0])) {
        return '';
    }

    return (string) $meta[$key][0];
}

/**
 * Return an integer meta value from the raw post meta array.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @param string                           $key  Meta key.
 */
function eventon_apify_get_meta_int(array $meta, $key) {
    if (!isset($meta[$key][0]) || $meta[$key][0] === '') {
        return 0;
    }

    return absint($meta[$key][0]);
}

/**
 * Resolve EventON timezone key from saved meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_timezone_key_from_meta(array $meta) {
    $timezone_key = eventon_apify_get_meta_text($meta, '_evo_tz');

    if ($timezone_key !== '' && eventon_apify_is_valid_timezone($timezone_key)) {
        return $timezone_key;
    }

    $wp_timezone = wp_timezone_string();
    return $wp_timezone !== '' ? $wp_timezone : 'UTC';
}

/**
 * Return true when a saved meta flag is yes.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_yes_no_flag(array $meta, $key) {
    return eventon_apify_is_yes(eventon_apify_get_meta_text($meta, $key));
}

/**
 * Format timestamps in the provided timezone.
 */
function eventon_apify_format_timestamp_for_timezone($timestamp, $timezone_key, $format) {
    if (!$timestamp) {
        return '';
    }

    try {
        $timezone = new DateTimeZone(eventon_apify_is_valid_timezone($timezone_key) ? $timezone_key : 'UTC');
    } catch (Exception $exception) {
        $timezone = new DateTimeZone('UTC');
    }

    return wp_date($format, $timestamp, $timezone);
}

/**
 * Normalize EventON color storage to an API-friendly format.
 */
function eventon_apify_format_color_output($value) {
    $value = ltrim(trim((string) $value), '#');

    return $value !== '' ? '#' . $value : '';
}

/**
 * Read EventON event status from meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_event_status_from_meta(array $meta) {
    $status = eventon_apify_get_meta_text($meta, '_status');

    return $status !== '' ? $status : 'scheduled';
}

/**
 * Read the current EventON status reason from meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_event_status_reason_from_meta(array $meta, $event_status) {
    if ($event_status === '') {
        return '';
    }

    return eventon_apify_get_meta_text($meta, '_' . $event_status . '_reason');
}

/**
 * Read EventON attendance mode from meta, respecting moved-online status.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 */
function eventon_apify_get_attendance_mode_from_meta(array $meta, $event_status = '') {
    if ($event_status === 'movedonline') {
        return 'online';
    }

    $attendance_mode = eventon_apify_get_meta_text($meta, '_attendance_mode');

    return $attendance_mode !== '' ? $attendance_mode : 'offline';
}

/**
 * Build the location payload from EventON taxonomies.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_location_payload($post_id, array $meta) {
    $terms = wp_get_post_terms($post_id, 'event_location');
    $location = array(
        'term_id' => 0,
        'name' => '',
        'slug' => '',
        'archive_url' => '',
        'type' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'country' => '',
        'zip' => '',
        'lat' => '',
        'lon' => '',
        'latlng' => '',
        'link' => '',
        'link_target' => false,
        'phone' => '',
        'email' => '',
        'description' => '',
        'use_latlng_for_directions' => false,
        'map_enabled' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_gen'),
        'open_google_maps_link' => eventon_apify_get_yes_no_flag($meta, 'evcal_gmap_link'),
        'map_url' => '',
    );

    if ($terms && !is_wp_error($terms)) {
        $term = $terms[0];
        $term_meta = eventon_apify_get_term_meta_payload('event_location', $term->term_id);

        $location['term_id'] = (int) $term->term_id;
        $location['name'] = $term->name;
        $location['slug'] = $term->slug;
        $archive_url = get_term_link($term, 'event_location');
        $location['archive_url'] = is_wp_error($archive_url) ? '' : $archive_url;
        $location['type'] = $term_meta['location_type'] ?? '';
        $location['address'] = $term_meta['location_address'] ?? '';
        $location['city'] = $term_meta['location_city'] ?? '';
        $location['state'] = $term_meta['location_state'] ?? '';
        $location['country'] = $term_meta['location_country'] ?? '';
        $location['zip'] = $term_meta['location_zip'] ?? '';
        $location['lat'] = $term_meta['location_lat'] ?? '';
        $location['lon'] = $term_meta['location_lon'] ?? '';
        $location['link'] = $term_meta['location_link'] ?? ($term_meta['evcal_location_link'] ?? '');
        $location['link_target'] = eventon_apify_is_yes($term_meta['evcal_location_link_target'] ?? '');
        $location['phone'] = $term_meta['loc_phone'] ?? '';
        $location['email'] = $term_meta['loc_email'] ?? '';
        $location['use_latlng_for_directions'] = eventon_apify_is_yes($term_meta['location_getdir_latlng'] ?? '');
        $location['description'] = $term->description;
    } else {
        // Fallback for events created before this compatibility pass.
        $location['name'] = eventon_apify_get_meta_text($meta, 'evcal_location_name_t');
        $location['address'] = eventon_apify_get_meta_text($meta, 'evcal_location_addr');
        $location['city'] = eventon_apify_get_meta_text($meta, 'evcal_location_city');
        $location['state'] = eventon_apify_get_meta_text($meta, 'evcal_location_state');
        $location['country'] = eventon_apify_get_meta_text($meta, 'evcal_location_country');
    }

    if ($location['lat'] !== '' && $location['lon'] !== '') {
        $location['latlng'] = $location['lat'] . ',' . $location['lon'];
    }

    $location['map_url'] = eventon_apify_build_google_maps_url(
        $location['address'],
        $location['lat'],
        $location['lon']
    );

    return $location;
}

/**
 * Build organizer payload from EventON organizer terms.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_organizer_payload($post_id, array $meta) {
    $terms = wp_get_post_terms($post_id, 'event_organizer');
    $organizers = array();

    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $term_meta = eventon_apify_get_term_meta_payload('event_organizer', $term->term_id);
            $archive_url = get_term_link($term, 'event_organizer');

            $organizers[] = array(
                'term_id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'archive_url' => is_wp_error($archive_url) ? '' : $archive_url,
                'description' => $term->description,
                'contact' => $term_meta['evcal_org_contact'] ?? '',
                'email' => $term_meta['evcal_org_contact_e'] ?? '',
                'phone' => $term_meta['evcal_org_contact_phone'] ?? '',
                'address' => $term_meta['evcal_org_address'] ?? '',
                'link' => $term_meta['evcal_org_exlink'] ?? '',
                'link_target' => eventon_apify_is_yes($term_meta['_evocal_org_exlink_target'] ?? ''),
                'excerpt' => $term_meta['excerpt'] ?? '',
            );
        }
    } elseif (eventon_apify_get_meta_text($meta, 'evcal_organizer_name') !== '') {
        $organizers[] = array(
            'term_id' => 0,
            'name' => eventon_apify_get_meta_text($meta, 'evcal_organizer_name'),
            'slug' => '',
            'archive_url' => '',
            'description' => '',
            'contact' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'link' => '',
            'link_target' => false,
            'excerpt' => '',
        );
    }

    return $organizers;
}

/**
 * Build virtual event payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_virtual_payload(array $meta, $timezone_key, $virtual_end_timestamp) {
    return array(
        'enabled' => eventon_apify_get_yes_no_flag($meta, '_virtual'),
        'type' => eventon_apify_get_meta_text($meta, '_virtual_type'),
        'url' => eventon_apify_get_meta_text($meta, '_vir_url'),
        'password' => eventon_apify_get_meta_text($meta, '_vir_pass'),
        'embed' => eventon_apify_get_meta_text($meta, '_vir_embed'),
        'other' => eventon_apify_get_meta_text($meta, '_vir_other'),
        'show' => eventon_apify_get_meta_text($meta, '_vir_show'),
        'hide_when_live' => eventon_apify_get_yes_no_flag($meta, '_vir_hide'),
        'disable_redirect_hiding' => eventon_apify_get_yes_no_flag($meta, '_vir_nohiding'),
        'moderator_id' => eventon_apify_get_meta_int($meta, '_mod'),
        'end_time_enabled' => eventon_apify_get_yes_no_flag($meta, '_evo_virtual_endtime'),
        'end_timestamp' => $virtual_end_timestamp,
        'end_at' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'c') : '',
        'end_date' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'Y-m-d') : '',
        'end_time' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'H:i') : '',
        'after_content' => eventon_apify_get_meta_text($meta, '_vir_after_content'),
        'after_content_when' => eventon_apify_get_meta_text($meta, '_vir_after_content_when'),
    );
}

/**
 * Build repeat payload.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_repeat_payload(array $meta, $timezone_key) {
    $intervals = maybe_unserialize($meta['repeat_intervals'][0] ?? array());
    $items = array();

    if (is_array($intervals)) {
        foreach ($intervals as $index => $interval) {
            if (!is_array($interval) || count($interval) < 2) {
                continue;
            }

            $items[] = array(
                'index' => (int) $index,
                'start_timestamp' => (int) $interval[0],
                'start_at' => eventon_apify_format_timestamp_for_timezone((int) $interval[0], $timezone_key, 'c'),
                'end_timestamp' => (int) $interval[1],
                'end_at' => eventon_apify_format_timestamp_for_timezone((int) $interval[1], $timezone_key, 'c'),
            );
        }
    }

    return array(
        'enabled' => eventon_apify_get_yes_no_flag($meta, 'evcal_repeat'),
        'frequency' => eventon_apify_get_meta_text($meta, 'evcal_rep_freq'),
        'gap' => eventon_apify_get_meta_int($meta, 'evcal_rep_gap'),
        'count' => eventon_apify_get_meta_int($meta, 'evcal_rep_num'),
        'series_visible' => eventon_apify_get_yes_no_flag($meta, '_evcal_rep_series'),
        'intervals' => $items,
    );
}

/**
 * Build EventON RSVP payload from event meta.
 *
 * @param array<string, array<int, mixed>> $meta Post meta array.
 * @return array<string, mixed>
 */
function eventon_apify_get_rsvp_payload(array $meta) {
    $repeat_capacities = maybe_unserialize($meta['ri_capacity_rs'][0] ?? array());

    if (!is_array($repeat_capacities)) {
        $repeat_capacities = array();
    }

    return array(
        'enabled' => eventon_apify_get_yes_no_flag($meta, 'evors_rsvp'),
        'show_count' => eventon_apify_get_yes_no_flag($meta, 'evors_show_rsvp'),
        'show_whos_coming' => eventon_apify_get_yes_no_flag($meta, 'evors_show_whos_coming'),
        'only_loggedin' => eventon_apify_get_yes_no_flag($meta, 'evors_only_loggedin'),
        'capacity_enabled' => eventon_apify_get_yes_no_flag($meta, 'evors_capacity'),
        'capacity_count' => eventon_apify_get_meta_int($meta, 'evors_capacity_count'),
        'capacity_show_remaining' => eventon_apify_get_yes_no_flag($meta, 'evors_capacity_show'),
        'show_bars' => eventon_apify_get_yes_no_flag($meta, 'evors_show_bars'),
        'max_active' => eventon_apify_get_yes_no_flag($meta, 'evors_max_active'),
        'max_count' => eventon_apify_get_meta_int($meta, 'evors_max_count'),
        'min_capacity_active' => eventon_apify_get_yes_no_flag($meta, 'evors_min_cap'),
        'min_count' => eventon_apify_get_meta_int($meta, 'evors_min_count'),
        'close_before_minutes' => eventon_apify_get_meta_int($meta, 'evors_close_time'),
        'additional_emails' => eventon_apify_get_meta_text($meta, 'evors_add_emails'),
        'manage_repeat_capacity' => eventon_apify_get_yes_no_flag($meta, '_manage_repeat_cap_rs'),
        'repeat_capacities' => array_map('absint', $repeat_capacities),
    );
}

/**
 * Read EventON term meta from the shared option store.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_term_meta_payload($taxonomy, $term_id) {
    if (!$term_id) {
        return array();
    }

    if (function_exists('evo_get_term_meta')) {
        $meta = evo_get_term_meta($taxonomy, $term_id, true);
        return is_array($meta) ? $meta : array();
    }

    $all_term_meta = get_option('evo_tax_meta', array());
    if (isset($all_term_meta[$taxonomy][$term_id]) && is_array($all_term_meta[$taxonomy][$term_id])) {
        return $all_term_meta[$taxonomy][$term_id];
    }

    $legacy = get_option('taxonomy_' . $term_id, array());
    return is_array($legacy) ? $legacy : array();
}

/**
 * Build a Google Maps URL from saved location details.
 */
function eventon_apify_build_google_maps_url($address, $lat = '', $lon = '') {
    if ($lat !== '' && $lon !== '') {
        return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lon);
    }

    if (trim((string) $address) !== '') {
        return 'https://www.google.com/maps?q=' . rawurlencode($address);
    }

    return '';
}

/**
 * List EventON events.
 */
function eventon_apify_get_events(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('list');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $query = new WP_Query(
        array(
            'post_type' => 'ajde_events',
            'post_status' => eventon_apify_get_requested_statuses($request->get_param('status')),
            'posts_per_page' => (int) $request->get_param('per_page'),
            'paged' => (int) $request->get_param('page'),
            'orderby' => 'meta_value_num',
            'meta_key' => 'evcal_srow',
            'order' => 'ASC',
            's' => (string) $request->get_param('search'),
        )
    );

    $events = array();
    foreach ($query->posts as $post) {
        $events[] = eventon_apify_format_event($post);
    }

    return rest_ensure_response(
        array(
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page' => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
            'events' => $events,
        )
    );
}

/**
 * Convert a CSV-like status value into allowed post statuses.
 *
 * @param mixed $status_param Raw request value.
 * @return array<int, string>
 */
function eventon_apify_get_requested_statuses($status_param) {
    $allowed = array('publish', 'draft', 'private', 'pending', 'future');
    $default = array('publish', 'draft', 'private');

    if (!is_string($status_param) || trim($status_param) === '') {
        return $default;
    }

    $statuses = array_map('trim', explode(',', $status_param));
    $statuses = array_values(array_intersect($allowed, $statuses));

    return !empty($statuses) ? $statuses : $default;
}

/**
 * Return a single EventON event.
 */
function eventon_apify_get_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('read');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $post = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($post)) {
        return $post;
    }

    return rest_ensure_response(eventon_apify_format_event($post));
}

/**
 * Create a new EventON event.
 */
function eventon_apify_create_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('create');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $params = eventon_apify_normalize_request_payload(eventon_apify_get_request_payload($request));
    $validation = eventon_apify_validate_event_payload($params, true);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $post_id = wp_insert_post(
        array(
            'post_type' => 'ajde_events',
            'post_title' => sanitize_text_field((string) $params['title']),
            'post_content' => wp_kses_post($params['description'] ?? ''),
            'post_excerpt' => sanitize_textarea_field((string) ($params['excerpt'] ?? '')),
            'post_status' => eventon_apify_get_sanitized_status($params['status'] ?? 'draft'),
        ),
        true
    );

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $meta_result = eventon_apify_save_event_meta($post_id, $params);
    if (is_wp_error($meta_result)) {
        wp_delete_post($post_id, true);
        return $meta_result;
    }

    $term_result = eventon_apify_save_event_terms($post_id, $params);
    if (is_wp_error($term_result)) {
        wp_delete_post($post_id, true);
        return $term_result;
    }

    $response = rest_ensure_response(eventon_apify_format_event(get_post($post_id)));
    $response->set_status(201);

    return $response;
}

/**
 * Update an existing EventON event.
 */
function eventon_apify_update_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('update');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $post = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($post)) {
        return $post;
    }

    $params = eventon_apify_normalize_request_payload(eventon_apify_get_request_payload($request));
    $validation = eventon_apify_validate_event_payload($params, false, $post->ID);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $updates = array('ID' => $post->ID);

    if (array_key_exists('title', $params)) {
        $updates['post_title'] = sanitize_text_field((string) $params['title']);
    }

    if (array_key_exists('description', $params)) {
        $updates['post_content'] = wp_kses_post((string) $params['description']);
    }

    if (array_key_exists('excerpt', $params)) {
        $updates['post_excerpt'] = sanitize_textarea_field((string) $params['excerpt']);
    }

    if (array_key_exists('status', $params)) {
        $updates['post_status'] = eventon_apify_get_sanitized_status($params['status']);
    }

    if (count($updates) > 1) {
        $result = wp_update_post($updates, true);
        if (is_wp_error($result)) {
            return $result;
        }
    }

    $meta_result = eventon_apify_save_event_meta($post->ID, $params);
    if (is_wp_error($meta_result)) {
        return $meta_result;
    }

    $term_result = eventon_apify_save_event_terms($post->ID, $params);
    if (is_wp_error($term_result)) {
        return $term_result;
    }

    return rest_ensure_response(eventon_apify_format_event(get_post($post->ID)));
}

/**
 * Trash an EventON event.
 */
function eventon_apify_delete_event(WP_REST_Request $request) {
    $ready = eventon_apify_assert_api_capability_is_ready('delete');
    if (is_wp_error($ready)) {
        return $ready;
    }

    $post = eventon_apify_get_event_post((int) $request->get_param('id'));
    if (is_wp_error($post)) {
        return $post;
    }

    $deleted = wp_trash_post($post->ID);
    if (!$deleted) {
        return new WP_Error(
            'eventon_apify_delete_failed',
            'The event could not be moved to the trash.',
            array('status' => 500)
        );
    }

    return rest_ensure_response(
        array(
            'deleted' => true,
            'id' => $post->ID,
            'title' => $post->post_title,
        )
    );
}

/**
 * Retrieve an EventON event post or return a 404 error.
 *
 * @return WP_Post|WP_Error
 */
function eventon_apify_get_event_post($post_id) {
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'ajde_events') {
        return new WP_Error(
            'eventon_apify_not_found',
            'Event not found.',
            array('status' => 404)
        );
    }

    return $post;
}

/**
 * Fetch JSON/body parameters as an array.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_request_payload(WP_REST_Request $request) {
    $params = $request->get_json_params();

    if (!is_array($params)) {
        $params = $request->get_body_params();
    }

    return is_array($params) ? $params : array();
}

/**
 * Normalize flexible API input into the canonical keys used internally.
 *
 * @param array<string, mixed> $params Raw request payload.
 * @return array<string, mixed>
 */
function eventon_apify_normalize_request_payload(array $params) {
    $normalized = $params;

    if (!array_key_exists('description', $normalized) && array_key_exists('content', $params)) {
        $normalized['description'] = $params['content'];
    }

    if (!array_key_exists('excerpt', $normalized) && array_key_exists('post_excerpt', $params)) {
        $normalized['excerpt'] = $params['post_excerpt'];
    }

    if (!array_key_exists('event_subtitle', $normalized) && array_key_exists('subtitle', $params)) {
        $normalized['event_subtitle'] = $params['subtitle'];
    }

    if (!array_key_exists('event_excerpt', $normalized) && array_key_exists('evo_excerpt', $params)) {
        $normalized['event_excerpt'] = $params['evo_excerpt'];
    }

    if (!array_key_exists('event_type', $normalized) && array_key_exists('event_types', $params)) {
        $normalized['event_type'] = $params['event_types'];
    }

    if (!array_key_exists('event_status', $normalized) && array_key_exists('_status', $params)) {
        $normalized['event_status'] = $params['_status'];
    }

    if (array_key_exists('start_at', $params) && !array_key_exists('start_date', $normalized)) {
        $normalized = eventon_apify_apply_datetime_parts_to_payload($normalized, 'start', $params['start_at']);
    }

    if (array_key_exists('end_at', $params) && !array_key_exists('end_date', $normalized)) {
        $normalized = eventon_apify_apply_datetime_parts_to_payload($normalized, 'end', $params['end_at']);
    }

    if (array_key_exists('timezone', $params)) {
        if (is_array($params['timezone'])) {
            if (!array_key_exists('timezone_key', $normalized)) {
                $normalized['timezone_key'] = eventon_apify_array_get($params['timezone'], array('key', 'timezone', 'id', 'value'), '');
            }

            if (!array_key_exists('timezone_text', $normalized)) {
                $normalized['timezone_text'] = eventon_apify_array_get($params['timezone'], array('text', 'label', 'display'), '');
            }
        } elseif (!array_key_exists('timezone_key', $normalized)) {
            $normalized['timezone_key'] = $params['timezone'];
        }
    }

    if (isset($params['eventon']) && is_array($params['eventon'])) {
        $eventon_map = array(
            'event_subtitle' => array('event_subtitle', 'subtitle'),
            'event_excerpt' => array('event_excerpt', 'excerpt'),
            'event_color' => array('event_color', 'color'),
            'event_color_secondary' => array('event_color_secondary', 'secondary_color', 'color_secondary'),
            'gradient_enabled' => array('gradient_enabled'),
            'gradient_angle' => array('gradient_angle'),
            'learn_more_link' => array('learn_more_link', 'link'),
            'learn_more_link_target' => array('learn_more_link_target', 'link_target'),
            'event_status' => array('event_status', 'status'),
            'status_reason' => array('status_reason', 'reason'),
            'attendance_mode' => array('attendance_mode'),
            'time_extend_type' => array('time_extend_type', 'extend_type'),
        );

        foreach ($eventon_map as $target => $aliases) {
            if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($params['eventon'], $aliases)) {
                $normalized[$target] = eventon_apify_array_get($params['eventon'], $aliases);
            }
        }
    }

    if (isset($params['flags']) && is_array($params['flags'])) {
        $flags_map = array(
            'featured' => array('featured'),
            'completed' => array('completed'),
            'exclude_from_calendar' => array('exclude_from_calendar'),
            'loggedin_only' => array('loggedin_only'),
            'hide_end_time' => array('hide_end_time'),
            'span_hidden_end' => array('span_hidden_end'),
            'hide_location_name' => array('hide_location_name'),
            'generate_gmap' => array('generate_gmap', 'map_enabled'),
            'open_google_maps_link' => array('open_google_maps_link'),
            'location_access_loggedin_only' => array('location_access_loggedin_only'),
            'location_info_over_image' => array('location_info_over_image'),
        );

        foreach ($flags_map as $target => $aliases) {
            if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($params['flags'], $aliases)) {
                $normalized[$target] = eventon_apify_array_get($params['flags'], $aliases);
            }
        }
    }

    if (array_key_exists('location', $params)) {
        if (is_string($params['location'])) {
            $normalized['location_name'] = $params['location'];
        } elseif (is_array($params['location'])) {
            $location_map = array(
                'location_term_id' => array('term_id', 'id'),
                'location_name' => array('name', 'title'),
                'location_slug' => array('slug'),
                'location_description' => array('description'),
                'location_type' => array('type'),
                'location_address' => array('address', 'location_address'),
                'location_city' => array('city', 'location_city'),
                'location_state' => array('state', 'location_state'),
                'location_country' => array('country', 'location_country'),
                'location_zip' => array('zip', 'location_zip'),
                'location_lat' => array('lat', 'latitude', 'location_lat'),
                'location_lon' => array('lon', 'lng', 'longitude', 'location_lon'),
                'location_link' => array('link', 'location_link', 'map_url'),
                'location_link_target' => array('link_target'),
                'location_phone' => array('phone', 'loc_phone'),
                'location_email' => array('email', 'loc_email'),
                'location_getdir_latlng' => array('use_latlng_for_directions', 'location_getdir_latlng'),
            );

            foreach ($location_map as $target => $aliases) {
                if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($params['location'], $aliases)) {
                    $normalized[$target] = eventon_apify_array_get($params['location'], $aliases);
                }
            }
        }
    }

    if (!array_key_exists('location_link', $normalized) && array_key_exists('map_url', $params)) {
        $normalized['location_link'] = $params['map_url'];
    }

    if (!array_key_exists('location_lat', $normalized) && array_key_exists('lat', $params)) {
        $normalized['location_lat'] = $params['lat'];
    }

    if (!array_key_exists('location_lon', $normalized) && eventon_apify_array_has_any($params, array('lon', 'lng'))) {
        $normalized['location_lon'] = eventon_apify_array_get($params, array('lon', 'lng'));
    }

    if (array_key_exists('organizers', $params)) {
        $normalized['organizers'] = eventon_apify_normalize_organizer_items($params['organizers']);
    } elseif (array_key_exists('organizer', $params)) {
        $normalized['organizers'] = eventon_apify_normalize_organizer_items($params['organizer']);
    }

    if (isset($params['virtual']) && is_array($params['virtual'])) {
        $virtual_map = array(
            'virtual_enabled' => array('enabled'),
            'virtual_type' => array('type'),
            'virtual_url' => array('url'),
            'virtual_password' => array('password'),
            'virtual_embed' => array('embed'),
            'virtual_other' => array('other'),
            'virtual_show' => array('show'),
            'virtual_hide_when_live' => array('hide_when_live'),
            'virtual_disable_redirect_hiding' => array('disable_redirect_hiding'),
            'virtual_after_content' => array('after_content'),
            'virtual_after_content_when' => array('after_content_when'),
            'virtual_moderator_id' => array('moderator_id'),
            'virtual_end_enabled' => array('end_time_enabled', 'end_enabled'),
        );

        foreach ($virtual_map as $target => $aliases) {
            if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($params['virtual'], $aliases)) {
                $normalized[$target] = eventon_apify_array_get($params['virtual'], $aliases);
            }
        }

        if (eventon_apify_array_has_any($params['virtual'], array('end_at')) && !array_key_exists('virtual_end_date', $normalized)) {
            $normalized = eventon_apify_apply_datetime_parts_to_payload($normalized, 'virtual_end', $params['virtual']['end_at']);
        }

        if (!array_key_exists('virtual_end_date', $normalized) && eventon_apify_array_has_any($params['virtual'], array('end_date'))) {
            $normalized['virtual_end_date'] = eventon_apify_array_get($params['virtual'], array('end_date'));
        }

        if (!array_key_exists('virtual_end_time', $normalized) && eventon_apify_array_has_any($params['virtual'], array('end_time'))) {
            $normalized['virtual_end_time'] = eventon_apify_array_get($params['virtual'], array('end_time'));
        }
    }

    if (isset($params['repeat']) && is_array($params['repeat'])) {
        $repeat_map = array(
            'repeat_enabled' => array('enabled'),
            'repeat_frequency' => array('frequency', 'type'),
            'repeat_gap' => array('gap'),
            'repeat_count' => array('count', 'number'),
            'repeat_series_visible' => array('series_visible'),
            'repeat_intervals' => array('intervals'),
        );

        foreach ($repeat_map as $target => $aliases) {
            if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($params['repeat'], $aliases)) {
                $normalized[$target] = eventon_apify_array_get($params['repeat'], $aliases);
            }
        }
    }

    if (isset($params['rsvp']) && is_array($params['rsvp'])) {
        $rsvp_map = array(
            'rsvp_enabled' => array('enabled'),
            'rsvp_show_count' => array('show_count'),
            'rsvp_show_whos_coming' => array('show_whos_coming'),
            'rsvp_only_loggedin' => array('only_loggedin'),
            'rsvp_capacity_enabled' => array('capacity_enabled'),
            'rsvp_capacity_count' => array('capacity_count'),
            'rsvp_capacity_show_remaining' => array('capacity_show_remaining'),
            'rsvp_show_bars' => array('show_bars'),
            'rsvp_max_active' => array('max_active'),
            'rsvp_max_count' => array('max_count'),
            'rsvp_min_capacity_active' => array('min_capacity_active'),
            'rsvp_min_count' => array('min_count'),
            'rsvp_close_before_minutes' => array('close_before_minutes'),
            'rsvp_additional_emails' => array('additional_emails'),
            'rsvp_manage_repeat_capacity' => array('manage_repeat_capacity'),
            'rsvp_repeat_capacities' => array('repeat_capacities'),
        );

        foreach ($rsvp_map as $target => $aliases) {
            if (!array_key_exists($target, $normalized) && eventon_apify_array_has_any($params['rsvp'], $aliases)) {
                $normalized[$target] = eventon_apify_array_get($params['rsvp'], $aliases);
            }
        }
    }

    return $normalized;
}

/**
 * Apply an ISO-like datetime string to date/time payload keys.
 *
 * @param array<string, mixed> $payload Existing payload.
 * @return array<string, mixed>
 */
function eventon_apify_apply_datetime_parts_to_payload(array $payload, $prefix, $value) {
    $parts = eventon_apify_extract_datetime_parts($value);

    if (!$parts) {
        return $payload;
    }

    $payload[$prefix . '_date'] = $parts['date'];
    $payload[$prefix . '_time'] = $parts['time'];

    if (!array_key_exists('timezone_key', $payload) && $parts['timezone'] !== '' && eventon_apify_is_valid_timezone($parts['timezone'])) {
        $payload['timezone_key'] = $parts['timezone'];
    }

    return $payload;
}

/**
 * Parse datetime strings commonly returned by the API.
 *
 * @return array<string, string>|null
 */
function eventon_apify_extract_datetime_parts($value) {
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        $datetime = new DateTimeImmutable($value);
    } catch (Exception $exception) {
        return null;
    }

    return array(
        'date' => $datetime->format('Y-m-d'),
        'time' => $datetime->format('H:i'),
        'timezone' => $datetime->getTimezone()->getName(),
    );
}

/**
 * Normalize organizer inputs into a consistent array of organizer objects.
 *
 * @param mixed $value Organizer input value.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_normalize_organizer_items($value) {
    if ($value === null || $value === '') {
        return array();
    }

    if (is_string($value) || is_numeric($value)) {
        return array(eventon_apify_normalize_organizer_item($value));
    }

    if (!is_array($value)) {
        return array();
    }

    if (eventon_apify_array_has_any($value, array('term_id', 'id', 'name', 'title', 'email', 'contact', 'phone'))) {
        return array(eventon_apify_normalize_organizer_item($value));
    }

    $items = array();
    foreach ($value as $item) {
        $items[] = eventon_apify_normalize_organizer_item($item);
    }

    return $items;
}

/**
 * Normalize one organizer item into a keyed array.
 *
 * @param mixed $value Organizer input value.
 * @return array<string, mixed>
 */
function eventon_apify_normalize_organizer_item($value) {
    if (is_string($value) || is_numeric($value)) {
        return array(
            'name' => (string) $value,
        );
    }

    if (!is_array($value)) {
        return array();
    }

    $normalized = array();
    $field_map = array(
        'term_id' => array('term_id', 'id'),
        'name' => array('name', 'title'),
        'slug' => array('slug'),
        'description' => array('description'),
        'contact' => array('contact', 'organizer_contact'),
        'email' => array('email', 'contact_email'),
        'phone' => array('phone', 'contact_phone'),
        'address' => array('address', 'organizer_address'),
        'link' => array('link', 'organizer_link'),
        'link_target' => array('link_target', 'organizer_link_target'),
        'excerpt' => array('excerpt'),
    );

    foreach ($field_map as $target => $aliases) {
        if (eventon_apify_array_has_any($value, $aliases)) {
            $normalized[$target] = eventon_apify_array_get($value, $aliases);
        }
    }

    return $normalized;
}

/**
 * Validate request payload fields before create/update.
 *
 * @param array<string, mixed> $params    Request body parameters.
 * @param bool                 $is_create Whether this is a create request.
 * @param int                  $post_id   Existing post ID for updates.
 * @return true|WP_Error
 */
function eventon_apify_validate_event_payload(array $params, $is_create, $post_id = 0) {
    if ($is_create && empty($params['title'])) {
        return new WP_Error(
            'eventon_apify_missing_title',
            'Event title is required.',
            array('status' => 400)
        );
    }

    if (array_key_exists('title', $params) && trim((string) $params['title']) === '') {
        return new WP_Error(
            'eventon_apify_invalid_title',
            'title cannot be empty.',
            array('status' => 400)
        );
    }

    if ($is_create && empty($params['start_date'])) {
        return new WP_Error(
            'eventon_apify_missing_start_date',
            'start_date is required when creating an EventON event.',
            array('status' => 400)
        );
    }

    if (array_key_exists('status', $params) && eventon_apify_get_sanitized_status($params['status']) === '') {
        return new WP_Error(
            'eventon_apify_invalid_status',
            'status must be one of: publish, draft, private, pending, future.',
            array('status' => 400)
        );
    }

    foreach (array('event_color', 'event_color_secondary') as $color_key) {
        if (array_key_exists($color_key, $params) && eventon_apify_normalize_color_input($params[$color_key]) === null) {
            return new WP_Error(
                'eventon_apify_invalid_color',
                $color_key . ' must be a valid hex color such as #ff0000 or ff0000.',
                array('status' => 400)
            );
        }
    }

    if (array_key_exists('event_status', $params) && !in_array(sanitize_key((string) $params['event_status']), eventon_apify_get_allowed_event_statuses(), true)) {
        return new WP_Error(
            'eventon_apify_invalid_event_status',
            'event_status must be one of: ' . implode(', ', eventon_apify_get_allowed_event_statuses()) . '.',
            array('status' => 400)
        );
    }

    if (array_key_exists('attendance_mode', $params) && !in_array(sanitize_key((string) $params['attendance_mode']), eventon_apify_get_allowed_attendance_modes(), true)) {
        return new WP_Error(
            'eventon_apify_invalid_attendance_mode',
            'attendance_mode must be one of: offline, online, mixed.',
            array('status' => 400)
        );
    }

    if (array_key_exists('time_extend_type', $params) && !in_array(sanitize_key((string) $params['time_extend_type']), array('n', 'dl', 'ml', 'yl'), true)) {
        return new WP_Error(
            'eventon_apify_invalid_time_extend_type',
            'time_extend_type must be one of: n, dl, ml, yl.',
            array('status' => 400)
        );
    }

    if (array_key_exists('timezone_key', $params) && trim((string) $params['timezone_key']) !== '' && !eventon_apify_is_valid_timezone((string) $params['timezone_key'])) {
        return new WP_Error(
            'eventon_apify_invalid_timezone',
            'timezone_key must be a valid PHP timezone identifier.',
            array('status' => 400)
        );
    }

    if (array_key_exists('gradient_angle', $params) && trim((string) $params['gradient_angle']) !== '' && !is_numeric($params['gradient_angle'])) {
        return new WP_Error(
            'eventon_apify_invalid_gradient_angle',
            'gradient_angle must be numeric.',
            array('status' => 400)
        );
    }

    foreach (array('location_lat', 'location_lon') as $coord_key) {
        if (array_key_exists($coord_key, $params) && trim((string) $params[$coord_key]) !== '' && !is_numeric($params[$coord_key])) {
            return new WP_Error(
                'eventon_apify_invalid_location_coordinate',
                $coord_key . ' must be numeric.',
                array('status' => 400)
            );
        }
    }

    foreach (array('learn_more_link', 'location_link', 'virtual_url') as $url_key) {
        if (array_key_exists($url_key, $params) && !eventon_apify_validate_url($params[$url_key])) {
            return new WP_Error(
                'eventon_apify_invalid_url',
                $url_key . ' must be a valid absolute URL.',
                array('status' => 400)
            );
        }
    }

    if (array_key_exists('organizers', $params) && is_array($params['organizers'])) {
        foreach ($params['organizers'] as $organizer) {
            if (!is_array($organizer)) {
                continue;
            }

            if (isset($organizer['link']) && !eventon_apify_validate_url($organizer['link'])) {
                return new WP_Error(
                    'eventon_apify_invalid_organizer_url',
                    'organizer.link must be a valid absolute URL.',
                    array('status' => 400)
                );
            }
        }
    }

    if (array_key_exists('repeat_frequency', $params) && trim((string) $params['repeat_frequency']) !== '' && !in_array(sanitize_key((string) $params['repeat_frequency']), eventon_apify_get_allowed_repeat_frequencies(), true)) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_frequency',
            'repeat.frequency must be one of: ' . implode(', ', eventon_apify_get_allowed_repeat_frequencies()) . '.',
            array('status' => 400)
        );
    }

    if (array_key_exists('repeat_intervals', $params)) {
        $repeat_timezone_state = eventon_apify_resolve_datetime_inputs($params, $post_id);
        $intervals = eventon_apify_normalize_repeat_intervals_input(
            $params['repeat_intervals'],
            (string) ($repeat_timezone_state['timezone_key'] ?? '')
        );

        if (is_wp_error($intervals)) {
            return $intervals;
        }
    }

    $datetime_validation = eventon_apify_validate_datetime_fields($params, $post_id);
    if (is_wp_error($datetime_validation)) {
        return $datetime_validation;
    }

    return true;
}

/**
 * Validate date/time combinations before saving.
 *
 * @param array<string, mixed> $params  Request body parameters.
 * @param int                  $post_id Existing post ID when updating.
 * @return true|WP_Error
 */
function eventon_apify_validate_datetime_fields(array $params, $post_id = 0) {
    $state = eventon_apify_resolve_datetime_inputs($params, $post_id);
    $timezone_key = $state['timezone_key'];

    if ($state['start_date'] === '') {
        return new WP_Error(
            'eventon_apify_missing_start_date',
            'start_date is required for EventON events.',
            array('status' => 400)
        );
    }

    if ($state['start_time'] !== '' && !eventon_apify_split_time_string($state['start_time'])) {
        return new WP_Error(
            'eventon_apify_invalid_start_time',
            'start_time must use HH:MM in 24-hour format.',
            array('status' => 400)
        );
    }

    $start_timestamp = eventon_apify_build_timestamp($state['start_date'], $state['start_time'], $timezone_key);
    if ($start_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_start_datetime',
            'The start_date/start_time combination could not be parsed.',
            array('status' => 400)
        );
    }

    if ($state['end_time'] !== '' && !eventon_apify_split_time_string($state['end_time'])) {
        return new WP_Error(
            'eventon_apify_invalid_end_time',
            'end_time must use HH:MM in 24-hour format.',
            array('status' => 400)
        );
    }

    $end_timestamp = eventon_apify_build_timestamp($state['end_date'], $state['end_time'], $timezone_key);
    if ($end_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_end_datetime',
            'The end_date/end_time combination could not be parsed.',
            array('status' => 400)
        );
    }

    if ($end_timestamp < $start_timestamp) {
        return new WP_Error(
            'eventon_apify_invalid_datetime_range',
            'The end date/time must be on or after the start date/time.',
            array('status' => 400)
        );
    }

    if ($state['virtual_end_enabled']) {
        if ($state['virtual_end_date'] === '') {
            return new WP_Error(
                'eventon_apify_missing_virtual_end',
                'virtual.end_date is required when virtual end time is enabled.',
                array('status' => 400)
            );
        }

        if ($state['virtual_end_time'] !== '' && !eventon_apify_split_time_string($state['virtual_end_time'])) {
            return new WP_Error(
                'eventon_apify_invalid_virtual_end_time',
                'virtual.end_time must use HH:MM in 24-hour format.',
                array('status' => 400)
            );
        }

        $virtual_end_timestamp = eventon_apify_build_timestamp($state['virtual_end_date'], $state['virtual_end_time'], $timezone_key);
        if ($virtual_end_timestamp === null) {
            return new WP_Error(
                'eventon_apify_invalid_virtual_end_datetime',
                'The virtual end date/time could not be parsed.',
                array('status' => 400)
            );
        }
    }

    return true;
}

/**
 * Build the merged datetime state for a create/update request.
 *
 * @param array<string, mixed> $params Request parameters.
 * @return array<string, mixed>
 */
function eventon_apify_resolve_datetime_inputs(array $params, $post_id = 0) {
    $state = $post_id ? eventon_apify_get_existing_datetime_state($post_id) : array(
        'start_date' => '',
        'start_time' => '',
        'end_date' => '',
        'end_time' => '',
        'virtual_end_date' => '',
        'virtual_end_time' => '',
        'timezone_key' => wp_timezone_string() ?: 'UTC',
        'time_extend_type' => 'n',
        'hide_end_time' => false,
        'span_hidden_end' => false,
        'virtual_end_enabled' => false,
    );

    foreach (array('start_date', 'start_time', 'end_date', 'end_time', 'virtual_end_date', 'virtual_end_time') as $field) {
        if (array_key_exists($field, $params)) {
            $state[$field] = sanitize_text_field((string) $params[$field]);
        }
    }

    if (array_key_exists('timezone_key', $params) && trim((string) $params['timezone_key']) !== '') {
        $state['timezone_key'] = sanitize_text_field((string) $params['timezone_key']);
    }

    if (array_key_exists('time_extend_type', $params)) {
        $state['time_extend_type'] = sanitize_key((string) $params['time_extend_type']);
    }

    foreach (array('hide_end_time', 'span_hidden_end', 'virtual_end_enabled') as $flag) {
        if (array_key_exists($flag, $params)) {
            $state[$flag] = eventon_apify_is_yes($params[$flag]);
        }
    }

    if ($state['end_date'] === '' && $state['start_date'] !== '') {
        $state['end_date'] = $state['start_date'];
    }

    if ($state['start_time'] === '') {
        $state['start_time'] = '00:00';
    }

    if ($state['end_time'] === '' && $state['start_time'] !== '') {
        $state['end_time'] = $state['start_time'];
    }

    if ($state['hide_end_time'] && !$state['span_hidden_end']) {
        $state['end_date'] = $state['start_date'];
        $state['end_time'] = '23:59';
    }

    if ($state['virtual_end_enabled'] && $state['virtual_end_date'] === '') {
        $state['virtual_end_date'] = $state['end_date'];
        $state['virtual_end_time'] = $state['end_time'];
    }

    return $state;
}

/**
 * Read existing datetime configuration from EventON meta.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_existing_datetime_state($post_id) {
    $meta = get_post_meta($post_id);
    $timezone_key = eventon_apify_get_timezone_key_from_meta($meta);
    $start_timestamp = eventon_apify_get_meta_int($meta, '_unix_start_ev') ?: eventon_apify_get_meta_int($meta, 'evcal_srow');
    $end_timestamp = eventon_apify_get_meta_int($meta, '_unix_end_ev') ?: eventon_apify_get_meta_int($meta, 'evcal_erow');
    $virtual_end_timestamp = eventon_apify_get_meta_int($meta, '_unix_vend_ev') ?: eventon_apify_get_meta_int($meta, '_evo_virtual_erow');

    return array(
        'start_date' => $start_timestamp ? eventon_apify_format_timestamp_for_timezone($start_timestamp, $timezone_key, 'Y-m-d') : '',
        'start_time' => $start_timestamp ? eventon_apify_format_timestamp_for_timezone($start_timestamp, $timezone_key, 'H:i') : '',
        'end_date' => $end_timestamp ? eventon_apify_format_timestamp_for_timezone($end_timestamp, $timezone_key, 'Y-m-d') : '',
        'end_time' => $end_timestamp ? eventon_apify_format_timestamp_for_timezone($end_timestamp, $timezone_key, 'H:i') : '',
        'virtual_end_date' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'Y-m-d') : '',
        'virtual_end_time' => $virtual_end_timestamp ? eventon_apify_format_timestamp_for_timezone($virtual_end_timestamp, $timezone_key, 'H:i') : '',
        'timezone_key' => $timezone_key,
        'time_extend_type' => eventon_apify_get_meta_text($meta, '_time_ext_type') ?: 'n',
        'hide_end_time' => eventon_apify_get_yes_no_flag($meta, 'evo_hide_endtime'),
        'span_hidden_end' => eventon_apify_get_yes_no_flag($meta, 'evo_span_hidden_end'),
        'virtual_end_enabled' => eventon_apify_get_yes_no_flag($meta, '_evo_virtual_endtime'),
    );
}

/**
 * Save EventON meta fields from request parameters.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request body parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_event_meta($post_id, array $params) {
    $datetime_result = eventon_apify_save_datetime_meta($post_id, $params);
    if (is_wp_error($datetime_result)) {
        return $datetime_result;
    }

    $text_meta_map = array(
        'event_subtitle' => 'evcal_subtitle',
        'event_excerpt' => 'evo_excerpt',
        'timezone_text' => 'evo_event_timezone',
        'learn_more_link' => 'evcal_lmlink',
        'virtual_type' => '_virtual_type',
        'virtual_password' => '_vir_pass',
        'virtual_other' => '_vir_other',
        'virtual_show' => '_vir_show',
        'virtual_after_content_when' => '_vir_after_content_when',
    );

    foreach ($text_meta_map as $request_key => $meta_key) {
        if (!array_key_exists($request_key, $params)) {
            continue;
        }

        $value = (string) $params[$request_key];
        if ($request_key === 'learn_more_link') {
            $value = esc_url_raw($value);
        } else {
            $value = sanitize_text_field($value);
        }

        eventon_apify_update_or_delete_meta($post_id, $meta_key, $value);
    }

    if (array_key_exists('event_status', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_status', sanitize_key((string) $params['event_status']));
    } elseif ('' === get_post_meta($post_id, '_status', true)) {
        update_post_meta($post_id, '_status', 'scheduled');
    }

    $current_event_status = sanitize_key((string) get_post_meta($post_id, '_status', true));
    $current_event_status = in_array($current_event_status, eventon_apify_get_allowed_event_statuses(), true) ? $current_event_status : 'scheduled';

    if (array_key_exists('status_reason', $params) || array_key_exists('event_status', $params)) {
        foreach (eventon_apify_get_allowed_event_statuses() as $status_key) {
            $reason_meta_key = '_' . $status_key . '_reason';

            if ($status_key === $current_event_status && array_key_exists('status_reason', $params) && $current_event_status !== 'scheduled') {
                eventon_apify_update_or_delete_meta($post_id, $reason_meta_key, sanitize_textarea_field((string) $params['status_reason']));
                continue;
            }

            delete_post_meta($post_id, $reason_meta_key);
        }
    }

    if (array_key_exists('attendance_mode', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_attendance_mode', sanitize_key((string) $params['attendance_mode']));
    }

    if (array_key_exists('time_extend_type', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_time_ext_type', sanitize_key((string) $params['time_extend_type']));
    }

    if (array_key_exists('timezone_key', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_evo_tz', sanitize_text_field((string) $params['timezone_key']));
    }

    if (array_key_exists('event_color', $params)) {
        $color = eventon_apify_normalize_color_input($params['event_color']);
        eventon_apify_update_or_delete_meta($post_id, 'evcal_event_color', $color ?: '');
    }

    if (array_key_exists('event_color_secondary', $params)) {
        $color = eventon_apify_normalize_color_input($params['event_color_secondary']);
        eventon_apify_update_or_delete_meta($post_id, 'evcal_event_color2', $color ?: '');
    }

    if (array_key_exists('gradient_enabled', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evo_event_grad_colors', $params['gradient_enabled']);
    }

    if (array_key_exists('gradient_angle', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_evo_event_grad_ang', sanitize_text_field((string) $params['gradient_angle']));
    }

    if (array_key_exists('learn_more_link_target', $params)) {
        eventon_apify_update_yes_no_meta($post_id, 'evcal_lmlink_target', $params['learn_more_link_target']);
    }

    $flag_meta_map = array(
        'featured' => '_featured',
        'completed' => '_completed',
        'exclude_from_calendar' => 'evo_exclude_ev',
        'loggedin_only' => '_onlyloggedin',
        'hide_end_time' => 'evo_hide_endtime',
        'span_hidden_end' => 'evo_span_hidden_end',
        'hide_location_name' => 'evcal_hide_locname',
        'generate_gmap' => 'evcal_gmap_gen',
        'open_google_maps_link' => 'evcal_gmap_link',
        'location_access_loggedin_only' => 'evo_access_control_location',
        'location_info_over_image' => 'evcal_name_over_img',
        'virtual_enabled' => '_virtual',
        'virtual_hide_when_live' => '_vir_hide',
        'virtual_disable_redirect_hiding' => '_vir_nohiding',
        'virtual_end_enabled' => '_evo_virtual_endtime',
    );

    foreach ($flag_meta_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            eventon_apify_update_yes_no_meta($post_id, $meta_key, $params[$request_key]);
        }
    }

    if (array_key_exists('virtual_url', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_vir_url', esc_url_raw((string) $params['virtual_url']));
    }

    if (array_key_exists('virtual_embed', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_vir_embed', wp_kses_post((string) $params['virtual_embed']));
    }

    if (array_key_exists('virtual_after_content', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_vir_after_content', wp_kses_post((string) $params['virtual_after_content']));
    }

    if (array_key_exists('virtual_moderator_id', $params)) {
        eventon_apify_update_or_delete_meta($post_id, '_mod', (string) absint($params['virtual_moderator_id']));
    }

    $repeat_result = eventon_apify_save_repeat_meta($post_id, $params);
    if (is_wp_error($repeat_result)) {
        return $repeat_result;
    }

    $rsvp_result = eventon_apify_save_rsvp_meta($post_id, $params);
    if (is_wp_error($rsvp_result)) {
        return $rsvp_result;
    }

    return true;
}

/**
 * Save EventON's primary date/time meta using the same conventions as the plugin itself.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_datetime_meta($post_id, array $params) {
    $state = eventon_apify_resolve_datetime_inputs($params, $post_id);
    $calculated = eventon_apify_calculate_datetime_meta($state);

    if (is_wp_error($calculated)) {
        return $calculated;
    }

    update_post_meta($post_id, 'evcal_srow', absint($calculated['unix_start']));
    update_post_meta($post_id, 'evcal_erow', absint($calculated['unix_end']));
    update_post_meta($post_id, '_unix_start_ev', absint($calculated['unix_start_ev']));
    update_post_meta($post_id, '_unix_end_ev', absint($calculated['unix_end_ev']));
    update_post_meta($post_id, '_evo_tz', sanitize_text_field((string) $state['timezone_key']));
    update_post_meta($post_id, '_time_ext_type', sanitize_key((string) $state['time_extend_type']));

    if ($state['virtual_end_enabled']) {
        update_post_meta($post_id, '_evo_virtual_erow', absint($calculated['unix_vir_end']));
        update_post_meta($post_id, '_unix_vend_ev', absint($calculated['unix_vend_ev']));
    } else {
        delete_post_meta($post_id, '_evo_virtual_erow');
        delete_post_meta($post_id, '_unix_vend_ev');
    }

    // Remove legacy non-EventON time keys from previous plugin revisions.
    delete_post_meta($post_id, 'evcal_st');
    delete_post_meta($post_id, 'evcal_et');

    return true;
}

/**
 * Calculate EventON timestamp values, using EventON helpers when available.
 *
 * @param array<string, mixed> $state Resolved datetime state.
 * @return array<string, int>|WP_Error
 */
function eventon_apify_calculate_datetime_meta(array $state) {
    $payload = eventon_apify_build_eventon_datetime_payload($state);

    if (function_exists('eventon_get_unix_time')) {
        try {
            $timezone = new DateTimeZone($state['timezone_key']);
        } catch (Exception $exception) {
            $timezone = wp_timezone();
        }

        $calculated = eventon_get_unix_time($payload, 'Y/m/d', '24h', $timezone);

        if (is_array($calculated) && !empty($calculated['unix_start']) && !empty($calculated['unix_end'])) {
            return array(
                'unix_start' => absint($calculated['unix_start']),
                'unix_end' => absint($calculated['unix_end']),
                'unix_vir_end' => absint($calculated['unix_vir_end'] ?? 0),
                'unix_start_ev' => absint($calculated['unix_start_ev'] ?? $calculated['unix_start']),
                'unix_end_ev' => absint($calculated['unix_end_ev'] ?? $calculated['unix_end']),
                'unix_vend_ev' => absint($calculated['unix_vend_ev'] ?? ($calculated['unix_vir_end'] ?? 0)),
            );
        }
    }

    $start_timestamp = eventon_apify_build_timestamp($state['start_date'], $state['start_time'], $state['timezone_key']);
    $end_timestamp = eventon_apify_build_timestamp($state['end_date'], $state['end_time'], $state['timezone_key']);

    if ($start_timestamp === null || $end_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_datetime',
            'Event dates could not be converted into timestamps.',
            array('status' => 400)
        );
    }

    $virtual_end_timestamp = 0;
    if ($state['virtual_end_enabled']) {
        $virtual_end_timestamp = eventon_apify_build_timestamp($state['virtual_end_date'], $state['virtual_end_time'], $state['timezone_key']) ?: 0;
    }

    return array(
        'unix_start' => $start_timestamp,
        'unix_end' => $end_timestamp,
        'unix_vir_end' => $virtual_end_timestamp,
        'unix_start_ev' => $start_timestamp,
        'unix_end_ev' => $end_timestamp,
        'unix_vend_ev' => $virtual_end_timestamp,
    );
}

/**
 * Convert a resolved datetime state into the POST-like structure EventON expects.
 *
 * @param array<string, mixed> $state Resolved datetime state.
 * @return array<string, string>
 */
function eventon_apify_build_eventon_datetime_payload(array $state) {
    $payload = array(
        'evcal_start_date' => str_replace('-', '/', (string) $state['start_date']),
        'evcal_end_date' => str_replace('-', '/', (string) $state['end_date']),
        '_evo_date_format' => 'Y/m/d',
        '_evo_time_format' => '24h',
        'extend_type' => (string) $state['time_extend_type'],
    );

    $start_time = eventon_apify_split_time_string((string) $state['start_time']);
    $end_time = eventon_apify_split_time_string((string) $state['end_time']);

    if ($start_time) {
        $payload['evcal_start_time_hour'] = $start_time['hour'];
        $payload['evcal_start_time_min'] = $start_time['minute'];
    }

    if ($end_time) {
        $payload['evcal_end_time_hour'] = $end_time['hour'];
        $payload['evcal_end_time_min'] = $end_time['minute'];
    }

    if ($state['virtual_end_enabled']) {
        $virtual_time = eventon_apify_split_time_string((string) $state['virtual_end_time']);
        $payload['event_vir_date_x'] = str_replace('-', '/', (string) $state['virtual_end_date']);

        if ($virtual_time) {
            $payload['_vir_hour'] = $virtual_time['hour'];
            $payload['_vir_minute'] = $virtual_time['minute'];
        }
    }

    return $payload;
}

/**
 * Save EventON repeat-event fields.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_repeat_meta($post_id, array $params) {
    $has_repeat_payload = eventon_apify_array_has_any(
        $params,
        array('repeat_enabled', 'repeat_frequency', 'repeat_gap', 'repeat_count', 'repeat_series_visible', 'repeat_intervals')
    );

    if (!$has_repeat_payload) {
        return true;
    }

    $enabled = array_key_exists('repeat_enabled', $params)
        ? eventon_apify_is_yes($params['repeat_enabled'])
        : (
            eventon_apify_array_has_any($params, array('repeat_frequency', 'repeat_gap', 'repeat_count', 'repeat_intervals'))
                ? true
                : eventon_apify_is_yes(get_post_meta($post_id, 'evcal_repeat', true))
        );

    eventon_apify_update_yes_no_meta($post_id, 'evcal_repeat', $enabled);

    if (!$enabled) {
        delete_post_meta($post_id, 'repeat_intervals');
        delete_post_meta($post_id, 'evcal_rep_freq');
        delete_post_meta($post_id, 'evcal_rep_gap');
        delete_post_meta($post_id, 'evcal_rep_num');
        delete_post_meta($post_id, '_evcal_rep_series');
        return true;
    }

    $repeat_frequency = array_key_exists('repeat_frequency', $params)
        ? sanitize_key((string) $params['repeat_frequency'])
        : sanitize_key((string) get_post_meta($post_id, 'evcal_rep_freq', true));
    $repeat_gap = array_key_exists('repeat_gap', $params)
        ? absint($params['repeat_gap'])
        : absint(get_post_meta($post_id, 'evcal_rep_gap', true));
    $repeat_count = array_key_exists('repeat_count', $params)
        ? absint($params['repeat_count'])
        : absint(get_post_meta($post_id, 'evcal_rep_num', true));

    $repeat_frequency = $repeat_frequency !== '' ? $repeat_frequency : 'daily';
    $repeat_gap = $repeat_gap > 0 ? $repeat_gap : 1;
    $repeat_count = $repeat_count > 0 ? $repeat_count : 1;

    update_post_meta($post_id, 'evcal_rep_freq', $repeat_frequency);
    update_post_meta($post_id, 'evcal_rep_gap', $repeat_gap);
    update_post_meta($post_id, 'evcal_rep_num', $repeat_count);

    if (array_key_exists('repeat_series_visible', $params)) {
        eventon_apify_update_yes_no_meta($post_id, '_evcal_rep_series', $params['repeat_series_visible']);
    }

    $intervals = eventon_apify_generate_repeat_intervals($post_id, $params, $repeat_frequency, $repeat_gap, $repeat_count);
    if (is_wp_error($intervals)) {
        return $intervals;
    }

    if (!empty($intervals)) {
        update_post_meta($post_id, 'repeat_intervals', $intervals);
    } else {
        delete_post_meta($post_id, 'repeat_intervals');
    }

    return true;
}

/**
 * Generate the repeat_intervals structure EventON expects.
 *
 * @param int                  $post_id         Event post ID.
 * @param array<string, mixed> $params          Request parameters.
 * @param string               $repeat_frequency Repeat frequency.
 * @return array<int, array<int, int>>|WP_Error
 */
function eventon_apify_generate_repeat_intervals($post_id, array $params, $repeat_frequency, $repeat_gap, $repeat_count) {
    $base_start = absint(get_post_meta($post_id, 'evcal_srow', true));
    $base_end = absint(get_post_meta($post_id, 'evcal_erow', true));
    $timezone_key = sanitize_text_field((string) (array_key_exists('timezone_key', $params) ? $params['timezone_key'] : get_post_meta($post_id, '_evo_tz', true)));

    if ($repeat_frequency === 'custom' && !array_key_exists('repeat_intervals', $params)) {
        $existing_intervals = get_post_meta($post_id, 'repeat_intervals', true);
        return is_array($existing_intervals) ? $existing_intervals : array();
    }

    $custom_intervals = array();
    if (array_key_exists('repeat_intervals', $params)) {
        $custom_intervals = eventon_apify_normalize_repeat_intervals_input($params['repeat_intervals'], $timezone_key);
        if (is_wp_error($custom_intervals)) {
            return $custom_intervals;
        }
    }

    if (empty($base_start) || empty($base_end)) {
        return array();
    }

    if (function_exists('eventon_get_repeat_intervals')) {
        $repeat_payload = array(
            'evcal_rep_freq' => $repeat_frequency,
            'evcal_rep_gap' => $repeat_gap,
            'evcal_rep_num' => $repeat_count,
            'repeat_intervals' => $custom_intervals,
            '_evo_date_format' => 'Y/m/d',
            '_evo_time_format' => '24h',
        );

        $intervals = eventon_get_repeat_intervals($base_start, $base_end, $repeat_payload);
        if (is_array($intervals)) {
            return $intervals;
        }
    }

    if ($repeat_frequency === 'custom') {
        if (empty($custom_intervals)) {
            return new WP_Error(
                'eventon_apify_missing_repeat_intervals',
                'repeat.intervals is required when repeat.frequency is custom.',
                array('status' => 400)
            );
        }

        return $custom_intervals;
    }

    return array(array($base_start, $base_end));
}

/**
 * Normalize repeat interval input into the storage format EventON uses.
 *
 * @param mixed  $intervals    Repeat interval input.
 * @param string $timezone_key Event timezone.
 * @return array<int, array<int, int>>|WP_Error
 */
function eventon_apify_normalize_repeat_intervals_input($intervals, $timezone_key) {
    if ($intervals === null || $intervals === '') {
        return array();
    }

    if (!is_array($intervals)) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_intervals',
            'repeat.intervals must be an array.',
            array('status' => 400)
        );
    }

    $normalized = array();
    foreach ($intervals as $interval) {
        $normalized_interval = eventon_apify_normalize_repeat_interval_item($interval, $timezone_key);

        if (is_wp_error($normalized_interval)) {
            return $normalized_interval;
        }

        if ($normalized_interval) {
            $normalized[] = $normalized_interval;
        }
    }

    usort(
        $normalized,
        static function (array $left, array $right) {
            return $left[0] <=> $right[0];
        }
    );

    $unique = array_map('unserialize', array_unique(array_map('serialize', $normalized)));

    return array_values($unique);
}

/**
 * Normalize one repeat interval item.
 *
 * @param mixed  $interval     One interval item.
 * @param string $timezone_key Event timezone.
 * @return array<int, int>|false|WP_Error
 */
function eventon_apify_normalize_repeat_interval_item($interval, $timezone_key) {
    if (!is_array($interval)) {
        return false;
    }

    if (isset($interval[0], $interval[1]) && is_numeric($interval[0]) && is_numeric($interval[1])) {
        $start = absint($interval[0]);
        $end = absint($interval[1]);

        if ($end < $start) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_interval',
                'Each repeat interval end must be on or after its start.',
                array('status' => 400)
            );
        }

        return array($start, $end);
    }

    $start_timestamp = eventon_apify_array_has_any($interval, array('start_timestamp'))
        ? absint($interval['start_timestamp'])
        : 0;
    $end_timestamp = eventon_apify_array_has_any($interval, array('end_timestamp'))
        ? absint($interval['end_timestamp'])
        : 0;

    if ($start_timestamp && $end_timestamp) {
        if ($end_timestamp < $start_timestamp) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_interval',
                'Each repeat interval end must be on or after its start.',
                array('status' => 400)
            );
        }

        return array($start_timestamp, $end_timestamp);
    }

    $start_at = eventon_apify_array_get($interval, array('start_at'), '');
    $end_at = eventon_apify_array_get($interval, array('end_at'), '');

    if ($start_at !== '' && $end_at !== '') {
        $start_parts = eventon_apify_extract_datetime_parts($start_at);
        $end_parts = eventon_apify_extract_datetime_parts($end_at);

        if (!$start_parts || !$end_parts) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_interval',
                'repeat.intervals start_at/end_at could not be parsed.',
                array('status' => 400)
            );
        }

        $start_timestamp = eventon_apify_build_timestamp($start_parts['date'], $start_parts['time'], $start_parts['timezone'] ?: $timezone_key);
        $end_timestamp = eventon_apify_build_timestamp($end_parts['date'], $end_parts['time'], $end_parts['timezone'] ?: $timezone_key);
    } else {
        $start_date = sanitize_text_field((string) eventon_apify_array_get($interval, array('start_date'), ''));
        $start_time = sanitize_text_field((string) eventon_apify_array_get($interval, array('start_time'), '00:00'));
        $end_date = sanitize_text_field((string) eventon_apify_array_get($interval, array('end_date'), $start_date));
        $end_time = sanitize_text_field((string) eventon_apify_array_get($interval, array('end_time'), $start_time));

        if ($start_date === '') {
            return false;
        }

        $start_timestamp = eventon_apify_build_timestamp($start_date, $start_time, $timezone_key);
        $end_timestamp = eventon_apify_build_timestamp($end_date, $end_time, $timezone_key);
    }

    if ($start_timestamp === null || $end_timestamp === null) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_interval',
            'repeat.intervals contains an invalid date/time.',
            array('status' => 400)
        );
    }

    if ($end_timestamp < $start_timestamp) {
        return new WP_Error(
            'eventon_apify_invalid_repeat_interval',
            'Each repeat interval end must be on or after its start.',
            array('status' => 400)
        );
    }

    return array($start_timestamp, $end_timestamp);
}

/**
 * Save EventON RSVP addon meta when present.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_rsvp_meta($post_id, array $params) {
    $has_rsvp_payload = eventon_apify_array_has_any(
        $params,
        array(
            'rsvp_enabled',
            'rsvp_show_count',
            'rsvp_show_whos_coming',
            'rsvp_only_loggedin',
            'rsvp_capacity_enabled',
            'rsvp_capacity_count',
            'rsvp_capacity_show_remaining',
            'rsvp_show_bars',
            'rsvp_max_active',
            'rsvp_max_count',
            'rsvp_min_capacity_active',
            'rsvp_min_count',
            'rsvp_close_before_minutes',
            'rsvp_additional_emails',
            'rsvp_manage_repeat_capacity',
            'rsvp_repeat_capacities',
        )
    );

    if (!$has_rsvp_payload) {
        return true;
    }

    if (!array_key_exists('rsvp_enabled', $params)) {
        eventon_apify_update_yes_no_meta($post_id, 'evors_rsvp', true);
    }

    $boolean_map = array(
        'rsvp_enabled' => 'evors_rsvp',
        'rsvp_show_count' => 'evors_show_rsvp',
        'rsvp_show_whos_coming' => 'evors_show_whos_coming',
        'rsvp_only_loggedin' => 'evors_only_loggedin',
        'rsvp_capacity_enabled' => 'evors_capacity',
        'rsvp_capacity_show_remaining' => 'evors_capacity_show',
        'rsvp_show_bars' => 'evors_show_bars',
        'rsvp_max_active' => 'evors_max_active',
        'rsvp_min_capacity_active' => 'evors_min_cap',
        'rsvp_manage_repeat_capacity' => '_manage_repeat_cap_rs',
    );

    foreach ($boolean_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            eventon_apify_update_yes_no_meta($post_id, $meta_key, $params[$request_key]);
        }
    }

    if (array_key_exists('rsvp_capacity_count', $params) && !array_key_exists('rsvp_capacity_enabled', $params) && absint($params['rsvp_capacity_count']) > 0) {
        eventon_apify_update_yes_no_meta($post_id, 'evors_capacity', true);
    }

    $integer_map = array(
        'rsvp_capacity_count' => 'evors_capacity_count',
        'rsvp_max_count' => 'evors_max_count',
        'rsvp_min_count' => 'evors_min_count',
        'rsvp_close_before_minutes' => 'evors_close_time',
    );

    foreach ($integer_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            eventon_apify_update_or_delete_meta($post_id, $meta_key, (string) absint($params[$request_key]));
        }
    }

    if (array_key_exists('rsvp_additional_emails', $params)) {
        eventon_apify_update_or_delete_meta($post_id, 'evors_add_emails', sanitize_text_field((string) $params['rsvp_additional_emails']));
    }

    if (array_key_exists('rsvp_repeat_capacities', $params)) {
        if (!is_array($params['rsvp_repeat_capacities'])) {
            return new WP_Error(
                'eventon_apify_invalid_repeat_capacities',
                'rsvp.repeat_capacities must be an array of integers.',
                array('status' => 400)
            );
        }

        $capacities = array_map('absint', $params['rsvp_repeat_capacities']);
        $capacities = array_values($capacities);

        if (!array_key_exists('rsvp_manage_repeat_capacity', $params) && !empty($capacities)) {
            eventon_apify_update_yes_no_meta($post_id, '_manage_repeat_cap_rs', true);
        }

        if (!empty($capacities)) {
            update_post_meta($post_id, 'ri_capacity_rs', $capacities);
        } else {
            delete_post_meta($post_id, 'ri_capacity_rs');
        }
    } elseif (array_key_exists('rsvp_manage_repeat_capacity', $params) && !eventon_apify_is_yes($params['rsvp_manage_repeat_capacity'])) {
        delete_post_meta($post_id, 'ri_capacity_rs');
    }

    return true;
}

/**
 * Save event taxonomy terms.
 *
 * @param int                  $post_id Event post ID.
 * @param array<string, mixed> $params  Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_save_event_terms($post_id, array $params) {
    if (array_key_exists('event_type', $params)) {
        $event_type_result = eventon_apify_sync_simple_terms($post_id, 'event_type', $params['event_type']);
        if (is_wp_error($event_type_result)) {
            return $event_type_result;
        }
    }

    if (eventon_apify_array_has_any(
        $params,
        array(
            'location',
            'location_term_id',
            'location_name',
            'location_slug',
            'location_description',
            'location_type',
            'location_address',
            'location_city',
            'location_state',
            'location_country',
            'location_zip',
            'location_lat',
            'location_lon',
            'location_link',
            'location_link_target',
            'location_phone',
            'location_email',
            'location_getdir_latlng',
        )
    )) {
        $location_result = eventon_apify_sync_location_term($post_id, $params);
        if (is_wp_error($location_result)) {
            return $location_result;
        }
    }

    if (array_key_exists('organizers', $params) || array_key_exists('organizer', $params)) {
        $organizer_result = eventon_apify_sync_organizer_terms($post_id, $params);
        if (is_wp_error($organizer_result)) {
            return $organizer_result;
        }
    }

    return true;
}

/**
 * Sync simple taxonomy assignments such as event_type.
 *
 * @param mixed $terms Raw term input.
 * @return true|WP_Error
 */
function eventon_apify_sync_simple_terms($post_id, $taxonomy, $terms) {
    if (is_string($terms)) {
        $terms = array_filter(array_map('trim', explode(',', $terms)));
    } elseif (!is_array($terms)) {
        $terms = array();
    }

    $term_ids = array();
    foreach ($terms as $term_input) {
        if (is_scalar($term_input) && !is_array($term_input)) {
            if (is_numeric($term_input)) {
                $term = get_term(absint($term_input), $taxonomy);
                if (!$term || is_wp_error($term)) {
                    return new WP_Error(
                        'eventon_apify_invalid_term',
                        'A requested ' . $taxonomy . ' term does not exist.',
                        array('status' => 400)
                    );
                }
            } else {
                $term = eventon_apify_resolve_taxonomy_term($taxonomy, array('name' => (string) $term_input));
            }
        } elseif (is_array($term_input)) {
            $term = eventon_apify_resolve_taxonomy_term($taxonomy, $term_input);
        } else {
            continue;
        }

        if (is_wp_error($term)) {
            return $term;
        }

        $term_ids[] = (int) $term->term_id;

        if ($taxonomy === 'event_type' && is_array($term_input)) {
            $term_color = eventon_apify_array_get($term_input, array('et_color', 'color'), '');
            $normalized_color = eventon_apify_normalize_color_input($term_color);

            if ($normalized_color !== null && $normalized_color !== '') {
                $term_meta_result = eventon_apify_save_term_meta_payload('event_type', (int) $term->term_id, array('et_color' => $normalized_color));
                if (is_wp_error($term_meta_result)) {
                    return $term_meta_result;
                }
            }
        }
    }

    $result = wp_set_post_terms($post_id, $term_ids, $taxonomy, false);

    return is_wp_error($result) ? $result : true;
}

/**
 * Sync the single EventON location term attached to an event.
 *
 * @param array<string, mixed> $params Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_sync_location_term($post_id, array $params) {
    $explicit_clear = array_key_exists('location_name', $params)
        && trim((string) $params['location_name']) === ''
        && absint($params['location_term_id'] ?? 0) === 0;

    if ($explicit_clear || (array_key_exists('location', $params) && empty($params['location']))) {
        $result = wp_set_post_terms($post_id, array(), 'event_location', false);
        if (is_wp_error($result)) {
            return $result;
        }

        eventon_apify_clear_legacy_location_meta($post_id);
        return true;
    }

    $term_item = array();
    if (array_key_exists('location_term_id', $params)) {
        $term_item['term_id'] = absint($params['location_term_id']);
    }
    if (array_key_exists('location_name', $params)) {
        $term_item['name'] = sanitize_text_field((string) $params['location_name']);
    }
    if (array_key_exists('location_slug', $params)) {
        $term_item['slug'] = sanitize_title((string) $params['location_slug']);
    }
    if (array_key_exists('location_description', $params)) {
        $term_item['description'] = wp_kses_post((string) $params['location_description']);
    }

    $term = null;
    if (!empty($term_item['term_id'])) {
        $term = eventon_apify_resolve_taxonomy_term('event_location', $term_item, false);
    } elseif (!empty($term_item['name'])) {
        $term = eventon_apify_resolve_taxonomy_term('event_location', $term_item);
    } else {
        $existing_terms = wp_get_post_terms($post_id, 'event_location');
        if (!is_wp_error($existing_terms) && !empty($existing_terms)) {
            $term = eventon_apify_resolve_taxonomy_term(
                'event_location',
                array_merge($term_item, array('term_id' => (int) $existing_terms[0]->term_id)),
                false
            );
        }
    }

    if (is_wp_error($term)) {
        return $term;
    }

    if (!$term || !($term instanceof WP_Term)) {
        return new WP_Error(
            'eventon_apify_missing_location_term',
            'location.name or location.term_id is required when setting location details.',
            array('status' => 400)
        );
    }

    $result = wp_set_post_terms($post_id, array((int) $term->term_id), 'event_location', false);
    if (is_wp_error($result)) {
        return $result;
    }

    $term_meta = array();
    $text_term_meta_map = array(
        'location_address' => 'location_address',
        'location_city' => 'location_city',
        'location_state' => 'location_state',
        'location_country' => 'location_country',
        'location_zip' => 'location_zip',
        'location_lat' => 'location_lat',
        'location_lon' => 'location_lon',
        'location_type' => 'location_type',
        'location_phone' => 'loc_phone',
    );

    foreach ($text_term_meta_map as $request_key => $meta_key) {
        if (array_key_exists($request_key, $params)) {
            $term_meta[$meta_key] = sanitize_text_field((string) $params[$request_key]);
        }
    }

    if (array_key_exists('location_email', $params)) {
        $term_meta['loc_email'] = sanitize_email((string) $params['location_email']);
    }

    if (array_key_exists('location_link', $params)) {
        $location_link = esc_url_raw((string) $params['location_link']);
        $term_meta['location_link'] = $location_link;
        $term_meta['evcal_location_link'] = $location_link;
    }

    if (array_key_exists('location_link_target', $params)) {
        $term_meta['evcal_location_link_target'] = eventon_apify_to_yes_no($params['location_link_target']);
    }

    if (array_key_exists('location_getdir_latlng', $params)) {
        $term_meta['location_getdir_latlng'] = eventon_apify_to_yes_no($params['location_getdir_latlng']);
    }

    if (!empty($term_meta)) {
        $term_meta_result = eventon_apify_save_term_meta_payload('event_location', (int) $term->term_id, $term_meta);
        if (is_wp_error($term_meta_result)) {
            return $term_meta_result;
        }
    }
    eventon_apify_clear_legacy_location_meta($post_id);

    return true;
}

/**
 * Sync EventON organizer terms attached to an event.
 *
 * @param array<string, mixed> $params Request parameters.
 * @return true|WP_Error
 */
function eventon_apify_sync_organizer_terms($post_id, array $params) {
    $organizers = $params['organizers'] ?? array();

    if (!is_array($organizers) || empty($organizers)) {
        $result = wp_set_post_terms($post_id, array(), 'event_organizer', false);
        if (is_wp_error($result)) {
            return $result;
        }

        delete_post_meta($post_id, '_evotax_order_event_organizer');
        eventon_apify_clear_legacy_organizer_meta($post_id);
        return true;
    }

    $term_ids = array();
    foreach ($organizers as $organizer) {
        if (!is_array($organizer)) {
            continue;
        }

        $term = eventon_apify_resolve_taxonomy_term('event_organizer', $organizer);
        if (is_wp_error($term)) {
            return $term;
        }

        if (!$term || !($term instanceof WP_Term)) {
            return new WP_Error(
                'eventon_apify_invalid_organizer',
                'Each organizer must include a valid name or term_id.',
                array('status' => 400)
            );
        }

        $term_ids[] = (int) $term->term_id;

        $term_meta = array();
        $organizer_meta_map = array(
            'contact' => 'evcal_org_contact',
            'phone' => 'evcal_org_contact_phone',
            'address' => 'evcal_org_address',
        );

        foreach ($organizer_meta_map as $request_key => $meta_key) {
            if (array_key_exists($request_key, $organizer)) {
                $term_meta[$meta_key] = sanitize_text_field((string) $organizer[$request_key]);
            }
        }

        if (array_key_exists('email', $organizer)) {
            $term_meta['evcal_org_contact_e'] = sanitize_email((string) $organizer['email']);
        }

        if (array_key_exists('link', $organizer)) {
            $term_meta['evcal_org_exlink'] = esc_url_raw((string) $organizer['link']);
        }

        if (array_key_exists('link_target', $organizer)) {
            $term_meta['_evocal_org_exlink_target'] = eventon_apify_to_yes_no($organizer['link_target']);
        }

        if (array_key_exists('excerpt', $organizer)) {
            $term_meta['excerpt'] = sanitize_textarea_field((string) $organizer['excerpt']);
        }

        if (!empty($term_meta)) {
            $term_meta_result = eventon_apify_save_term_meta_payload('event_organizer', (int) $term->term_id, $term_meta);
            if (is_wp_error($term_meta_result)) {
                return $term_meta_result;
            }
        }
    }

    $result = wp_set_post_terms($post_id, $term_ids, 'event_organizer', false);
    if (is_wp_error($result)) {
        return $result;
    }

    if (!empty($term_ids)) {
        update_post_meta($post_id, '_evotax_order_event_organizer', implode(',', $term_ids));
    } else {
        delete_post_meta($post_id, '_evotax_order_event_organizer');
    }

    eventon_apify_clear_legacy_organizer_meta($post_id);

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
        'Managing shared EventON taxonomy records requires administrator privileges.',
        array('status' => rest_authorization_required_code())
    );
}

/**
 * Return true when the term payload carries mutable core term attributes.
 */
function eventon_apify_term_payload_has_term_changes(array $item) {
    foreach (array('name', 'title', 'label', 'slug', 'description') as $key) {
        if (!array_key_exists($key, $item)) {
            continue;
        }

        if ($key === 'description') {
            return true;
        }

        if (trim((string) $item[$key]) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Resolve or create a term for one of EventON's taxonomies.
 *
 * @param array<string, mixed> $item Term input.
 * @return WP_Term|WP_Error
 */
function eventon_apify_resolve_taxonomy_term($taxonomy, array $item, $create_if_missing = true) {
    $term_id = absint(eventon_apify_array_get($item, array('term_id', 'id'), 0));

    if ($term_id) {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return new WP_Error(
                'eventon_apify_invalid_term_reference',
                'The provided ' . $taxonomy . ' term_id does not exist.',
                array('status' => 400)
            );
        }

        if (eventon_apify_term_payload_has_term_changes($item)) {
            $authorization = eventon_apify_assert_can_manage_shared_terms();
            if (is_wp_error($authorization)) {
                return $authorization;
            }
        }

        return eventon_apify_update_taxonomy_term($taxonomy, $term, $item);
    }

    $slug = sanitize_title((string) eventon_apify_array_get($item, array('slug'), ''));
    if ($slug !== '') {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term instanceof WP_Term) {
            if (eventon_apify_term_payload_has_term_changes($item)) {
                $authorization = eventon_apify_assert_can_manage_shared_terms();
                if (is_wp_error($authorization)) {
                    return $authorization;
                }
            }

            return eventon_apify_update_taxonomy_term($taxonomy, $term, $item);
        }
    }

    $name = sanitize_text_field((string) eventon_apify_array_get($item, array('name', 'title', 'label'), ''));
    if ($name === '') {
        return new WP_Error(
            'eventon_apify_missing_term_name',
            'A taxonomy item must include a name or term_id.',
            array('status' => 400)
        );
    }

    $existing = term_exists($name, $taxonomy);
    if ($existing) {
        $existing_term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
        $term = get_term($existing_term_id, $taxonomy);
        if ($term instanceof WP_Term) {
            if (eventon_apify_term_payload_has_term_changes($item) && count($item) > 1) {
                $authorization = eventon_apify_assert_can_manage_shared_terms();
                if (is_wp_error($authorization)) {
                    return $authorization;
                }
            }

            return eventon_apify_update_taxonomy_term($taxonomy, $term, $item);
        }
    }

    if (!$create_if_missing) {
        return new WP_Error(
            'eventon_apify_missing_term',
            'The referenced ' . $taxonomy . ' term does not exist.',
            array('status' => 400)
        );
    }

    $authorization = eventon_apify_assert_can_manage_shared_terms();
    if (is_wp_error($authorization)) {
        return $authorization;
    }

    $inserted = wp_insert_term(
        $name,
        $taxonomy,
        array(
            'slug' => $slug ?: sanitize_title($name),
            'description' => array_key_exists('description', $item) ? wp_kses_post((string) $item['description']) : '',
        )
    );

    if (is_wp_error($inserted)) {
        return $inserted;
    }

    $term = get_term((int) $inserted['term_id'], $taxonomy);

    return $term instanceof WP_Term ? $term : new WP_Error(
        'eventon_apify_term_creation_failed',
        'The ' . $taxonomy . ' term could not be loaded after creation.',
        array('status' => 500)
    );
}

/**
 * Update a term's core data when the payload explicitly supplies it.
 *
 * @param array<string, mixed> $item Term payload.
 * @return WP_Term|WP_Error
 */
function eventon_apify_update_taxonomy_term($taxonomy, WP_Term $term, array $item) {
    $update_args = array();

    if (eventon_apify_array_has_any($item, array('name', 'title', 'label'))) {
        $name = sanitize_text_field((string) eventon_apify_array_get($item, array('name', 'title', 'label'), ''));
        if ($name !== '') {
            $update_args['name'] = $name;
        }
    }

    if (array_key_exists('slug', $item)) {
        $update_args['slug'] = sanitize_title((string) $item['slug']);
    }

    if (array_key_exists('description', $item)) {
        $update_args['description'] = wp_kses_post((string) $item['description']);
    }

    if (!empty($update_args)) {
        $authorization = eventon_apify_assert_can_manage_shared_terms();
        if (is_wp_error($authorization)) {
            return $authorization;
        }

        $updated = wp_update_term($term->term_id, $taxonomy, $update_args);
        if (is_wp_error($updated)) {
            return $updated;
        }

        $term = get_term($term->term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return new WP_Error(
                'eventon_apify_term_update_failed',
                'The ' . $taxonomy . ' term could not be loaded after update.',
                array('status' => 500)
            );
        }
    }

    return $term;
}

/**
 * Persist EventON taxonomy meta into the shared evo_tax_meta option store.
 *
 * @param array<string, scalar|null> $term_meta Term meta to save.
 * @return true|WP_Error
 */
function eventon_apify_save_term_meta_payload($taxonomy, $term_id, array $term_meta) {
    $authorization = eventon_apify_assert_can_manage_shared_terms();
    if (is_wp_error($authorization)) {
        return $authorization;
    }

    $payload = array();
    foreach ($term_meta as $meta_key => $meta_value) {
        if (is_bool($meta_value)) {
            $payload[$meta_key] = eventon_apify_to_yes_no($meta_value);
            continue;
        }

        if ($meta_value === null) {
            $payload[$meta_key] = '';
            continue;
        }

        $payload[$meta_key] = (string) $meta_value;
    }

    if (function_exists('evo_save_term_metas')) {
        evo_save_term_metas($taxonomy, $term_id, $payload);
        return true;
    }

    $all_term_meta = get_option('evo_tax_meta', array());
    if (!is_array($all_term_meta)) {
        $all_term_meta = array();
    }

    $existing = $all_term_meta[$taxonomy][$term_id] ?? array();
    if (!is_array($existing)) {
        $existing = array();
    }

    $merged = array_merge($existing, $payload);
    foreach ($merged as $key => $value) {
        if ($value === '') {
            unset($merged[$key]);
        }
    }

    $all_term_meta[$taxonomy][$term_id] = $merged;
    update_option('evo_tax_meta', $all_term_meta);

    return true;
}

/**
 * Remove legacy location meta from earlier plugin revisions.
 */
function eventon_apify_clear_legacy_location_meta($post_id) {
    foreach (array(
        'evcal_location_name_t',
        'evcal_location_addr',
        'evcal_location_city',
        'evcal_location_state',
        'evcal_location_country',
    ) as $meta_key) {
        delete_post_meta($post_id, $meta_key);
    }
}

/**
 * Remove legacy organizer meta from earlier plugin revisions.
 */
function eventon_apify_clear_legacy_organizer_meta($post_id) {
    delete_post_meta($post_id, 'evcal_organizer_name');
}

/**
 * Update a meta key when a non-empty value is present, otherwise delete it.
 */
function eventon_apify_update_or_delete_meta($post_id, $meta_key, $value) {
    if ($value === '') {
        delete_post_meta($post_id, $meta_key);
        return;
    }

    update_post_meta($post_id, $meta_key, $value);
}

/**
 * Update a yes/no EventON meta flag.
 */
function eventon_apify_update_yes_no_meta($post_id, $meta_key, $value) {
    update_post_meta($post_id, $meta_key, eventon_apify_to_yes_no($value));
}

/**
 * Sanitize allowed post statuses.
 *
 * @param mixed $status Submitted post status.
 */
function eventon_apify_get_sanitized_status($status) {
    $allowed = array('publish', 'draft', 'private', 'pending', 'future');
    $status = sanitize_key((string) $status);

    return in_array($status, $allowed, true) ? $status : '';
}

/**
 * Allowed EventON event statuses.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_event_statuses() {
    return array('scheduled', 'cancelled', 'movedonline', 'postponed', 'rescheduled', 'preliminary', 'tentative');
}

/**
 * Allowed EventON attendance modes.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_attendance_modes() {
    return array('offline', 'online', 'mixed');
}

/**
 * Allowed repeat frequencies.
 *
 * @return array<int, string>
 */
function eventon_apify_get_allowed_repeat_frequencies() {
    return array('hourly', 'daily', 'weekly', 'monthly', 'yearly', 'custom');
}

/**
 * Validate a URL-like input.
 *
 * @param mixed $value URL input.
 */
function eventon_apify_validate_url($value) {
    if ($value === null || trim((string) $value) === '') {
        return true;
    }

    $url = esc_url_raw((string) $value);

    if ($url === '') {
        return false;
    }

    if (function_exists('wp_http_validate_url')) {
        return (bool) wp_http_validate_url($url);
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Normalize colors to EventON's stored format (hex without the leading #).
 *
 * @param mixed $value Color input.
 * @return string|null
 */
function eventon_apify_normalize_color_input($value) {
    $value = ltrim(trim((string) $value), '#');

    if ($value === '') {
        return '';
    }

    return preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? strtolower($value) : null;
}

/**
 * Return the first matching key from an array.
 *
 * @param array<string, mixed> $source Source array.
 * @param array<int, string>   $keys   Candidate keys.
 * @return mixed
 */
function eventon_apify_array_get(array $source, array $keys, $default = null) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $source)) {
            return $source[$key];
        }
    }

    return $default;
}

/**
 * Determine whether any of the provided keys exist in the array.
 *
 * @param array<string, mixed> $source Source array.
 * @param array<int, string>   $keys   Candidate keys.
 */
function eventon_apify_array_has_any(array $source, array $keys) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $source)) {
            return true;
        }
    }

    return false;
}

/**
 * Check whether an EventON yes/no style value means yes.
 *
 * @param mixed $value Yes/no style input.
 */
function eventon_apify_is_yes($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (int) $value === 1;
    }

    return in_array(strtolower(trim((string) $value)), array('yes', 'y', '1', 'true', 'on'), true);
}

/**
 * Convert a truthy value into EventON's yes/no string format.
 *
 * @param mixed $value Yes/no style input.
 */
function eventon_apify_to_yes_no($value) {
    return eventon_apify_is_yes($value) ? 'yes' : 'no';
}

/**
 * Validate timezone identifiers.
 */
function eventon_apify_is_valid_timezone($timezone_key) {
    return in_array((string) $timezone_key, timezone_identifiers_list(), true);
}

/**
 * Split HH:MM time strings into EventON-compatible pieces.
 *
 * @param string $time Time string.
 * @return array<string, string>|null
 */
function eventon_apify_split_time_string($time) {
    $time = trim($time);

    if ($time === '') {
        return null;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
        return null;
    }

    $hour = (int) $matches[1];
    $minute = (int) $matches[2];

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        return null;
    }

    return array(
        'hour' => (string) $hour,
        'minute' => str_pad((string) $minute, 2, '0', STR_PAD_LEFT),
    );
}

/**
 * Build a site-timezone timestamp from date/time inputs.
 *
 * @param string $date Date string.
 * @param string $time Optional time string.
 * @return int|null
 */
function eventon_apify_build_timestamp($date, $time = '', $timezone_key = '') {
    $date = trim($date);
    $time = trim($time);

    if ($date === '') {
        return null;
    }

    try {
        $timezone = $timezone_key !== '' ? new DateTimeZone($timezone_key) : wp_timezone();
    } catch (Exception $exception) {
        $timezone = wp_timezone();
    }

    $datetime_string = $date . ' ' . ($time !== '' ? $time : '00:00');

    try {
        $datetime = new DateTimeImmutable($datetime_string, $timezone);
    } catch (Exception $exception) {
        return null;
    }

    return $datetime->getTimestamp();
}

/**
 * Get an existing EventON timestamp meta field as Y-m-d.
 */
function eventon_apify_get_existing_meta_date($post_id, $meta_key) {
    $timestamp = absint(get_post_meta($post_id, $meta_key, true));

    return $timestamp ? wp_date('Y-m-d', $timestamp) : '';
}
