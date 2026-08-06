<?php
/**
 * Regression tests for the 3.2.0 audit fixes in the write path, read path,
 * listing, and payload normalization. Each case pins a specific defect that
 * shipped, so a future refactor cannot silently reintroduce it.
 */

// --- Impossible calendar dates -------------------------------------------

test('build_timestamp rejects rolled-over calendar dates', function () {
    eq(eventon_apify_build_timestamp('2026-02-31', '10:00', 'UTC'), null);
    eq(eventon_apify_build_timestamp('2026-13-01', '10:00', 'UTC'), null);
    ok(eventon_apify_build_timestamp('2026-02-28', '10:00', 'UTC') !== null);
});

test('build_timestamp rejects non ISO date shapes', function () {
    eq(eventon_apify_build_timestamp('next tuesday', '10:00', 'UTC'), null);
    eq(eventon_apify_build_timestamp('2026/02/01', '10:00', 'UTC'), null);
});

// --- HH:MM:SS acceptance -------------------------------------------------

test('split_time_string accepts HH:MM:SS and drops the seconds', function () {
    eq(eventon_apify_split_time_string('09:00:00'), array('hour' => '9', 'minute' => '00'));
    eq(eventon_apify_split_time_string('23:59:59'), array('hour' => '23', 'minute' => '59'));
    eq(eventon_apify_split_time_string('09:00:0'), null);
});

// --- Interaction mode ----------------------------------------------------

test('unknown interaction modes are detectable before normalization', function () {
    ok(!eventon_apify_is_known_interaction_mode('externallink'));
    ok(!eventon_apify_is_known_interaction_mode(''));
    ok(eventon_apify_is_known_interaction_mode('external_link'));
    ok(eventon_apify_is_known_interaction_mode('2'));
    ok(eventon_apify_is_known_interaction_mode('X'));
});

// --- Wall-as-UTC coordinate space ---------------------------------------

test('wall-as-UTC timestamps store the wall clock regardless of event timezone', function () {
    $wall = eventon_apify_build_wall_utc_timestamp('2026-08-10', '10:00');
    eq(gmdate('Y-m-d H:i', $wall), '2026-08-10 10:00');
});

test('wall timestamps format back with the event timezone offset', function () {
    $wall = eventon_apify_build_wall_utc_timestamp('2026-08-10', '10:00');
    eq(eventon_apify_format_wall_timestamp($wall, 'America/Los_Angeles', 'Y-m-d H:i'), '2026-08-10 10:00');
    eq(eventon_apify_format_wall_timestamp($wall, 'America/Los_Angeles', 'c'), '2026-08-10T10:00:00-07:00');
});

test('ISO intervals with a UTC designator convert into the event wall clock', function () {
    // 17:00Z is 10:00 Los Angeles, so the stored wall clock must read 10:00.
    $wall = eventon_apify_build_wall_utc_timestamp_from_iso('2026-08-10T17:00:00Z', 'America/Los_Angeles');
    eq(gmdate('Y-m-d H:i', $wall), '2026-08-10 10:00');
});

test('naive ISO intervals are taken as the event wall clock as-is', function () {
    $wall = eventon_apify_build_wall_utc_timestamp_from_iso('2026-08-10T10:00:00', 'America/Los_Angeles');
    eq(gmdate('Y-m-d H:i', $wall), '2026-08-10 10:00');
});

// --- start_at with an explicit offset -----------------------------------

test('start_at with a UTC designator keeps the instant and pins the timezone', function () {
    $normalized = eventon_apify_normalize_request_payload(array(
        'title' => 'X',
        'start_at' => '2026-04-01T16:00:00Z',
        'timezone' => 'America/Los_Angeles',
    ));

    // 16:00Z is 09:00 Pacific: the wall clock must be converted, not kept.
    eq($normalized['start_date'], '2026-04-01');
    eq($normalized['start_time'], '09:00');
    eq($normalized['timezone_key'], 'America/Los_Angeles');
});

test('naive start_at is preserved verbatim', function () {
    $normalized = eventon_apify_normalize_request_payload(array(
        'start_at' => '2026-04-01T16:00:00',
        'timezone' => 'America/Los_Angeles',
    ));

    eq($normalized['start_date'], '2026-04-01');
    eq($normalized['start_time'], '16:00');
});

// --- fields / custom_fields wrapper -------------------------------------

test('nested objects inside the fields wrapper are normalized', function () {
    $normalized = eventon_apify_normalize_request_payload(array(
        'title' => 'Wrapped',
        'fields' => array(
            'start_date' => '2026-08-13',
            'location' => array('name' => 'Brewery X', 'address' => '3191 E La Palma Ave'),
            'timezone' => array('key' => 'America/Los_Angeles'),
            'flags' => array('featured' => true),
            'rsvp' => array('capacity_count' => 40),
            'virtual' => array('url' => 'https://example.com/stream'),
            'health' => array('mask_required' => true),
            'seo' => array('offer_price' => '10'),
            'repeat' => array('frequency' => 'weekly'),
            'faqs' => array('items' => array(array('question' => 'Q', 'answer' => 'A'))),
            'related_events' => array('items' => array()),
            'interaction' => array('mode' => 'external_link', 'url' => 'https://tickets.example.com'),
        ),
    ));

    eq($normalized['location_name'], 'Brewery X');
    eq($normalized['location_address'], '3191 E La Palma Ave');
    eq($normalized['timezone_key'], 'America/Los_Angeles');
    eq($normalized['featured'], true);
    eq($normalized['rsvp_capacity_count'], 40);
    eq($normalized['virtual_url'], 'https://example.com/stream');
    eq($normalized['health_mask_required'], true);
    eq($normalized['seo_offer_price'], '10');
    eq($normalized['repeat_frequency'], 'weekly');
    eq($normalized['interaction_mode'], 'external_link');
    eq(count($normalized['faq_items']), 1);
});

test('custom_fields wrapper normalizes nested objects too', function () {
    $normalized = eventon_apify_normalize_request_payload(array(
        'custom_fields' => array('location' => array('name' => 'Venue')),
    ));

    eq($normalized['location_name'], 'Venue');
});

test('explicit top-level values win over wrapper values', function () {
    $normalized = eventon_apify_normalize_request_payload(array(
        'start_date' => '2026-01-01',
        'fields' => array('start_date' => '2027-01-01'),
    ));

    eq($normalized['start_date'], '2026-01-01');
});

// --- extend_type alias ---------------------------------------------------

test('extend_type is accepted as a top-level alias of time_extend_type', function () {
    $normalized = eventon_apify_normalize_request_payload(array('extend_type' => 'dl'));
    eq($normalized['time_extend_type'], 'dl');
});

// --- all_day derivation -------------------------------------------------

test('all_day is derived from the EventON extend type, not a parallel flag', function () {
    // EventON expresses all-day as _time_ext_type 'dl'; evcal_allday is a
    // legacy display-only flag it never writes.
    ok(eventon_apify_event_flag_is_all_day(array('_time_ext_type' => array('dl'))));
    ok(eventon_apify_event_flag_is_all_day(array('evcal_allday' => array('yes'))));
    ok(!eventon_apify_event_flag_is_all_day(array('_time_ext_type' => array('n'))));
    ok(!eventon_apify_event_flag_is_all_day(array()));
});

test('all_day is read-only and not writable through the flags map', function () {
    $normalized = eventon_apify_normalize_request_payload(array('flags' => array('all_day' => true)));
    ok(!array_key_exists('all_day', $normalized));
});

// --- datetime input detection (the corruption guard) --------------------

test('datetime input detection distinguishes datetime-bearing requests', function () {
    ok(!eventon_apify_has_datetime_input(array('title' => 'New name')));
    ok(!eventon_apify_has_datetime_input(array('event_color' => 'ff0000')));
    ok(eventon_apify_has_datetime_input(array('start_date' => '2026-01-01')));
    ok(eventon_apify_has_datetime_input(array('time_extend_type' => 'dl')));
    ok(eventon_apify_has_datetime_input(array('hide_end_time' => true)));
    ok(eventon_apify_has_datetime_input(array('virtual_end_enabled' => true)));
});

test('updates without datetime input skip datetime validation entirely', function () {
    // An event whose datetime meta cannot be resolved must still accept a
    // title-only update instead of 400ing on a missing start_date.
    eq(eventon_apify_validate_datetime_fields(array('title' => 'X'), 4242), true);
    ok(is_wp_error(eventon_apify_validate_datetime_fields(array('title' => 'X'), 0)));
});

// --- repeat interval base occurrence ------------------------------------

test('custom intervals always keep the base occurrence at index 0', function () {
    $intervals = eventon_apify_prepend_base_repeat_interval(
        array(array(200, 300), array(400, 500)),
        100,
        150
    );

    eq($intervals[0], array(100, 150));
    eq(count($intervals), 3);
});

test('a client interval equal to the base is not duplicated', function () {
    $intervals = eventon_apify_prepend_base_repeat_interval(
        array(array(100, 150), array(400, 500)),
        100,
        150
    );

    eq($intervals[0], array(100, 150));
    eq(count($intervals), 2);
});

// --- coordinate and moderator validation --------------------------------

test('out-of-range coordinates are rejected', function () {
    eq(eventon_test_validate_code(array('location_lat' => 999)), 'eventon_apify_invalid_location_coordinate');
    eq(eventon_test_validate_code(array('location_lon' => -181)), 'eventon_apify_invalid_location_coordinate');
    ok(eventon_test_validate_code(array('location_lat' => 0, 'location_lon' => 0)) !== 'eventon_apify_invalid_location_coordinate');
    ok(eventon_test_validate_code(array('location_lat' => -33.86)) !== 'eventon_apify_invalid_location_coordinate');
});

test('negative moderator ids are rejected instead of being flipped', function () {
    eq(eventon_test_validate_code(array('virtual_moderator_id' => -7)), 'eventon_apify_invalid_moderator_id');
    ok(eventon_test_validate_code(array('virtual_moderator_id' => 7)) !== 'eventon_apify_invalid_moderator_id');
});

test('malformed emails are rejected rather than silently erasing stored values', function () {
    eq(eventon_test_validate_code(array('location_email' => 'not-an-email')), 'eventon_apify_invalid_email');
    ok(eventon_test_validate_code(array('location_email' => '')) !== 'eventon_apify_invalid_email');
    ok(eventon_test_validate_code(array('location_email' => 'venue@example.com')) !== 'eventon_apify_invalid_email');
    eq(
        eventon_test_validate_code(array('organizers' => array(array('name' => 'A', 'email' => 'bogus')))),
        'eventon_apify_invalid_email'
    );
});

// --- status filter -------------------------------------------------------

test('unknown list status values error instead of falling back silently', function () {
    ok(is_wp_error(eventon_apify_get_requested_statuses('trash')));
    ok(is_wp_error(eventon_apify_get_requested_statuses('publish,bogus')));
    eq(eventon_apify_get_requested_statuses('publish,draft'), array('publish', 'draft'));
    eq(eventon_apify_get_requested_statuses(''), array('publish', 'draft', 'private'));
});

// --- repeat occurrence matching -----------------------------------------

test('a repeating event matches on a later occurrence, not just the base start', function () {
    $june = eventon_apify_build_wall_utc_timestamp('2026-06-01', '10:00');
    $august = eventon_apify_build_wall_utc_timestamp('2026-08-10', '10:00');

    $matches = eventon_apify_occurrence_meta_matches_range(
        array(
            'evcal_srow' => (string) $june,
            'evcal_repeat' => 'yes',
            'repeat_intervals' => serialize(array(array($june, $june + 3600), array($august, $august + 3600))),
        ),
        eventon_apify_build_wall_utc_timestamp('2026-08-01', '00:00'),
        eventon_apify_build_wall_utc_timestamp('2026-09-01', '00:00')
    );

    ok($matches);
});

test('a non-repeating event outside the range does not match', function () {
    $june = eventon_apify_build_wall_utc_timestamp('2026-06-01', '10:00');

    ok(!eventon_apify_occurrence_meta_matches_range(
        array('evcal_srow' => (string) $june),
        eventon_apify_build_wall_utc_timestamp('2026-08-01', '00:00'),
        null
    ));
});

test('repeat intervals are ignored when repeat is disabled', function () {
    $august = eventon_apify_build_wall_utc_timestamp('2026-08-10', '10:00');
    $june = eventon_apify_build_wall_utc_timestamp('2026-06-01', '10:00');

    ok(!eventon_apify_occurrence_meta_matches_range(
        array(
            'evcal_srow' => (string) $june,
            'evcal_repeat' => 'no',
            'repeat_intervals' => serialize(array(array($august, $august + 3600))),
        ),
        eventon_apify_build_wall_utc_timestamp('2026-08-01', '00:00'),
        eventon_apify_build_wall_utc_timestamp('2026-09-01', '00:00')
    ));
});

test('a base start inside the range matches without repeats', function () {
    $august = eventon_apify_build_wall_utc_timestamp('2026-08-10', '10:00');

    ok(eventon_apify_occurrence_meta_matches_range(
        array('evcal_srow' => (string) $august),
        eventon_apify_build_wall_utc_timestamp('2026-08-01', '00:00'),
        eventon_apify_build_wall_utc_timestamp('2026-09-01', '00:00')
    ));
});

test('the upper date bound is exclusive', function () {
    $boundary = eventon_apify_build_wall_utc_timestamp('2026-09-01', '00:00');

    ok(!eventon_apify_occurrence_meta_matches_range(
        array('evcal_srow' => (string) $boundary),
        null,
        $boundary
    ));
});

// --- timezone resolution -------------------------------------------------

test('offset timezone keys are honored instead of falling back to UTC', function () {
    eq(eventon_apify_get_timezone_key_from_meta(array('_evo_tz' => array('-07:00'))), '-07:00');
    eq(eventon_apify_get_timezone_key_from_meta(array('_evo_tz' => array('America/Denver'))), 'America/Denver');
    eq(eventon_apify_get_timezone_key_from_meta(array('_evo_tz' => array('Not/AZone'))), 'UTC');
});

test('formatting honors offset timezone keys', function () {
    // 2026-08-10 17:00 UTC is 10:00 at -07:00; falling back to UTC would
    // report 17:00 while the payload claimed the offset.
    eq(eventon_apify_format_timestamp_for_timezone(1786726800, '-07:00', 'H:i'), '10:00');
});

// --- term ordering -------------------------------------------------------

test('terms are returned in EventON saved order, not alphabetical', function () {
    $terms = array(
        (object) array('term_id' => 11, 'name' => 'Amy Co'),
        (object) array('term_id' => 22, 'name' => 'Zed Productions'),
    );

    $sorted = eventon_apify_sort_terms_by_saved_order($terms, '22,11');

    eq($sorted[0]->name, 'Zed Productions');
    eq($sorted[1]->name, 'Amy Co');
});

test('terms keep their given order when no saved order exists', function () {
    $terms = array(
        (object) array('term_id' => 11, 'name' => 'Amy Co'),
        (object) array('term_id' => 22, 'name' => 'Zed Productions'),
    );

    $sorted = eventon_apify_sort_terms_by_saved_order($terms, '');
    eq($sorted[0]->name, 'Amy Co');
});

// --- wp/v2 route casing --------------------------------------------------

test('compatibility route matching is case-insensitive', function () {
    ok(eventon_apify_is_wp_v2_compatibility_route('/wp/v2/ajde_events'));
    ok(eventon_apify_is_wp_v2_compatibility_route('/wp/v2/AJDE_events'));
    ok(eventon_apify_is_wp_v2_compatibility_route('/wp/v2/ajde_events/15803'));
    ok(!eventon_apify_is_wp_v2_compatibility_route('/wp/v2/posts'));
});

test('the compatibility taxonomy fallback covers every registered taxonomy', function () {
    $taxonomies = eventon_apify_get_wp_v2_compatibility_taxonomies();

    foreach (array('event_type', 'event_type_2', 'event_type_3', 'event_type_4', 'event_location', 'event_organizer') as $taxonomy) {
        ok(in_array($taxonomy, $taxonomies, true), $taxonomy . ' must be guarded');
    }
});

// --- term meta falsy preservation ---------------------------------------

test('a zero coordinate survives the term meta store', function () {
    EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 409, array(
        'location_address' => 'Equator St',
        'location_lat' => '0',
        'location_lon' => '6.6',
    ));

    $stored = get_option('evo_tax_meta', array());
    eq($stored['event_location'][409]['location_lat'], '0');
    eq($stored['event_location'][409]['location_lon'], '6.6');
});

test('term meta merges rather than replacing siblings, and empty clears', function () {
    EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 7, array(
        'location_address' => 'A St',
        'location_city' => 'Anaheim',
    ));
    EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 7, array('location_city' => ''));

    $stored = get_option('evo_tax_meta', array());
    eq($stored['event_location'][7]['location_address'], 'A St');
    ok(!array_key_exists('location_city', $stored['event_location'][7]));
});
