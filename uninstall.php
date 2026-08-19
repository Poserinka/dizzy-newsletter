<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
if (! defined('DIZZY_NL_REMOVE_DATA') || DIZZY_NL_REMOVE_DATA !== true) return;
global $wpdb;
foreach (['contacts','campaigns','queue','events'] as $table) $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dizzy_nl_{$table}");
delete_option('dizzy_nl_settings');
delete_option('dizzy_nl_db_version');

