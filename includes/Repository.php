<?php

declare(strict_types=1);

namespace Dizzy\Newsletter;

defined('ABSPATH') || exit;

final class Repository
{
    private string $contacts;
    private string $campaigns;
    private string $queue;
    private string $events;

    public function __construct()
    {
        global $wpdb;
        $this->contacts = $wpdb->prefix . 'dizzy_nl_contacts';
        $this->campaigns = $wpdb->prefix . 'dizzy_nl_campaigns';
        $this->queue = $wpdb->prefix . 'dizzy_nl_queue';
        $this->events = $wpdb->prefix . 'dizzy_nl_events';
    }

    public function saveContact(string $email, string $name = '', string $source = 'website', array $tags = [], bool $confirmed = false): array
    {
        global $wpdb;
        $email = strtolower(sanitize_email($email));
        if (! is_email($email)) {
            return ['ok' => false, 'message' => __('Please enter a valid email address.', 'dizzy-newsletter')];
        }
        $existing = $this->contactByEmail($email);
        if ($existing && $existing['status'] === 'subscribed') {
            $now = current_time('mysql', true);
            $wpdb->update($this->contacts, [
                'name' => sanitize_text_field($name),
                'tags' => implode(',', array_filter(array_map('sanitize_key', $tags))),
                'source' => sanitize_text_field($source),
                'consent_at' => $now,
                'updated_at' => $now,
            ], ['id' => (int) $existing['id']]);
            return ['ok' => true, 'id' => (int) $existing['id'], 'token' => '', 'status' => 'subscribed'];
        }
        $now = current_time('mysql', true);
        $token = bin2hex(random_bytes(32));
        $data = [
            'email' => $email,
            'name' => sanitize_text_field($name),
            'status' => $confirmed ? 'subscribed' : 'pending',
            'tags' => implode(',', array_filter(array_map('sanitize_key', $tags))),
            'source' => sanitize_text_field($source),
            'consent_at' => $now,
            'confirmed_at' => $confirmed ? $now : null,
            'token_hash' => hash('sha256', $token),
            'updated_at' => $now,
        ];
        if ($existing) {
            $wpdb->update($this->contacts, $data, ['id' => (int) $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($this->contacts, $data);
            $id = (int) $wpdb->insert_id;
        }
        return ['ok' => true, 'id' => $id, 'token' => $token, 'status' => $data['status']];
    }

    public function contactByEmail(string $email): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->contacts} WHERE email=%s LIMIT 1", strtolower($email)), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function contactByToken(string $token): ?array
    {
        global $wpdb;
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->contacts} WHERE token_hash=%s LIMIT 1", hash('sha256', $token)), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function setContactStatus(int $id, string $status): void
    {
        global $wpdb;
        $allowed = ['pending', 'subscribed', 'unsubscribed', 'bounced'];
        if (! in_array($status, $allowed, true)) {
            return;
        }
        $now = current_time('mysql', true);
        $data = ['status' => $status, 'updated_at' => $now];
        if ($status === 'subscribed') {
            $data['confirmed_at'] = $now;
        } elseif ($status === 'unsubscribed') {
            $data['unsubscribed_at'] = $now;
        }
        $wpdb->update($this->contacts, $data, ['id' => $id]);
    }

    public function contacts(int $page = 1, int $perPage = 50, string $search = '', string $status = ''): array
    {
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        if ($search !== '') {
            $where[] = '(email LIKE %s OR name LIKE %s OR tags LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($args, $like, $like, $like);
        }
        if (in_array($status, ['pending', 'subscribed', 'unsubscribed', 'bounced'], true)) {
            $where[] = 'status=%s';
            $args[] = $status;
        }
        $base = ' FROM ' . $this->contacts . ' WHERE ' . implode(' AND ', $where);
        $countSql = $args ? $wpdb->prepare('SELECT COUNT(*)' . $base, ...$args) : 'SELECT COUNT(*)' . $base;
        $total = (int) $wpdb->get_var($countSql);
        $sqlArgs = array_merge($args, [$perPage, max(0, ($page - 1) * $perPage)]);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT *' . $base . ' ORDER BY id DESC LIMIT %d OFFSET %d', ...$sqlArgs), ARRAY_A) ?: [];
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function deleteContact(int $id): bool
    {
        global $wpdb;
        if ($id <= 0) {
            return false;
        }

        $wpdb->query('START TRANSACTION');
        $wpdb->delete($this->queue, ['contact_id' => $id], ['%d']);
        $wpdb->delete($this->events, ['contact_id' => $id], ['%d']);
        $deleted = $wpdb->delete($this->contacts, ['id' => $id], ['%d']);

        if ($deleted === 1) {
            $wpdb->query('COMMIT');
            return true;
        }

        $wpdb->query('ROLLBACK');
        return false;
    }

    public function saveCampaign(array $input): int
    {
        global $wpdb;
        $id = absint($input['id'] ?? 0);
        $now = current_time('mysql', true);
        $data = [
            'name' => sanitize_text_field((string) ($input['name'] ?? '')),
            'subject' => sanitize_text_field((string) ($input['subject'] ?? '')),
            'preheader' => sanitize_text_field((string) ($input['preheader'] ?? '')),
            'content' => wp_kses_post((string) ($input['content'] ?? '')),
            'hero_image_url' => esc_url_raw((string) ($input['hero_image_url'] ?? '')),
            'button_text' => sanitize_text_field((string) ($input['button_text'] ?? '')),
            'button_url' => esc_url_raw((string) ($input['button_url'] ?? '')),
            'target_tag' => sanitize_key((string) ($input['target_tag'] ?? '')),
            'updated_at' => $now,
        ];
        if ($id > 0) {
            $wpdb->update($this->campaigns, $data, ['id' => $id]);
            return $id;
        }
        $data['status'] = 'draft';
        $data['created_at'] = $now;
        $wpdb->insert($this->campaigns, $data);
        return (int) $wpdb->insert_id;
    }

    public function campaign(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->campaigns} WHERE id=%d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function campaigns(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT c.*,
            (SELECT COUNT(*) FROM {$this->queue} q WHERE q.campaign_id=c.id) recipients,
            (SELECT COUNT(*) FROM {$this->queue} q WHERE q.campaign_id=c.id AND q.status='sent') sent,
            (SELECT COUNT(*) FROM {$this->queue} q WHERE q.campaign_id=c.id AND q.status='failed') failed
            FROM {$this->campaigns} c ORDER BY c.id DESC", ARRAY_A) ?: [];
    }

    public function enqueueCampaign(int $campaignId, ?string $scheduledAt = null): int
    {
        global $wpdb;
        $campaign = $this->campaign($campaignId);
        if (! $campaign) {
            return 0;
        }

        $queueState = $this->campaignQueueState($campaignId);

        if (! $queueState['allowed']) {
            return -1;
        }

        $where = "status='subscribed'";
        $args = [];
        if ($campaign['target_tag'] !== '') {
            $where .= ' AND FIND_IN_SET(%s,tags)';
            $args[] = $campaign['target_tag'];
        }
        $sql = "SELECT id FROM {$this->contacts} WHERE {$where}";
        $ids = $args ? $wpdb->get_col($wpdb->prepare($sql, ...$args)) : $wpdb->get_col($sql);
        $now = current_time('mysql', true);

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->queue} q
                 LEFT JOIN {$this->contacts} c ON c.id=q.contact_id
                 SET q.status='skipped'
                 WHERE q.campaign_id=%d
                   AND (c.id IS NULL OR c.status<>'subscribed')",
                $campaignId
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->queue} q
                 INNER JOIN {$this->contacts} c ON c.id=q.contact_id
                 SET q.status='queued',q.attempts=0,q.error_message=NULL,
                     q.sent_at=NULL,q.created_at=%s
                 WHERE q.campaign_id=%d AND c.status='subscribed'",
                $now,
                $campaignId
            )
        );

        foreach ($ids as $contactId) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$this->queue} (campaign_id,contact_id,status,created_at) VALUES (%d,%d,'queued',%s)",
                $campaignId,
                (int) $contactId,
                $now
            ));
        }
        $wpdb->update($this->campaigns, [
            'status' => $scheduledAt ? 'scheduled' : 'sending',
            'scheduled_at' => $scheduledAt,
            'started_at' => $scheduledAt ? null : $now,
            'updated_at' => $now,
        ], ['id' => $campaignId]);
        return count($ids);
    }

    /**
     * Determine whether a campaign can be queued now.
     *
     * @return array{allowed:bool,available_at:int,pending:int,last_sent_at:string}
     */
    public function campaignQueueState(int $campaignId): array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    MAX(sent_at) last_sent_at,
                    SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) pending
                 FROM {$this->queue}
                 WHERE campaign_id=%d",
                $campaignId
            ),
            ARRAY_A
        ) ?: [];

        $pending = (int) ($row['pending'] ?? 0);
        $lastSentAt = (string) ($row['last_sent_at'] ?? '');
        $lastSentTimestamp = $lastSentAt !== ''
            ? strtotime($lastSentAt . ' UTC')
            : false;
        $availableAt = $lastSentTimestamp !== false
            ? $lastSentTimestamp + DAY_IN_SECONDS
            : 0;

        return [
            'allowed' => $pending === 0 && ($availableAt === 0 || time() >= $availableAt),
            'available_at' => $availableAt,
            'pending' => $pending,
            'last_sent_at' => $lastSentAt,
        ];
    }

    public function dueQueue(int $limit): array
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare("UPDATE {$this->campaigns} SET status='sending',started_at=%s WHERE status='scheduled' AND scheduled_at<=%s", $now, $now));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.id queue_id,q.campaign_id,q.contact_id,q.attempts,
                    c.email,c.name subscriber_name,
                    p.name,p.subject,p.preheader,p.content,p.hero_image_url,p.button_text,p.button_url,p.target_tag
             FROM {$this->queue} q
             INNER JOIN {$this->contacts} c ON c.id=q.contact_id AND c.status='subscribed'
             INNER JOIN {$this->campaigns} p ON p.id=q.campaign_id AND p.status='sending'
             WHERE q.status='queued' ORDER BY q.id LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
    }

    public function queueResult(int $id, bool $sent, string $error = ''): void
    {
        global $wpdb;
        $wpdb->update($this->queue, [
            'status' => $sent ? 'sent' : 'failed',
            'attempts' => 1,
            'error_message' => $error,
            'sent_at' => $sent ? current_time('mysql', true) : null,
        ], ['id' => $id]);
    }

    public function finishCampaigns(): void
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->campaigns} c SET c.status='sent',c.completed_at=%s,c.updated_at=%s
             WHERE c.status='sending' AND NOT EXISTS (SELECT 1 FROM {$this->queue} q WHERE q.campaign_id=c.id AND q.status='queued')",
            $now,
            $now
        ));
    }

    public function logEvent(int $campaignId, int $contactId, string $type, array $meta = []): void
    {
        global $wpdb;
        $wpdb->insert($this->events, [
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'event_type' => sanitize_key($type),
            'meta' => wp_json_encode($meta),
            'created_at' => current_time('mysql', true),
        ]);
    }

    public function analytics(): array
    {
        global $wpdb;
        $totals = $wpdb->get_row("SELECT
            SUM(status='sent') sent,SUM(status='failed') failed,SUM(status='queued') queued
            FROM {$this->queue}", ARRAY_A) ?: [];
        $events = $wpdb->get_results("SELECT campaign_id,event_type,COUNT(*) total FROM {$this->events} GROUP BY campaign_id,event_type", ARRAY_A) ?: [];
        return ['totals' => $totals, 'events' => $events, 'campaigns' => $this->campaigns()];
    }

    public function cleanup(): void
    {
        global $wpdb;
        $before = gmdate('Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM {$this->events} WHERE created_at<%s", $before));
    }
}

