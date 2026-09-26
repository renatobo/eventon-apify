<?php
/**
 * Phase 1 audit fixes: WordPress write errors must not leak database detail.
 */

test('db write errors keep code and message but drop the SQL error data', function () {
    $core = new WP_Error(
        'db_insert_error',
        'Could not insert post into the database.',
        "Duplicate entry '42' for key 'PRIMARY'"
    );

    $redacted = eventon_apify_redact_wp_write_error($core);

    eq($redacted->get_error_code(), 'db_insert_error');
    eq($redacted->get_error_message(), 'Could not insert post into the database.');
    eq($redacted->get_error_data(), array('status' => 500));
});

test('write errors that already carry a status keep it and nothing else', function () {
    $core = new WP_Error('invalid_post', 'Invalid post ID.', array('status' => 404, 'sql' => 'SELECT 1'));

    eq(eventon_apify_redact_wp_write_error($core)->get_error_data(), array('status' => 404));
});

// --- S-2: guard only what compatibility mode itself exposed ---------------

/**
 * Run the registration filters the way init would, with EventON's own
 * show_in_rest value for the post type and every whitelisted taxonomy.
 */
function eventon_test_register_eventon_objects($post_type_native, $taxonomies_native) {
    eventon_apify_filter_post_type_args_for_wp_v2_compat(array('show_in_rest' => $post_type_native), 'ajde_events');

    foreach (array('event_type', 'event_type_2', 'event_type_3', 'event_type_4', 'event_location', 'event_organizer') as $taxonomy) {
        eventon_apify_filter_taxonomy_args_for_wp_v2_compat(array('show_in_rest' => $taxonomies_native), $taxonomy);
    }
}

test('a natively REST-enabled post type keeps its registration args', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);

    $native = array('show_in_rest' => true, 'rest_base' => 'events-native');

    eq(eventon_apify_filter_post_type_args_for_wp_v2_compat($native, 'ajde_events'), $native);
});

test('natively exposed EventON routes stay open to non-admins in compatibility mode', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);
    eventon_test_register_eventon_objects(true, true);
    eventon_test_set_current_user_can(false);

    foreach (array('/wp/v2/ajde_events', '/wp/v2/ajde_events/42', '/wp/v2/event_type', '/wp/v2/types/ajde_events') as $route) {
        eq(
            eventon_apify_restrict_wp_v2_compatibility_routes('untouched', null, new WP_REST_Request(array(), $route)),
            'untouched',
            $route . ' is EventON\'s own route'
        );
    }

    $endpoints = array('/wp/v2/ajde_events' => 'keep', '/wp/v2/event_location' => 'keep');
    eq(eventon_apify_filter_wp_v2_compatibility_endpoints($endpoints), $endpoints);

    $types = array('post' => 'a', 'ajde_events' => 'c');
    eq(eventon_apify_filter_wp_v2_compatibility_responses(new WP_HTTP_Response($types), null, new WP_REST_Request(array(), '/wp/v2/types'))->get_data(), $types);

    $post_args = array('post_type' => array('post', 'ajde_events'));
    eq(eventon_apify_filter_wp_v2_compatibility_post_search_query($post_args), $post_args);

    $term_args = array('taxonomy' => array('category', 'event_location'));
    eq(eventon_apify_filter_wp_v2_compatibility_term_search_query($term_args), $term_args);
});

test('only the objects compatibility mode exposed are guarded when support is mixed', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_WP_V2_COMPAT, true);
    eventon_test_register_eventon_objects(false, true);
    eventon_test_set_current_user_can(false);

    ok(is_wp_error(eventon_apify_restrict_wp_v2_compatibility_routes(null, null, new WP_REST_Request(array(), '/wp/v2/ajde_events'))), 'plugin-exposed post type is guarded');
    eq(eventon_apify_restrict_wp_v2_compatibility_routes('untouched', null, new WP_REST_Request(array(), '/wp/v2/event_type')), 'untouched', 'native taxonomy is not');

    $filtered = eventon_apify_filter_wp_v2_compatibility_term_search_query(array('taxonomy' => array('category', 'event_location')));
    eq($filtered['taxonomy'], array('category', 'event_location'));
});

// --- S-1c: the update path redacts core write errors too ------------------

test('a failed post update in the coordinator drops the SQL error data', function () {
    $GLOBALS['__eventon_test_wp_update_post_result'] = new WP_Error(
        'db_update_error',
        'Could not update post in the database.',
        'Deadlock found when trying to get lock'
    );

    $result = EventON_APIfy_Event_Write_Coordinator::persist(7, array(), false, array('ID' => 7, 'post_title' => 'Ride'));

    ok(is_wp_error($result));
    eq($result->get_error_code(), 'db_update_error');
    eq($result->get_error_data(), array('status' => 500));
});
