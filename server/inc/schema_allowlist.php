<?php
/**
 * Identifier allow-list.
 *
 * Prepared statements bind values, not identifiers -- "SELECT * FROM ?" is not
 * valid SQL. Table and column names from user input are therefore checked
 * against this fixed set and the request refused if absent. Deny by default.
 */

function schemaAllowList(): array
{
    return [
        'area'        => ['keys' => ['area_id'],        'fields' => ['area_name']],
        'branch'      => ['keys' => ['branch_id'],      'fields' => ['branch_name']],
        'contact'     => ['keys' => ['contact_id'],     'fields' => []],
        'customer'    => ['keys' => ['customer_id'],
                          'fields' => ['name', 'email', 'phone', 'nic',
                                       'address', 'gender', 'password']],
        'employee'    => ['keys' => ['emp_id', 'email'],
                          'fields' => ['name', 'email', 'phone', 'nic', 'address',
                                       'gender', 'password', 'branch_id']],
        'gallery'     => ['keys' => ['gallery_id'],     'fields' => []],
        'price_table' => ['keys' => ['price_id'],
                          'fields' => ['start_area', 'end_area', 'price']],
        'request'     => ['keys' => ['request_id'],
                          'fields' => ['tracking_status', 'res_name',
                                       'res_phone', 'red_address']],
    ];
}

// Hard delete is narrower than soft delete by design: only contact messages
// and gallery images are ever permanently removed.
function allowedTablesFor(string $operation): array
{
    $map = [
        'soft_delete' => ['area', 'branch', 'customer', 'employee',
                          'price_table', 'request'],
        'hard_delete' => ['contact', 'gallery'],
        'update'      => ['area', 'branch', 'customer', 'employee',
                          'price_table', 'request'],
    ];
    return $map[$operation] ?? [];
}

function allowedSettingsFields(): array
{
    return ['header_image', 'header_title', 'header_desc', 'about_title',
            'about_desc', 'company_phone', 'company_email', 'company_address',
            'sub_image', 'about_image', 'link_facebook', 'link_twiiter',
            'link_instragram', 'background_image'];
}

function rejectRequest(string $why = 'Invalid request'): void
{
    http_response_code(400);
    exit($why);
}

function assertIdentifiers(string $operation, string $table,
                           string $keyColumn, ?string $field = null): void
{
    $schema = schemaAllowList();

    if (!in_array($table, allowedTablesFor($operation), true)) rejectRequest();
    if (!isset($schema[$table]))                               rejectRequest();
    if (!in_array($keyColumn, $schema[$table]['keys'], true))  rejectRequest();

    if ($field !== null && !in_array($field, $schema[$table]['fields'], true)) {
        rejectRequest();
    }
}