<?php
/**
 * Phase 2 audit fixes: rollback touches only what the failed write changed.
 */

/**
 * Call the coordinator's private meta-restore planner.
 *
 * @return array{delete: array<int, string>, restore: array<string, array<int, mixed>>}
 */
function eventon_test_meta_restore_plan(array $current, array $snapshot) {
    $method = new ReflectionMethod('EventON_APIfy_Event_Write_Coordinator', 'meta_restore_plan');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true); // Required on 8.0; a deprecated no-op from 8.5.
    }

    return $method->invoke(null, $current, $snapshot);
}

test('meta rollback leaves unchanged keys alone', function () {
    $plan = eventon_test_meta_restore_plan(
        array('evcal_srow' => array('100'), '_edit_lock' => array('1:1')),
        array('evcal_srow' => array('100'), '_edit_lock' => array('1:1'))
    );

    eq($plan, array('delete' => array(), 'restore' => array()));
});

test('meta rollback removes added keys, rewrites changed ones, restores deleted ones', function () {
    $plan = eventon_test_meta_restore_plan(
        array('same' => array('1'), 'changed' => array('new'), 'added' => array('x')),
        array('same' => array('1'), 'changed' => array('old'), 'removed' => array('g', 'h'))
    );

    eq($plan['delete'], array('changed', 'added'));
    eq($plan['restore'], array('changed' => array('old'), 'removed' => array('g', 'h')));
});

test('meta rollback treats a reordered multi-value key as changed', function () {
    $plan = eventon_test_meta_restore_plan(
        array('multi' => array('b', 'a')),
        array('multi' => array('a', 'b'))
    );

    eq($plan['delete'], array('multi'));
    eq($plan['restore'], array('multi' => array('a', 'b')));
});

// --- R-2: evo_tax_meta rollback is scoped to the entries this write touched ---

/**
 * Snapshot the option, run $write through the store, then roll back.
 */
function eventon_test_tax_meta_rollback(callable $write, ?callable $concurrent = null) {
    EventON_APIfy_Taxonomy_Meta_Store::reset_written();
    $snapshot = get_option('evo_tax_meta', null);

    $write();
    if ($concurrent) {
        $concurrent();
    }

    $method = new ReflectionMethod('EventON_APIfy_Event_Write_Coordinator', 'restore_eventon_term_meta');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true); // Required on 8.0; a deprecated no-op from 8.5.
    }
    $method->invoke(null, $snapshot, EventON_APIfy_Taxonomy_Meta_Store::written());
}

test('rollback keeps a concurrent evo_tax_meta write to another term', function () {
    update_option('evo_tax_meta', array('event_location' => array(1 => array('location_city' => 'Anaheim'))));

    eventon_test_tax_meta_rollback(
        static function () {
            EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 5, array('location_city' => 'Irvine'));
        },
        static function () {
            $all = get_option('evo_tax_meta', array());
            $all['event_organizer'][9] = array('evcal_org_contact' => 'other admin');
            update_option('evo_tax_meta', $all);
        }
    );

    eq(get_option('evo_tax_meta'), array(
        'event_location' => array(1 => array('location_city' => 'Anaheim')),
        'event_organizer' => array(9 => array('evcal_org_contact' => 'other admin')),
    ));
});

test('rollback restores a term entry the write changed', function () {
    update_option('evo_tax_meta', array('event_location' => array(1 => array('location_city' => 'Anaheim'))));

    eventon_test_tax_meta_rollback(static function () {
        EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 1, array('location_city' => 'Irvine'));
    });

    eq(get_option('evo_tax_meta'), array('event_location' => array(1 => array('location_city' => 'Anaheim'))));
});

test('rollback drops a taxonomy key the write introduced', function () {
    $baseline = array('event_type' => array(3 => array('et_color' => 'ff0000')));
    update_option('evo_tax_meta', $baseline);

    eventon_test_tax_meta_rollback(static function () {
        EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 5, array('location_city' => 'Irvine'));
    });

    eq(get_option('evo_tax_meta'), $baseline);
});

test('rollback deletes evo_tax_meta when the write created it', function () {
    eventon_test_tax_meta_rollback(static function () {
        EventON_APIfy_Taxonomy_Meta_Store::save('event_location', 5, array('location_city' => 'Irvine'));
    });

    eq(get_option('evo_tax_meta', null), null);
});
