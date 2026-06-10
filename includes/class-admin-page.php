<?php
/**
 * Settings page UI for Ops Center.
 *
 * @package OpsCenter
 */

declare(strict_types=1);

namespace Ops_Center;

use WP_Roles;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin_Page {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function register(): void {
        add_menu_page(
            __('Ops Center', 'unified-ops-center'),
            __('Ops Center', 'unified-ops-center'),
            'manage_options',
            Settings::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-superhero',
            58
        );

        add_submenu_page(
            Settings::PAGE_SLUG,
            __('Ops Center Settings', 'unified-ops-center'),
            __('Settings', 'unified-ops-center'),
            'manage_options',
            Settings::PAGE_SLUG,
            [$this, 'render']
        );

        add_submenu_page(
            Settings::PAGE_SLUG,
            __('Ops Center Resources', 'unified-ops-center'),
            __('Resources', 'unified-ops-center'),
            'manage_options',
            Settings::PAGE_SLUG . '-resources',
            [$this, 'render_resources']
        );
    }

    public function ajax_save_appearance(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'unified-ops-center')], 403);
        }

        check_ajax_referer('ops_center_admin_appearance', 'nonce');
        $appearance = isset($_POST['appearance']) ? sanitize_key((string) wp_unslash($_POST['appearance'])) : 'auto';
        if (!in_array($appearance, ['auto', 'light', 'dark'], true)) {
            wp_send_json_error(['message' => __('Invalid appearance.', 'unified-ops-center')], 400);
        }

        $settings = $this->settings->get();
        $settings['admin_appearance'] = $appearance;
        update_option(Settings::OPTION_KEY, $settings);
        wp_send_json_success(['appearance' => $appearance]);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'unified-ops-center'));
        }
        $settings = $this->settings->get();
        $roles    = wp_roles();
        $tools    = $this->detected_tools();
        ?>
        <div class="wrap ops-center-admin ops-center-admin--<?php echo esc_attr((string) $settings['admin_appearance']); ?>" data-ops-center-admin>
            <?php $this->render_shell_start('settings', $settings); ?>
            <form method="post" action="options.php" class="ops-center-admin__grid">
                <?php settings_fields('ops_center_settings_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[settings_screen]" value="settings">


                <section class="ops-center-panel ops-center-panel--wide" aria-labelledby="ops-center-builder-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon dashicons dashicons-superhero" aria-hidden="true"></span><div><h2 id="ops-center-builder-title"><?php esc_html_e('Builder Detection', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Ops Center uses this detected builder to choose editor links and resource links.', 'unified-ops-center'); ?></p></div></div>
                    <p class="ops-center-builder-status"><strong><?php esc_html_e('Builder detected:', 'unified-ops-center'); ?></strong> <span class="ops-center-tool-card__body"><em><?php echo esc_html($this->detected_builder_label()); ?></em></span></p>
                </section>

                <section class="ops-center-panel ops-center-panel--wide" aria-labelledby="ops-center-detected-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">⌘</span><div><h2 id="ops-center-detected-title"><?php esc_html_e('Detected Tools', 'unified-ops-center'); ?></h2><p><?php esc_html_e('This section is enabled by default. Only detected tools can be enabled and shown in the admin bar.', 'unified-ops-center'); ?></p></div></div>
                    <label class="ops-center-toggle ops-center-toggle--primary"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enabled_menus][integrations]" value="1" <?php checked(!empty($settings['enabled_menus']['integrations'])); ?>><span><?php esc_html_e('Show Detected Tools section', 'unified-ops-center'); ?></span></label>
                    <div class="ops-center-tool-grid">
                        <?php foreach ($tools as $tool) : $is_active = !empty($tool['active']); ?>
                            <label class="ops-center-tool-card <?php echo $is_active ? 'is-detected' : 'is-missing'; ?>">
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[detected_tools_enabled][<?php echo esc_attr($tool['key']); ?>]" value="1" <?php checked($is_active && !empty($settings['detected_tools_enabled'][$tool['key']])); ?> <?php disabled(!$is_active); ?>>
                                <span class="ops-center-tool-card__mark" aria-hidden="true"></span>
                                <span class="ops-center-tool-card__body"><strong><?php echo esc_html($tool['label']); ?></strong><em><?php echo $is_active ? esc_html__('Detected', 'unified-ops-center') : esc_html__('Not detected', 'unified-ops-center'); ?></em></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="ops-center-panel" aria-labelledby="ops-center-display-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">◐</span><div><h2 id="ops-center-display-title"><?php esc_html_e('Appearance', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Switch instantly. This preference saves automatically.', 'unified-ops-center'); ?></p></div></div>
                    <div class="ops-center-segmented" role="group" aria-label="<?php esc_attr_e('Settings appearance', 'unified-ops-center'); ?>" data-ops-center-appearance-control>
                        <?php foreach (['auto' => __('Auto', 'unified-ops-center'), 'light' => __('Light', 'unified-ops-center'), 'dark' => __('Dark', 'unified-ops-center')] as $appearance_key => $appearance_label) : ?>
                            <label><input type="radio" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[admin_appearance]" value="<?php echo esc_attr($appearance_key); ?>" <?php checked($settings['admin_appearance'], $appearance_key); ?>><span><?php echo esc_html($appearance_label); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="ops-center-save-status" data-ops-center-save-status aria-live="polite"></p>
                </section>

                <section class="ops-center-panel" aria-labelledby="ops-center-menus-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">☰</span><div><h2 id="ops-center-menus-title"><?php esc_html_e('Admin Bar Menus', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Choose which Ops Center sections appear in the admin bar.', 'unified-ops-center'); ?></p></div></div>
                    <?php foreach ((array) $settings['enabled_menus'] as $key => $enabled) : ?>
                        <?php if (in_array($key, ['integrations', 'community', 'resources', 'shortcuts', 'command_palette'], true)) { continue; } ?>
                        <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enabled_menus][<?php echo esc_attr((string) $key); ?>]" value="1" <?php checked($enabled); ?>><span><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $key))); ?></span></label>
                    <?php endforeach; ?>
                    <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enabled_menus][resources]" value="1" <?php checked(!empty($settings['enabled_menus']['resources'])); ?>><span><?php esc_html_e('Builder Resources', 'unified-ops-center'); ?></span></label>
                    <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enabled_menus][shortcuts]" value="1" <?php checked(!empty($settings['enabled_menus']['shortcuts'])); ?>><span><?php esc_html_e('Quick Links', 'unified-ops-center'); ?></span></label>
                    <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enabled_menus][command_palette]" value="1" <?php checked(!empty($settings['enabled_menus']['command_palette'])); ?>><span><?php esc_html_e('WordPress Command Palette shortcut', 'unified-ops-center'); ?></span></label>
                    <p class="description"><?php esc_html_e('When enabled, Ops Center shows an Open Command Palette action at the top of the admin bar panel. This uses the native WordPress command palette when WordPress has loaded it.', 'unified-ops-center'); ?></p>
                </section>

                <section class="ops-center-panel" aria-labelledby="ops-center-content-types-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">▦</span><div><h2 id="ops-center-content-types-title"><?php esc_html_e('Content Type Menus', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Enable pages, posts, media, and public custom post types.', 'unified-ops-center'); ?></p></div></div>
                    <div class="ops-center-check-list">
                        <?php foreach ($this->post_type_options() as $post_type => $label) : ?>
                            <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[content_types][]" value="<?php echo esc_attr((string) $post_type); ?>" <?php checked(in_array($post_type, (array) $settings['content_types'], true)); ?>><span><?php echo esc_html($label); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="ops-center-panel" aria-labelledby="ops-center-cleanup-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">✦</span><div><h2 id="ops-center-cleanup-title"><?php esc_html_e('Admin Cleanup', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Optional cleanup features merged from Dynamic Post Type Menu.', 'unified-ops-center'); ?></p></div></div>
                    <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[remove_default_posts]" value="1" <?php checked(!empty($settings['remove_default_posts'])); ?>><span><?php esc_html_e('Remove the built-in Posts menu and block direct access to Posts admin screens', 'unified-ops-center'); ?></span></label>
                    <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[hide_wp_new_menu]" value="1" <?php checked(!empty($settings['hide_wp_new_menu'])); ?>><span><?php esc_html_e('Hide the WordPress + New admin bar menu', 'unified-ops-center'); ?></span></label>
                    <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[cleanup_on_deactivation]" value="1" <?php checked(!empty($settings['cleanup_on_deactivation'])); ?>><span><?php esc_html_e('Remove all Ops Center settings when deactivated', 'unified-ops-center'); ?></span></label>
                </section>

                <section class="ops-center-panel" aria-labelledby="ops-center-roles-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">◌</span><div><h2 id="ops-center-roles-title"><?php esc_html_e('Allowed Roles', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Administrators are selected by default. Choose additional roles that may see Ops Center in the admin bar.', 'unified-ops-center'); ?></p></div></div>
                    <div class="ops-center-check-list">
                        <?php if ($roles instanceof WP_Roles) : foreach ($roles->roles as $role_key => $role_data) : ?>
                            <label class="ops-center-toggle"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[allowed_roles][]" value="<?php echo esc_attr((string) $role_key); ?>" <?php checked(in_array($role_key, (array) $settings['allowed_roles'], true)); ?>><span><?php echo esc_html(translate_user_role($role_data['name'])); ?></span></label>
                        <?php endforeach; endif; ?>
                    </div>
                </section>

                <div class="ops-center-admin__actions"><?php submit_button(__('Save Settings', 'unified-ops-center')); ?></div>
            </form>
            <?php $this->render_shell_end(); ?>
        </div>
        <?php
    }

    public function render_resources(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'unified-ops-center'));
        }
        $settings = $this->settings->get();
        ?>
        <div class="wrap ops-center-admin ops-center-admin--<?php echo esc_attr((string) $settings['admin_appearance']); ?>" data-ops-center-admin>
            <?php $this->render_shell_start('resources', $settings); ?>
            <form method="post" action="options.php" class="ops-center-admin__grid">
                <?php settings_fields('ops_center_settings_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[settings_screen]" value="resources">
                <section class="ops-center-panel ops-center-panel--wide" aria-labelledby="ops-center-resources-title">
                    <?php $resource_heading = $this->resource_heading(); ?>
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon dashicons dashicons-admin-links" aria-hidden="true"></span><div><h2 id="ops-center-resources-title"><?php echo esc_html($resource_heading); ?></h2><p><?php printf(esc_html__('Resources change based on the active builder. Current set: %s. Edit, remove, reorder, or add more links.', 'unified-ops-center'), esc_html($resource_heading)); ?></p></div></div>
                    <?php $this->render_links_manager('resource_links', $this->resources_for_current_builder($settings), $resource_heading); ?>
                </section>
                <section class="ops-center-panel ops-center-panel--wide" aria-labelledby="ops-center-quick-links-title">
                    <div class="ops-center-panel__header"><span class="ops-center-panel__icon" aria-hidden="true">＋</span><div><h2 id="ops-center-quick-links-title"><?php esc_html_e('Quick Links', 'unified-ops-center'); ?></h2><p><?php esc_html_e('Global admin-managed quick links. These are not user-specific shortcuts.', 'unified-ops-center'); ?></p></div></div>
                    <?php $this->render_links_manager('shortcut_links', (array) ($settings['shortcut_links'] ?? []), __('Quick links', 'unified-ops-center')); ?>
                </section>
                <div class="ops-center-admin__actions"><?php submit_button(__('Save Resources', 'unified-ops-center')); ?></div>
            </form>
            <?php $this->render_shell_end(); ?>
        </div>
        <?php
    }

    private function render_shell_start(string $active, array $settings): void { ?>
        <div class="ops-center-shell">
            <aside class="ops-center-sidebar" aria-label="<?php esc_attr_e('Ops Center sections', 'unified-ops-center'); ?>">
                <div class="ops-center-brand"><span class="ops-center-brand__icon" aria-hidden="true"><?php $this->render_icon(); ?></span><span>Ops Center</span></div>
                <nav class="ops-center-sidebar__nav">
                    <a class="<?php echo 'settings' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . Settings::PAGE_SLUG)); ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e('Settings', 'unified-ops-center'); ?></a>
                    <a class="<?php echo 'resources' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . Settings::PAGE_SLUG . '-resources')); ?>"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php esc_html_e('Resources', 'unified-ops-center'); ?></a>
                </nav>
                <div class="ops-center-sidebar__version">v<?php echo esc_html(OPS_CENTER_VERSION); ?></div>
            </aside>
            <main class="ops-center-main">
                <header class="ops-center-main__header"><div><p class="ops-center-admin__eyebrow"><?php esc_html_e('WordPress admin bar tools', 'unified-ops-center'); ?></p><h1><?php echo 'resources' === $active ? esc_html__('Resources', 'unified-ops-center') : esc_html__('Settings', 'unified-ops-center'); ?></h1><p><?php esc_html_e('Manage quick access to builder templates, patterns, content, tools, resources, and links.', 'unified-ops-center'); ?></p></div></header>
    <?php }

    private function render_shell_end(): void { ?>
            </main>
        </div>
    <?php }

    private function render_links_manager(string $field_key, array $links, string $label): void {
        $links[] = ['label' => '', 'url' => '']; ?>
        <div class="ops-center-links" role="group" aria-label="<?php echo esc_attr($label); ?>" data-ops-center-sortable data-ops-center-repeater="<?php echo esc_attr($field_key); ?>">
            <?php foreach ($links as $index => $link) : ?>
                <div class="ops-center-link-row" draggable="true" data-ops-center-row tabindex="-1"><div class="ops-center-link-row__tools" aria-label="<?php esc_attr_e('Reorder this link', 'unified-ops-center'); ?>"><button type="button" class="ops-center-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'unified-ops-center'); ?>" title="<?php esc_attr_e('Drag to reorder', 'unified-ops-center'); ?>">☰</button><button type="button" class="ops-center-move-button" data-ops-center-move="up"><?php esc_html_e('Up', 'unified-ops-center'); ?></button><button type="button" class="ops-center-move-button" data-ops-center-move="down"><?php esc_html_e('Down', 'unified-ops-center'); ?></button><button type="button" class="ops-center-remove-button" data-ops-center-remove><?php esc_html_e('Remove', 'unified-ops-center'); ?></button></div><label><span><?php esc_html_e('Label', 'unified-ops-center'); ?></span><input type="text" data-ops-center-field="label" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[<?php echo esc_attr($field_key); ?>][<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr((string) ($link['label'] ?? '')); ?>"></label><label><span><?php esc_html_e('URL', 'unified-ops-center'); ?></span><input type="url" data-ops-center-field="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[<?php echo esc_attr($field_key); ?>][<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_url((string) ($link['url'] ?? '')); ?>"></label></div>
            <?php endforeach; ?>
        </div><p><button type="button" class="button button-secondary" data-ops-center-add-row="<?php echo esc_attr($field_key); ?>"><?php esc_html_e('Add link', 'unified-ops-center'); ?></button></p>
    <?php }

    private function post_type_options(): array {
        $post_types = get_post_types(['show_ui' => true], 'objects');
        $excluded_post_types = array_merge(Settings::feature_post_type_slugs(), ['wp_block','wp_navigation','wp_template','wp_template_part','wp_global_styles','wp_font_family','wp_font_face','custom_css','customize_changeset','oembed_cache','user_request','wp_changeset','wp_pattern_category','wp_template_part_area']);
        uasort($post_types, static fn($a, $b): int => strcasecmp((string) $a->labels->name, (string) $b->labels->name));
        $options = [];
        foreach ($post_types as $post_type => $post_type_object) {
            $is_allowed_builtin = in_array($post_type, ['post', 'page', 'attachment'], true);
            if (in_array($post_type, $excluded_post_types, true) || (!$is_allowed_builtin && !empty($post_type_object->_builtin))) { continue; }
            $options[(string) $post_type] = (string) $post_type_object->labels->name;
        }
        return $options;
    }

    private function detected_builder_label(): string {
        if (class_exists('\\Etch\\Plugin')) {
            return 'Etch';
        }

        if (class_exists('\\Bricks\\Helpers') || defined('BRICKS_VERSION') || 'bricks' === get_template()) {
            return 'Bricks';
        }

        return 'WordPress';
    }

    private function detected_tools(): array {
        return [
            [
                'key' => 'acf',
                'label'  => 'Advanced Custom Fields',
                'active' => $this->is_tool_active(['advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php'], ['ACF', 'ACF\\ACF', 'acf_plugin'], ['acf', 'acf_get_field_groups'], ['ACF_VERSION']),
            ],
            [
                'key' => 'metabox',
                'label'  => 'Meta Box',
                'active' => $this->is_tool_active(['meta-box/meta-box.php', 'meta-box-aio/meta-box-aio.php'], ['RWMB_Loader', 'RWMB_Core'], ['rwmb_meta'], ['RWMB_VER']),
            ],
            [
                'key' => 'acpt',
                'label'  => 'ACPT',
                'active' => $this->is_tool_active(['advanced-custom-post-type/acpt.php', 'acpt/acpt.php'], ['ACPT_Loader', 'ACPT\\Core\\Plugin'], [], ['ACPT_PLUGIN_VERSION']),
            ],
            [
                'key' => 'jetengine',
                'label'  => 'JetEngine',
                'active' => $this->is_tool_active(['jet-engine/jet-engine.php'], ['Jet_Engine'], [], ['JET_ENGINE_VERSION']),
            ],
            [
                'key' => 'wpcodebox',
                'label'  => 'WPCodeBox',
                'active' => $this->is_tool_active(['wpcodebox/wpcodebox.php', 'wpcodebox2/wpcodebox.php', 'wpcodebox2/wpcodebox2.php', 'wpcodebox/wpcodebox2.php'], ['WPCodeBox', 'Wpcb\\Plugin', 'WPCodeBox2\\Plugin'], [], ['WPCB_VERSION', 'WPCB2_VERSION']),
            ],
        ];
    }

    private function is_tool_active(array $plugin_files, array $classes = [], array $functions = [], array $constants = []): bool {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        foreach ($functions as $function) {
            if (function_exists($function)) {
                return true;
            }
        }

        foreach ($constants as $constant) {
            if (defined($constant)) {
                return true;
            }
        }

        $active_plugins = (array) get_option('active_plugins', []);

        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        foreach ($active_plugins as $active_plugin) {
            foreach ($plugin_files as $plugin_file) {
                if ($active_plugin === $plugin_file || str_contains((string) $active_plugin, trim($plugin_file, '/'))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function render_icon(): void {
        ?>
        <span class="dashicons dashicons-superhero" aria-hidden="true"></span>
        <?php
    }

    private function detected_builder_key(): string {
        if (class_exists('\Etch\Plugin')) {
            return 'etch';
        }

        if (class_exists('\Bricks\Helpers') || defined('BRICKS_VERSION') || 'bricks' === get_template()) {
            return 'bricks';
        }

        return 'wordpress';
    }

    private function resource_heading(): string {
        return match ($this->detected_builder_key()) {
            'bricks' => __('Bricks Resources', 'unified-ops-center'),
            'etch'   => __('Etch Resources', 'unified-ops-center'),
            default  => __('WP Resources', 'unified-ops-center'),
        };
    }

    private function resources_for_current_builder(array $settings): array {
        $custom_links = isset($settings['resource_links']) && is_array($settings['resource_links']) ? $settings['resource_links'] : [];
        $wp_defaults  = $this->default_resources('wordpress');

        if ($custom_links && $custom_links !== $wp_defaults) {
            return $custom_links;
        }

        return $this->default_resources($this->detected_builder_key());
    }

    private function default_resources(string $builder_key): array {
        return match ($builder_key) {
            'bricks' => [
                ['label' => 'Bricks Documentation', 'url' => 'https://academy.bricksbuilder.io/'],
                ['label' => 'Bricks Builder Forum', 'url' => 'https://forum.bricksbuilder.io/'],
                ['label' => 'Bricks Ideas', 'url' => 'https://ideas.bricksbuilder.io/'],
                ['label' => 'Bricks Changelog', 'url' => 'https://bricksbuilder.io/changelog/'],
            ],
            'etch' => [
                ['label' => 'Etch Documentation', 'url' => 'https://docs.etchwp.com/'],
                ['label' => 'Etch Patterns', 'url' => 'https://patterns.etchwp.com/'],
                ['label' => 'Etch Circle Community', 'url' => 'https://community.etchwp.com/'],
                ['label' => 'EtchWP Homepage', 'url' => 'https://etchwp.com/?aff=77d60d8c'],
            ],
            default => [
                ['label' => 'WordPress Documentation', 'url' => 'https://wordpress.org/documentation/'],
                ['label' => 'WordPress Developer Resources', 'url' => 'https://developer.wordpress.org/'],
                ['label' => 'WordPress Plugins', 'url' => 'https://wordpress.org/plugins/'],
                ['label' => 'WordPress Themes', 'url' => 'https://wordpress.org/themes/'],
            ],
        };
    }
}
