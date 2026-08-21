<?php

declare(strict_types=1);

namespace Dizzy\Newsletter;

defined('ABSPATH') || exit;

final class Plugin
{
    public static function boot(): void
    {
        $repository = new Repository();
        $sender = new CampaignSender($repository);
        (new Frontend($repository, $sender))->register();
        (new Admin($repository, $sender))->register();

        add_action('dizzy_nl_process_queue', [$sender, 'processQueue']);
        add_action('dizzy_nl_cleanup', [$repository, 'cleanup']);
    }

    public static function activate(): void
    {
        Database::install();
        if (! wp_next_scheduled('dizzy_nl_process_queue')) {
            wp_schedule_event(time() + 60, 'dizzy_nl_five_minutes', 'dizzy_nl_process_queue');
        }
        if (! wp_next_scheduled('dizzy_nl_cleanup')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'dizzy_nl_cleanup');
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('dizzy_nl_process_queue');
        wp_clear_scheduled_hook('dizzy_nl_cleanup');
    }
}

