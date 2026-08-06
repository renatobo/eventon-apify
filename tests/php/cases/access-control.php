<?php
/**
 * Tests for the read-only ARMember access_control payload.
 *
 * Case files load in glob order, so this file's early tests run while
 * get_arm_term_meta() is still undefined; a later test defines the stub
 * (matching ARMember's get_metadata('arm_term', ...) helper) to exercise
 * the term-level branch.
 */

test('access control returns the empty state without ARMember data', function () {
    eq(eventon_apify_get_access_control_payload(123, array()), array(
        'restricted' => false,
        'provider' => '',
        'membership_plan_ids' => array(),
        'restricted_by' => array(),
    ));
});

test('post-level arm_access_plan rows mark the event restricted, 0 marker stripped', function () {
    $payload = eventon_apify_get_access_control_payload(123, array(
        'arm_access_plan' => array('0', '7', '3'),
    ));

    eq($payload['restricted'], true);
    eq($payload['provider'], 'armember');
    eq($payload['membership_plan_ids'], array(3, 7));
    eq($payload['restricted_by'], array('post'));
});

test('protected with no plan assigned yields restricted with empty plan list', function () {
    $payload = eventon_apify_get_access_control_payload(123, array(
        'arm_access_plan' => array('0'),
    ));

    eq($payload['restricted'], true);
    eq($payload['membership_plan_ids'], array());
    eq($payload['restricted_by'], array('post'));
});

test('term branch is skipped without a fatal when get_arm_term_meta is undefined', function () {
    ok(!function_exists('get_arm_term_meta'));

    $payload = eventon_apify_get_access_control_payload(123, array());
    eq($payload['restricted'], false);
});

test('a protected term marks the event restricted by term and merges its plans', function () {
    if (!function_exists('get_arm_term_meta')) {
        function get_arm_term_meta($term_id, $key, $single = false) {
            $rows = $GLOBALS['__eventon_test_arm_term_meta'][$term_id][$key] ?? array();
            if ($single) {
                return $rows[0] ?? '';
            }
            return $rows;
        }
    }
    if (!function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($post_id, $taxonomy, $args = array()) {
            return $GLOBALS['__eventon_test_terms'][$post_id][$taxonomy] ?? array();
        }
    }

    $GLOBALS['__eventon_test_terms'] = array(
        123 => array('event_type' => array((object) array('term_id' => 55))),
    );
    $GLOBALS['__eventon_test_arm_term_meta'] = array(
        55 => array(
            'arm_protection' => array('1'),
            'arm_access_plan' => array('4', '9'),
        ),
    );

    $payload = eventon_apify_get_access_control_payload(123, array(
        'arm_access_plan' => array('0', '4'),
    ));

    eq($payload['restricted'], true);
    eq($payload['provider'], 'armember');
    eq($payload['membership_plan_ids'], array(4, 9));
    eq($payload['restricted_by'], array('post', 'term'));
});

test('unprotected terms do not restrict the event', function () {
    $GLOBALS['__eventon_test_terms'] = array(
        123 => array('event_type' => array((object) array('term_id' => 55))),
    );
    $GLOBALS['__eventon_test_arm_term_meta'] = array(
        55 => array('arm_protection' => array('')),
    );

    $payload = eventon_apify_get_access_control_payload(123, array());
    eq($payload['restricted'], false);
    eq($payload['restricted_by'], array());
});

test('access_control is read-only and excluded from wp/v2 mutable fields', function () {
    $definitions = eventon_apify_get_contract_field_definitions();
    eq($definitions['access_control']['read_only'], true);
    eq($definitions['access_control']['wp_v2_field_mode'], 'read_only');

    ok(!in_array('access_control', eventon_apify_get_wp_v2_mutable_field_names(), true));
    ok(in_array('access_control', eventon_apify_get_wp_v2_read_only_field_names(), true));
});

test('access control reads from the supplied meta array, not get_post_meta', function () {
    $GLOBALS['__eventon_test_terms'] = array();
    $GLOBALS['__eventon_test_arm_term_meta'] = array();

    // Seed the post-meta store with a restriction the $meta argument lacks:
    // a builder that re-fetched meta would report restricted here.
    $GLOBALS['__eventon_test_post_meta'][123] = array(
        'arm_access_plan' => array('0', '3'),
    );

    $payload = eventon_apify_get_access_control_payload(123, array());
    eq($payload['restricted'], false);

    // And the inverse: data present only in $meta is honored.
    $GLOBALS['__eventon_test_post_meta'][123] = array();
    $payload = eventon_apify_get_access_control_payload(123, array(
        'arm_access_plan' => array('0', '3'),
    ));
    eq($payload['membership_plan_ids'], array(3));
});
