<?php
/**
 * Admin bar integration for Ops Center.
 *
 * @package OpsCenter
 */

declare(strict_types=1);

namespace Ops_Center;

use WP_Admin_Bar;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin_Bar {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function register(WP_Admin_Bar $admin_bar): void {
        if (!$this->settings->can_access()) {
            return;
        }


        $settings        = $this->settings->get();
        $current_context = $this->get_current_context();
        $builder        = $this->detected_builder();
        $content_url     = $current_context ? $this->builder_editor_url((int) $current_context['original_post_id'], 0, $builder['key']) : '';
        $template_data   = $current_context ? $this->get_best_template($current_context['candidates']) : null;

        $this->remove_etch_nodes($admin_bar);

        $admin_bar->add_node([
            'id'    => 'ops-center',
            'title' => 'Ops Center <span class="dashicons dashicons-arrow-down-alt2" style="font-family:dashicons;font-size:14px;line-height:34px;" aria-hidden="true"></span>',
            'href'  => false,
            'meta'  => [
                'title' => sprintf(__('Ops Center tools. Builder detected: %s', 'unified-ops-center'), $builder['label']),
            ],
        ]);

        $admin_bar->add_node([
            'id'     => 'ops-center-panel',
            'parent' => 'ops-center',
            'title'  => $this->render_flyout_panel($settings, $current_context, $content_url, $template_data),
            'href'   => false,
            'meta'   => [
                'class' => 'ops-center-panel-node',
            ],
        ]);
    }

    private function remove_etch_nodes(WP_Admin_Bar $admin_bar): void {
        foreach (['etch-edit-template', 'etch-edit-content', 'edit-with-etch', 'ops-center'] as $node_id) {
            $admin_bar->remove_node($node_id);
        }
    }

    private function render_flyout_panel(array $settings, ?array $current_context, string $content_url, ?array $template_data): string {
        $left_sections = [];
        $panes         = [];
        $active_id     = '';


        $current_items = $this->current_content_items($current_context, $content_url, $template_data);
        if ($current_items) {
            $left_sections[] = $this->render_left_link_section(__('Current Content', 'unified-ops-center'), $current_items);
        }

        $enabled_content_types = $this->enabled_content_types($settings);
        if ($enabled_content_types) {
            $buttons = '';

            foreach ($enabled_content_types as $post_type) {
                $browser = $this->post_type_browser_data($post_type);

                if (!$browser) {
                    continue;
                }

                if ('' === $active_id) {
                    $active_id = $browser['id'];
                }

                $buttons .= $this->render_left_panel_button($browser['id'], $browser['label'], $browser['id'] === $active_id);
                $panes[]  = $this->render_browser_pane($browser, $browser['id'] === $active_id);
            }

            if ('' !== $buttons) {
                $left_sections[] = $this->render_left_button_section(__('Content Types', 'unified-ops-center'), $buttons);
            }
        }

        $asset_buttons = '';
        if (!empty($settings['enabled_menus']['templates'])) {
            $browser = $this->templates_browser_data();

            if ('' === $active_id) {
                $active_id = $browser['id'];
            }

            $asset_buttons .= $this->render_left_panel_button($browser['id'], $browser['label'], $browser['id'] === $active_id);
            $panes[]       = $this->render_browser_pane($browser, $browser['id'] === $active_id);
        }

        if (!empty($settings['enabled_menus']['patterns'])) {
            $browser = $this->patterns_browser_data();

            if ('' === $active_id) {
                $active_id = $browser['id'];
            }

            $asset_buttons .= $this->render_left_panel_button($browser['id'], $browser['label'], $browser['id'] === $active_id);
            $panes[]       = $this->render_browser_pane($browser, $browser['id'] === $active_id);
        }

        if ('' !== $asset_buttons) {
            $left_sections[] = $this->render_left_button_section(__('Builder Assets', 'unified-ops-center'), $asset_buttons);
        }

        if (!empty($settings['enabled_menus']['integrations']) && !empty($settings['show_detected_tools'])) {
            $detected_integrations = $this->integration_browser_data((array) ($settings['detected_tools_enabled'] ?? []));
            if ($detected_integrations) {
                $integration_buttons = '';

                foreach ($detected_integrations as $browser) {
                    if ('' === $active_id) {
                        $active_id = $browser['id'];
                    }

                    $integration_buttons .= $this->render_left_panel_button($browser['id'], $browser['label'], $browser['id'] === $active_id);
                    $panes[]             = $this->render_link_pane($browser['id'], $browser['heading'], $browser['items'], $browser['id'] === $active_id);
                }

                if ('' !== $integration_buttons) {
                    $left_sections[] = $this->render_left_button_section(__('Detected Tools', 'unified-ops-center'), $integration_buttons);
                }
            }
        }

        $resource_buttons = '';
        if (!empty($settings['enabled_menus']['resources'])) {
            $builder          = $this->detected_builder();
            $resource_heading = $this->resource_heading((string) $builder['key']);
            $pane_id          = 'ops-center-pane-' . sanitize_key((string) $builder['key']) . '-resources';
            $items            = [];

            foreach ($this->resources_for_builder((string) $builder['key'], $settings) as $link) {
                $items[] = [
                    'label'  => (string) $link['label'],
                    'url'    => (string) $link['url'],
                    'target' => '_blank',
                ];
            }

            if ($items) {
                if ('' === $active_id) {
                    $active_id = $pane_id;
                }

                $resource_buttons .= $this->render_left_panel_button($pane_id, $resource_heading, $pane_id === $active_id);
                $panes[]          = $this->render_link_pane($pane_id, $resource_heading, $items, $pane_id === $active_id);
            }
        }

        if (!empty($settings['enabled_menus']['shortcuts']) || !empty($settings['enabled_menus']['community'])) {
            $pane_id = 'ops-center-pane-shortcuts';
            $items   = [];

            foreach ((array) ($settings['shortcut_links'] ?? $settings['community_links'] ?? []) as $link) {
                if (empty($link['label']) || empty($link['url'])) {
                    continue;
                }

                $items[] = [
                    'label'  => (string) $link['label'],
                    'url'    => (string) $link['url'],
                    'target' => '_blank',
                ];
            }

            if ($items) {
                if ('' === $active_id) {
                    $active_id = $pane_id;
                }

                $resource_buttons .= $this->render_left_panel_button($pane_id, __('Quick Links', 'unified-ops-center'), $pane_id === $active_id);
                $panes[]          = $this->render_link_pane($pane_id, __('Quick Links', 'unified-ops-center'), $items, $pane_id === $active_id);
            }
        }

        if ('' !== $resource_buttons) {
            $left_sections[] = $this->render_left_button_section(__('Resources', 'unified-ops-center'), $resource_buttons);
        }

        $left_sections[] = $this->render_left_link_section(
            __('Administration', 'unified-ops-center'),
            [
                [
                    'label' => __('Ops Center Settings', 'unified-ops-center'),
                    'url'   => admin_url('admin.php?page=' . Settings::PAGE_SLUG),
                ],
            ]
        );

        if (!$panes) {
            $panes[] = sprintf(
                '<section class="ops-center-panel__pane is-active" role="region"><h3 class="ops-center-panel__pane-heading">%s</h3><p class="ops-center-panel__empty">%s</p></section>',
                esc_html__('Ops Center', 'unified-ops-center'),
                esc_html__('Select an item from the left column.', 'unified-ops-center')
            );
        }

        return sprintf(
            '<div class="ops-center-panel" role="group" aria-label="%s"><div class="ops-center-panel__nav">%s</div><div class="ops-center-panel__content">%s</div></div>',
            esc_attr__('Ops Center tools', 'unified-ops-center'),
            implode('', $left_sections),
            implode('', $panes)
        );
    }


    private function detected_builder(): array {
        if (class_exists('\\Etch\\Plugin')) {
            return ['key' => 'etch', 'label' => 'Etch'];
        }

        if (class_exists('\\Bricks\\Helpers') || defined('BRICKS_VERSION') || 'bricks' === get_template()) {
            return ['key' => 'bricks', 'label' => 'Bricks'];
        }

        return ['key' => 'wordpress', 'label' => 'WordPress'];
    }

    private function current_content_items(?array $current_context, string $content_url, ?array $template_data): array {
        $builder     = $this->detected_builder();
        $items       = [];
        $homepage_id = (int) get_option('page_on_front');

        if (is_admin() && 'page' === get_option('show_on_front') && $homepage_id > 0) {
            $items[] = [
                'label' => sprintf(__('Edit Home in %s', 'unified-ops-center'), $builder['label']),
                'url'   => $this->etch_editor_url($homepage_id, 0),
            ];
        }

        if ($content_url && is_singular()) {
            $post_id       = (int) get_queried_object_id();
            $title         = get_the_title($post_id);
            $content_label = $title ?: __('this content', 'unified-ops-center');

            $items[] = [
                'label' => sprintf(
                    /* translators: 1: Post type label, 2: Post title. */
                    __('Current %1$s: %2$s', 'unified-ops-center'),
                    $this->current_post_type_label($post_id),
                    $content_label
                ),
                'url'   => $content_url,
            ];
        }

        if ($template_data && $current_context) {
            $items[] = [
                'label' => sprintf(
                    /* translators: %s: Template label. */
                    __('Current Template: %s', 'unified-ops-center'),
                    (string) $template_data['label']
                ),
                'url'   => $this->etch_editor_url((int) $template_data['id'], (int) $current_context['original_post_id']),
            ];
        }

        return $items;
    }

    private function enabled_content_types(array $settings): array {
        $enabled = [];
        $allowed = $this->settings->public_post_type_names();

        foreach ((array) ($settings['content_types'] ?? []) as $post_type) {
            $post_type = sanitize_key((string) $post_type);

            if ($post_type && in_array($post_type, $allowed, true)) {
                $enabled[] = $post_type;
            }
        }

        return array_values(array_unique($enabled));
    }

    private function render_left_link_section(string $heading, array $items): string {
        if (!$items) {
            return '';
        }

        $links = '';
        foreach ($items as $item) {
            $links .= $this->render_panel_link((string) $item['label'], (string) $item['url'], (string) ($item['target'] ?? ''));
        }

        return sprintf(
            '<section class="ops-center-panel__section ops-center-panel__section--nav"><h3 class="ops-center-panel__heading">%s</h3><ul class="ops-center-panel__list">%s</ul></section>',
            esc_html($heading),
            $links
        );
    }

    private function render_left_button_section(string $heading, string $buttons): string {
        if ('' === $buttons) {
            return '';
        }

        return sprintf(
            '<section class="ops-center-panel__section ops-center-panel__section--nav"><h3 class="ops-center-panel__heading">%s</h3><div class="ops-center-panel__nav-buttons">%s</div></section>',
            esc_html($heading),
            $buttons
        );
    }

    private function render_left_panel_button(string $target_id, string $label, bool $active = false): string {
        return sprintf(
            '<button type="button" class="ops-center-panel__button%s" data-ops-center-pane-trigger="%s" aria-controls="%s" aria-expanded="%s">%s</button>',
            $active ? ' is-active' : '',
            esc_attr($target_id),
            esc_attr($target_id),
            $active ? 'true' : 'false',
            esc_html($label)
        );
    }

    private function render_link_pane(string $id, string $heading, array $items, bool $active = false): string {
        $links = '';
        foreach ($items as $item) {
            $links .= $this->render_panel_link((string) $item['label'], (string) $item['url'], (string) ($item['target'] ?? ''));
        }

        if ('' === $links) {
            $links = '<li class="ops-center-panel__item ops-center-panel__empty">' . esc_html__('No links found', 'unified-ops-center') . '</li>';
        }

        return sprintf(
            '<section id="%s" class="ops-center-panel__pane%s" role="region" aria-label="%s"%s><h3 class="ops-center-panel__pane-heading">%s</h3><ul class="ops-center-panel__list ops-center-panel__list--links">%s</ul></section>',
            esc_attr($id),
            $active ? ' is-active' : '',
            esc_attr($heading),
            $active ? '' : ' hidden',
            esc_html($heading),
            $links
        );
    }

    private function render_action_link(string $label, string $aria_label, string $url): string {
        return sprintf(
            '<a class="ops-center-browser__action" href="%s" aria-label="%s" title="%s">%s</a>',
            esc_url($url),
            esc_attr($aria_label),
            esc_attr($aria_label),
            esc_html($label)
        );
    }

    private function render_filter_button(string $label, string $aria_label, string $filter, bool $active = false): string {
        return sprintf(
            '<button type="button" class="ops-center-browser__action%s" aria-label="%s" title="%s" data-ops-center-browser-filter="%s" aria-pressed="%s">%s</button>',
            $active ? ' is-active' : '',
            esc_attr($aria_label),
            esc_attr($aria_label),
            esc_attr($filter),
            $active ? 'true' : 'false',
            esc_html($label)
        );
    }

    private function render_panel_link(string $label, string $url, string $target = '', string $wp_editor_url = '', int $author_id = 0): string {
        $target_attr = '_blank' === $target ? ' target="_blank" rel="noopener noreferrer"' : '';
        $wp_attr     = '';

        if ('' !== $wp_editor_url) {
            $wp_attr = sprintf(
                ' data-ops-center-wp-editor-url="%s" title="%s"',
                esc_url($wp_editor_url),
                esc_attr(sprintf(__('Open in %s. Command/Ctrl + Option/Alt-click opens the WordPress editor.', 'unified-ops-center'), $this->detected_builder()['label']))
            );
        }

        $author_attr = $author_id > 0 ? sprintf(' data-ops-center-author="%d"', $author_id) : '';

        return sprintf(
            '<li class="ops-center-panel__item"%s><a class="ops-center-panel__link" href="%s"%s%s>%s</a></li>',
            $author_attr,
            esc_url($url),
            $target_attr,
            $wp_attr,
            esc_html($label)
        );
    }

    private function post_type_browser_data(string $post_type): ?array {
        $post_type_object = get_post_type_object($post_type);

        if (!$post_type_object) {
            return null;
        }

        $count           = wp_count_posts($post_type);
        $published_count = 'attachment' === $post_type && isset($count->inherit) ? (int) $count->inherit : (isset($count->publish) ? (int) $count->publish : 0);
        $browser_id      = 'ops-center-pane-' . sanitize_key($post_type);

        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => 200,
            'post_status'    => 'attachment' === $post_type ? 'inherit' : 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $actions = $this->post_type_action_links($post_type, $post_type_object);
        $items   = '';

        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $items .= $this->render_panel_link($post->post_title ?: $post->post_name, $this->etch_editor_url((int) $post->ID, 0), '', $this->wp_editor_url((int) $post->ID), (int) $post->post_author);
        }

        if ('' === $items) {
            $items = '<li class="ops-center-panel__item ops-center-panel__empty">' . esc_html__('No published items found', 'unified-ops-center') . '</li>';
        }

        return [
            'id'           => $browser_id,
            'label'        => sprintf(
                /* translators: 1: Post type plural label, 2: Published item count. */
                __('%1$s (%2$d)', 'unified-ops-center'),
                (string) $post_type_object->labels->name,
                $published_count
            ),
            'heading'      => (string) $post_type_object->labels->name,
            'search_label' => sprintf(
                /* translators: %s: Post type plural label. */
                __('Search %s', 'unified-ops-center'),
                strtolower((string) $post_type_object->labels->name)
            ),
            'actions'      => $actions,
            'items'        => $items,
        ];
    }


    private function post_type_action_links(string $post_type, object $post_type_object): string {
        $links = '';
        $label = (string) $post_type_object->labels->name;

        $links .= $this->render_filter_button(__('All', 'unified-ops-center'), sprintf(__('Filter to all %s', 'unified-ops-center'), $label), 'all', true);
        $links .= $this->render_filter_button(__('Mine', 'unified-ops-center'), sprintf(__('Filter to my %s', 'unified-ops-center'), $label), 'mine');

        if ($this->can_create_post_type($post_type_object)) {
            $links .= $this->render_action_link(__('New', 'unified-ops-center'), $this->add_new_label($post_type, $label), $this->add_new_url($post_type));
        }

        return $links;
    }

    private function all_items_url(string $post_type): string {
        if ('post' === $post_type) {
            return admin_url('edit.php');
        }

        if ('attachment' === $post_type) {
            return admin_url('upload.php');
        }

        return admin_url('edit.php?post_type=' . $post_type);
    }

    private function my_items_url(string $post_type): string {
        if ('post' === $post_type) {
            return admin_url('edit.php?author=' . get_current_user_id());
        }

        if ('attachment' === $post_type) {
            return admin_url('upload.php?author=' . get_current_user_id());
        }

        return admin_url('edit.php?post_type=' . $post_type . '&author=' . get_current_user_id());
    }

    private function add_new_url(string $post_type): string {
        if ('post' === $post_type) {
            return admin_url('post-new.php');
        }

        if ('attachment' === $post_type) {
            return admin_url('media-new.php');
        }

        return admin_url('post-new.php?post_type=' . $post_type);
    }

    private function can_create_post_type(object $post_type_object): bool {
        if ('attachment' === $post_type_object->name) {
            return current_user_can('upload_files');
        }

        $capability = $post_type_object->cap->create_posts ?? 'edit_posts';

        return current_user_can($capability);
    }

    private function add_new_label(string $post_type, string $plural_label): string {
        return match ($post_type) {
            'attachment' => __('Add New Media', 'unified-ops-center'),
            default      => sprintf(__('Add New %s', 'unified-ops-center'), $plural_label),
        };
    }

    private function templates_browser_data(): array {
        $builder       = $this->detected_builder();
        $template_type = 'bricks' === $builder['key'] && post_type_exists('bricks_template') ? 'bricks_template' : 'wp_template';
        $label         = 'bricks_template' === $template_type ? __('Bricks Templates', 'unified-ops-center') : __('Templates', 'unified-ops-center');

        $templates = get_posts([
            'post_type'      => $template_type,
            'posts_per_page' => 50,
            'post_status'    => ['publish', 'private'],
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $items = '';
        foreach ($templates as $template) {
            if (!$template instanceof WP_Post) {
                continue;
            }

            $items .= $this->render_panel_link($template->post_title ?: $template->post_name, $this->etch_editor_url((int) $template->ID, 0), '', $this->wp_editor_url((int) $template->ID));
        }

        if ('' === $items) {
            $items = '<li class="ops-center-panel__item ops-center-panel__empty">' . esc_html__('No templates found', 'unified-ops-center') . '</li>';
        }

        return [
            'id'           => 'ops-center-pane-templates',
            'label'        => $label,
            'heading'      => $label,
            'search_label' => __('Search templates', 'unified-ops-center'),
            'items'        => $items,
        ];
    }

    private function patterns_browser_data(): array {
        $patterns = get_posts([
            'post_type'      => 'wp_block',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $items = '';
        foreach ($patterns as $pattern) {
            if (!$pattern instanceof WP_Post) {
                continue;
            }

            $sync_label = $this->pattern_sync_label($pattern);
            $sync_abbr  = $this->pattern_sync_abbr($pattern);
            $label      = $pattern->post_title ?: $pattern->post_name;

            $items .= sprintf(
                '<li class="ops-center-panel__item"><a class="ops-center-panel__link ops-center-panel__link--pattern" href="%s" data-ops-center-wp-editor-url="%s" title="%s"><span>%s</span><span class="ops-center-panel__badge" title="%s" aria-label="%s">%s</span></a></li>',
                esc_url($this->etch_editor_url((int) $pattern->ID, 0)),
                esc_url($this->wp_editor_url((int) $pattern->ID)),
                esc_attr(sprintf(__('Open in %s. Command/Ctrl + Option/Alt-click opens the WordPress editor.', 'unified-ops-center'), $this->detected_builder()['label'])),
                esc_html($label),
                esc_attr($sync_label),
                esc_attr($sync_label),
                esc_html($sync_abbr)
            );
        }

        if ('' === $items) {
            $items = '<li class="ops-center-panel__item ops-center-panel__empty">' . esc_html__('No patterns found', 'unified-ops-center') . '</li>';
        }

        return [
            'id'           => 'ops-center-pane-patterns',
            'label'        => __('Patterns', 'unified-ops-center'),
            'heading'      => __('Patterns', 'unified-ops-center'),
            'search_label' => __('Search patterns', 'unified-ops-center'),
            'items'        => $items,
        ];
    }

    private function render_browser_pane(array $browser, bool $active = false): string {
        $actions = !empty($browser['actions'])
            ? '<div class="ops-center-browser__actions" aria-label="' . esc_attr__('Post type filters and actions', 'unified-ops-center') . '" data-ops-center-current-user="' . esc_attr((string) get_current_user_id()) . '">' . (string) $browser['actions'] . '</div>'
            : '';

        return sprintf(
            '<section id="%s" class="ops-center-panel__pane%s" role="region" aria-label="%s"%s><h3 class="ops-center-panel__pane-heading">%s</h3><div class="ops-center-browser__sticky"><label class="ops-center-browser__search" for="%s-search"><span class="screen-reader-text">%s</span><input id="%s-search" class="ops-center-browser__input" type="search" autocomplete="off" placeholder="%s" data-ops-center-browser-search></label>%s</div><ul class="ops-center-panel__list ops-center-panel__list--scroll" data-ops-center-browser-results>%s</ul></section>',
            esc_attr((string) $browser['id']),
            $active ? ' is-active' : '',
            esc_attr((string) $browser['heading']),
            $active ? '' : ' hidden',
            esc_html((string) $browser['heading']),
            esc_attr((string) $browser['id']),
            esc_html((string) $browser['search_label']),
            esc_attr((string) $browser['id']),
            esc_attr((string) $browser['search_label']),
            $actions,
            (string) $browser['items']
        );
    }

    private function get_existing_etch_url(WP_Admin_Bar $admin_bar): string {
        $parent = $admin_bar->get_node('edit-with-etch');

        return $parent && !empty($parent->href) ? (string) $parent->href : '';
    }

    private function get_current_context(): ?array {
        if (is_singular()) {
            $post_id   = get_queried_object_id();
            $post_type = get_post_type($post_id);

            if (!$post_id || !$post_type) {
                return null;
            }

            $slug = get_post_field('post_name', $post_id);

            return [
                'original_post_id' => (int) $post_id,
                'candidates'       => 'page' === $post_type
                    ? ["page-{$slug}", "page-{$post_id}", 'page', 'index']
                    : ["single-{$post_type}-{$slug}", "single-{$post_type}", 'single', 'singular', 'index'],
            ];
        }

        if (is_post_type_archive()) {
            $post_type_obj = get_queried_object();
            $post_type     = is_object($post_type_obj) && isset($post_type_obj->name) ? $post_type_obj->name : '';

            return $post_type
                ? ['original_post_id' => 0, 'candidates' => ["archive-{$post_type}", 'archive', 'index']]
                : null;
        }

        if (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();

            if (!is_object($term) || empty($term->taxonomy) || empty($term->slug)) {
                return null;
            }

            return [
                'original_post_id' => (int) $term->term_id,
                'candidates'       => [
                    "taxonomy-{$term->taxonomy}-{$term->slug}",
                    "taxonomy-{$term->taxonomy}",
                    'taxonomy',
                    'archive',
                    'index',
                ],
            ];
        }

        if (is_404()) {
            return [
                'original_post_id' => 0,
                'candidates'       => ['404', 'index'],
            ];
        }

        if (is_search()) {
            return [
                'original_post_id' => 0,
                'candidates'       => ['search', 'index'],
            ];
        }

        return null;
    }

    private function get_best_template(array $candidates): ?array {
        $matches = get_posts([
            'post_type'      => 'wp_template',
            'post_name__in'  => $candidates,
            'posts_per_page' => count($candidates),
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ]);

        if (!$matches) {
            return null;
        }

        $templates_by_slug = [];

        foreach ($matches as $match) {
            if (!$match instanceof WP_Post) {
                continue;
            }

            $templates_by_slug[(string) $match->post_name] = $match;
        }

        foreach ($candidates as $candidate) {
            if (isset($templates_by_slug[$candidate])) {
                $template = $templates_by_slug[$candidate];
                $title    = trim((string) get_the_title((int) $template->ID));

                return [
                    'id'    => (int) $template->ID,
                    'label' => '' !== $title ? $title : $this->template_label($candidate),
                ];
            }
        }

        return null;
    }

    private function template_label(string $slug): string {
        $label = preg_replace('/^(single|page|archive|taxonomy)-/', '', $slug);
        $label = ucwords(str_replace('-', ' ', (string) $label));

        if (str_starts_with($slug, 'archive-')) {
            $label .= ' Archive';
        }

        return $label;
    }

    private function current_post_type_label(int $post_id): string {
        $post_type = get_post_type($post_id);

        if (!$post_type) {
            return __('Content', 'unified-ops-center');
        }

        $post_type_object = get_post_type_object($post_type);

        if (!$post_type_object || empty($post_type_object->labels->singular_name)) {
            return __('Content', 'unified-ops-center');
        }

        return (string) $post_type_object->labels->singular_name;
    }

    private function pattern_sync_label(WP_Post $pattern): string {
        $status = (string) get_post_meta($pattern->ID, 'wp_pattern_sync_status', true);

        if ('unsynced' === $status) {
            return __('Not synced', 'unified-ops-center');
        }

        if (str_contains($pattern->post_content, 'metadata:{"bindings"') || str_contains($pattern->post_content, '"bindings"')) {
            return __('Partially synced', 'unified-ops-center');
        }

        return __('Synced', 'unified-ops-center');
    }

    private function pattern_sync_abbr(WP_Post $pattern): string {
        $label = $this->pattern_sync_label($pattern);

        if ($label === __('Partially synced', 'unified-ops-center')) {
            return __('P', 'unified-ops-center');
        }

        if ($label === __('Not synced', 'unified-ops-center')) {
            return __('N', 'unified-ops-center');
        }

        return __('S', 'unified-ops-center');
    }

    private function integration_browser_data(array $enabled_tools = []): array {
        $items = [];

        if (!empty($enabled_tools['acf']) && $this->is_tool_active(['advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php'], ['ACF', 'ACF\ACF', 'acf_plugin'], ['acf', 'acf_get_field_groups'], ['ACF_VERSION'])) {
            $items[] = [
                'id'      => 'ops-center-pane-acf',
                'label'   => 'ACF',
                'heading' => 'Advanced Custom Fields',
                'items'   => [
                    ['label' => __('Field Groups', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=acf-field-group')],
                    ['label' => __('Post Types', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=acf-post-type')],
                    ['label' => __('Taxonomies', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=acf-taxonomy')],
                    ['label' => __('Options Pages', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=acf-ui-options-page')],
                ],
            ];
        }

        if (!empty($enabled_tools['metabox']) && $this->is_tool_active(['meta-box/meta-box.php', 'meta-box-aio/meta-box-aio.php', 'meta-box-aio/meta-box-aio.php'], ['RWMB_Loader', 'RWMB_Core'], ['rwmb_meta'], ['RWMB_VER'])) {
            $items[] = [
                'id'      => 'ops-center-pane-metabox',
                'label'   => 'Meta Box',
                'heading' => 'Meta Box',
                'items'   => [
                    ['label' => __('Custom Fields', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=meta-box')],
                    ['label' => __('Post Types', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=mb-post-type')],
                    ['label' => __('Taxonomies', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=mb-taxonomy')],
                    ['label' => __('Settings Pages', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=mb-settings-page')],
                    ['label' => __('Relationships', 'unified-ops-center'), 'url' => admin_url('edit.php?post_type=mb-relationship')],
                ],
            ];
        }

        if (!empty($enabled_tools['acpt']) && $this->is_tool_active(['advanced-custom-post-type/acpt.php', 'acpt/acpt.php'], ['ACPT_Loader', 'ACPT\Core\Plugin'], [], ['ACPT_PLUGIN_VERSION'])) {
            $items[] = [
                'id'      => 'ops-center-pane-acpt',
                'label'   => 'ACPT',
                'heading' => 'ACPT',
                'items'   => [
                    ['label' => __('Dashboard', 'unified-ops-center'), 'url' => admin_url('admin.php?page=acpt')],
                    ['label' => __('Post Types', 'unified-ops-center'), 'url' => admin_url('admin.php?page=acpt_cpt')],
                    ['label' => __('Taxonomies', 'unified-ops-center'), 'url' => admin_url('admin.php?page=acpt_tax')],
                    ['label' => __('Meta Fields', 'unified-ops-center'), 'url' => admin_url('admin.php?page=acpt_meta')],
                ],
            ];
        }

        if (!empty($enabled_tools['jetengine']) && $this->is_tool_active(['jet-engine/jet-engine.php'], ['Jet_Engine'], [], ['JET_ENGINE_VERSION'])) {
            $items[] = [
                'id'      => 'ops-center-pane-jetengine',
                'label'   => 'JetEngine',
                'heading' => 'JetEngine',
                'items'   => [
                    ['label' => __('Dashboard', 'unified-ops-center'), 'url' => admin_url('admin.php?page=jet-engine')],
                    ['label' => __('Post Types', 'unified-ops-center'), 'url' => admin_url('admin.php?page=jet-engine-cpt')],
                    ['label' => __('Taxonomies', 'unified-ops-center'), 'url' => admin_url('admin.php?page=jet-engine-tax')],
                    ['label' => __('Meta Boxes', 'unified-ops-center'), 'url' => admin_url('admin.php?page=jet-engine-meta-boxes')],
                    ['label' => __('Relations', 'unified-ops-center'), 'url' => admin_url('admin.php?page=jet-engine-relations')],
                ],
            ];
        }

        if (!empty($enabled_tools['wpcodebox']) && $this->is_tool_active(['wpcodebox/wpcodebox.php', 'wpcodebox2/wpcodebox.php', 'wpcodebox2/wpcodebox2.php', 'wpcodebox/wpcodebox2.php'], ['WPCodeBox', 'Wpcb\Plugin', 'WPCodeBox2\Plugin'], [], ['WPCB_VERSION', 'WPCB2_VERSION'])) {
            $items[] = [
                'id'      => 'ops-center-pane-wpcodebox',
                'label'   => 'WPCodeBox',
                'heading' => 'WPCodeBox',
                'items'   => [
                    ['label' => __('Code Snippets', 'unified-ops-center'), 'url' => admin_url('admin.php?page=wpcodebox2')],
                ],
            ];
        }

        return $items;
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

    private function resource_heading(string $builder_key): string {
        return match ($builder_key) {
            'bricks' => __('Bricks Resources', 'unified-ops-center'),
            'etch'   => __('Etch Resources', 'unified-ops-center'),
            default  => __('WP Resources', 'unified-ops-center'),
        };
    }

    private function resources_for_builder(string $builder_key, array $settings = []): array {
        $custom_links = isset($settings['resource_links']) && is_array($settings['resource_links']) ? $settings['resource_links'] : [];
        $wp_defaults  = $this->default_resources('wordpress');

        // Preserve user-managed custom resources, but allow untouched/default installs to switch with the active builder.
        if ($custom_links && $custom_links !== $wp_defaults) {
            return $custom_links;
        }

        return $this->default_resources($builder_key);
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

    private function wp_editor_url(int $post_id): string {
        $url = get_edit_post_link($post_id, 'raw');

        return $url ? (string) $url : admin_url('post.php?post=' . $post_id . '&action=edit');
    }

    private function etch_editor_url(int $post_id, int $original_post_id = 0): string {
        return $this->builder_editor_url($post_id, $original_post_id, $this->detected_builder()['key']);
    }

    private function builder_editor_url(int $post_id, int $original_post_id = 0, string $builder = ''): string {
        $builder = $builder ?: (string) $this->detected_builder()['key'];

        if ('bricks' === $builder && class_exists('\\Bricks\\Helpers') && method_exists('\\Bricks\\Helpers', 'get_builder_edit_link')) {
            $url = \Bricks\Helpers::get_builder_edit_link($post_id);
            if ($url) {
                return (string) $url;
            }
        }

        if ('etch' === $builder) {
            $args = [
                'etch'    => 'magic',
                'post_id' => $post_id,
            ];

            if ($original_post_id) {
                $args['original_post_id'] = $original_post_id;
            }

            return add_query_arg($args, home_url('/'));
        }

        return $this->wp_editor_url($post_id);
    }
}
