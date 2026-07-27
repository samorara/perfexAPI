<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: WA SMS Communication
Description: WhatsApp & SMS communication for Perfex CRM. Send WhatsApp messages via your existing personal phone (HTTP gateway) or the official WhatsApp Business Cloud API, send SMS through any HTTP gateway (GET or POST), receive incoming messages via webhooks and reply automatically with keyword-based rules.
Version: 1.0.0
Requires at least: 2.3.*
Author: Perfex API Module
*/

define('WASMS_MODULE_NAME', 'wasms');
define('WASMS_VERSION', '1.0.0');

hooks()->add_action('admin_init', 'wasms_init_menu_items');
hooks()->add_action('admin_init', 'wasms_permissions');

register_activation_hook(WASMS_MODULE_NAME, 'wasms_activation_hook');
register_uninstall_hook(WASMS_MODULE_NAME, 'wasms_uninstall_hook');
register_language_files(WASMS_MODULE_NAME, [WASMS_MODULE_NAME]);

function wasms_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function wasms_uninstall_hook()
{
    $CI = &get_instance();
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'wasms_messages`;');
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'wasms_auto_replies`;');

    foreach ([
        'wasms_whatsapp_mode', 'wasms_personal_url', 'wasms_personal_method', 'wasms_personal_body_format',
        'wasms_cloud_token', 'wasms_cloud_phone_number_id', 'wasms_cloud_api_version', 'wasms_cloud_verify_token',
        'wasms_sms_url', 'wasms_sms_method', 'wasms_sms_body_format',
        'wasms_webhook_secret', 'wasms_auto_reply_enabled', 'wasms_default_reply',
    ] as $option) {
        delete_option($option);
    }
}

function wasms_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('wasms', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('wasms', [
            'name'     => _l('wasms'),
            'href'     => admin_url('wasms'),
            'icon'     => 'fa fa-comments',
            'position' => 46,
        ]);
    }
}

function wasms_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'delete' => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities('wasms', $capabilities, _l('wasms'));
}
