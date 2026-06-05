<?php
/**
 * Deactivation behavior.
 *
 * @package OpsCenter
 */

declare(strict_types=1);

namespace Ops_Center;

if (!defined('ABSPATH')) {
    exit;
}

final class Deactivator {
    public static function deactivate(): void {
        $settings = get_option(Settings::OPTION_KEY, []);

        if (is_array($settings) && !empty($settings['cleanup_on_deactivation'])) {
            delete_option(Settings::OPTION_KEY);
        }
    }
}
