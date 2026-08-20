<?php
/**
 * Plugin Name:       Weekly Holidays — Elementor Widget
 * Description:       ویجت المنتوری تعطیلات هفته (تقویم شمسی) برای بیکری عظام
 * Version:           1.0.0
 * Author:            Claude
 * Text Domain:       weekly-holidays-widget
 * Requires PHP:      8.1
 * Requires at least: 6.4
 * Elementor tested up to: 3.35
 *
 * Standalone plugin — deliberately independent of the sibling
 * bakery-widgets plugin, which stays on PHP 7.4 (Architecture V3 §1: this
 * plugin's own baseline is not shared with, and does not touch, the
 * existing plugin).
 *
 * PHP baseline note: originally targeted PHP 8.3 for Enums, class-level
 * readonly and typed class constants. Retargeted to PHP 8.1 — the actual
 * hosting environment's fixed version — which still gives us Enums,
 * per-property readonly, constructor promotion and first-class callable
 * syntax; only class-level `readonly class` and typed class constants
 * (both 8.2+/8.3-only) were dropped, in favor of per-property `readonly`
 * and plain (untyped) class constants. `#[\Override]` attributes are left
 * in place — inert on PHP < 8.3, become meaningful if the host ever
 * upgrades.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WHW_PLUGIN_VERSION', '1.0.0');
define('WHW_PLUGIN_FILE', __FILE__);
define('WHW_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WHW_PLUGIN_URL', plugin_dir_url(__FILE__));

const WHW_MIN_PHP = '8.1.0';
const WHW_MIN_ELEMENTOR = '3.13.0';

spl_autoload_register(static function (string $class): void {
    $prefix = 'WHW\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = WHW_PLUGIN_PATH . 'includes/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

register_activation_hook(__FILE__, static function (): void {
    if (version_compare(PHP_VERSION, WHW_MIN_PHP, '<')) {
        return;
    }

    \WHW\Plugin::instance()->cron()->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    if (version_compare(PHP_VERSION, WHW_MIN_PHP, '<')) {
        return;
    }

    \WHW\Plugin::instance()->cron()->deactivate();
});

add_action('plugins_loaded', static function (): void {
    add_action('init', static function (): void {
        load_plugin_textdomain('weekly-holidays-widget', false, dirname(plugin_basename(WHW_PLUGIN_FILE)) . '/languages');
    });

    if (version_compare(PHP_VERSION, WHW_MIN_PHP, '<')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: required PHP version */
                    esc_html__('افزونه «تعطیلات هفته» به PHP نسخه %s یا بالاتر نیاز دارد.', 'weekly-holidays-widget'),
                    WHW_MIN_PHP,
                ),
            );
        });

        return;
    }

    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                esc_html__('افزونه «تعطیلات هفته» برای کار کردن به افزونه المنتور نیاز دارد. لطفاً المنتور را نصب و فعال کنید.', 'weekly-holidays-widget'),
            );
        });

        return;
    }

    if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, WHW_MIN_ELEMENTOR, '<')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: required Elementor version */
                    esc_html__('افزونه «تعطیلات هفته» به المنتور نسخه %s یا بالاتر نیاز دارد.', 'weekly-holidays-widget'),
                    WHW_MIN_ELEMENTOR,
                ),
            );
        });

        return;
    }

    \WHW\Plugin::instance()->init();
});
