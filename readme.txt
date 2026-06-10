=== Ops Center ===
Contributors: stephenwalker
Tags: admin bar, workflow, builder, bricks, etch, templates
Requires at least: 6.9.4
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.1.5.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A builder-aware WordPress admin bar command center for WordPress, Bricks, and Etch sites.

== Description ==

Ops Center adds a centralized, builder-aware command center to the WordPress admin bar. It gives administrators and allowed users quick access to current content, templates, patterns, enabled content types, builder resources, detected tools, and administrator-managed quick links.

The plugin detects whether the site is using WordPress alone, Bricks, or Etch, then adjusts editor links and resource links for that environment. The packaged folder is `unified-ops-center`, but the plugin name shown in WordPress remains Ops Center.

== Features ==

* Builder-aware behavior for WordPress, Bricks, and Etch.
* Searchable admin bar panel.
* All, Mine, and New filters for the current list.
* Optional sections for templates, patterns, content types, detected tools, resources, and quick links.
* Builder-specific resource links for WordPress, Bricks, or Etch.
* Role-based access settings.
* Light, dark, and automatic admin appearance settings.
* Keyboard-visible focus states and WCAG AA-oriented interface choices.

== Supported Builders ==

* WordPress
* Bricks Builder
* Etch Builder

== Accessibility ==

Ops Center is built with WCAG AA-oriented interface choices, including keyboard-focusable controls, visible focus styles, semantic filter buttons, accessible labels for icon-only controls, and contrast-conscious light and dark interface styles.

== Installation ==

1. Upload the plugin zip from Plugins > Add Plugin > Upload Plugin.
2. Activate Ops Center.
3. Open Ops Center > Settings.
4. Confirm the detected builder, enabled sections, content types, and allowed roles.
5. Open Ops Center > Resources to manage resources and quick links.

== Frequently Asked Questions ==

= Why is the folder named unified-ops-center? =

The folder name identifies this as the unified builder-aware build. The public plugin name remains Ops Center.

= Does Ops Center replace Bricks or Etch features? =

No. Ops Center only provides quick access links and workflow shortcuts. It does not replace builder functionality.

= Can I disable sections? =

Yes. Settings allow administrators to enable or disable sections and content types.

= Where is builder detection shown? =

Builder detection is shown in Ops Center > Settings because it is informational and not a direct panel action.

== Changelog ==

= 1.1.5.1 =
* Corrected panel control radii so buttons, inputs, rows, and links use 6px while the main panel remains 12px.

= 1.1.5 =
* Refined panel border radius to 12px.
* Refined action button radius to 6px.
* Added 1em spacing between resource and quick link list items.

= 1.1.4 =
* Refined the admin bar panel styling to match the Ops Center settings interface.
* Improved row action button styling with safer CSS variable fallbacks.
* Added inline WordPress and Etch icons to row action buttons.

= 1.0.1 =
* Fixed admin bar panel switching after the plugin folder rename.
* Fixed the Ops Center Settings link to use the unified-ops-center page slug.
* Improved current template labels so matched templates show a readable title when available.

= 1.0.0 =
* Initial public release.
