=== Public Preview Links ===
Contributors: blisswebconcept
Donate link: https://blisswebconcept.com/
Tags: preview, drafts, pending, scheduled, public preview
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Generate temporary public preview links for selected post types and statuses, with configurable expiry.

== Description ==
Public Preview Links lets you share unpublished content securely:

- Enable per post (toggle in the editor)
- Choose allowed statuses (Draft, Pending, optionally Scheduled)
- Configure expiry window (1–365 days, default 3 days)
- Classic editor: button in the submit box
- Block editor: status panel item with link, read-only URL field, and Copy button


== Installation ==
1. Upload `public-links-drafts` to `/wp-content/plugins/` and activate.
2. Go to Settings → Public Preview Links to configure post types, statuses, and expiry days.
3. Edit a post and toggle "Enable public preview" to generate a shareable link.

== Frequently Asked Questions ==
= Does it work with custom post types? =
Yes. Any public, viewable post type can be enabled in settings.

= Can I change the expiry duration? =
Yes. Set between 1 and 365 days (default 3 days).

= Does it index my draft content? =
No. Preview responses include a `noindex` directive.

== Screenshots ==
1. Block editor panel with link and copy button
2. Classic editor submit box with preview link
3. Settings page

== Changelog ==
= 0.1.0 =
* Initial release: settings (post types, statuses, expiry), Classic/Block editor UI, per-post toggling, secure preview links.
