<?php
function eventon_apify_render_settings_page() {
    $site_url = untrailingslashit(get_site_url());
    $rest_root_url = $site_url . '/wp-json';
    $api_enabled = (bool) get_option(EVENTON_APIFY_OPTION_ENABLE_API, false);
    $capabilities = eventon_apify_get_api_capabilities();
    $definitions = eventon_apify_get_api_capability_definitions();
    $wp_v2_compat_enabled = eventon_apify_is_wp_v2_compatibility_enabled();
    $rsvp_available = eventon_apify_is_eventon_rsvp_available();
    $openapi_spec_url = plugins_url('docs/eventon-apify-openapi.json', EVENTON_APIFY_PLUGIN_FILE);
    $postman_collection_url = plugins_url('docs/eventon-apify-postman-collection.json', EVENTON_APIFY_PLUGIN_FILE);
    $manifest_collection_url = $site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/mcp-schema';
    $manifest_type_url = $site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/mcp-schema/ajde_events';
    $project_url = 'https://github.com/renatobo/eventon-apify';
    $release_notes_url = $project_url . '/releases/tag/v' . rawurlencode(EVENTON_APIFY_VERSION);
    $author_url = 'https://github.com/renatobo';
    $git_updater_url = 'https://github.com/afragen/git-updater';
    $banner_url = plugins_url('assets/eventon-apify-settings-banner.svg', EVENTON_APIFY_PLUGIN_FILE);
    ?>
    <div class="wrap">
        <div class="eventon-apify-admin">
            <div class="eventon-apify-hero">
                <img
                    src="<?php echo esc_url($banner_url); ?>"
                    alt="<?php echo esc_attr__('EventON APIfy settings banner', 'eventon-apify'); ?>"
                    class="eventon-apify-hero-image"
                />
            </div>

            <div class="eventon-apify-meta">
                <a href="<?php echo esc_url($project_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('GitHub Repository', 'eventon-apify'); ?>
                </a>
                <span>
                    <?php
                    /* translators: %s: Plugin version. */
                    echo esc_html(sprintf(__('Version %s', 'eventon-apify'), EVENTON_APIFY_VERSION));
                    ?>
                </span>
                <a href="<?php echo esc_url($release_notes_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr(sprintf(__('Release notes for version %s', 'eventon-apify'), EVENTON_APIFY_VERSION)); ?>">
                    <?php esc_html_e('Release notes', 'eventon-apify'); ?>
                </a>
                <a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Renato Bonomini on GitHub', 'eventon-apify'); ?>
                </a>
                <a href="<?php echo esc_url($git_updater_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('GitHub updates via Git Updater', 'eventon-apify'); ?>
                </a>
            </div>

            <div class="eventon-apify-headline">
                <h1><?php esc_html_e('EventON APIfy Settings', 'eventon-apify'); ?></h1>
                <p class="eventon-apify-intro">
                <?php esc_html_e('Control the availability of the custom EventON REST API surface, the standard', 'eventon-apify'); ?> <code>wp/v2</code>
                <?php esc_html_e('compatibility layer, and the discovery docs that compatible clients use to build correct requests.', 'eventon-apify'); ?>
                </p>
                <p class="eventon-apify-intro eventon-apify-intro-secondary">
                    <?php esc_html_e('GitHub Releases is the active distribution channel for packaged installs and updates through Git Updater. WordPress.org support is intended as a secondary channel when the directory build is in place.', 'eventon-apify'); ?>
                </p>
                <p class="eventon-apify-intro eventon-apify-intro-secondary">
                    <?php esc_html_e('This plugin enables using MCP, with an extended MCP server available at', 'eventon-apify'); ?>
                    <a href="https://github.com/renatobo/mcp-wp-cpt" target="_blank" rel="noopener noreferrer">renatobo/mcp-wp-cpt</a>.
                </p>
            </div>

            <?php settings_errors(); ?>

            <?php if (!eventon_apify_is_eventon_available()) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php esc_html_e('EventON not detected.', 'eventon-apify'); ?></strong>
                        <?php esc_html_e('Activate EventON so the', 'eventon-apify'); ?> <code>ajde_events</code> <?php esc_html_e('post type is available before using these endpoints.', 'eventon-apify'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper eventon-apify-tabs" role="tablist" aria-label="<?php echo esc_attr__('EventON APIfy sections', 'eventon-apify'); ?>">
                <a href="#api" class="nav-tab eventon-apify-tab nav-tab-active" role="tab" aria-selected="true" data-panel="api">
                    <?php esc_html_e('Event API', 'eventon-apify'); ?>
                </a>
                <a href="#compat" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="compat">
                    <?php esc_html_e('WP v2 compatibility', 'eventon-apify'); ?>
                </a>
                <a href="#specs" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="specs">
                    <?php esc_html_e('API Specs', 'eventon-apify'); ?>
                </a>
                <a href="#manifest" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="manifest">
                    <?php esc_html_e('MCP schema manifest', 'eventon-apify'); ?>
                </a>
                <a href="#fields" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="fields">
                    <?php esc_html_e('Request fields', 'eventon-apify'); ?>
                </a>
                <a href="#passwords" class="nav-tab eventon-apify-tab" role="tab" aria-selected="false" data-panel="passwords">
                    <?php esc_html_e('Application Passwords', 'eventon-apify'); ?>
                </a>
            </nav>

            <form method="post" action="options.php" class="eventon-apify-shell">
                <?php settings_fields('eventon_apify_settings_group'); ?>
                <?php do_settings_sections('eventon_apify_settings_group'); ?>

                <section class="eventon-apify-panel is-active" id="api" data-panel="api" role="tabpanel">
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2><?php esc_html_e('Event API and capability toggles', 'eventon-apify'); ?></h2>
                            <p>
                                <?php esc_html_e('Gate the custom', 'eventon-apify'); ?> <code><?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?></code> <?php esc_html_e('REST surface without changing authentication requirements.', 'eventon-apify'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card eventon-apify-card-accent">
                        <div class="eventon-apify-switch-row">
                            <div>
                                <h3><?php esc_html_e('Event API', 'eventon-apify'); ?></h3>
                                <p>
                                    <?php esc_html_e('Enable or disable the protected REST endpoints under', 'eventon-apify'); ?>
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
                                <span><?php esc_html_e('Enable EventON events API', 'eventon-apify'); ?></span>
                            </label>
                        </div>

                        <div class="eventon-apify-grid eventon-apify-grid-two">
                            <div class="eventon-apify-code-card">
                                <strong><?php esc_html_e('Namespace', 'eventon-apify'); ?></strong>
                                <code>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?></code>
                            </div>
                            <div class="eventon-apify-code-card">
                                <strong><?php esc_html_e('Authentication', 'eventon-apify'); ?></strong>
                                <span><?php esc_html_e('Administrator access using WordPress credentials or Application Passwords.', 'eventon-apify'); ?></span>
                            </div>
                        </div>

                        <div class="eventon-apify-route-list">
                            <strong><?php esc_html_e('Routes', 'eventon-apify'); ?></strong>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;</code>
                            <code>POST <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events</code>
                            <code>PUT/PATCH <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;</code>
                            <code>DELETE <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;</code>
                            <?php if ($rsvp_available) : ?>
                                <code>GET <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;/rsvps/summary</code>
                                <code>GET <?php echo esc_html($site_url); ?>/wp-json/<?php echo esc_html(EVENTON_APIFY_NAMESPACE); ?>/events/&lt;id&gt;/rsvps</code>
                            <?php endif; ?>
                        </div>

                        <div class="eventon-apify-example-grid">
                            <div class="eventon-apify-example">
                                <strong><?php esc_html_e('Collection example', 'eventon-apify'); ?></strong>
                                <code id="eventon-apify-example-get"><?php echo esc_html($site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/events?per_page=10&page=1'); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-example-get'); return false;"><?php esc_html_e('Copy', 'eventon-apify'); ?></button>
                            </div>
                            <div class="eventon-apify-example">
                                <strong><?php esc_html_e('Authenticated curl example', 'eventon-apify'); ?></strong>
                                <code id="eventon-apify-example-curl">curl -u your_username:your_app_password "<?php echo esc_html($site_url . '/wp-json/' . EVENTON_APIFY_NAMESPACE . '/events?search=ride'); ?>"</code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-example-curl'); return false;"><?php esc_html_e('Copy', 'eventon-apify'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-panel-copy">
                            <h3><?php esc_html_e('API capabilities', 'eventon-apify'); ?></h3>
                            <p>
                                <?php esc_html_e('Disable specific REST operations without turning off the entire API. Requests still require administrator authentication, and disabled capabilities return', 'eventon-apify'); ?> <code>403</code>.
                            </p>
                        </div>

                        <?php if (!$rsvp_available) : ?>
                            <p class="eventon-apify-note">
                                <strong><?php esc_html_e('EventON RSVP not detected.', 'eventon-apify'); ?></strong>
                                <?php esc_html_e('The RSVP summary and attendee routes remain unavailable until the', 'eventon-apify'); ?>
                                <code>EventON - RSVP Events</code> addon is active and has registered
                                <?php esc_html_e('the', 'eventon-apify'); ?> <code>evo-rsvp</code> <?php esc_html_e('post type.', 'eventon-apify'); ?>
                            </p>
                        <?php endif; ?>

                        <fieldset class="eventon-apify-capabilities">
                            <legend class="screen-reader-text"><?php esc_html_e('API capabilities', 'eventon-apify'); ?></legend>
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
                                            <?php echo esc_html($effective_enabled ? __('Enabled', 'eventon-apify') : __('Disabled', 'eventon-apify')); ?>
                                        </span>
                                    </span>
                                    <span class="eventon-apify-capability-meta">
                                        <code><?php echo esc_html($definition['methods'] . ' ' . EVENTON_APIFY_NAMESPACE . $definition['route']); ?></code>
                                        <span><?php echo esc_html($definition['description']); ?></span>
                                        <?php if (!$api_enabled && $saved_enabled) : ?>
                                            <em><?php esc_html_e('Saved as enabled, but inactive while the Event API switch is off.', 'eventon-apify'); ?></em>
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
                            <h2><?php esc_html_e('WP v2 compatibility', 'eventon-apify'); ?></h2>
                            <p>
                                <?php esc_html_e('Expose EventON content through the standard WordPress REST API so generic WordPress tools can discover and operate on it.', 'eventon-apify'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-switch-row">
                            <div>
                                <h3><?php esc_html_e('Standard', 'eventon-apify'); ?> <code>wp/v2</code> <?php esc_html_e('compatibility', 'eventon-apify'); ?></h3>
                                <p>
                                    <?php esc_html_e('Publish', 'eventon-apify'); ?> <code>ajde_events</code> <?php esc_html_e('and related taxonomies under the standard WordPress REST namespace while preserving EventON APIfy\'s custom namespace.', 'eventon-apify'); ?>
                                </p>
                            </div>
                            <label class="eventon-apify-toggle">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT); ?>"
                                    value="1"
                                    <?php checked(true, $wp_v2_compat_enabled, true); ?>
                                />
                                <span><?php esc_html_e('Enable', 'eventon-apify'); ?> <code>wp/v2</code> <?php esc_html_e('compatibility', 'eventon-apify'); ?></span>
                            </label>
                        </div>

                        <div class="eventon-apify-route-list">
                            <strong><?php esc_html_e('Compatibility routes', 'eventon-apify'); ?></strong>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/types/ajde_events</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/ajde_events</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/event_location</code>
                            <code>GET <?php echo esc_html($site_url); ?>/wp-json/wp/v2/event_organizer</code>
                        </div>

                        <p class="eventon-apify-note">
                            <?php esc_html_e('Intended for generic WordPress clients such as', 'eventon-apify'); ?>
                            <a href="https://github.com/InstaWP/mcp-wp" target="_blank" rel="noopener noreferrer">InstaWP mcp-wp</a>.
                            <?php esc_html_e('These routes remain administrator-only, and compatibility responses redact sensitive fields like virtual access secrets and notification email metadata.', 'eventon-apify'); ?>
                        </p>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="specs" data-panel="specs" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2><?php esc_html_e('API Specs', 'eventon-apify'); ?></h2>
                            <p>
                                <?php esc_html_e('Download the checked-in OpenAPI and Postman artifacts for the current EventON APIfy REST surface, then point them at this site\'s REST root.', 'eventon-apify'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card eventon-apify-card-accent">
                        <div class="eventon-apify-example-grid">
                            <div class="eventon-apify-example">
                                <strong><?php esc_html_e('OpenAPI 3.1 spec', 'eventon-apify'); ?></strong>
                                <p><?php esc_html_e('Covers the public MCP discovery routes plus the protected EventON event and RSVP endpoints.', 'eventon-apify'); ?></p>
                                <code id="eventon-apify-openapi-spec"><?php echo esc_html($openapi_spec_url); ?></code>
                                <div class="eventon-apify-example-actions">
                                    <a class="button button-primary" href="<?php echo esc_url($openapi_spec_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open spec', 'eventon-apify'); ?></a>
                                    <button class="button button-secondary" onclick="eventonApifyCopy('eventon-apify-openapi-spec'); return false;"><?php esc_html_e('Copy link', 'eventon-apify'); ?></button>
                                </div>
                            </div>
                            <div class="eventon-apify-example">
                                <strong><?php esc_html_e('Postman collection', 'eventon-apify'); ?></strong>
                                <p><?php esc_html_e('Includes ready-to-run requests for discovery, CRUD, and optional RSVP reporting routes.', 'eventon-apify'); ?></p>
                                <code id="eventon-apify-postman-collection"><?php echo esc_html($postman_collection_url); ?></code>
                                <div class="eventon-apify-example-actions">
                                    <a class="button button-primary" href="<?php echo esc_url($postman_collection_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open collection', 'eventon-apify'); ?></a>
                                    <button class="button button-secondary" onclick="eventonApifyCopy('eventon-apify-postman-collection'); return false;"><?php esc_html_e('Copy link', 'eventon-apify'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-grid eventon-apify-grid-two">
                            <div class="eventon-apify-code-card">
                                <strong><?php esc_html_e('REST root / Postman baseUrl', 'eventon-apify'); ?></strong>
                                <code id="eventon-apify-rest-root"><?php echo esc_html($rest_root_url); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-rest-root'); return false;"><?php esc_html_e('Copy', 'eventon-apify'); ?></button>
                            </div>
                            <div class="eventon-apify-code-card">
                                <strong><?php esc_html_e('Authentication', 'eventon-apify'); ?></strong>
                                <span><?php esc_html_e('Use a WordPress administrator username and an Application Password for secured routes. The MCP schema manifest routes remain public.', 'eventon-apify'); ?></span>
                            </div>
                        </div>

                        <p class="eventon-apify-note">
                            <?php esc_html_e('Import the Postman collection, set', 'eventon-apify'); ?> <code>baseUrl</code> <?php esc_html_e('to this site\'s REST root, then fill in your WordPress username and Application Password variables. The OpenAPI file uses the same REST root as its server variable.', 'eventon-apify'); ?>
                        </p>
                        <p class="eventon-apify-note">
                            <?php esc_html_e('RSVP requests are documented in both files, but they respond only when the EventON RSVP addon is active and the matching API capabilities are enabled.', 'eventon-apify'); ?>
                        </p>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="manifest" data-panel="manifest" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2><?php esc_html_e('MCP schema manifest', 'eventon-apify'); ?></h2>
                            <p>
                                <?php esc_html_e('Publish the executable EventON field contract for compatible MCP servers and other structured clients.', 'eventon-apify'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <div class="eventon-apify-example-grid">
                            <div class="eventon-apify-example">
                                <strong><?php esc_html_e('Manifest collection', 'eventon-apify'); ?></strong>
                                <code id="eventon-apify-manifest-collection"><?php echo esc_html($manifest_collection_url); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-manifest-collection'); return false;"><?php esc_html_e('Copy', 'eventon-apify'); ?></button>
                            </div>
                            <div class="eventon-apify-example">
                                <strong><?php esc_html_e('Content type detail', 'eventon-apify'); ?></strong>
                                <code id="eventon-apify-manifest-type"><?php echo esc_html($manifest_type_url); ?></code>
                                <button class="button button-secondary button-small" onclick="eventonApifyCopy('eventon-apify-manifest-type'); return false;"><?php esc_html_e('Copy', 'eventon-apify'); ?></button>
                            </div>
                        </div>

                        <p>
                            <?php esc_html_e('The manifest is read-only and safe to expose. It describes the executable EventON field contract using', 'eventon-apify'); ?> <code>preferred_endpoint</code>, <code>preferred_write_mode</code>, <?php esc_html_e('structured', 'eventon-apify'); ?> <code>fields</code>, <?php esc_html_e('executable', 'eventon-apify'); ?> <code>validation_rules</code>, <?php esc_html_e('and normalized', 'eventon-apify'); ?> <code>examples.create</code> <?php esc_html_e('and', 'eventon-apify'); ?> <code>examples.update</code> <?php esc_html_e('payloads for', 'eventon-apify'); ?> <code>ajde_events</code>.
                        </p>
                        <p class="eventon-apify-note">
                            <?php esc_html_e('The manifest is discovery-only. Compatible MCP clients should follow the advertised', 'eventon-apify'); ?> <code>preferred_endpoint</code> <?php esc_html_e('and use the EventON APIfy events routes when interacting with', 'eventon-apify'); ?> <code>ajde_events</code>.
                        </p>
                    </div>
                </section>

                <section class="eventon-apify-panel" id="fields" data-panel="fields" role="tabpanel" hidden>
                    <div class="eventon-apify-panel-header">
                        <div>
                            <h2><?php esc_html_e('Request fields', 'eventon-apify'); ?></h2>
                            <p>
                                <?php esc_html_e('Preferred JSON payloads use EventON-style nested objects. Legacy flat aliases such as', 'eventon-apify'); ?> <code>location_name</code> <?php esc_html_e('are still accepted for backward compatibility.', 'eventon-apify'); ?>
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
                            <h2><?php esc_html_e('How to set up an Application Password', 'eventon-apify'); ?></h2>
                            <p>
                                <?php esc_html_e('Use WordPress Application Passwords for administrator-authenticated requests to the custom EventON API or the compatibility routes.', 'eventon-apify'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="eventon-apify-card">
                        <ol class="eventon-apify-steps">
                            <li><?php esc_html_e('Log in to your WordPress Admin Dashboard.', 'eventon-apify'); ?></li>
                            <li><?php esc_html_e('Go to', 'eventon-apify'); ?> <strong><?php esc_html_e('Users -> Profile', 'eventon-apify'); ?></strong>.</li>
                            <li><?php esc_html_e('Scroll down to the', 'eventon-apify'); ?> <strong><?php esc_html_e('Application Passwords', 'eventon-apify'); ?></strong> <?php esc_html_e('section.', 'eventon-apify'); ?></li>
                            <li><?php esc_html_e('Enter a name like', 'eventon-apify'); ?> <em><?php esc_html_e('EventON API Access', 'eventon-apify'); ?></em> <?php esc_html_e('and click', 'eventon-apify'); ?> <strong><?php esc_html_e('Add New Application Password', 'eventon-apify'); ?></strong>.</li>
                            <li><?php esc_html_e('Copy the generated password.', 'eventon-apify'); ?></li>
                            <li><?php esc_html_e('Use it with your WordPress username in Basic Auth requests.', 'eventon-apify'); ?></li>
                        </ol>
                        <p class="eventon-apify-note">
                            <?php esc_html_e('Your site should use HTTPS for Application Passwords. Store the generated password once, because WordPress will not show it again.', 'eventon-apify'); ?>
                        </p>
                    </div>
                </section>

                <div class="eventon-apify-footer">
                    <?php submit_button(__('Save settings', 'eventon-apify'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>

    </div>
    <?php
}

/**
 * Enqueue the settings-page styles and scripts on the plugin options screen only.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function eventon_apify_enqueue_settings_assets($hook_suffix) {
    if ('settings_page_eventon-apify-settings' !== $hook_suffix) {
        return;
    }

    wp_enqueue_style(
        'eventon-apify-settings',
        plugins_url('assets/css/admin-settings.css', EVENTON_APIFY_PLUGIN_FILE),
        array(),
        EVENTON_APIFY_VERSION
    );

    wp_enqueue_script(
        'eventon-apify-settings',
        plugins_url('assets/js/admin-settings.js', EVENTON_APIFY_PLUGIN_FILE),
        array(),
        EVENTON_APIFY_VERSION,
        true
    );
}
