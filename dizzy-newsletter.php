<?php
/**
 * Plugin Name: Dizzy Newsletter
 * Description: Independent newsletter campaigns, audiences, analytics and signup forms.
 * Version: 1.0.6
 * Author: Poserinka
 * Text Domain: dizzy-newsletter
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('DIZZY_NL_VERSION', '1.0.6');
define('DIZZY_NL_FILE', __FILE__);
define('DIZZY_NL_DIR', plugin_dir_path(__FILE__));
define('DIZZY_NL_URL', plugin_dir_url(__FILE__));

add_filter('cron_schedules', static function (array $schedules): array {
    $schedules['dizzy_nl_five_minutes'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display' => __('Every five minutes', 'dizzy-newsletter'),
    ];
    return $schedules;
});

require_once DIZZY_NL_DIR . 'includes/Database.php';
require_once DIZZY_NL_DIR . 'includes/Repository.php';
require_once DIZZY_NL_DIR . 'includes/CampaignSender.php';
require_once DIZZY_NL_DIR . 'includes/Frontend.php';
require_once DIZZY_NL_DIR . 'includes/Admin.php';
require_once DIZZY_NL_DIR . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['Dizzy\\Newsletter\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Dizzy\\Newsletter\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Dizzy\Newsletter\Plugin::boot();
});

