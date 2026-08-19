<?php

declare(strict_types=1);

namespace Dizzy\Newsletter;

use Throwable;

defined('ABSPATH') || exit;

final class CampaignSender
{
    public function __construct(private Repository $repository)
    {
    }

    public function processQueue(): void
    {
        $settings = $this->settings();
        $limit = min(100, max(1, (int) ($settings['batch_size'] ?? 25)));
        foreach ($this->repository->dueQueue($limit) as $row) {
            try {
                $sent = $this->sendCampaign($row, $settings);
                $this->repository->queueResult((int) $row['queue_id'], $sent, $sent ? '' : 'wp_mail returned false');
            } catch (Throwable $e) {
                $this->repository->queueResult((int) $row['queue_id'], false, $e->getMessage());
            }
        }
        $this->repository->finishCampaigns();
    }

    public function sendTest(int $campaignId, string $email): bool
    {
        $campaign = $this->repository->campaign($campaignId);
        if (! $campaign || ! is_email($email)) {
            return false;
        }
        return $this->sendCampaign(array_merge($campaign, [
            'email' => $email,
            'subscriber_name' => __('Test subscriber', 'dizzy-newsletter'),
            'contact_id' => 0,
            'campaign_id' => $campaignId,
        ]), $this->settings(), true);
    }

    private function sendCampaign(array $row, array $settings, bool $test = false): bool
    {
        $campaignId = (int) ($row['campaign_id'] ?? $row['id'] ?? 0);
        $contactId = (int) ($row['contact_id'] ?? 0);
        $subscriber_name = (string) ($row['subscriber_name'] ?? '');
        $subscriber_email = (string) $row['email'];
        $campaign_title = (string) $row['name'];
        $replacements = ['{subscriber_name}' => $subscriber_name, '{subscriber_email}' => $subscriber_email];
        $email_subject = ($test ? '[TEST] ' : '') . strtr((string) $row['subject'], $replacements);
        $preheader = (string) $row['preheader'];
        $content = strtr((string) $row['content'], $replacements);
        $hero_image_url = (string) $row['hero_image_url'];
        $button_text = (string) $row['button_text'];
        $button_url = $this->trackingUrl((string) $row['button_url'], $campaignId, $contactId);
        $site_name = get_bloginfo('name');
        $site_url = home_url('/');
        $unsubscribe_url = $contactId > 0 ? Frontend::actionUrl('unsubscribe', $contactId) : home_url('/');
        $view_in_browser_url = $contactId > 0 ? Frontend::actionUrl('view', $contactId, ['campaign' => $campaignId]) : home_url('/');
        $tracking_pixel_url = ! empty($settings['track_opens']) && $contactId > 0
            ? add_query_arg(['dizzy_nl_action' => 'open', 'campaign' => $campaignId, 'contact' => $contactId, 'sig' => Frontend::signature('open', $contactId, $campaignId)], home_url('/'))
            : '';

        ob_start();
        include DIZZY_NL_DIR . 'includes/Email/Templates/newsletter.php';
        $html = (string) ob_get_clean();
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field((string) $settings['from_name']) . ' <' . sanitize_email((string) $settings['from_email']) . '>',
            'Reply-To: ' . sanitize_email((string) $settings['reply_to']),
            'List-Unsubscribe: <' . esc_url_raw($unsubscribe_url) . '>',
        ];
        return wp_mail($subscriber_email, $email_subject, $html, $headers);
    }

    private function trackingUrl(string $url, int $campaignId, int $contactId): string
    {
        if ($url === '' || $contactId === 0) {
            return $url;
        }
        return add_query_arg([
            'dizzy_nl_action' => 'click',
            'campaign' => $campaignId,
            'contact' => $contactId,
            'target' => rawurlencode($url),
            'sig' => Frontend::signature('click', $contactId, $campaignId),
        ], home_url('/'));
    }

    private function settings(): array
    {
        return wp_parse_args((array) get_option('dizzy_nl_settings', []), [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'reply_to' => get_option('admin_email'),
            'track_opens' => 0,
            'batch_size' => 25,
        ]);
    }
}
