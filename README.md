# Public Preview Links

Generate temporary public preview links for selected post types and statuses, with configurable expiry. Includes Classic and Block Editor buttons and per‑post enable/disable toggling.

## Features

- Enable public preview per post (toggle in editor)
- Works with Draft, Pending, and optionally Scheduled posts
- Expiry window: 1–365 days (default 3 days)
- Classic editor button (submit box)
- Block editor status panel with link, read‑only URL field, and Copy button
- Settings page to choose post types, allowed statuses, and expiry days

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the `public-links-drafts` folder to `wp-content/plugins/`.
2. Activate the plugin in WordPress → Plugins.
3. Go to Settings → Public Preview Links to configure:
   - Enabled post types
   - Allowed statuses (draft, pending, future)
   - Link expiry (days)

## Usage

- In the post editor (Classic or Block): toggle “Enable public preview”.
- Copy/share the generated link from the editor UI.
- The preview link is noindex and expires automatically according to your settings.

## Development

- Main bootstrap: `public-links-drafts.php`
- Core logic: `includes/Class_Public_Preview.php`
- Settings UI: `includes/Admin/Class_Settings.php`
- Block Editor UI: `assets/js/editor-button.js`, `assets/css/editor-button.css`

## License

GPL-2.0+ © Emmanuel Kuebutornye

## Author

- Author: Emmanuel Kuebutornye
- Website: https://blisswebconcept.com/
