<?php

declare(strict_types=1);

namespace Dizzy\Newsletter;

defined('ABSPATH') || exit;

final class Admin
{
    public function __construct(private Repository $repository, private CampaignSender $sender)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        foreach (['save_campaign', 'send_campaign', 'test_campaign', 'save_contact', 'delete_contact', 'import', 'export', 'settings'] as $action) {
            add_action('admin_post_dizzy_nl_' . $action, [$this, str_replace(' ', '', ucwords(str_replace('_', ' ', $action)))]);
        }
    }

    public function menu(): void
    {
        add_menu_page(__('Newsletter', 'dizzy-newsletter'), __('Newsletter', 'dizzy-newsletter'), 'manage_options', 'dizzy-newsletter', [$this, 'campaignsPage'], 'dashicons-email-alt2', 27);
        add_submenu_page('dizzy-newsletter', __('Campaigns', 'dizzy-newsletter'), __('Campaigns', 'dizzy-newsletter'), 'manage_options', 'dizzy-newsletter', [$this, 'campaignsPage']);
        add_submenu_page('dizzy-newsletter', __('Add Campaign', 'dizzy-newsletter'), __('Add Campaign', 'dizzy-newsletter'), 'manage_options', 'dizzy-newsletter-campaign', [$this, 'campaignPage']);
        add_submenu_page('dizzy-newsletter', __('Subscribers', 'dizzy-newsletter'), __('Subscribers', 'dizzy-newsletter'), 'manage_options', 'dizzy-newsletter-audience', [$this, 'audiencePage']);
        add_submenu_page('dizzy-newsletter', __('Analytics', 'dizzy-newsletter'), __('Analytics', 'dizzy-newsletter'), 'manage_options', 'dizzy-newsletter-analytics', [$this, 'analyticsPage']);
        add_submenu_page('dizzy-newsletter', __('Settings', 'dizzy-newsletter'), __('Settings', 'dizzy-newsletter'), 'manage_options', 'dizzy-newsletter-settings', [$this, 'settingsPage']);
    }

    public function assets(string $hook): void
    {
        if (! str_contains($hook, 'dizzy-newsletter')) {
            return;
        }
        wp_enqueue_style('dizzy-newsletter-admin', DIZZY_NL_URL . 'assets/admin.css', [], DIZZY_NL_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('dizzy-newsletter-admin', DIZZY_NL_URL . 'assets/admin.js', ['jquery', 'media-editor'], DIZZY_NL_VERSION, true);
    }

    public function campaignsPage(): void
    {
        $this->header(__('Campaigns', 'dizzy-newsletter'), __('Create, schedule and monitor newsletter campaigns.', 'dizzy-newsletter'));
        if (isset($_GET['send_locked'])) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('This campaign cannot be sent again until its 24-hour waiting period has ended.', 'dizzy-newsletter') . '</p></div>';
        } elseif (isset($_GET['queued'])) {
            printf(
                '<div class="notice notice-success inline"><p>%s</p></div>',
                esc_html(sprintf(__('Campaign queued for %d subscribers.', 'dizzy-newsletter'), absint($_GET['queued'])))
            );
        }
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=dizzy-newsletter-campaign')) . '">' . esc_html__('Add Campaign', 'dizzy-newsletter') . '</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Campaign', 'dizzy-newsletter') . '</th><th>' . esc_html__('Subject', 'dizzy-newsletter') . '</th><th>' . esc_html__('Status', 'dizzy-newsletter') . '</th><th>' . esc_html__('Recipients', 'dizzy-newsletter') . '</th><th>' . esc_html__('Sent', 'dizzy-newsletter') . '</th><th>' . esc_html__('Failed', 'dizzy-newsletter') . '</th><th></th></tr></thead><tbody>';
        foreach ($this->repository->campaigns() as $row) {
            $url = add_query_arg(['page' => 'dizzy-newsletter-campaign', 'id' => (int) $row['id']], admin_url('admin.php'));
            echo '<tr><td><strong>' . esc_html((string) $row['name']) . '</strong></td><td>' . esc_html((string) $row['subject']) . '</td><td><span class="dizzy-nl-status">' . esc_html((string) $row['status']) . '</span></td><td>' . absint($row['recipients']) . '</td><td>' . absint($row['sent']) . '</td><td>' . absint($row['failed']) . '</td><td><a href="' . esc_url($url) . '">' . esc_html__('Edit', 'dizzy-newsletter') . '</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function campaignPage(): void
    {
        $campaign = $this->repository->campaign(absint($_GET['id'] ?? 0)) ?: [];
        $this->header($campaign ? __('Edit Campaign', 'dizzy-newsletter') : __('Add Campaign', 'dizzy-newsletter'), __('Build the message, select a subscriber tag and send immediately or later.', 'dizzy-newsletter'));
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dizzy-nl-card">
            <input type="hidden" name="action" value="dizzy_nl_save_campaign"><input type="hidden" name="id" value="<?php echo absint($campaign['id'] ?? 0); ?>">
            <?php wp_nonce_field('dizzy_nl_save_campaign'); ?>
            <div class="dizzy-nl-grid">
                <label><?php esc_html_e('Campaign name', 'dizzy-newsletter'); ?><input required name="name" value="<?php echo esc_attr((string) ($campaign['name'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Email subject', 'dizzy-newsletter'); ?><input required name="subject" value="<?php echo esc_attr((string) ($campaign['subject'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Preheader', 'dizzy-newsletter'); ?><input name="preheader" value="<?php echo esc_attr((string) ($campaign['preheader'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Audience tag (blank = everyone)', 'dizzy-newsletter'); ?><input name="target_tag" value="<?php echo esc_attr((string) ($campaign['target_tag'] ?? '')); ?>"></label>
                <div class="dizzy-nl-hero-field">
                    <span class="dizzy-nl-field-label"><?php esc_html_e('Hero image', 'dizzy-newsletter'); ?></span>
                    <input type="hidden" class="dizzy-nl-hero-url" name="hero_image_url" value="<?php echo esc_attr((string) ($campaign['hero_image_url'] ?? '')); ?>">
                    <div class="dizzy-nl-hero-preview"<?php echo empty($campaign['hero_image_url']) ? ' hidden' : ''; ?>>
                        <img src="<?php echo esc_url((string) ($campaign['hero_image_url'] ?? '')); ?>" alt="">
                    </div>
                    <p>
                        <button type="button" class="button dizzy-nl-select-hero"><?php esc_html_e('Select / Upload Image', 'dizzy-newsletter'); ?></button>
                        <button type="button" class="button dizzy-nl-remove-hero"<?php echo empty($campaign['hero_image_url']) ? ' hidden' : ''; ?>><?php esc_html_e('Remove Image', 'dizzy-newsletter'); ?></button>
                    </p>
                </div>
                <label><?php esc_html_e('Button text', 'dizzy-newsletter'); ?><input name="button_text" value="<?php echo esc_attr((string) ($campaign['button_text'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Button URL', 'dizzy-newsletter'); ?><input type="url" name="button_url" value="<?php echo esc_attr((string) ($campaign['button_url'] ?? '')); ?>"></label>
            </div>
            <h2><?php esc_html_e('Email content', 'dizzy-newsletter'); ?></h2>
            <?php wp_editor((string) ($campaign['content'] ?? ''), 'dizzy_nl_content', ['textarea_name' => 'content', 'textarea_rows' => 16]); ?>
            <p><button class="button button-primary button-large"><?php esc_html_e('Save Campaign', 'dizzy-newsletter'); ?></button></p>
        </form>
        <?php if ($campaign) : ?>
            <?php $queueState = $this->repository->campaignQueueState((int) $campaign['id']); ?>
            <div class="dizzy-nl-actions dizzy-nl-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="dizzy_nl_send_campaign"><input type="hidden" name="id" value="<?php echo absint($campaign['id']); ?>"><?php wp_nonce_field('dizzy_nl_send_campaign'); ?>
                    <label><?php esc_html_e('Schedule (optional)', 'dizzy-newsletter'); ?> <input type="datetime-local" name="scheduled_at"></label>
                    <button
                        class="button button-primary dizzy-nl-queue-button"
                        <?php disabled(! $queueState['allowed']); ?>
                        data-available-at="<?php echo esc_attr((string) ($queueState['available_at'] * 1000)); ?>"
                    ><?php esc_html_e('Queue Campaign', 'dizzy-newsletter'); ?></button>
                    <span class="dizzy-nl-queue-message">
                        <?php
                        if ($queueState['pending'] > 0) {
                            esc_html_e('This campaign is currently queued or sending.', 'dizzy-newsletter');
                        } elseif (! $queueState['allowed'] && $queueState['available_at'] > 0) {
                            printf(
                                esc_html__('This campaign can be sent again after %s.', 'dizzy-newsletter'),
                                esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $queueState['available_at']))
                            );
                        }
                        ?>
                    </span>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="dizzy_nl_test_campaign"><input type="hidden" name="id" value="<?php echo absint($campaign['id']); ?>"><?php wp_nonce_field('dizzy_nl_test_campaign'); ?>
                    <input type="email" required name="test_email" placeholder="name@example.com"><button class="button"><?php esc_html_e('Send Test', 'dizzy-newsletter'); ?></button>
                </form>
            </div>
            <script>
            (() => {
                const button = document.querySelector('.dizzy-nl-queue-button');
                const message = document.querySelector('.dizzy-nl-queue-message');
                if (!button || !button.disabled) return;
                const availableAt = Number(button.dataset.availableAt || 0);
                if (!availableAt) return;
                const update = () => {
                    const remaining = availableAt - Date.now();
                    if (remaining <= 0) {
                        button.disabled = false;
                        if (message) message.textContent = '';
                        return;
                    }
                    const hours = Math.floor(remaining / 3600000);
                    const minutes = Math.ceil((remaining % 3600000) / 60000);
                    if (message) message.textContent = <?php echo wp_json_encode(__('Available again in', 'dizzy-newsletter')); ?> + ' ' + hours + 'h ' + minutes + 'm';
                    window.setTimeout(update, 30000);
                };
                update();
            })();
            </script>
        <?php endif;
        $previewCampaign = wp_parse_args($campaign, [
            'id' => 0,
            'name' => __('Newsletter Preview', 'dizzy-newsletter'),
            'subject' => __('Newsletter Preview', 'dizzy-newsletter'),
            'preheader' => __('Your campaign preheader will appear here.', 'dizzy-newsletter'),
            'content' => '<p>' . __('Your campaign content preview will appear here.', 'dizzy-newsletter') . '</p>',
            'hero_image_url' => '',
            'button_text' => __('Read more', 'dizzy-newsletter'),
            'button_url' => home_url('/'),
        ]);
        $previewHtml = $this->sender->renderCampaignHtml(
            $previewCampaign,
            ['id' => 0, 'name' => __('Subscriber Name', 'dizzy-newsletter'), 'email' => 'subscriber@example.com'],
            false
        );
        ?>
        <section class="dizzy-nl-card dizzy-nl-preview-section">
            <h2><?php esc_html_e('Preview', 'dizzy-newsletter'); ?></h2>
            <p class="description"><?php esc_html_e('This preview uses the same email template as the delivered campaign. Save the campaign to refresh edited content.', 'dizzy-newsletter'); ?></p>
            <div class="dizzy-nl-preview-stage">
                <iframe
                    class="dizzy-nl-preview-frame"
                    title="<?php esc_attr_e('Campaign email preview', 'dizzy-newsletter'); ?>"
                    srcdoc="<?php echo esc_attr($previewHtml); ?>"
                    sandbox
                ></iframe>
            </div>
        </section>
        <?php echo '</div>';
    }

    public function audiencePage(): void
    {
        $page = max(1, absint($_GET['paged'] ?? 1));
        $search = sanitize_text_field(wp_unslash((string) ($_GET['s'] ?? '')));
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        $data = $this->repository->contacts($page, 50, $search, $status);
        $stats = $this->repository->subscriberStats();
        $this->header(__('Subscribers', 'dizzy-newsletter'), sprintf(__('%d records. Fifty subscribers are shown per page.', 'dizzy-newsletter'), $data['total']));
        ?>
        <div class="dizzy-nl-stats dizzy-nl-subscriber-stats">
            <?php foreach ([
                __('Total Subscribers', 'dizzy-newsletter') => $stats['total'],
                __('Today', 'dizzy-newsletter') => $stats['today'],
                __('This Month', 'dizzy-newsletter') => $stats['month'],
                __('Unsubscribed', 'dizzy-newsletter') => $stats['unsubscribed'],
            ] as $label => $value) : ?>
                <div><strong><?php echo esc_html((string) $value); ?></strong><span><?php echo esc_html($label); ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="dizzy-nl-toolbar">
            <form><input type="hidden" name="page" value="dizzy-newsletter-audience"><input name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search contacts', 'dizzy-newsletter'); ?>"><select name="status"><option value=""><?php esc_html_e('All statuses', 'dizzy-newsletter'); ?></option><?php foreach (['subscribed','pending','unsubscribed','bounced'] as $value) : ?><option <?php selected($status, $value); ?>><?php echo esc_html(ucfirst($value)); ?></option><?php endforeach; ?></select><button class="button"><?php esc_html_e('Filter', 'dizzy-newsletter'); ?></button></form>
            <span><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dizzy_nl_export&format=csv'), 'dizzy_nl_export')); ?>"><?php esc_html_e('Export CSV / Google Sheets', 'dizzy-newsletter'); ?></a> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dizzy_nl_export&format=txt'), 'dizzy_nl_export')); ?>"><?php esc_html_e('Export TXT', 'dizzy-newsletter'); ?></a></span>
        </div>
        <details class="dizzy-nl-card dizzy-nl-add-subscriber">
            <summary><?php esc_html_e('Add contact', 'dizzy-newsletter'); ?></summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dizzy_nl_save_contact">
                <?php wp_nonce_field('dizzy_nl_save_contact'); ?>
                <div class="dizzy-nl-add-subscriber-fields">
                    <label>
                        <span><?php esc_html_e('Name', 'dizzy-newsletter'); ?></span>
                        <input name="name" placeholder="<?php esc_attr_e('Name', 'dizzy-newsletter'); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Email', 'dizzy-newsletter'); ?></span>
                        <input required type="email" name="email" placeholder="name@example.com">
                    </label>
                    <label>
                        <span><?php esc_html_e('Tags', 'dizzy-newsletter'); ?></span>
                        <input name="tags" value="website" placeholder="website">
                    </label>
                </div>
                <p class="dizzy-nl-add-subscriber-submit"><button class="button button-primary"><?php esc_html_e('Add', 'dizzy-newsletter'); ?></button></p>
            </form>
        </details>
        <details class="dizzy-nl-card"><summary><?php esc_html_e('Import CSV, TXT or Google Sheet', 'dizzy-newsletter'); ?></summary><form enctype="multipart/form-data" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="dizzy_nl_import"><?php wp_nonce_field('dizzy_nl_import'); ?><input type="file" name="import_file" accept=".csv,.txt"><input type="url" name="sheet_url" placeholder="Published Google Sheet CSV URL"><button class="button"><?php esc_html_e('Import', 'dizzy-newsletter'); ?></button><p class="description"><?php esc_html_e('Accepted columns: email, name, tags. Publish Google Sheets as CSV before importing.', 'dizzy-newsletter'); ?></p></form></details>
        <table class="widefat striped"><thead><tr><th><?php esc_html_e('Name', 'dizzy-newsletter'); ?></th><th>Email</th><th><?php esc_html_e('Status', 'dizzy-newsletter'); ?></th><th><?php esc_html_e('Tags', 'dizzy-newsletter'); ?></th><th><?php esc_html_e('Source', 'dizzy-newsletter'); ?></th><th><?php esc_html_e('Subscribed', 'dizzy-newsletter'); ?></th><th></th></tr></thead><tbody>
        <?php foreach ($data['rows'] as $row) : ?><tr><td><?php echo esc_html((string) $row['name']); ?></td><td><?php echo esc_html((string) $row['email']); ?></td><td><?php echo esc_html((string) $row['status']); ?></td><td><?php echo esc_html((string) $row['tags']); ?></td><td><?php echo esc_html((string) $row['source']); ?></td><td><?php echo esc_html((string) ($row['confirmed_at'] ?: $row['created_at'])); ?></td><td><a class="submitdelete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dizzy_nl_delete_contact&id=' . absint($row['id'])), 'dizzy_nl_delete_contact')); ?>"><?php esc_html_e('Delete', 'dizzy-newsletter'); ?></a></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php if ($data['pages'] > 1) : ?>
            <div class="tablenav-pages">
                <?php
                $pagination = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'current' => $page,
                    'total' => $data['pages'],
                    'type' => 'list',
                ]);
                echo $pagination !== null ? wp_kses_post($pagination) : '';
                ?>
            </div>
        <?php endif; ?>
        </div>
        <?php
    }

    public function analyticsPage(): void
    {
        $data = $this->repository->analytics();
        $eventTotals = [];
        foreach ($data['events'] as $event) {
            $eventTotals[$event['event_type']] = ($eventTotals[$event['event_type']] ?? 0) + (int) $event['total'];
        }
        $this->header(__('Analytics', 'dizzy-newsletter'), __('Sending and engagement overview.', 'dizzy-newsletter'));
        echo '<div class="dizzy-nl-stats">';
        foreach (['sent' => __('Sent', 'dizzy-newsletter'), 'failed' => __('Failed', 'dizzy-newsletter'), 'queued' => __('Queued', 'dizzy-newsletter'), 'open' => __('Opens', 'dizzy-newsletter'), 'click' => __('Clicks', 'dizzy-newsletter'), 'unsubscribe' => __('Unsubscribed', 'dizzy-newsletter')] as $key => $label) {
            $value = array_key_exists($key, $data['totals']) ? $data['totals'][$key] : ($eventTotals[$key] ?? 0);
            echo '<div><strong>' . absint($value) . '</strong><span>' . esc_html($label) . '</span></div>';
        }
        echo '</div><table class="widefat striped"><thead><tr><th>Campaign</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Date</th></tr></thead><tbody>';
        foreach ($data['campaigns'] as $row) {
            echo '<tr><td>' . esc_html((string) $row['name']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>' . absint($row['recipients']) . '</td><td>' . absint($row['sent']) . '</td><td>' . absint($row['failed']) . '</td><td>' . esc_html((string) ($row['completed_at'] ?: $row['scheduled_at'] ?: $row['created_at'])) . '</td></tr>';
        }
        echo '</tbody></table><p class="description">' . esc_html__('wp_mail success means WordPress handed the message to its mail system. Confirmed delivery and bounce reporting require a mail provider webhook.', 'dizzy-newsletter') . '</p></div>';
    }

    public function settingsPage(): void
    {
        $s = wp_parse_args((array) get_option('dizzy_nl_settings', []), ['from_name' => '', 'from_email' => '', 'reply_to' => '', 'double_optin' => 1, 'track_opens' => 0, 'batch_size' => 25, 'privacy_url' => '']);
        $this->header(__('Settings', 'dizzy-newsletter'), __('Sender, privacy and delivery controls.', 'dizzy-newsletter')); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dizzy-nl-card"><input type="hidden" name="action" value="dizzy_nl_settings"><?php wp_nonce_field('dizzy_nl_settings'); ?>
            <table class="form-table"><tr><th><?php esc_html_e('Sender name', 'dizzy-newsletter'); ?></th><td><input required name="from_name" value="<?php echo esc_attr($s['from_name']); ?>"></td></tr><tr><th><?php esc_html_e('Sender email', 'dizzy-newsletter'); ?></th><td><input required type="email" name="from_email" value="<?php echo esc_attr($s['from_email']); ?>"></td></tr><tr><th>Reply-to</th><td><input required type="email" name="reply_to" value="<?php echo esc_attr($s['reply_to']); ?>"></td></tr><tr><th><?php esc_html_e('Privacy policy URL', 'dizzy-newsletter'); ?></th><td><input class="regular-text" type="url" name="privacy_url" value="<?php echo esc_attr($s['privacy_url']); ?>"></td></tr><tr><th><?php esc_html_e('Batch size', 'dizzy-newsletter'); ?></th><td><input type="number" min="1" max="100" name="batch_size" value="<?php echo absint($s['batch_size']); ?>"></td></tr><tr><th><?php esc_html_e('Double opt-in', 'dizzy-newsletter'); ?></th><td><label><input type="checkbox" name="double_optin" value="1" <?php checked($s['double_optin']); ?>> <?php esc_html_e('Require email confirmation', 'dizzy-newsletter'); ?></label></td></tr><tr><th><?php esc_html_e('Open tracking', 'dizzy-newsletter'); ?></th><td><label><input type="checkbox" name="track_opens" value="1" <?php checked($s['track_opens']); ?>> <?php esc_html_e('Use a tracking pixel (privacy-sensitive and approximate)', 'dizzy-newsletter'); ?></label></td></tr></table><button class="button button-primary"><?php esc_html_e('Save Settings', 'dizzy-newsletter'); ?></button></form></div>
        <?php
    }

    public function saveCampaign(): void { $this->guard('dizzy_nl_save_campaign'); $id = $this->repository->saveCampaign(wp_unslash($_POST)); $this->redirect('dizzy-newsletter-campaign', ['id' => $id, 'saved' => 1]); }
    public function sendCampaign(): void { $this->guard('dizzy_nl_send_campaign'); $raw = sanitize_text_field(wp_unslash((string) ($_POST['scheduled_at'] ?? ''))); $scheduled = $raw !== '' ? get_gmt_from_date(str_replace('T', ' ', $raw) . ':00') : null; $count = $this->repository->enqueueCampaign(absint($_POST['id'] ?? 0), $scheduled); if ($count >= 0 && $scheduled === null) wp_schedule_single_event(time() + 10, 'dizzy_nl_process_queue'); $this->redirect('dizzy-newsletter', $count < 0 ? ['send_locked' => 1] : ['queued' => $count]); }
    public function testCampaign(): void { $this->guard('dizzy_nl_test_campaign'); $ok = $this->sender->sendTest(absint($_POST['id'] ?? 0), sanitize_email(wp_unslash((string) ($_POST['test_email'] ?? '')))); $this->redirect('dizzy-newsletter-campaign', ['id' => absint($_POST['id'] ?? 0), 'test' => $ok ? 1 : 0]); }
    public function saveContact(): void { $this->guard('dizzy_nl_save_contact'); $tags = explode(',', sanitize_text_field(wp_unslash((string) ($_POST['tags'] ?? '')))); $this->repository->saveContact((string) $_POST['email'], (string) ($_POST['name'] ?? ''), 'admin', $tags, true); $this->redirect('dizzy-newsletter-audience'); }
    public function deleteContact(): void { $this->guard('dizzy_nl_delete_contact'); $deleted = $this->repository->deleteContact(absint($_GET['id'] ?? 0)); $this->redirect('dizzy-newsletter-audience', ['deleted' => $deleted ? 1 : 0]); }

    public function import(): void
    {
        $this->guard('dizzy_nl_import');
        $content = '';
        $sheet = esc_url_raw(wp_unslash((string) ($_POST['sheet_url'] ?? '')));
        if ($sheet !== '') {
            $response = wp_safe_remote_get($sheet, ['timeout' => 20, 'limit_response_size' => 5 * MB_IN_BYTES]);
            if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) $content = wp_remote_retrieve_body($response);
        } elseif (! empty($_FILES['import_file']['tmp_name']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            $content = (string) file_get_contents($_FILES['import_file']['tmp_name']);
        }
        $count = 0;
        $lines = preg_split('/\R/', $content) ?: [];
        foreach ($lines as $index => $line) {
            $row = str_getcsv($line);
            if ($index === 0 && isset($row[0]) && ! is_email(trim($row[0]))) continue;
            if (! isset($row[0]) || ! is_email(trim($row[0]))) continue;
            $result = $this->repository->saveContact(trim($row[0]), trim($row[1] ?? ''), $sheet ? 'google-sheets' : 'file-import', explode(',', (string) ($row[2] ?? '')), true);
            if (! empty($result['ok'])) $count++;
        }
        $this->redirect('dizzy-newsletter-audience', ['imported' => $count]);
    }

    public function export(): void
    {
        $this->guard('dizzy_nl_export');
        $format = sanitize_key((string) ($_GET['format'] ?? 'csv'));
        nocache_headers();
        if ($format === 'txt') {
            header('Content-Type: text/plain; charset=UTF-8'); header('Content-Disposition: attachment; filename=dizzy-newsletter-audience.txt');
            $page = 1; do { $data = $this->repository->contacts($page++, 500); foreach ($data['rows'] as $row) echo sanitize_email((string) $row['email']) . "\r\n"; } while ($page <= $data['pages']); exit;
        }
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename=dizzy-newsletter-audience.csv');
        $out = fopen('php://output', 'wb'); fputcsv($out, ['email', 'name', 'status', 'tags', 'source', 'subscribed']);
        $page = 1; do { $data = $this->repository->contacts($page++, 500); foreach ($data['rows'] as $row) fputcsv($out, [$row['email'], $row['name'], $row['status'], $row['tags'], $row['source'], $row['confirmed_at']]); } while ($page <= $data['pages']); fclose($out); exit;
    }

    public function settings(): void
    {
        $this->guard('dizzy_nl_settings');
        update_option('dizzy_nl_settings', ['from_name' => sanitize_text_field(wp_unslash((string) $_POST['from_name'])), 'from_email' => sanitize_email(wp_unslash((string) $_POST['from_email'])), 'reply_to' => sanitize_email(wp_unslash((string) $_POST['reply_to'])), 'privacy_url' => esc_url_raw(wp_unslash((string) ($_POST['privacy_url'] ?? ''))), 'batch_size' => min(100, max(1, absint($_POST['batch_size'] ?? 25))), 'double_optin' => ! empty($_POST['double_optin']) ? 1 : 0, 'track_opens' => ! empty($_POST['track_opens']) ? 1 : 0]);
        $this->redirect('dizzy-newsletter-settings', ['saved' => 1]);
    }

    private function header(string $title, string $description): void { echo '<div class="wrap dizzy-nl-admin"><div class="dizzy-nl-head"><div><h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p></div></div>'; }
    private function guard(string $action): void { if (! current_user_can('manage_options')) wp_die('Forbidden', '', ['response' => 403]); check_admin_referer($action); }
    private function redirect(string $page, array $args = []): void { wp_safe_redirect(add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'))); exit; }
}

