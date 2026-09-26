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
     * $post_updates is applied before meta on an update, and after meta and
     * terms on a create, where it carries the final status of the new draft.
     *
     * @return true|WP_Error
     */
    public static function persist($post_id, array $params, $created = false, array $post_updates = array()) {
        $snapshot = self::capture($post_id, $created);

        if (!$created && count($post_updates) > 1) {
            $post_result = wp_update_post($post_updates, true);
            if (is_wp_error($post_result)) {
                return eventon_apify_redact_wp_write_error($post_result);
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

        if ($created && count($post_updates) > 1) {
            $post_result = wp_update_post($post_updates, true);
            if (is_wp_error($post_result)) {
                self::rollback($post_id, $snapshot);
                return eventon_apify_redact_wp_write_error($post_result);
            }
        }

        /**
         * Fires after an eventonapify/v1 create or update has fully succeeded.
         *
         * @param int                  $post_id Event post ID.
         * @param array<string, mixed> $params  Normalized, validated request payload.
         * @param bool                 $created Whether the event was just created.
         */
        do_action('eventon_apify_event_saved', (int) $post_id, $params, (bool) $created);

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
        EventON_APIfy_Taxonomy_Meta_Store::reset_written();

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
            self::restore_eventon_term_meta($snapshot['evo_tax_meta'], EventON_APIfy_Taxonomy_Meta_Store::written());
            return;
        }

        if (!empty($snapshot['post']) && is_array($snapshot['post'])) {
            // wp_update_post() unslashes its input, so re-slash the snapshot
            // to keep backslashes in post content intact on rollback.
            wp_update_post(wp_slash($snapshot['post']));
        }

        self::restore_post_meta($post_id, $snapshot['meta'] ?? array());

        foreach (($snapshot['terms'] ?? array()) as $taxonomy => $term_ids) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
        }

        self::restore_eventon_term_meta($snapshot['evo_tax_meta'], EventON_APIfy_Taxonomy_Meta_Store::written());
    }

    /**
     * Restore post metadata to a pre-write snapshot, touching only the keys
     * the failed write changed so unrelated meta hooks do not fire.
     */
    private static function restore_post_meta($post_id, array $snapshot) {
        $plan = self::meta_restore_plan(get_post_meta($post_id), $snapshot);

        foreach ($plan['delete'] as $meta_key) {
            delete_post_meta($post_id, $meta_key);
        }

        foreach ($plan['restore'] as $meta_key => $values) {
            foreach ($values as $value) {
                add_post_meta($post_id, $meta_key, maybe_unserialize($value));
            }
        }
    }

    /**
     * Diff current meta against the snapshot. Both sides are get_post_meta()
     * raw value lists, so a strict comparison also catches reordered values.
     *
     * @param array<string, array<int, mixed>> $current  Meta as it is now.
     * @param array<string, array<int, mixed>> $snapshot Meta before the write.
     * @return array{delete: array<int, string>, restore: array<string, array<int, mixed>>}
     */
    private static function meta_restore_plan(array $current, array $snapshot) {
        $plan = array('delete' => array(), 'restore' => array());

        foreach ($current as $meta_key => $values) {
            if (!array_key_exists($meta_key, $snapshot) || (array) $snapshot[$meta_key] !== (array) $values) {
                $plan['delete'][] = (string) $meta_key;
            }
        }

        foreach ($snapshot as $meta_key => $values) {
            if (!array_key_exists($meta_key, $current) || (array) $current[$meta_key] !== (array) $values) {
                $plan['restore'][(string) $meta_key] = array_values((array) $values);
            }
        }

        return $plan;
    }

    /**
     * Restore EventON's shared taxonomy metadata option.
     *
     * evo_tax_meta holds every term's metadata site-wide, so writing the whole
     * snapshot back would discard changes other requests made since it was
     * taken. Only the entries this write saved are put back.
     *
     * @param mixed                            $snapshot Option value before the write, or null.
     * @param array<string, array<int, bool>> $written  Entries saved during the write.
     */
    private static function restore_eventon_term_meta($snapshot, array $written) {
        if (empty($written)) {
            return;
        }

        $current = get_option('evo_tax_meta', null);
        if (!is_array($current) || ($snapshot !== null && !is_array($snapshot))) {
            // Not the shape the store writes: fall back to the full snapshot.
            if ($snapshot === null) {
                delete_option('evo_tax_meta');
            } else {
                update_option('evo_tax_meta', $snapshot);
            }
            return;
        }

        $before = is_array($snapshot) ? $snapshot : array();

        foreach ($written as $taxonomy => $term_ids) {
            foreach (array_keys($term_ids) as $term_id) {
                if (isset($before[$taxonomy]) && is_array($before[$taxonomy]) && array_key_exists($term_id, $before[$taxonomy])) {
                    $current[$taxonomy][$term_id] = $before[$taxonomy][$term_id];
                    continue;
                }

                if (isset($current[$taxonomy]) && is_array($current[$taxonomy])) {
                    unset($current[$taxonomy][$term_id]);

                    if (empty($current[$taxonomy]) && !array_key_exists($taxonomy, $before)) {
                        unset($current[$taxonomy]);
                    }
                }
            }
        }

        if ($snapshot === null && empty($current)) {
            delete_option('evo_tax_meta');
            return;
        }

        update_option('evo_tax_meta', $current);
    }
}
