<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PerfexPilot
Description: Zero-setup OCR and autofill for Perfex CRM. Drag & drop a PDF or photo of an invoice or receipt (or use your phone camera) on the Invoice, Expense and Record Payment forms; PerfexPilot extracts header fields, line items and taxes and writes them straight into the open form without a page reload.
Version: 1.0.0
Requires at least: 2.3.*
Author: Perfex API Module
*/

define('PERFEXPILOT_MODULE_NAME', 'perfexpilot');
define('PERFEXPILOT_VERSION', '1.0.0');

hooks()->add_action('admin_init', 'perfexpilot_init_menu_items');
hooks()->add_action('admin_init', 'perfexpilot_permissions');
hooks()->add_action('app_admin_footer', 'perfexpilot_inject_assets');
hooks()->add_action('after_cron_run', 'perfexpilot_purge_expired');

register_activation_hook(PERFEXPILOT_MODULE_NAME, 'perfexpilot_activation_hook');
register_uninstall_hook(PERFEXPILOT_MODULE_NAME, 'perfexpilot_uninstall_hook');
register_language_files(PERFEXPILOT_MODULE_NAME, [PERFEXPILOT_MODULE_NAME]);

/**
 * Absolute path of the directory holding scanned documents.
 */
function perfexpilot_upload_path()
{
    return FCPATH . 'uploads/' . PERFEXPILOT_MODULE_NAME . '/';
}

function perfexpilot_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

function perfexpilot_uninstall_hook()
{
    $CI = &get_instance();
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'perfexpilot_scans`;');

    foreach ([
        'perfexpilot_openai_key', 'perfexpilot_model', 'perfexpilot_languages',
        'perfexpilot_max_file_mb', 'perfexpilot_confidence_threshold', 'perfexpilot_timeout',
        'perfexpilot_auto_create_tax', 'perfexpilot_retention_days',
        'perfexpilot_enable_invoices', 'perfexpilot_enable_expenses', 'perfexpilot_enable_payments',
    ] as $option) {
        delete_option($option);
    }

    // Remove retained documents.
    $path = perfexpilot_upload_path();
    if (is_dir($path)) {
        foreach (glob($path . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($path);
    }
}

function perfexpilot_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission(PERFEXPILOT_MODULE_NAME, '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('perfexpilot', [
            'name'     => _l('perfexpilot'),
            'href'     => admin_url('perfexpilot'),
            'icon'     => 'fa fa-magic',
            'position' => 47,
        ]);
    }
}

function perfexpilot_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'delete' => _l('permission_delete'),
        ],
    ];

    register_staff_capabilities(PERFEXPILOT_MODULE_NAME, $capabilities, _l('perfexpilot'));
}

/**
 * Which scan context (if any) applies to the admin page currently being rendered.
 *
 * Invoices cover both the invoice form and the AJAX "Record payment" drawer that is
 * loaded inside the invoice preview, so no separate payment URL check is needed.
 *
 * @return string|null invoice|expense|payment
 */
function perfexpilot_current_context()
{
    $CI      = &get_instance();
    $segment = $CI->uri->segment(2);

    $contexts = hooks()->apply_filters('perfexpilot_supported_contexts', [
        'invoices' => 'invoice',
        'expenses' => 'expense',
        'payments' => 'payment',
    ]);

    if (!isset($contexts[$segment])) {
        return null;
    }

    $enabled = [
        'invoice' => 'perfexpilot_enable_invoices',
        'expense' => 'perfexpilot_enable_expenses',
        'payment' => 'perfexpilot_enable_payments',
    ];

    $context = $contexts[$segment];

    if (isset($enabled[$context]) && get_option($enabled[$context]) != '1') {
        return null;
    }

    return $context;
}

/**
 * Print the Scan & Fill modal plus its assets on the supported admin forms.
 */
function perfexpilot_inject_assets()
{
    if (!has_permission(PERFEXPILOT_MODULE_NAME, '', 'create') && !is_admin()) {
        return;
    }

    $context = perfexpilot_current_context();

    if ($context === null) {
        return;
    }

    $CI                   = &get_instance();
    $data                 = [];
    $data['context']      = $context;
    $data['dateformat']   = get_option('dateformat');
    $data['threshold']    = (int) get_option('perfexpilot_confidence_threshold');
    $data['max_file_mb']  = (int) get_option('perfexpilot_max_file_mb');
    $data['configured']   = get_option('perfexpilot_openai_key') !== '';

    echo '<link rel="stylesheet" type="text/css" href="' . module_dir_url(PERFEXPILOT_MODULE_NAME, 'assets/css/perfexpilot.css') . '?v=' . PERFEXPILOT_VERSION . '">';
    $CI->load->view('perfexpilot/modal', $data);
    echo '<script src="' . module_dir_url(PERFEXPILOT_MODULE_NAME, 'assets/js/perfexpilot.js') . '?v=' . PERFEXPILOT_VERSION . '"></script>';
}

/**
 * Delete scans (rows and files) older than the configured retention window.
 * Runs on the Perfex cron; a retention of 0 keeps everything.
 */
function perfexpilot_purge_expired()
{
    $CI = &get_instance();
    $CI->load->model(PERFEXPILOT_MODULE_NAME . '/perfexpilot_model');
    $CI->perfexpilot_model->purge_expired();
}
