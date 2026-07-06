<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'perfex_api_keys')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "perfex_api_keys` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `token_hash` VARCHAR(64) NOT NULL,
        `token_prefix` VARCHAR(12) NOT NULL,
        `can_create` TINYINT(1) NOT NULL DEFAULT 1,
        `can_update` TINYINT(1) NOT NULL DEFAULT 1,
        `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `expires_at` DATETIME NULL DEFAULT NULL,
        `last_used_at` DATETIME NULL DEFAULT NULL,
        `created_by` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token_hash` (`token_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
