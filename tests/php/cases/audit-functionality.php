<?php
/**
 * Phase 3 audit fixes: functionality and extensibility.
 */

// --- F-1: RSVP cascade on permanent event delete is a visible setting ------

test('a fresh install seeds the RSVP cascade setting on', function () {
    eventon_apify_bootstrap_settings();

    eq(get_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS, null), true);
});

test('an upgrade whose backup predates the setting keeps the cascade on', function () {
    update_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, array('enable_api' => true));

    eventon_apify_bootstrap_settings();

    eq(get_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS, null), true);
});

test('a backed-up opt-out is restored', function () {
    update_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, array('cascade_delete_rsvps' => false));

    eventon_apify_bootstrap_settings();

    eq(get_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS, null), false);
});

test('the settings backup records the cascade setting and tracks its changes', function () {
    update_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS, false);
    eventon_apify_sync_settings_backup();

    eq(get_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP)['cascade_delete_rsvps'], false);
    ok(eventon_apify_is_tracked_settings_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS));
});

test('the cascade does nothing when the setting is off', function () {
    update_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS, false);
    $GLOBALS['__eventon_test_post_type_by_id'][42] = 'ajde_events';
    $GLOBALS['__eventon_test_post_types']['evo-rsvp'] = true;
    $GLOBALS['__eventon_test_get_posts_result'] = array(501, 502);

    eventon_apify_delete_event_rsvps_on_event_delete(42);

    eq($GLOBALS['__eventon_test_get_posts_args'], array(), 'no RSVP lookup');
    eq($GLOBALS['__eventon_test_deleted_posts'], array());
});

test('the composition root registers the RSVP cascade', function () {
    $method = new ReflectionMethod('EventON_APIfy\Plugin', 'register_hooks');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true); // Required on 8.0; a deprecated no-op from 8.5.
    }
    $method->invoke(null);

    ok(in_array('eventon_apify_delete_event_rsvps_on_event_delete', $GLOBALS['__eventon_test_actions']['before_delete_post'] ?? array(), true));
});

// --- F-2: create inserts as draft and publishes only after meta and terms ---

test('create publishes through a status transition only when the target is not draft', function () {
    eq(eventon_apify_get_create_status_transition(5, 'draft'), array());
    eq(eventon_apify_get_create_status_transition(5, 'publish'), array('ID' => 5, 'post_status' => 'publish'));
    eq(eventon_apify_get_create_status_transition(5, 'future'), array('ID' => 5, 'post_status' => 'future'));
});

test('a created event gets its final status after meta and terms are saved', function () {
    $result = EventON_APIfy_Event_Write_Coordinator::persist(5, array(), true, array('ID' => 5, 'post_status' => 'publish'));

    eq($result, true);
    eq($GLOBALS['__eventon_test_wp_update_post_calls'], array(array('ID' => 5, 'post_status' => 'publish')));
});

test('a failed publish rolls the created event back and redacts the error', function () {
    $GLOBALS['__eventon_test_wp_update_post_result'] = new WP_Error('db_update_error', 'Could not update post in the database.', 'Lock wait timeout');

    $result = EventON_APIfy_Event_Write_Coordinator::persist(5, array(), true, array('ID' => 5, 'post_status' => 'publish'));

    ok(is_wp_error($result));
    eq($result->get_error_data(), array('status' => 500));
    eq($GLOBALS['__eventon_test_deleted_posts'], array(array(5, true)));
});

// --- F-3: extension hooks -------------------------------------------------

test('a successful write fires eventon_apify_event_saved once', function () {
    EventON_APIfy_Event_Write_Coordinator::persist(5, array('title' => 'Ride'), true, array());

    eq($GLOBALS['__eventon_test_fired_actions'], array(
        array('eventon_apify_event_saved', array(5, array('title' => 'Ride'), true)),
    ));
});

test('a failed write does not fire eventon_apify_event_saved', function () {
    $GLOBALS['__eventon_test_wp_update_post_result'] = new WP_Error('db_update_error', 'x', 'y');

    EventON_APIfy_Event_Write_Coordinator::persist(5, array(), false, array('ID' => 5, 'post_title' => 'Ride'));

    eq($GLOBALS['__eventon_test_fired_actions'], array());
});

test('trashing an event fires eventon_apify_event_deleted', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, true);
    $post = (object) array('ID' => 12, 'post_type' => 'ajde_events', 'post_title' => 'Ride');
    $GLOBALS['__eventon_test_posts'][12] = $post;

    eventon_apify_delete_event(new WP_REST_Request(array('id' => 12)));

    eq($GLOBALS['__eventon_test_trashed_posts'], array(12));
    eq($GLOBALS['__eventon_test_fired_actions'], array(array('eventon_apify_event_deleted', array(12, $post))));
});

test('a failed trash does not fire eventon_apify_event_deleted', function () {
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, true);
    $GLOBALS['__eventon_test_posts'][12] = (object) array('ID' => 12, 'post_type' => 'ajde_events', 'post_title' => 'Ride');
    $GLOBALS['__eventon_test_wp_trash_post_result'] = false;

    ok(is_wp_error(eventon_apify_delete_event(new WP_REST_Request(array('id' => 12)))));
    eq($GLOBALS['__eventon_test_fired_actions'], array());
});

test('the validation filter can reject a payload and cannot unset an error', function () {
    eventon_test_add_filter_callback('eventon_apify_validate_event_payload', static function ($result, $params, $is_create, $post_id) {
        return $params['title'] === 'blocked'
            ? new WP_Error('site_rule', 'Blocked by site rule.', array('status' => 422))
            : 'not-a-valid-result';
    });

    $rejected = eventon_apify_validate_event_payload(array('title' => 'blocked'), false, 5);
    ok(is_wp_error($rejected));
    eq($rejected->get_error_code(), 'site_rule');

    eq(eventon_apify_validate_event_payload(array('title' => 'fine'), false, 5), true, 'junk filter output normalizes to true');

    // A built-in error must win: the filter only runs once built-in checks pass.
    $builtin = eventon_apify_validate_event_payload(array('title' => ''), false, 5);
    eq($builtin->get_error_code(), 'eventon_apify_invalid_title');
});
