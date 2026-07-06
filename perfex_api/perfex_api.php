<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Perfex API
Description: REST API for Perfex CRM. Exposes customers, contacts, leads, invoices, estimates, payments, projects, tasks, tickets, staff, expenses, contracts and items over token-authenticated JSON endpoints, with an admin panel for managing API keys.
Version: 1.0.0
Requires at least: 2.3.*
Author: Perfex API Module
*/

define('PERFEX_API_MODULE_NAME', 'perfex_api');
define('PERFEX_API_VERSION', '1.0.0');

hooks()->add_action('admin_init', 'perfex_api_init_menu_items');
hooks()->add_action('admin_init', 'perfex_api_permissions');

register_activation_hook(PERFEX_API_MODULE_NAME, 'perfex_api_activation_hook');
register_uninstall_hook(PERFEX_API_MODULE_NAME, 'perfex_api_uninstall_hook');
register_language_files(PERFEX_API_MODULE_NAME, [PERFEX_API_MODULE_NAME]);

/**
 * Runs when the module is activated from Setup -> Modules.
 */
function perfex_api_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

/**
 * Runs when the module is uninstalled (not just deactivated).
 */
function perfex_api_uninstall_hook()
{
    $CI = &get_instance();
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'perfex_api_keys`;');
}

/**
 * Register the admin sidebar menu item.
 */
function perfex_api_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('perfex_api', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('perfex-api', [
            'name'     => _l('perfex_api'),
            'href'     => admin_url('perfex_api'),
            'icon'     => 'fa fa-plug',
            'position' => 45,
        ]);
    }
}

/**
 * Register staff permissions for the module.
 */
function perfex_api_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'delete' => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities('perfex_api', $capabilities, _l('perfex_api'));
}
