<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capture and restore the WordPress state touched by an event write.
 *
 * WordPress does not provide transactions across posts, metadata, terms, and
 * EventON's shared option store. This coordinator provides compensating writes
 * so REST callers do not receive an error after silently retaining partial data.
 */
final class EventON_APIfy_Event_Write_Coordinator {
    /**
     * Apply event metadata and terms, rolling back on failure.
     *
     * @return true|WP_Error
     */
    public static function persist($post_id, array $params, $created = false, array $post_updates = array()) {
        $snapshot = self::capture($post_id, $created);

        if (!$created && count($post_updates) > 1) {
            $post_result = wp_update_post($post_updates, true);
            if (is_wp_error($post_result)) {
                return $post_result;
            }
        }

        $meta_result = eventon_apify_save_event_meta($post_id, $params);
        if (is_wp_error($meta_result)) {
            self::rollback($post_id, $snapshot);
            return $meta_result;
        }

        $term_result = eventon_apify_save_event_terms($post_id, $params);
        if (is_wp_error($term_result)) {
            self::rollback($post_id, $snapshot);
            return $term_result;
        }

        return true;
    }

    /**
     * Capture state before a potentially partial write.
     *
     * @return array<string, mixed>
     */
    private static function capture($post_id, $created) {
        $snapshot = array(
            'created' => (bool) $created,
            'evo_tax_meta' => get_option('evo_tax_meta', null),
        );

        if ($created) {
            return $snapshot;
        }

        $snapshot['post'] = get_post($post_id, ARRAY_A);
        $snapshot['meta'] = get_post_meta($post_id);
        $snapshot['terms'] = self::capture_terms($post_id);

        return $snapshot;
    }

    /**
     * Capture assigned term IDs for every event taxonomy.
     *
     * @return array<string, array<int, int>>
     */
    private static function capture_terms($post_id) {
        $assignments = array();
        $taxonomies = get_object_taxonomies('ajde_events', 'names');

        foreach ((array) $taxonomies as $taxonomy) {
            $term_ids = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($term_ids)) {
                $assignments[$taxonomy] = array_map('intval', $term_ids);
            }
        }

        return $assignments;
    }

    /**
     * Restore captured state after a failed write.
     */
    private static function rollback($post_id, array $snapshot) {
        if (!empty($snapshot['created'])) {
            wp_delete_post($post_id, true);
            self::restore_eventon_term_meta($snapshot['evo_tax_meta']);
            return;
        }

        if (!empty($snapshot['post']) && is_array($snapshot['post'])) {
            wp_update_post($snapshot['post']);
        }

        self::restore_post_meta($post_id, $snapshot['meta'] ?? array());

        foreach (($snapshot['terms'] ?? array()) as $taxonomy => $term_ids) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
        }

        self::restore_eventon_term_meta($snapshot['evo_tax_meta']);
    }

    /**
     * Restore all post metadata values from a pre-write snapshot.
     */
    private static function restore_post_meta($post_id, array $snapshot) {
        foreach (array_keys(get_post_meta($post_id)) as $meta_key) {
            delete_post_meta($post_id, $meta_key);
        }

        foreach ($snapshot as $meta_key => $values) {
            foreach ((array) $values as $value) {
                add_post_meta($post_id, $meta_key, maybe_unserialize($value));
            }
        }
    }

    /**
     * Restore EventON's shared taxonomy metadata option.
     */
    private static function restore_eventon_term_meta($snapshot) {
        if ($snapshot === null) {
            delete_option('evo_tax_meta');
            return;
        }

        update_option('evo_tax_meta', $snapshot);
    }
}
