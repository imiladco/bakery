<?php
/**
 * Plugin Name:       Bakery Elementor Widgets
 * Description:       ویجت‌های اختصاصی المنتور برای بیکری عظام
 * Version:           1.3.0
 * Author:            Claude
 * Text Domain:       bakery-widgets
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Elementor tested up to: 3.30
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAKERY_WIDGETS_VERSION', '1.3.0');
define('BAKERY_WIDGETS_FILE', __FILE__);
define('BAKERY_WIDGETS_PATH', plugin_dir_path(__FILE__));
define('BAKERY_WIDGETS_URL', plugin_dir_url(__FILE__));

// حداقل نسخه‌های مورد نیاز
define('BAKERY_WIDGETS_MIN_ELEMENTOR', '3.13.0');
define('BAKERY_WIDGETS_MIN_PHP', '7.4');

/**
 * بارگذاری افزونه بعد از لود شدن همه افزونه‌ها تا بتوانیم وجود المنتور را بررسی کنیم.
 */
function bakery_widgets_init() {

    // ترجمه‌ها روی init لود می‌شوند (نه زودتر) تا نوتیس _load_textdomain_just_in_time ندهد
    add_action('init', static function () {
        load_plugin_textdomain('bakery-widgets', false, dirname(plugin_basename(BAKERY_WIDGETS_FILE)) . '/languages');
    });

    if (version_compare(PHP_VERSION, BAKERY_WIDGETS_MIN_PHP, '<')) {
        add_action('admin_notices', 'bakery_widgets_notice_php');
        return;
    }

    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', 'bakery_widgets_notice_elementor');
        return;
    }

    if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, BAKERY_WIDGETS_MIN_ELEMENTOR, '<')) {
        add_action('admin_notices', 'bakery_widgets_notice_elementor_version');
        return;
    }

    require_once BAKERY_WIDGETS_PATH . 'includes/plugin.php';
    \Bakery_Widgets\Plugin::instance();
}
add_action('plugins_loaded', 'bakery_widgets_init');

function bakery_widgets_notice_php() {
    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        sprintf(
            /* translators: %s: required PHP version */
            esc_html__('افزونه «ویجت‌های بیکری عظام» به PHP نسخه %s یا بالاتر نیاز دارد.', 'bakery-widgets'),
            BAKERY_WIDGETS_MIN_PHP
        )
    );
}

function bakery_widgets_notice_elementor() {
    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        esc_html__('افزونه «ویجت‌های بیکری عظام» برای کار کردن به افزونه المنتور نیاز دارد. لطفاً المنتور را نصب و فعال کنید.', 'bakery-widgets')
    );
}

function bakery_widgets_notice_elementor_version() {
    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        sprintf(
            /* translators: %s: required Elementor version */
            esc_html__('افزونه «ویجت‌های بیکری عظام» به المنتور نسخه %s یا بالاتر نیاز دارد. لطفاً المنتور را به‌روزرسانی کنید.', 'bakery-widgets'),
            BAKERY_WIDGETS_MIN_ELEMENTOR
        )
    );
}
