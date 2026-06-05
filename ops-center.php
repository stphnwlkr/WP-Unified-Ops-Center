<?php
/**
 * Plugin Name: Ops Center
 * Description: A builder-aware command center for WordPress sites using WordPress, Etch, or Bricks.
 * Version: 1.0.0
 * Requires at least: 6.9.4
 * Requires PHP: 8.3
 * Tested up to: 7.0
 * Author: Stephen Walker
 * License: GPL-2.0-or-later
 * Text Domain: unified-ops-center
 * Domain Path: /languages
 *
 * @package OpsCenter
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('OPS_CENTER_VERSION', '1.0.0');
define('OPS_CENTER_FILE', __FILE__);
define('OPS_CENTER_PATH', plugin_dir_path(__FILE__));
define('OPS_CENTER_URL', plugin_dir_url(__FILE__));

require_once OPS_CENTER_PATH . 'includes/class-settings.php';
require_once OPS_CENTER_PATH . 'includes/class-assets.php';
require_once OPS_CENTER_PATH . 'includes/class-admin-page.php';
require_once OPS_CENTER_PATH . 'includes/class-admin-bar.php';
require_once OPS_CENTER_PATH . 'includes/class-deactivator.php';
require_once OPS_CENTER_PATH . 'includes/class-plugin.php';

add_action('plugins_loaded', static function (): void {
    Ops_Center\Plugin::instance()->init();
});

register_deactivation_hook(__FILE__, ['Ops_Center\\Deactivator', 'deactivate']);
