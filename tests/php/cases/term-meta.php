<?php
/**
 * Tests for eventon_apify_get_term_meta_payload() against EventON's real
 * evo_get_term_meta() signature: ($tax, $termid, $wp_option_val = '', $secondarycheck = false).
 * The third parameter is a pre-fetched options array, NOT a $single flag;
 * passing a boolean short-circuits the lookup and returns false.
 */

if (!function_exists('evo_get_term_meta')) {
    function evo_get_term_meta($tax, $termid, $wp_option_val = '', $secondarycheck = false) {
        $GLOBALS['__eventon_test_term_meta_args'] = func_get_args();
        if (is_bool($wp_option_val)) {
            // Reproduces EventON: !empty(true) makes the bool the meta source,
            // indexing it yields nothing, and the function returns false.
            return false;
        }

        $termmetas = !empty($wp_option_val)
            ? $wp_option_val
            : get_option('evo_tax_meta', array());

        if (empty($termmetas[$tax][$termid])) {
            if ($secondarycheck) {
                $legacy = get_option('taxonomy_' . $termid, array());
                if (is_array($legacy) && !empty($legacy)) {
                    return $legacy;
                }
            }
            return false;
        }

        return $termmetas[$tax][$termid];
    }
}

test('term meta payload reads location fields from evo_tax_meta', function () {
    update_option('evo_tax_meta', array(
        'event_location' => array(
            409 => array(
                'location_address' => '3191 E La Palma Ave',
                'location_city' => 'Anaheim',
                'location_state' => 'CA',
            ),
        ),
    ));

    $meta = eventon_apify_get_term_meta_payload('event_location', 409);
    eq($meta['location_address'], '3191 E La Palma Ave');
    eq($meta['location_city'], 'Anaheim');
    eq($meta['location_state'], 'CA');
});

test('term meta payload never passes a boolean as the meta source argument', function () {
    unset($GLOBALS['__eventon_test_term_meta_args']);
    eventon_apify_get_term_meta_payload('event_location', 409);

    $args = $GLOBALS['__eventon_test_term_meta_args'];
    ok(!is_bool($args[2] ?? ''));
    eq($args[3] ?? null, true);
});

test('term meta payload falls back to legacy taxonomy_<id> option', function () {
    update_option('taxonomy_77', array('evcal_org_contact' => '555-1234'));

    $meta = eventon_apify_get_term_meta_payload('event_organizer', 77);
    eq($meta['evcal_org_contact'], '555-1234');
});

test('term meta payload returns empty array for unknown or missing terms', function () {
    eq(eventon_apify_get_term_meta_payload('event_location', 0), array());
    eq(eventon_apify_get_term_meta_payload('event_location', 999), array());
});
