<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Export raw field definitions into the executable MCP contract shape.
 *
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_get_mcp_contract_fields() {
    $fields = array();
    $definitions = eventon_apify_get_contract_field_definitions();

    foreach (eventon_apify_get_mcp_contract_field_names() as $field_name) {
        if (!isset($definitions[$field_name])) {
            continue;
        }

        $fields[] = eventon_apify_build_mcp_contract_field_definition($field_name, $definitions[$field_name]);
    }

    return $fields;
}

/**
 * Convert one raw internal field definition into the manifest contract format.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_contract_field_definition($field_name, array $definition) {
    $field = array(
        'name' => $field_name,
        'label' => eventon_apify_format_contract_field_label($field_name),
        'description' => $definition['description'] ?? '',
        'type' => eventon_apify_map_contract_field_type($definition),
        'write_key' => eventon_apify_get_mcp_contract_write_key($field_name),
        'operations' => array('create', 'update'),
    );

    if (!empty($definition['required_on_create'])) {
        $field['required_on'] = array('create');
    }

    if (!empty($definition['allowed_values']) && is_array($definition['allowed_values'])) {
        $field['enum'] = array_values($definition['allowed_values']);
    }

    if (!empty($definition['guidance']) && is_string($definition['guidance'])) {
        $field['guidance'] = $definition['guidance'];
    }

    $aliases = eventon_apify_get_mcp_contract_field_aliases($field_name);
    if (!empty($aliases)) {
        $field['aliases'] = $aliases;
    }

    $coerce = eventon_apify_get_mcp_contract_field_coerce($field_name);
    if (!empty($coerce)) {
        $field['coerce'] = $coerce;
    }

    if (!empty($definition['shape']) && is_array($definition['shape'])) {
        $field['shape'] = eventon_apify_export_contract_shape_definitions($definition['shape']);
    }

    if ($definition['type'] === 'array') {
        $items = eventon_apify_build_mcp_contract_items_definition($field_name, $definition);
        if (!empty($items)) {
            $field['items'] = $items;
        }
    }

    return $field;
}

/**
 * Convert nested raw field definitions into contract field objects.
 *
 * @param array<string, array<string, mixed>> $shape Raw nested field definitions.
 * @return array<int, array<string, mixed>>
 */
function eventon_apify_export_contract_shape_definitions(array $shape) {
    $fields = array();

    foreach ($shape as $field_name => $definition) {
        $item = array(
            'name' => $field_name,
            'label' => eventon_apify_format_contract_field_label($field_name),
            'description' => $definition['description'] ?? '',
            'type' => eventon_apify_map_contract_field_type($definition),
        );

        if (!empty($definition['allowed_values']) && is_array($definition['allowed_values'])) {
            $item['enum'] = array_values($definition['allowed_values']);
        }

        if (!empty($definition['guidance']) && is_string($definition['guidance'])) {
            $item['guidance'] = $definition['guidance'];
        }

        if (!empty($definition['shape']) && is_array($definition['shape'])) {
            $item['shape'] = eventon_apify_export_contract_shape_definitions($definition['shape']);
        }

        if (($definition['type'] ?? '') === 'array') {
            $nested_items = eventon_apify_build_mcp_contract_items_definition($field_name, $definition);
            if (!empty($nested_items)) {
                $item['items'] = $nested_items;
            }
        }

        $fields[] = $item;
    }

    return $fields;
}

/**
 * Build the manifest `items` definition for array fields.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 * @return array<string, mixed>
 */
function eventon_apify_build_mcp_contract_items_definition($field_name, array $definition) {
    $item_type = $definition['item_type'] ?? '';

    if (!is_string($item_type) || $item_type === '') {
        return array();
    }

    $items = array(
        'name' => eventon_apify_get_mcp_contract_array_item_name($field_name),
        'type' => $item_type,
    );

    if (!empty($definition['item_shape']) && is_array($definition['item_shape'])) {
        $items['shape'] = eventon_apify_export_contract_shape_definitions($definition['item_shape']);
    }

    return $items;
}

/**
 * Map the internal field type metadata to the external contract type vocabulary.
 *
 * @param array<string, mixed> $definition Raw internal field definition.
 */
function eventon_apify_map_contract_field_type(array $definition) {
    $format = $definition['format'] ?? '';
    if ($format === 'date') {
        return 'date';
    }

    if ($format === 'time') {
        return 'time';
    }

    return $definition['type'] ?? 'string';
}

/**
 * Build a readable field label from the canonical field key.
 */
function eventon_apify_format_contract_field_label($field_name) {
    return ucwords(str_replace('_', ' ', $field_name));
}
