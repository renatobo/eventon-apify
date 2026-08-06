<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Isolates EventON taxonomy metadata persistence from REST use cases.
 */
final class EventON_APIfy_Taxonomy_Meta_Store {
    /**
     * Merge metadata into EventON's taxonomy store.
     *
     * @param array<string, string> $payload Sanitized EventON metadata.
     * @return true|WP_Error
     */
    public static function save($taxonomy, $term_id, array $payload) {
        // Always merged here rather than through EventON's
        // evo_save_term_metas(), which runs a bare array_filter() over the
        // merged result and so deletes legitimate falsy values such as a
        // latitude of '0' — including values already stored, which a payload
        // check cannot protect. EventON's helper is a plain update_option()
        // on this same key with no hooks or cache invalidation, so writing it
        // directly loses nothing. Only '' clears a value.
        $all_term_meta = get_option('evo_tax_meta', array());
        if (!is_array($all_term_meta)) {
            $all_term_meta = array();
        }

        $existing = $all_term_meta[$taxonomy][$term_id] ?? array();
        if (!is_array($existing)) {
            $existing = array();
        }

        $merged = array_merge($existing, $payload);
        $merged = array_filter(
            $merged,
            static function ($value) {
                return $value !== '';
            }
        );

        $all_term_meta[$taxonomy][$term_id] = $merged;
        $updated = update_option('evo_tax_meta', $all_term_meta);

        if ($updated || get_option('evo_tax_meta', array()) === $all_term_meta) {
            return true;
        }

        return new WP_Error(
            'eventon_apify_term_meta_write_failed',
            __('EventON taxonomy metadata could not be saved.', 'eventon-apify'),
            array('status' => 500)
        );
    }
}
