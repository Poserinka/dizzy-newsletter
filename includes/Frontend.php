<?php

declare(strict_types=1);

namespace Dizzy\Newsletter;

defined('ABSPATH') || exit;

final class Frontend
{
    public function __construct(
        private Repository $repository,
        private CampaignSender $sender
    )
    {
    }

    public function register(): void
    {
        add_shortcode('dizzy_newsletter', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_dizzy_nl_subscribe', [$this, 'subscribe']);
        add_action('wp_ajax_nopriv_dizzy_nl_subscribe', [$this, 'subscribe']);
        add_action('template_redirect', [$this, 'actions']);
        add_action('init', [$this, 'block']);
    }

    public function assets(): void
    {
        wp_register_style('dizzy-newsletter', DIZZY_NL_URL . 'assets/frontend.css', [], DIZZY_NL_VERSION);
        wp_register_script('dizzy-newsletter', DIZZY_NL_URL . 'assets/frontend.js', [], DIZZY_NL_VERSION, true);
        wp_localize_script('dizzy-newsletter', 'DizzyNewsletter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dizzy_nl_subscribe'),
        ]);
    }

    public function block(): void
    {
        wp_register_script('dizzy-newsletter-block', DIZZY_NL_URL . 'assets/block.js', ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'], DIZZY_NL_VERSION, true);
        register_block_type('dizzy/newsletter-signup', [
            'editor_script' => 'dizzy-newsletter-block',
            'render_callback' => fn (array $attributes): string => $this->shortcode($attributes),
            'attributes' => [
                'title' => ['type' => 'string', 'default' => __('Keep Up to Date with Jazzcafe Dizzy', 'dizzy-newsletter')],
                'description' => ['type' => 'string', 'default' => __('Sign up to be the first to receive special news and event updates from Jazzcafe Dizzy.', 'dizzy-newsletter')],
                'namePlaceholder' => ['type' => 'string', 'default' => __('Enter Name', 'dizzy-newsletter')],
                'placeholder' => ['type' => 'string', 'default' => __('Enter Email', 'dizzy-newsletter')],
                'buttonText' => ['type' => 'string', 'default' => __('Sign Up', 'dizzy-newsletter')],
                'tag' => ['type' => 'string', 'default' => 'website'],
                'layout' => ['type' => 'string', 'default' => 'horizontal'],
                'theme' => ['type' => 'string', 'default' => 'dark'],
                'showName' => ['type' => 'boolean', 'default' => true],
            ],
        ]);
    }

    public function shortcode(array $atts = []): string
    {
        $showNameValue = array_key_exists('showName', $atts)
            ? $atts['showName']
            : ($atts['show_name'] ?? true);
        $atts = shortcode_atts([
            'title' => __('Keep Up to Date with Jazzcafe Dizzy', 'dizzy-newsletter'),
            'description' => __('Sign up to be the first to receive special news and event updates from Jazzcafe Dizzy.', 'dizzy-newsletter'),
            'namePlaceholder' => __('Enter Name', 'dizzy-newsletter'),
            'name_placeholder' => '', 'placeholder' => __('Enter Email', 'dizzy-newsletter'),
            'buttonText' => __('Sign Up', 'dizzy-newsletter'), 'button_text' => '',
            'tag' => 'website', 'layout' => 'horizontal', 'theme' => 'dark',
            'showName' => true, 'show_name' => true,
        ], $atts);
        $button = $atts['button_text'] !== '' ? $atts['button_text'] : $atts['buttonText'];
        $namePlaceholder = $atts['name_placeholder'] !== '' ? $atts['name_placeholder'] : $atts['namePlaceholder'];
        $showName = filter_var($showNameValue, FILTER_VALIDATE_BOOLEAN);
        wp_enqueue_style('dizzy-newsletter');
        wp_enqueue_script('dizzy-newsletter');
        ob_start(); ?>
        <div class="dizzy-nl-signup dizzy-nl-<?php echo esc_attr((string) $atts['theme']); ?> dizzy-nl-<?php echo esc_attr((string) $atts['layout']); ?>">
            <?php if ($atts['title'] !== '' || $atts['description'] !== '') : ?>
                <div class="dizzy-nl-copy">
                    <?php if ($atts['title'] !== '') : ?><h3><?php echo esc_html((string) $atts['title']); ?></h3><?php endif; ?>
                    <?php if ($atts['description'] !== '') : ?><p><?php echo esc_html((string) $atts['description']); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <form class="dizzy-nl-form" novalidate>
                <input type="hidden" name="action" value="dizzy_nl_subscribe">
                <input type="hidden" name="tag" value="<?php echo esc_attr((string) $atts['tag']); ?>">
                <input class="dizzy-nl-trap" name="company" tabindex="-1" autocomplete="off">
                <?php if ($showName) : ?><input type="text" name="name" placeholder="<?php echo esc_attr((string) $namePlaceholder); ?>" autocomplete="name"><?php endif; ?>
                <input type="email" name="email" placeholder="<?php echo esc_attr((string) $atts['placeholder']); ?>" required autocomplete="email">
                <button type="submit"><?php echo esc_html((string) $button); ?></button>
                <div class="dizzy-nl-result" role="status" aria-live="polite"></div>
            </form>
        </div>
        <?php return (string) ob_get_clean();
    }

    public function subscribe(): void
    {
        check_ajax_referer('dizzy_nl_subscribe', 'nonce');
        $remote = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $rateKey = 'dizzy_nl_rate_' . substr(hash('sha256', $remote), 0, 32);
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 10) {
            wp_send_json_error(['message' => __('Please wait before trying again.', 'dizzy-newsletter')], 429);
        }
        set_transient($rateKey, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        if (! empty($_POST['company'])) {
            wp_send_json_success(['message' => __('Thank you.', 'dizzy-newsletter')]);
        }
        $settings = (array) get_option('dizzy_nl_settings', []);
        $confirmed = empty($settings['double_optin']);
        $result = $this->repository->saveContact(
            sanitize_email(wp_unslash((string) ($_POST['email'] ?? ''))),
            sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))),
            'website',
            [sanitize_key(wp_unslash((string) ($_POST['tag'] ?? 'website')))],
            $confirmed
        );
        if (empty($result['ok'])) {
            wp_send_json_error(['message' => $result['message']], 400);
        }
        if (($result['status'] ?? '') === 'subscribed') {
            wp_send_json_success(['message' => __('You are subscribed.', 'dizzy-newsletter')]);
        }
        if (! $confirmed) {
            $url = add_query_arg(['dizzy_nl_action' => 'confirm', 'token' => $result['token']], home_url('/'));
            wp_mail(
                sanitize_email(wp_unslash((string) $_POST['email'])),
                __('Confirm your newsletter subscription', 'dizzy-newsletter'),
                sprintf(__('Please confirm your subscription: %s', 'dizzy-newsletter'), $url)
            );
        }
        wp_send_json_success(['message' => $confirmed ? __('You are subscribed.', 'dizzy-newsletter') : __('Please check your email to confirm your subscription.', 'dizzy-newsletter')]);
    }

    public function actions(): void
    {
        $action = sanitize_key((string) ($_GET['dizzy_nl_action'] ?? ''));
        if ($action === '') {
            return;
        }
        if ($action === 'confirm') {
            $contact = $this->repository->contactByToken(sanitize_text_field(wp_unslash((string) ($_GET['token'] ?? ''))));
            if ($contact) {
                $this->repository->setContactStatus((int) $contact['id'], 'subscribed');
                wp_die(esc_html__('Your newsletter subscription is confirmed.', 'dizzy-newsletter'), esc_html__('Subscription confirmed', 'dizzy-newsletter'), ['response' => 200]);
            }
        }
        $contactId = absint($_GET['contact'] ?? 0);
        $campaignId = absint($_GET['campaign'] ?? 0);
        $sig = sanitize_text_field(wp_unslash((string) ($_GET['sig'] ?? '')));
        if ($contactId === 0 || ! hash_equals(self::signature($action, $contactId, $campaignId), $sig)) {
            return;
        }
        if ($action === 'unsubscribe') {
            $this->repository->setContactStatus($contactId, 'unsubscribed');
            $this->repository->logEvent($campaignId, $contactId, 'unsubscribe');
            wp_die(esc_html__('You have been unsubscribed.', 'dizzy-newsletter'), esc_html__('Unsubscribed', 'dizzy-newsletter'), ['response' => 200]);
        }
        if ($action === 'click') {
            $target = esc_url_raw(rawurldecode((string) ($_GET['target'] ?? '')));
            $this->repository->logEvent($campaignId, $contactId, 'click', ['target' => $target]);
            if (! wp_http_validate_url($target)) {
                $target = home_url('/');
            }
            wp_redirect($target, 302, 'Dizzy Newsletter');
            exit;
        }
        if ($action === 'open') {
            $this->repository->logEvent($campaignId, $contactId, 'open');
            status_header(200);
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
            exit;
        }
        if ($action === 'view') {
            $campaign = $this->repository->campaign($campaignId);
            $contact = $this->repository->contact($contactId);
            if ($campaign && $contact) {
                status_header(200);
                nocache_headers();
                header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
                echo $this->sender->renderCampaignHtml($campaign, $contact, false);
                exit;
            }
        }
    }

    public static function actionUrl(string $action, int $contactId, array $extra = []): string
    {
        $campaignId = absint($extra['campaign'] ?? 0);
        return add_query_arg(array_merge($extra, [
            'dizzy_nl_action' => $action,
            'contact' => $contactId,
            'sig' => self::signature($action, $contactId, $campaignId),
        ]), home_url('/'));
    }

    public static function signature(string $action, int $contactId, int $campaignId = 0): string
    {
        return hash_hmac('sha256', $action . '|' . $contactId . '|' . $campaignId, wp_salt('auth'));
    }
}

