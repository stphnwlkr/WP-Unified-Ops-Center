# Ops Center

Ops Center is a builder-aware WordPress admin bar command center for WordPress, Bricks Builder, and Etch Builder sites.

The packaged folder is `unified-ops-center`, but the plugin name shown in WordPress remains **Ops Center**.

## Description

Ops Center provides a centralized workflow panel for administrators, content editors, and site builders. It gives allowed users quick access to current content, templates, patterns, enabled content types, builder resources, detected tools, and managed quick links from the WordPress admin bar.

The plugin detects the active builder context and adapts editor links and resource links for WordPress, Bricks, or Etch. Builder detection is shown in **Ops Center → Settings** because it is informational rather than a front-end panel action.

## Features

- Builder-aware behavior for WordPress, Bricks, and Etch.
- Current content shortcuts.
- Template and pattern browser.
- Enabled content type browser.
- Searchable admin bar panel.
- All, Mine, and New filters for the current list.
- Builder-specific resource links.
- Global Quick Links manager.
- Detected tools section.
- Role-based access settings.
- Light, dark, and automatic admin appearance settings.
- Keyboard-friendly, WCAG AA-oriented interface.

## Supported Builders

- WordPress
- Bricks Builder
- Etch Builder

## Accessibility

Ops Center is designed with WCAG AA in mind:

- Keyboard-focusable controls.
- Visible focus states.
- Semantic buttons for filtering and list actions.
- ARIA labels where visual text alone is not enough.
- Accessible labels for icon-only controls.
- Contrast-conscious light and dark interface styles.
- Non-actionable builder detection kept in Settings instead of the admin bar panel.

## Requirements

- WordPress 6.9.4 or newer.
- Tested up to WordPress 7.0.
- PHP 8.3 or newer.
- A user role allowed in Ops Center settings.

## Installation

1. Upload the provided zip from **Plugins → Add Plugin → Upload Plugin**, or upload the `unified-ops-center` folder to `wp-content/plugins/`.
2. Activate **Ops Center**.
3. Go to **Ops Center → Settings**.
4. Confirm the detected builder, enabled sections, content types, and allowed roles.
5. Go to **Ops Center → Resources** to manage resource links and quick links.

## Changelog

### 1.0.1

- Fixed admin bar panel switching after the plugin folder rename.
- Fixed the Ops Center Settings link to use the `unified-ops-center` page slug.
- Improved current template labels so matched templates show a readable title when available.

### 1.0.0

Initial public release.

## License

GPL-2.0-or-later.
