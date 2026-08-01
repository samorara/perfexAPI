<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'perfexpilot_scans')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "perfexpilot_scans` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL DEFAULT 0,
        `context` VARCHAR(20) NOT NULL DEFAULT 'invoice',
        `original_name` VARCHAR(191) NOT NULL,
        `stored_name` VARCHAR(191) NULL,
        `mime` VARCHAR(100) NOT NULL,
        `filesize` INT(11) NOT NULL DEFAULT 0,
        `model` VARCHAR(60) NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'ok',
        `confidence` TINYINT(4) NOT NULL DEFAULT 0,
        `extracted_json` LONGTEXT NULL,
        `error` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `created_at` (`created_at`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Default settings (Perfex options). add_option ignores existing keys.
add_option('perfexpilot_openai_key', '');
add_option('perfexpilot_model', 'gpt-5');
add_option('perfexpilot_languages', 'en');
add_option('perfexpilot_max_file_mb', '20');
add_option('perfexpilot_confidence_threshold', '70');
add_option('perfexpilot_timeout', '120');
add_option('perfexpilot_auto_create_tax', '1');
add_option('perfexpilot_retention_days', '90');
add_option('perfexpilot_enable_invoices', '1');
add_option('perfexpilot_enable_expenses', '1');
add_option('perfexpilot_enable_payments', '1');

// Private storage for scanned documents; they are only ever served through
// admin/perfexpilot/file/{id}, never directly over HTTP.
$upload_path = perfexpilot_upload_path();

if (!is_dir($upload_path)) {
    @mkdir($upload_path, 0755, true);
}

if (is_dir($upload_path)) {
    if (!file_exists($upload_path . 'index.html')) {
        @file_put_contents($upload_path . 'index.html', '');
    }
    if (!file_exists($upload_path . '.htaccess')) {
        @file_put_contents($upload_path . '.htaccess', "Order Allow,Deny\nDeny from all\n");
    }
}
