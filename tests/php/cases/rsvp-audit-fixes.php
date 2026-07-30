<?php
/**
 * Tests for the 3.2.0 RSVP audit fixes: waitlist normalization and filtering,
 * EventON-aligned summary math, repeat_interval filtering, custom-field meta
 * exclusions, the event-time object cache, and orphaned RSVP cleanup.
 */

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($value) {
        return $value;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value) {
        return json_encode($value);
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id) {
        return $GLOBALS['__eventon_test_post_type_by_id'][$post_id] ?? false;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = array()) {
        $GLOBALS['__eventon_test_get_posts_args'][] = $args;
        return $GLOBALS['__eventon_test_get_posts_result'] ?? array();
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, $force_delete = false) {
        $GLOBALS['__eventon_test_deleted_posts'][] = array($post_id, $force_delete);
        return true;
    }
}

if (!class_exists('EVORS_Event')) {
    class EVORS_Event {
        public static $constructions = 0;
        public $event;

        public function __construct($event_id, $repeat_interval = 0) {
            self::$constructions++;
            $this->event = new class {
                public function get_formatted_smart_time($repeat_interval = 0) {
                    return 'time-' . $repeat_interval;
                }
            };
        }
    }
}

function eventon_test_reset_rsvp_cleanup_state() {
    $GLOBALS['__eventon_test_post_type_by_id'] = array();
    $GLOBALS['__eventon_test_get_posts_args'] = array();
    $GLOBALS['__eventon_test_get_posts_result'] = array();
    $GLOBALS['__eventon_test_deleted_posts'] = array();
}

function eventon_test_waitlist_attendees() {
    return array(
        array('id' => 1, 'rsvp' => 'yes', 'status' => 'check-in', 'headcount' => 3, 'repeat_interval' => 0),
        array('id' => 2, 'rsvp' => 'yes', 'status' => 'waitlist', 'headcount' => 2, 'repeat_interval' => 0),
        array('id' => 3, 'rsvp' => 'waitlist', 'status' => '', 'headcount' => 1, 'repeat_interval' => 1),
        array('id' => 4, 'rsvp' => 'no', 'status' => '', 'headcount' => 1, 'repeat_interval' => 1),
        array('id' => 5, 'rsvp' => 'yes', 'status' => '', 'headcount' => 0, 'repeat_interval' => 0),
    );
}

test('normalize maps w to waitlist alongside y/n/m', function () {
    eq(eventon_apify_normalize_rsvp_response('w'), 'waitlist');
    eq(eventon_apify_normalize_rsvp_response('Waitlist'), 'waitlist');
    eq(eventon_apify_normalize_rsvp_response('Y'), 'yes');
    eq(eventon_apify_normalize_rsvp_response('n'), 'no');
    eq(eventon_apify_normalize_rsvp_response('m'), 'maybe');
});

test('rsvp filter sanitizer accepts waitlist and falls back to all', function () {
    eq(eventon_apify_sanitize_rsvp_response_filter('waitlist'), 'waitlist');
    eq(eventon_apify_sanitize_rsvp_response_filter('w'), 'waitlist');
    eq(eventon_apify_sanitize_rsvp_response_filter('yes'), 'yes');
    eq(eventon_apify_sanitize_rsvp_response_filter('bogus'), 'all');
    eq(eventon_apify_sanitize_rsvp_response_filter(''), 'all');
});

test('rsvp filter waitlist selects waitlist responses and waitlisted check-ins', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_waitlist_attendees(), 'waitlist', '', '');
    eq(array_column($result, 'id'), array(2, 3));
});

test('repeat_interval filter narrows attendees to one instance', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_waitlist_attendees(), 'all', '', '', 1);
    eq(array_column($result, 'id'), array(3, 4));

    $result = eventon_apify_filter_rsvp_attendees(eventon_test_waitlist_attendees(), 'all', '', '', null);
    eq(count($result), 5);
});

test('repeat_interval filter parser distinguishes absent from zero', function () {
    eq(eventon_apify_parse_rsvp_repeat_interval_filter(null), null);
    eq(eventon_apify_parse_rsvp_repeat_interval_filter(''), null);
    eq(eventon_apify_parse_rsvp_repeat_interval_filter(0), 0);
    eq(eventon_apify_parse_rsvp_repeat_interval_filter('2'), 2);
});

test('summary math skips zero headcounts and buckets waitlist separately', function () {
    $summary = eventon_apify_summarize_rsvp_attendees(eventon_test_waitlist_attendees());
    eq($summary['yes_submissions'], 1);
    eq($summary['yes_attendees_total'], 3);
    eq($summary['yes_additional_attendees'], 2);
    eq($summary['waitlist_records'], 2);
    eq($summary['waitlist_attendees_total'], 3);
});

test('custom fields exclude repeat_interval, uid, and lang meta', function () {
    $fields = eventon_apify_get_rsvp_custom_fields(
        array(
            'repeat_interval' => array('2'),
            'uid' => array('abc123'),
            'lang' => array('L1'),
            'tshirt_size' => array('XL'),
        )
    );
    eq($fields, array('tshirt_size' => 'XL'));
});

test('event time builds one EVORS_Event per event and interval pair', function () {
    EVORS_Event::$constructions = 0;

    eq(eventon_apify_get_rsvp_event_time(9001, 2), 'time-2');
    eq(eventon_apify_get_rsvp_event_time(9001, 2), 'time-2');
    eq(EVORS_Event::$constructions, 1);

    eq(eventon_apify_get_rsvp_event_time(9001, 3), 'time-3');
    eq(EVORS_Event::$constructions, 2);
});

test('event delete cleanup force-deletes matching rsvp posts', function () {
    eventon_test_reset_rsvp_cleanup_state();
    $GLOBALS['__eventon_test_post_types']['evo-rsvp'] = true;
    $GLOBALS['__eventon_test_post_type_by_id'][42] = 'ajde_events';
    $GLOBALS['__eventon_test_get_posts_result'] = array(7, 8);

    eventon_apify_delete_event_rsvps_on_event_delete(42);

    eq(count($GLOBALS['__eventon_test_get_posts_args']), 1);
    $args = $GLOBALS['__eventon_test_get_posts_args'][0];
    eq($args['post_type'], 'evo-rsvp');
    eq($args['meta_key'], 'e_id');
    eq($args['meta_value'], '42');
    eq($GLOBALS['__eventon_test_deleted_posts'], array(array(7, true), array(8, true)));
});

test('event delete cleanup ignores other post types', function () {
    eventon_test_reset_rsvp_cleanup_state();
    $GLOBALS['__eventon_test_post_types']['evo-rsvp'] = true;
    $GLOBALS['__eventon_test_post_type_by_id'][43] = 'post';

    eventon_apify_delete_event_rsvps_on_event_delete(43);

    eq($GLOBALS['__eventon_test_get_posts_args'], array());
    eq($GLOBALS['__eventon_test_deleted_posts'], array());
});

test('event delete cleanup requires the evo-rsvp post type', function () {
    eventon_test_reset_rsvp_cleanup_state();
    $GLOBALS['__eventon_test_post_type_by_id'][44] = 'ajde_events';

    eventon_apify_delete_event_rsvps_on_event_delete(44);

    eq($GLOBALS['__eventon_test_get_posts_args'], array());
    eq($GLOBALS['__eventon_test_deleted_posts'], array());
});
