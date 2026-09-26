<?php
/**
 * Phase 4 audit fixes: bounded work per request.
 */

// --- P-2: repeat generation limits ----------------------------------------

test('repeat limits default to 500 occurrences, a gap of 365, and 500 intervals', function () {
    eq(eventon_apify_get_repeat_limits(), array('repeat_count' => 500, 'repeat_gap' => 365, 'repeat_intervals' => 500));
});

test('repeat values at the limit pass and one over is a 400', function () {
    eq(eventon_apify_validate_repeat_bounds(array('repeat_count' => 500, 'repeat_gap' => 365)), true);

    foreach (array('repeat_count' => 501, 'repeat_gap' => 366) as $key => $value) {
        $error = eventon_apify_validate_repeat_bounds(array($key => $value));
        ok(is_wp_error($error), $key . ' over the limit');
        eq($error->get_error_code(), 'eventon_apify_repeat_limit_exceeded');
        eq($error->get_error_data(), array('status' => 400));
    }
});

test('too many repeat intervals is a 400 before any interval is parsed', function () {
    // Items that would fail interval parsing: the bound must reject first.
    $error = eventon_apify_validate_event_payload(array('repeat_intervals' => array_fill(0, 501, 'not-an-interval')), false, 5);

    ok(is_wp_error($error));
    eq($error->get_error_code(), 'eventon_apify_repeat_limit_exceeded');
});

test('sites can raise the repeat limits with a filter', function () {
    eventon_test_add_filter_callback('eventon_apify_repeat_limits', static function (array $limits) {
        $limits['repeat_count'] = 1000;
        return $limits;
    });

    eq(eventon_apify_validate_repeat_bounds(array('repeat_count' => 1000)), true);
});

test('saving repeat meta clamps count and gap even without validation', function () {
    $GLOBALS['__eventon_test_post_meta'][5] = array();

    eventon_apify_save_repeat_meta(5, array('repeat_enabled' => true, 'repeat_frequency' => 'daily', 'repeat_count' => 100000, 'repeat_gap' => 100000));

    eq(get_post_meta(5, 'evcal_rep_num', true), 500);
    eq(get_post_meta(5, 'evcal_rep_gap', true), 365);
});

test('the published repeat contract carries the limits', function () {
    $shape = eventon_apify_get_repeat_contract_shape();

    eq($shape['count']['maximum'], 500);
    eq($shape['gap']['maximum'], 365);
    eq($shape['intervals']['max_items'], 500);
});

// --- P-1: RSVP paging in SQL and a light summary ---------------------------

/**
 * Seed RSVP posts for event 77 with core-shaped meta (key => list of values).
 */
function eventon_test_seed_rsvps($count) {
    if (!class_exists('EventON_rsvp')) {
        eval('class EventON_rsvp {}');
    }
    $GLOBALS['__eventon_test_post_types']['evo-rsvp'] = true;
    update_option(EVENTON_APIFY_OPTION_ENABLE_API, true);
    update_option(EVENTON_APIFY_OPTION_API_CAPABILITIES, array('rsvp_attendees' => true, 'rsvp_counts' => true));
    $GLOBALS['__eventon_test_posts'][77] = (object) array('ID' => 77, 'post_type' => 'ajde_events', 'post_title' => 'Ride');

    $responses = array('y', 'n', 'm', 'w');
    for ($id = 1001; $id < 1001 + $count; $id++) {
        $GLOBALS['__eventon_test_wp_query_posts'][] = new WP_Post(array(
            'ID' => $id,
            'post_type' => 'evo-rsvp',
            'post_title' => 'Rider ' . $id,
            'post_date_gmt' => '2026-01-01 00:00:00',
        ));
        $GLOBALS['__eventon_test_post_meta'][$id] = array(
            'e_id' => array('77'),
            'first_name' => array('Rider'),
            'last_name' => array((string) $id),
            'rsvp' => array($responses[$id % 4]),
            'count' => array((string) ($id % 3)),
            'status' => array($id % 5 === 0 ? 'checked' : 'check-in'),
            'repeat_interval' => array((string) ($id % 2)),
        );
    }
}

test('only unfiltered RSVP lists page in SQL', function () {
    ok(eventon_apify_rsvp_list_can_page_in_sql('all', 'all', '', null, null));
    ok(eventon_apify_rsvp_list_can_page_in_sql('all', '', '', null, null));
    ok(!eventon_apify_rsvp_list_can_page_in_sql('yes', 'all', '', null, null));
    ok(!eventon_apify_rsvp_list_can_page_in_sql('all', 'checked', '', null, null));
    ok(!eventon_apify_rsvp_list_can_page_in_sql('all', 'all', 'rider', null, null));
    ok(!eventon_apify_rsvp_list_can_page_in_sql('all', 'all', '', 0, null));
    ok(!eventon_apify_rsvp_list_can_page_in_sql('all', 'all', '', null, new DateTimeImmutable('2026-01-01')));
});

test('an unfiltered RSVP page queries one page, not the whole list', function () {
    eventon_test_seed_rsvps(12);

    eventon_apify_get_event_rsvps(new WP_REST_Request(array('id' => 77, 'page' => 2, 'per_page' => 5, 'rsvp' => 'all', 'status' => 'all')));

    $args = $GLOBALS['__eventon_test_wp_query_args'][0];
    eq($args['posts_per_page'], 5);
    eq($args['paged'], 2);
    ok(empty($args['no_found_rows']), 'the page query must count the total');
    eq(array($args['orderby'], $args['order'], $args['post_status']), array('ID', 'DESC', 'publish'));
});

test('the SQL page matches the in-PHP page for every page, including past the end', function () {
    eventon_test_seed_rsvps(12);

    foreach (array(1, 2, 3, 4) as $page) {
        $fast = eventon_apify_get_event_rsvps(new WP_REST_Request(array('id' => 77, 'page' => $page, 'per_page' => 5, 'rsvp' => 'all', 'status' => 'all')));
        // A search every fixture row matches forces the in-PHP path over the same set.
        $slow = eventon_apify_get_event_rsvps(new WP_REST_Request(array('id' => 77, 'page' => $page, 'per_page' => 5, 'rsvp' => 'all', 'status' => 'all', 'search' => 'rider')));

        eq(json_encode($fast), json_encode($slow), 'page ' . $page);
    }

    eq(eventon_apify_get_event_rsvps(new WP_REST_Request(array('id' => 77, 'page' => 4, 'per_page' => 5, 'rsvp' => 'all', 'status' => 'all')))['total'], 12, 'past-the-end page keeps the real total');
});

test('the light RSVP summary matches the summary of fully formatted attendees', function () {
    eventon_test_seed_rsvps(40);

    foreach (array(null, 0, 1) as $repeat_interval) {
        $params = array('id' => 77);
        if ($repeat_interval !== null) {
            $params['repeat_interval'] = $repeat_interval;
        }
        $summary = eventon_apify_get_event_rsvp_summary(new WP_REST_Request($params));

        $full = eventon_apify_filter_rsvp_attendees(eventon_apify_get_event_rsvp_attendees(77), 'all', '', '', $repeat_interval);
        $expected = array_merge(array('event_id' => 77, 'event_title' => 'Ride'), eventon_apify_summarize_rsvp_attendees($full));

        eq($summary, $expected, 'repeat_interval ' . var_export($repeat_interval, true));
    }
});

test('the RSVP summary does not build full attendee payloads', function () {
    eventon_test_seed_rsvps(3);
    $formatted = 0;
    eventon_test_add_filter_callback('eventon_apify_format_rsvp_attendee', static function ($attendee) use (&$formatted) {
        $formatted++;
        return $attendee;
    });

    eventon_apify_get_event_rsvp_summary(new WP_REST_Request(array('id' => 77)));

    eq($formatted, 0);
});

test('the RSVP summary totals match hand-computed fixture values', function () {
    // 1001..1008: rsvp by id % 4 (n, m, w, y), headcount id % 3.
    // Waitlist: 1003 (1) and 1007 (2). Yes: 1004 (2); 1008 has headcount 0.
    eventon_test_seed_rsvps(8);

    eq(eventon_apify_get_event_rsvp_summary(new WP_REST_Request(array('id' => 77))), array(
        'event_id' => 77,
        'event_title' => 'Ride',
        'yes_submissions' => 1,
        'yes_attendees_total' => 2,
        'yes_additional_attendees' => 1,
        'waitlist_records' => 2,
        'waitlist_attendees_total' => 3,
    ));
});

// --- P-3: settings bootstrap fast path ------------------------------------

function eventon_test_seed_bootstrapped_settings() {
    eventon_apify_bootstrap_settings();
    $GLOBALS['__eventon_test_option_reads'] = array();
}

test('a bootstrapped install at the current version skips the backup entirely', function () {
    eventon_test_seed_bootstrapped_settings();

    eventon_apify_bootstrap_settings();

    ok(!in_array(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, $GLOBALS['__eventon_test_option_reads'], true), 'backup must not be read');
});

test('a version change runs the full bootstrap and refreshes the backup', function () {
    eventon_test_seed_bootstrapped_settings();
    update_option(EVENTON_APIFY_OPTION_INSTALLED_VERSION, '0.0.0-older');

    eventon_apify_bootstrap_settings();

    eq(get_option(EVENTON_APIFY_OPTION_INSTALLED_VERSION), EVENTON_APIFY_VERSION);
    eq(get_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP)['version'], EVENTON_APIFY_VERSION);
});

test('a missing option at the current version is still restored', function () {
    eventon_test_seed_bootstrapped_settings();
    update_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP, array('cascade_delete_rsvps' => false) + get_option(EVENTON_APIFY_OPTION_SETTINGS_BACKUP));
    delete_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS);

    eventon_apify_bootstrap_settings();

    eq(get_option(EVENTON_APIFY_OPTION_CASCADE_DELETE_RSVPS, null), false);
});

test('the installed-version marker is autoloaded so the fast path costs no query', function () {
    eventon_apify_bootstrap_settings();

    eq($GLOBALS['__eventon_test_option_autoload'][EVENTON_APIFY_OPTION_INSTALLED_VERSION] ?? null, true);
});

// --- P-4: one delta-sync timestamp write per RSVP per request -------------

test('RSVP meta changes queue one timestamp write, taken at shutdown', function () {
    $GLOBALS['__eventon_test_post_type_by_id'][900] = 'evo-rsvp';

    foreach (array('first_name', 'last_name', 'email', 'count', 'status') as $key) {
        eventon_apify_touch_rsvp_post_on_meta_change(1, 900, $key);
    }

    eq(get_post_meta(900, EVENTON_APIFY_RSVP_UPDATED_AT_META, true), '', 'nothing written before shutdown');
    eq(eventon_apify_get_pending_rsvp_touches(), array(900));

    eventon_apify_flush_rsvp_touches();

    ok(get_post_meta(900, EVENTON_APIFY_RSVP_UPDATED_AT_META, true) !== '', 'written at shutdown');
    eq(eventon_apify_get_pending_rsvp_touches(), array());
});

test('saves and meta changes across RSVPs are all flushed', function () {
    $GLOBALS['__eventon_test_post_type_by_id'][901] = 'evo-rsvp';
    eventon_apify_touch_rsvp_post_on_save(900, new WP_Post(array('ID' => 900, 'post_type' => 'evo-rsvp')));
    eventon_apify_touch_rsvp_post_on_meta_change(1, 901, 'email');
    eventon_apify_touch_rsvp_post_on_meta_change(1, 901, EVENTON_APIFY_RSVP_UPDATED_AT_META);

    eq(eventon_apify_get_pending_rsvp_touches(), array(900, 901));
    eventon_apify_flush_rsvp_touches();

    ok(get_post_meta(900, EVENTON_APIFY_RSVP_UPDATED_AT_META, true) !== '');
    ok(get_post_meta(901, EVENTON_APIFY_RSVP_UPDATED_AT_META, true) !== '');
});

test('the composition root flushes queued RSVP timestamps on shutdown', function () {
    $method = new ReflectionMethod('EventON_APIfy\Plugin', 'register_hooks');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true); // Required on 8.0; a deprecated no-op from 8.5.
    }
    $method->invoke(null);

    ok(in_array('eventon_apify_flush_rsvp_touches', $GLOBALS['__eventon_test_actions']['shutdown'] ?? array(), true));
});
