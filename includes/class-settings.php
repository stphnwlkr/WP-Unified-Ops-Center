<?php
/**
 * Settings management for Ops Center.
 *
 * @package OpsCenter
 */

declare(strict_types=1);

namespace Ops_Center;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings {
    public const OPTION_KEY = 'ops_center_settings';
    public const PAGE_SLUG  = 'unified-ops-center';

    public function defaults(): array {
        return [
            'enabled_menus' => [
                'templates'    => true,
                'patterns'     => true,
                'integrations' => true,
                'resources'    => true,
                'shortcuts'    => true,
            ],
            'content_types' => $this->public_post_type_names(),
            'show_internal_plugin_post_types' => false,
            'show_detected_tools' => true,
            'detected_tools_enabled' => [
                'acf' => true,
                'metabox' => true,
                'acpt' => true,
                'jetengine' => true,
                'wpcodebox' => true,
            ],
            'remove_default_posts' => false,
            'hide_wp_new_menu' => false,
            'admin_appearance' => 'auto',
            'allowed_roles' => ['administrator'],
            'cleanup_on_deactivation' => false,
            'shortcut_links' => [
                ['label' => 'WordPress Developer Blog', 'url' => 'https://developer.wordpress.org/news/'],
                ['label' => 'WordPress Learn', 'url' => 'https://learn.wordpress.org/'],
            ],
            'resource_links' => [
                ['label' => 'WordPress Documentation', 'url' => 'https://wordpress.org/documentation/'],
                ['label' => 'WordPress Developer Resources', 'url' => 'https://developer.wordpress.org/'],
                ['label' => 'WordPress Plugins', 'url' => 'https://wordpress.org/plugins/'],
                ['label' => 'WordPress Themes', 'url' => 'https://wordpress.org/themes/'],
            ],
        ];
    }

    public function get(): array {
        $settings = get_option(self::OPTION_KEY, []);

        if (!is_array($settings)) {
            $settings = [];
        }

        $defaults = $this->defaults();
        // Use a shallow merge so saved list-style options, such as content_types,
        // replace defaults instead of being merged by numeric index.
        $settings = array_merge($defaults, $settings);

        if (!empty($settings['enabled_menus']) && is_array($settings['enabled_menus'])) {
            $settings['enabled_menus'] = array_merge($defaults['enabled_menus'], $settings['enabled_menus']);
        } else {
            $settings['enabled_menus'] = $defaults['enabled_menus'];
        }

        if (!empty($settings['detected_tools_enabled']) && is_array($settings['detected_tools_enabled'])) {
            $settings['detected_tools_enabled'] = array_merge($defaults['detected_tools_enabled'], $settings['detected_tools_enabled']);
        } else {
            $settings['detected_tools_enabled'] = $defaults['detected_tools_enabled'];
        }

        if (isset($settings['content_types']) && is_array($settings['content_types'])) {
            $settings['content_types'] = array_values(array_unique(array_map('sanitize_key', $settings['content_types'])));
        } else {
            $settings['content_types'] = $defaults['content_types'];
        }

        if (!empty($settings['community_links']) && empty($settings['shortcut_links'])) {
            $settings['shortcut_links'] = $settings['community_links'];
        }

        if (isset($settings['enabled_menus']['community']) && !isset($settings['enabled_menus']['shortcuts'])) {
            $settings['enabled_menus']['shortcuts'] = (bool) $settings['enabled_menus']['community'];
        }

        $settings['enabled_menus']['integrations'] = $settings['enabled_menus']['integrations'] ?? true;
        $settings['show_internal_plugin_post_types'] = (bool) ($settings['show_internal_plugin_post_types'] ?? false);
        $settings['show_detected_tools'] = (bool) ($settings['show_detected_tools'] ?? true);
        $settings['detected_tools_enabled'] = array_merge($defaults['detected_tools_enabled'], array_map('boolval', (array) $settings['detected_tools_enabled']));
        $settings['remove_default_posts'] = (bool) ($settings['remove_default_posts'] ?? false);
        $settings['hide_wp_new_menu'] = (bool) ($settings['hide_wp_new_menu'] ?? false);
        $settings['admin_appearance'] = in_array(($settings['admin_appearance'] ?? 'auto'), ['auto', 'light', 'dark'], true) ? $settings['admin_appearance'] : 'auto';

        return $settings;
    }

    public function can_access(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $settings = $this->get();
        $roles    = isset($settings['allowed_roles']) && is_array($settings['allowed_roles'])
            ? array_filter(array_map('sanitize_key', $settings['allowed_roles']))
            : ['administrator'];

        $user = wp_get_current_user();

        foreach ((array) $user->roles as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return current_user_can('manage_options');
    }

    public function register(): void {
        register_setting(
            'ops_center_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => $this->defaults(),
            ]
        );
    }


    public function public_post_type_names(bool $include_internal = false): array {
        $post_types = get_post_types(
            [
                'show_ui' => true,
            ],
            'objects'
        );

        $excluded = array_merge(
            self::feature_post_type_slugs(),
            [
                'wp_block',
                'wp_navigation',
                'wp_template',
                'wp_template_part',
                'wp_global_styles',
                'wp_font_family',
                'wp_font_face',
                'custom_css',
                'customize_changeset',
                'oembed_cache',
                'user_request',
                'wp_changeset',
                'wp_pattern_category',
                'wp_template_part_area',
            ]
        );

        $names = [];

        foreach ($post_types as $post_type => $post_type_object) {
            if (in_array($post_type, $excluded, true)) {
                continue;
            }

            if (!$include_internal && self::is_internal_plugin_post_type((string) $post_type)) {
                continue;
            }

            $is_allowed_builtin = in_array($post_type, ['post', 'page', 'attachment'], true);

            if (!$is_allowed_builtin && !empty($post_type_object->_builtin)) {
                continue;
            }

            $names[] = (string) $post_type;
        }

        sort($names);

        return $names;
    }



    public static function feature_post_type_slugs(): array {
        return [
            'acf-field-group',
            'acf-post-type',
            'acf-taxonomy',
            'acf-ui-options-page',
            'meta-box',
            'mb-post-type',
            'mb-taxonomy',
            'mb-settings-page',
            'mb-relationship',
            'mb-views',
            'jet-engine',
        ];
    }


    public static function internal_plugin_post_type_slugs(): array {
        return [
            'shop_order',
            'shop_coupon',
            'product_variation',
            'sc_order',
            'sc_subscription',
            'sc_customer',
            'surecart_order',
            'surecart_subscription',
            'surecart_customer',
            'surecart_checkout',
            'surecart_product',
            'wpcode',
            'wpcode_snippet',
            'wpcode_block',
            'wpforms',
            'wpcf7_contact_form',
            'flamingo_contact',
            'flamingo_inbound',
            'forminator_forms',
            'forminator_polls',
            'forminator_quizzes',
            'rank_math_schema',
            'elementor_library',
            'elementor_font',
            'elementor_icons',
            'bricks_template',
        ];
    }

    public static function internal_plugin_post_type_prefixes(): array {
        return [
            'sc_',
            'surecart_',
            'wpcode_',
            'rank_math_',
            'elementor_',
            'fluentform_',
            'frm_',
            'tribe_',
            'edd_',
            'download_',
        ];
    }

    public static function is_internal_plugin_post_type(string $post_type): bool {
        if (in_array($post_type, self::internal_plugin_post_type_slugs(), true)) {
            return true;
        }

        foreach (self::internal_plugin_post_type_prefixes() as $prefix) {
            if (str_starts_with($post_type, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function sanitize($value): array {
        $defaults = $this->defaults();
        $value    = is_array($value) ? wp_unslash($value) : [];
        $existing = get_option(self::OPTION_KEY, []);
        $settings = array_merge($defaults, is_array($existing) ? $existing : []);
        if (!empty($existing['enabled_menus']) && is_array($existing['enabled_menus'])) {
            $settings['enabled_menus'] = array_merge($defaults['enabled_menus'], $existing['enabled_menus']);
        }
        if (!empty($existing['detected_tools_enabled']) && is_array($existing['detected_tools_enabled'])) {
            $settings['detected_tools_enabled'] = array_merge($defaults['detected_tools_enabled'], $existing['detected_tools_enabled']);
        }
        $screen   = isset($value['settings_screen']) ? sanitize_key((string) $value['settings_screen']) : 'settings';

        if ('resources' === $screen) {
            foreach (['shortcut_links', 'resource_links'] as $link_key) {
                $settings[$link_key] = [];
                $links_value = !empty($value[$link_key]) && is_array($value[$link_key]) ? $value[$link_key] : [];

                foreach ($links_value as $link) {
                    if (!is_array($link)) {
                        continue;
                    }

                    $label = isset($link['label']) ? sanitize_text_field((string) $link['label']) : '';
                    $url   = isset($link['url']) ? esc_url_raw((string) $link['url']) : '';

                    if ($label && $url) {
                        $settings[$link_key][] = [
                            'label' => $label,
                            'url'   => $url,
                        ];
                    }
                }
            }

            if (empty($settings['resource_links'])) {
                $settings['resource_links'] = $defaults['resource_links'];
            }

            return $settings;
        }

        $settings['enabled_menus'] = [
            'templates'    => !empty($value['enabled_menus']['templates']),
            'patterns'     => !empty($value['enabled_menus']['patterns']),
            'integrations' => !empty($value['enabled_menus']['integrations']),
            'resources'    => !empty($value['enabled_menus']['resources']),
            'shortcuts'    => !empty($value['enabled_menus']['shortcuts']) || !empty($value['enabled_menus']['community']),
        ];

        $settings['show_internal_plugin_post_types'] = !empty($value['show_internal_plugin_post_types']);
        $settings['content_types'] = [];
        $allowed_post_types = $this->public_post_type_names((bool) $settings['show_internal_plugin_post_types']);

        if (!empty($value['content_types']) && is_array($value['content_types'])) {
            foreach ($value['content_types'] as $post_type) {
                $post_type = sanitize_key((string) $post_type);

                if ($post_type && in_array($post_type, $allowed_post_types, true)) {
                    $settings['content_types'][] = $post_type;
                }
            }
        }

        $settings['content_types'] = array_values(array_unique($settings['content_types']));
        $settings['allowed_roles'] = [];
        $editable_roles = array_keys(get_editable_roles());

        if (!empty($value['allowed_roles']) && is_array($value['allowed_roles'])) {
            foreach ($value['allowed_roles'] as $role) {
                $role = sanitize_key((string) $role);

                if ($role && in_array($role, $editable_roles, true)) {
                    $settings['allowed_roles'][] = $role;
                }
            }
        }

        if (empty($settings['allowed_roles'])) {
            $settings['allowed_roles'] = ['administrator'];
        }

        $settings['cleanup_on_deactivation'] = !empty($value['cleanup_on_deactivation']);
        $settings['show_detected_tools'] = true;
        $settings['detected_tools_enabled'] = [];
        foreach (array_keys($defaults['detected_tools_enabled']) as $tool_key) {
            $settings['detected_tools_enabled'][$tool_key] = !empty($value['detected_tools_enabled'][$tool_key]);
        }
        $settings['remove_default_posts'] = !empty($value['remove_default_posts']);
        $settings['hide_wp_new_menu'] = !empty($value['hide_wp_new_menu']);
        $appearance = isset($value['admin_appearance']) ? sanitize_key((string) $value['admin_appearance']) : 'auto';
        $settings['admin_appearance'] = in_array($appearance, ['auto', 'light', 'dark'], true) ? $appearance : 'auto';

        return $settings;
    }


    public function maybe_remove_posts_menu_page(): void {
        if (!is_admin() || empty($this->get()['remove_default_posts'])) {
            return;
        }

        remove_menu_page('edit.php');
    }

    public function maybe_block_default_posts_admin_screens(): void {
        if (!is_admin() || !current_user_can('edit_posts') || empty($this->get()['remove_default_posts'])) {
            return;
        }

        global $pagenow;

        if ('edit.php' === $pagenow && empty($_GET['post_type'])) {
            wp_safe_redirect(admin_url());
            exit;
        }

        if ('post-new.php' === $pagenow && empty($_GET['post_type'])) {
            wp_safe_redirect(admin_url());
            exit;
        }

        if ('post.php' === $pagenow && !empty($_GET['post'])) {
            $post_id = absint($_GET['post']);

            if ($post_id > 0 && 'post' === get_post_type($post_id)) {
                wp_safe_redirect(admin_url());
                exit;
            }
        }
    }

    public function maybe_hide_wp_new_menu($wp_admin_bar): void {
        if (!is_admin_bar_showing()) {
            return;
        }

        $settings = $this->get();

        if (!empty($settings['hide_wp_new_menu'])) {
            $wp_admin_bar->remove_node('new-content');
            return;
        }

        if (!empty($settings['remove_default_posts'])) {
            $wp_admin_bar->remove_node('new-post');
        }
    }

    public function maybe_hide_wp_new_menu_frontend(): void {
        global $wp_admin_bar;

        if ($wp_admin_bar instanceof \WP_Admin_Bar) {
            $this->maybe_hide_wp_new_menu($wp_admin_bar);
        }
    }

}
