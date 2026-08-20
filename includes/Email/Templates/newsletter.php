<?php
/**
 * Dizzy Newsletter email template.
 *
 * Available variables: $campaign_title, $email_subject, $preheader,
 * $subscriber_name, $subscriber_email, $content, $hero_image_url,
 * $button_text, $button_url, $site_name, $site_url, $unsubscribe_url,
 * $view_in_browser_url and $tracking_pixel_url.
 */
defined('ABSPATH') || exit;
?>
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html($email_subject); ?></title></head>
<body style="margin:0;padding:0;background:#0a0a0a;color:#f7f7f7;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden;opacity:0"><?php echo esc_html($preheader); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0a0a0a"><tr><td align="center" style="padding:30px 15px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#191919">
<tr><td align="center" style="padding:34px 30px 26px;border-bottom:1px solid #333"><a href="<?php echo esc_url($site_url); ?>" style="color:#fff;font-size:26px;font-weight:700;text-decoration:none"><?php echo esc_html($site_name); ?></a></td></tr>
<?php if ($hero_image_url !== '') : ?><tr><td><img src="<?php echo esc_url($hero_image_url); ?>" width="600" alt="" style="display:block;width:100%;height:auto;border:0"></td></tr><?php endif; ?>
<tr><td style="padding:42px 42px 20px"><h1 style="margin:0 0 24px;font-size:34px;line-height:1.2;color:#fff"><?php echo esc_html($campaign_title); ?></h1>
<?php if ($subscriber_name !== '') : ?><p style="color:#ddd;font-size:16px;line-height:1.7"><?php printf(esc_html__('Hello %s,', 'dizzy-newsletter'), esc_html($subscriber_name)); ?></p><?php endif; ?>
<div style="color:#ddd;font-size:16px;line-height:1.7"><?php echo wp_kses_post($content); ?></div></td></tr>
<?php if ($button_text !== '' && $button_url !== '') : ?><tr><td align="center" style="padding:16px 42px 44px"><a href="<?php echo esc_url($button_url); ?>" style="display:inline-block;background:#fff;color:#111;padding:16px 30px;font-size:13px;font-weight:700;letter-spacing:1.5px;text-decoration:none;text-transform:uppercase"><?php echo esc_html($button_text); ?></a></td></tr><?php endif; ?>
<tr><td align="center" style="padding:28px 30px;border-top:1px solid #333;color:#999;font-size:12px;line-height:1.7"><p style="margin:0 0 8px"><a href="<?php echo esc_url($view_in_browser_url); ?>" style="color:#ccc">View in browser</a> &nbsp;·&nbsp; <a href="<?php echo esc_url($unsubscribe_url); ?>" style="color:#ccc">Unsubscribe</a></p><p style="margin:0">© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html($site_name); ?></p></td></tr>
</table></td></tr></table>
<?php if ($tracking_pixel_url !== '') : ?><img src="<?php echo esc_url($tracking_pixel_url); ?>" width="1" height="1" alt="" style="display:none"><?php endif; ?>
</body></html>

