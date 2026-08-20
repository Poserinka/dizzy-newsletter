=== Dizzy Newsletter ===
Contributors: poserinka
Tags: newsletter, email, campaigns, subscribers
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.0.6
License: GPLv2 or later

Independent newsletter campaigns, audience management, analytics, queued sending, Gutenberg signup block and shortcode.

== Installation ==
Upload the dizzy-newsletter directory, activate the plugin and configure Newsletter > Settings.

== Usage ==
Insert the Dizzy Newsletter Signup block or use [dizzy_newsletter].

== Changelog ==
= 1.0.6 =
* Select, upload, preview and remove campaign hero images through the WordPress Media Library.

= 1.0.5 =
* Allow the same campaign to be sent again after a mandatory 24-hour waiting period.
* Show the remaining resend time and enforce the limit on the server.

= 1.0.4 =
* Preserve campaign paragraph breaks with email-safe inline spacing.

= 1.0.3 =
* Make newsletter signup idempotent and allow an explicitly submitted address to rejoin after unsubscribing.
* Refresh consent, source, name and tags when an existing subscriber submits the form again.

= 1.0.2 =
* Fully remove a deleted contact and its queued and analytics records so the address can subscribe again.

= 1.0.1 =
* Avoid a PHP 8.1 deprecation warning when the audience has only one page.

= 1.0.0 =
* Initial release.

