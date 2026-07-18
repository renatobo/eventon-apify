<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert the canonical EventON contract into WordPress REST argument schemas.
 *
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_event_write_args($is_create = false) {
    $args = array();

    foreach (eventon_apify_get_contract_field_definitions() as $field_name => $definition) {
        $schema = eventon_apify_get_rest_schema_for_contract_field($definition);
        $schema['required'] = (bool) ($is_create && !empty($definition['required_on_create']));
        $schema['sanitize_callback'] = 'eventon_apify_sanitize_rest_contract_value';
        $args[$field_name] = $schema;
    }

    return $args;
}

/**
 * Build one WordPress-compatible REST argument schema.
 *
 * @param array<string, mixed> $definition Canonical contract field definition.
 * @return array<string, mixed>
 */
function eventon_apify_get_rest_schema_for_contract_field(array $definition) {
    $schema = array(
        'type' => $definition['type'] ?? 'string',
        'description' => $definition['description'] ?? '',
    );

    $format = $definition['format'] ?? '';
    if ($format === 'date') {
        $schema['pattern'] = '^(|[0-9]{4}-[0-9]{2}-[0-9]{2})$';
    } elseif ($format === 'time') {
        $schema['pattern'] = '^(|[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?)$';
    } elseif ($format === 'color') {
        $schema['pattern'] = '^(|#?[0-9a-fA-F]{3}|#?[0-9a-fA-F]{6})$';
    }

    if (!empty($definition['allowed_values'])) {
        $schema['enum'] = array_values($definition['allowed_values']);
    }

    if (($schema['type'] ?? '') === 'array') {
        $schema['items'] = array('type' => $definition['item_type'] ?? 'string');

        if (!empty($definition['item_shape']) && is_array($definition['item_shape'])) {
            $schema['items']['type'] = 'object';
            $schema['items']['properties'] = eventon_apify_get_rest_schema_properties($definition['item_shape']);
        }
    }

    if (($schema['type'] ?? '') === 'object' && !empty($definition['shape']) && is_array($definition['shape'])) {
        $schema['properties'] = eventon_apify_get_rest_schema_properties($definition['shape']);
    }

    if (!empty($definition['also_accepts']) && in_array('comma_separated_string', $definition['also_accepts'], true)) {
        $schema['type'] = array('array', 'string');
    }

    return $schema;
}

/**
 * Convert a contract shape into JSON Schema properties recursively.
 *
 * @param array<string, array<string, mixed>> $shape Contract shape.
 * @return array<string, array<string, mixed>>
 */
function eventon_apify_get_rest_schema_properties(array $shape) {
    $properties = array();

    foreach ($shape as $property_name => $definition) {
        $properties[$property_name] = eventon_apify_get_rest_schema_for_contract_field($definition);
    }

    return $properties;
}

/**
 * Preserve structured REST values; scalar sanitization happens in the domain
 * normalization and validation layer where field context is available.
 */
function eventon_apify_sanitize_rest_contract_value($value) {
    return $value;
}

/**
 * Return the schema for a custom field registered on the wp/v2 surface.
 *
 * @return array<string, mixed>
 */
function eventon_apify_get_wp_v2_field_schema($field_name) {
    $definitions = eventon_apify_get_contract_field_definitions();
    if (!isset($definitions[$field_name])) {
        return array(
            'description' => __('EventON compatibility field.', 'eventon-apify'),
            'type' => 'object',
            'context' => array('view', 'edit'),
        );
    }

    $schema = eventon_apify_get_rest_schema_for_contract_field($definitions[$field_name]);
    $schema['context'] = array('view', 'edit');
    return $schema;
}
