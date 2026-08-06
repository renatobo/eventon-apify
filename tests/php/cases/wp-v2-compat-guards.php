<?php
/**
 * Tests for the wp/v2 compatibility guards (wp-v2-compat.php).
 *
 * Compatibility mode forces show_in_rest on ajde_events and the event_*
 * taxonomies at registration time, so these request-time guards are the only
 * thing keeping that surface away from non-administrators. Every one of them
 * is written as "if not admin, restrict", which means unresolved auth has to
 * fail closed. These cases pin that polarity.
 */

/**
 * Put the request in the state the guards are meant to restrict.
 */
function eventon_test_enable_compat_as_anonymous() {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);
    eventon_test_set_current_user_can(false);
}

// --- the shared predicate -------------------------------------------------

test('filtering is off while compatibility mode is disabled', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, false);

    eventon_test_set_current_user_can(false);
    ok(!eventon_apify_should_filter_wp_v2_compatibility_for_request());

    eventon_test_set_current_user_can(true);
    ok(!eventon_apify_should_filter_wp_v2_compatibility_for_request());
});

test('filtering applies to non-admins and never to admins', function () {
    eventon_test_enable_compat_as_anonymous();
    ok(eventon_apify_should_filter_wp_v2_compatibility_for_request(), 'anonymous callers must be filtered');

    eventon_test_set_current_user_can(true);
    ok(!eventon_apify_should_filter_wp_v2_compatibility_for_request(), 'admins see the full surface');
});

// --- rest_pre_dispatch ----------------------------------------------------

test('compatibility routes are refused for non-admins', function () {
    eventon_test_enable_compat_as_anonymous();

    foreach (array('/wp/v2/ajde_events', '/wp/v2/ajde_events/42', '/wp/v2/event_location', '/wp/v2/types/ajde_events') as $route) {
        $result = eventon_apify_restrict_wp_v2_compatibility_routes(null, null, new WP_REST_Request(array(), $route));
        ok(is_wp_error($result), $route . ' must be refused');
        eq($result->get_error_code(), 'eventon_apify_wp_v2_admin_only', $route . ' error code');
    }
});

test('a mixed-case compatibility route is still refused', function () {
    eventon_test_enable_compat_as_anonymous();

    // Core dispatches case-insensitively but get_route() returns the raw client
    // path, so a verbatim comparison would let /wp/v2/AJDE_events through.
    $result = eventon_apify_restrict_wp_v2_compatibility_routes(null, null, new WP_REST_Request(array(), '/wp/v2/AJDE_events'));

    ok(is_wp_error($result));
    eq($result->get_error_code(), 'eventon_apify_wp_v2_admin_only');
});

test('unrelated routes and admin callers pass through untouched', function () {
    eventon_test_enable_compat_as_anonymous();
    eq(eventon_apify_restrict_wp_v2_compatibility_routes('untouched', null, new WP_REST_Request(array(), '/wp/v2/posts')), 'untouched');

    eventon_test_set_current_user_can(true);
    eq(eventon_apify_restrict_wp_v2_compatibility_routes('untouched', null, new WP_REST_Request(array(), '/wp/v2/ajde_events')), 'untouched');
});

// --- REST index stripping -------------------------------------------------

test('compatibility routes are stripped from the REST index for non-admins', function () {
    eventon_test_enable_compat_as_anonymous();

    $filtered = eventon_apify_filter_wp_v2_compatibility_endpoints(array(
        '/wp/v2/posts' => 'keep',
        '/wp/v2/ajde_events' => 'strip',
        '/wp/v2/ajde_events/(?P<id>[\d]+)' => 'strip',
        '/wp/v2/event_organizer' => 'strip',
    ));

    eq(array_keys($filtered), array('/wp/v2/posts'));
});

test('the REST index is left intact for admins', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);
    eventon_test_set_current_user_can(true);

    $endpoints = array('/wp/v2/posts' => 'keep', '/wp/v2/ajde_events' => 'keep');

    eq(eventon_apify_filter_wp_v2_compatibility_endpoints($endpoints), $endpoints);
});

// --- shared collection responses ------------------------------------------

test('ajde_events is removed from the /wp/v2/types response', function () {
    eventon_test_enable_compat_as_anonymous();

    $response = new WP_HTTP_Response(array('post' => 'a', 'page' => 'b', 'ajde_events' => 'c'));
    $filtered = eventon_apify_filter_wp_v2_compatibility_responses($response, null, new WP_REST_Request(array(), '/wp/v2/types'));

    eq($filtered->get_data(), array('post' => 'a', 'page' => 'b'));
});

test('event taxonomies are removed from the /wp/v2/taxonomies response', function () {
    eventon_test_enable_compat_as_anonymous();

    $data = array('category' => 'a', 'post_tag' => 'b');
    foreach (eventon_apify_get_wp_v2_compatibility_taxonomies() as $taxonomy) {
        $data[$taxonomy] = 'hidden';
    }

    $response = new WP_HTTP_Response($data);
    $filtered = eventon_apify_filter_wp_v2_compatibility_responses($response, null, new WP_REST_Request(array(), '/wp/v2/taxonomies'));

    eq($filtered->get_data(), array('category' => 'a', 'post_tag' => 'b'));
});

test('a mixed-case collection route is still redacted', function () {
    eventon_test_enable_compat_as_anonymous();

    $response = new WP_HTTP_Response(array('post' => 'a', 'ajde_events' => 'c'));
    $filtered = eventon_apify_filter_wp_v2_compatibility_responses($response, null, new WP_REST_Request(array(), '/wp/v2/Types'));

    eq($filtered->get_data(), array('post' => 'a'));
});

test('redacting the only entry yields an object, not an empty array', function () {
    eventon_test_enable_compat_as_anonymous();

    // An empty PHP array encodes as [], which breaks clients expecting a map.
    $response = new WP_HTTP_Response(array('ajde_events' => 'c'));
    $filtered = eventon_apify_filter_wp_v2_compatibility_responses($response, null, new WP_REST_Request(array(), '/wp/v2/types'));
    $data = $filtered->get_data();

    // eq() is strict, and two empty stdClass instances are equal but not
    // identical, so assert the shape rather than the instance.
    ok($data instanceof stdClass, 'an emptied map must stay an object');
    eq(get_object_vars($data), array());
    eq(json_encode($data), '{}');
});

test('admins receive the unredacted collection responses', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);
    eventon_test_set_current_user_can(true);

    $data = array('post' => 'a', 'ajde_events' => 'c');
    $filtered = eventon_apify_filter_wp_v2_compatibility_responses(new WP_HTTP_Response($data), null, new WP_REST_Request(array(), '/wp/v2/types'));

    eq($filtered->get_data(), $data);
});

// --- shared search queries ------------------------------------------------

test('events are excluded from wp/v2 post search for non-admins', function () {
    eventon_test_enable_compat_as_anonymous();

    $filtered = eventon_apify_filter_wp_v2_compatibility_post_search_query(array('post_type' => array('post', 'page', 'ajde_events')));

    eq($filtered['post_type'], array('post', 'page'));
});

test('an events-only post search is narrowed to a sentinel, never to empty', function () {
    eventon_test_enable_compat_as_anonymous();

    // WP_Query treats an empty post_type as "no restriction", so dropping the
    // last entry without a sentinel would widen the search instead of closing it.
    $filtered = eventon_apify_filter_wp_v2_compatibility_post_search_query(array('post_type' => array('ajde_events')));

    eq($filtered['post_type'], array('__eventon_apify_no_results__'));
    ok(!empty($filtered['post_type']), 'post_type must never be emptied');
});

test('an events-only term search is narrowed to a sentinel, never to empty', function () {
    eventon_test_enable_compat_as_anonymous();

    $filtered = eventon_apify_filter_wp_v2_compatibility_term_search_query(array(
        'taxonomy' => eventon_apify_get_wp_v2_compatibility_taxonomies(),
    ));

    eq($filtered['taxonomy'], array('__eventon_apify_no_results__'));
});

test('unrelated taxonomies survive the term search filter', function () {
    eventon_test_enable_compat_as_anonymous();

    $filtered = eventon_apify_filter_wp_v2_compatibility_term_search_query(array(
        'taxonomy' => array('category', 'event_location', 'post_tag'),
    ));

    eq($filtered['taxonomy'], array('category', 'post_tag'));
});

test('search queries are untouched for admins', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);
    eventon_test_set_current_user_can(true);

    $post_args = array('post_type' => array('post', 'ajde_events'));
    $term_args = array('taxonomy' => array('category', 'event_location'));

    eq(eventon_apify_filter_wp_v2_compatibility_post_search_query($post_args), $post_args);
    eq(eventon_apify_filter_wp_v2_compatibility_term_search_query($term_args), $term_args);
});
