<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'wasms_messages')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "wasms_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `channel` VARCHAR(20) NOT NULL,
        `direction` VARCHAR(3) NOT NULL DEFAULT 'out',
        `phone` VARCHAR(50) NOT NULL,
        `message` TEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
        `gateway_response` TEXT NULL,
        `staff_id` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `phone` (`phone`),
        KEY `channel` (`channel`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'wasms_auto_replies')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "wasms_auto_replies` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `keyword` VARCHAR(191) NOT NULL,
        `match_type` VARCHAR(20) NOT NULL DEFAULT 'contains',
        `channel` VARCHAR(20) NOT NULL DEFAULT 'both',
        `reply` TEXT NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Default settings (Perfex options). add_option ignores existing keys.
add_option('wasms_whatsapp_mode', 'personal');           // personal | cloud
add_option('wasms_personal_url', '');                    // e.g. http://phone-gateway/send?to={phone}&text={message}
add_option('wasms_personal_method', 'GET');              // GET | POST
add_option('wasms_personal_body_format', 'form');        // form | json (POST only)
add_option('wasms_cloud_token', '');
add_option('wasms_cloud_phone_number_id', '');
add_option('wasms_cloud_api_version', 'v20.0');
add_option('wasms_cloud_verify_token', bin2hex(random_bytes(12)));
add_option('wasms_sms_url', '');
add_option('wasms_sms_method', 'GET');
add_option('wasms_sms_body_format', 'form');
add_option('wasms_webhook_secret', bin2hex(random_bytes(12)));
add_option('wasms_auto_reply_enabled', '1');
add_option('wasms_default_reply', '');
