<?php

declare(strict_types=1);

namespace Dizzy\Newsletter;

defined('ABSPATH') || exit;

final class Database
{
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'dizzy_nl_';

        dbDelta("CREATE TABLE {$prefix}contacts (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            name varchar(190) NOT NULL DEFAULT '',
            status varchar(24) NOT NULL DEFAULT 'pending',
            tags text NULL,
            source varchar(100) NOT NULL DEFAULT '',
            consent_at datetime NULL,
            confirmed_at datetime NULL,
            unsubscribed_at datetime NULL,
            token_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY email (email), KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$prefix}campaigns (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            subject varchar(255) NOT NULL,
            preheader varchar(255) NOT NULL DEFAULT '',
            content longtext NOT NULL,
            hero_image_url text NULL,
            button_text varchar(100) NOT NULL DEFAULT '',
            button_url text NULL,
            target_tag varchar(100) NOT NULL DEFAULT '',
            status varchar(24) NOT NULL DEFAULT 'draft',
            scheduled_at datetime NULL,
            started_at datetime NULL,
            completed_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY status_schedule (status,scheduled_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$prefix}queue (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint unsigned NOT NULL,
            contact_id bigint unsigned NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'queued',
            attempts tinyint unsigned NOT NULL DEFAULT 0,
            error_message text NULL,
            sent_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY campaign_contact (campaign_id,contact_id), KEY queue_status (status,id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$prefix}events (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint unsigned NOT NULL DEFAULT 0,
            contact_id bigint unsigned NOT NULL DEFAULT 0,
            event_type varchar(30) NOT NULL,
            meta text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY campaign_type (campaign_id,event_type), KEY contact_id (contact_id)
        ) {$charset};");

        add_option('dizzy_nl_settings', [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'reply_to' => get_option('admin_email'),
            'double_optin' => 1,
            'track_opens' => 0,
            'batch_size' => 25,
            'privacy_url' => get_privacy_policy_url(),
        ]);
        update_option('dizzy_nl_db_version', DIZZY_NL_VERSION);
    }
}

