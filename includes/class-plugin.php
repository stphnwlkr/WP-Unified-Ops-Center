<?php
/**
 * Main plugin bootstrap.
 *
 * @package OpsCenter
 */

declare(strict_types=1);

namespace Ops_Center;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {
    private static ?self $instance = null;
    private Settings $settings;
    private Assets $assets;
    private Admin_Page $admin_page;
    private Admin_Bar $admin_bar;

    private function __construct() {
        $this->settings   = new Settings();
        $this->assets     = new Assets();
        $this->admin_page = new Admin_Page($this->settings);
        $this->admin_bar  = new Admin_Bar($this->settings);
    }

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init(): void {
        load_plugin_textdomain('unified-ops-center', false, dirname(plugin_basename(OPS_CENTER_FILE)) . '/languages');

        add_action('admin_init', [$this->settings, 'register']);
        add_action('admin_menu', [$this->admin_page, 'register']);
        add_action('admin_menu', [$this->settings, 'maybe_remove_posts_menu_page'], 999);
        add_action('admin_init', [$this->settings, 'maybe_block_default_posts_admin_screens'], 999);
        add_action('admin_enqueue_scripts', [$this->assets, 'enqueue_admin_assets']);
        add_action('admin_enqueue_scripts', [$this->assets, 'enqueue_admin_bar_assets']);
        add_action('wp_enqueue_scripts', [$this->assets, 'enqueue_admin_bar_assets']);
        add_action('admin_bar_menu', [$this->settings, 'maybe_hide_wp_new_menu'], 999);
        add_action('wp_before_admin_bar_render', [$this->settings, 'maybe_hide_wp_new_menu_frontend'], 999);
        add_action('admin_bar_menu', [$this->admin_bar, 'register'], 9999);
        add_action('wp_ajax_ops_center_save_appearance', [$this->admin_page, 'ajax_save_appearance']);
    }
}
