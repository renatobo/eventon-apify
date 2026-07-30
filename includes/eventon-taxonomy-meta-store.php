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
        $has_falsy_value = false;
        foreach ($payload as $value) {
            if ($value !== '' && empty($value)) {
                $has_falsy_value = true;
                break;
            }
        }

        // EventON's evo_save_term_metas() runs a bare array_filter() over the
        // merged values, which silently deletes legitimate falsy entries such
        // as a latitude of '0'. Merge into the same option ourselves whenever
        // the payload carries one; otherwise defer to EventON.
        if (function_exists('evo_save_term_metas') && !$has_falsy_value) {
            evo_save_term_metas($taxonomy, $term_id, $payload);
            return true;
        }

        // Writes the same 'evo_tax_meta' option EventON reads through
        // get_tax_meta(), keeping the private option shape out of the
        // REST/domain layers.
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
