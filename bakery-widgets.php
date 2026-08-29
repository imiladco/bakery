<?php
/**
 * Plugin Name:       Bakery Elementor Widgets
 * Description:       ویجت‌های اختصاصی المنتور برای بیکری عظام — آیکون/عنوان/توضیحات، قیمت، افزودن به سبد، و تعطیلات هفته (تقویم شمسی)
 * Version:           2.6.0
 * Author:            Claude
 * Text Domain:       bakery-widgets
 * Requires PHP:      8.1
 * Requires at least: 6.4
 * Elementor tested up to: 3.35
 *
 * One plugin, three internal code families sharing one Elementor category
 * ("bakery"):
 *   - includes/bakery/*        Icon Box + Price widgets (procedural style,
 *                              manual require_once, no autoloader — as
 *                              originally built).
 *   - includes/{Domain,Storage,Service,Integration,Admin}/*, includes/Cron.php
 *                              Weekly Holidays widget (PSR-4-ish autoloaded
 *                              under the WHW\ namespace, PHP 8.1 target —
 *                              see includes/Domain/JalaliDate.php for why).
 *   - includes/Credit/*        Monthly store credit (Bakery_Credit\ namespace,
 *                              autoloaded, layered Domain/Storage/Service/
 *                              Integration/Admin with pure PHPUnit coverage —
 *                              this one handles money, so the balance formula
 *                              and Jalali period maths are unit tested).
 * Kept in separate namespaces/directories deliberately (Bakery_Widgets\*
 * vs WHW\* vs Bakery_Credit\*) rather than forced into one — they were built independently
 * and merging their internals would be pure churn with no benefit. What's
 * unified here is everything WordPress/Elementor actually sees: one
 * plugin header, one activation lifecycle, one PHP/Elementor version
 * gate, one widget category.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAKERY_WIDGETS_VERSION', '2.6.0');
define('BAKERY_WIDGETS_FILE', __FILE__);
define('BAKERY_WIDGETS_PATH', plugin_dir_path(__FILE__));
define('BAKERY_WIDGETS_URL', plugin_dir_url(__FILE__));

// Weekly Holidays subsystem uses its own constant names internally
// (Storage\Official, Admin\Page, ...) — same plugin, same location.
define('WHW_PLUGIN_VERSION', BAKERY_WIDGETS_VERSION);
define('WHW_PLUGIN_FILE', BAKERY_WIDGETS_FILE);
define('WHW_PLUGIN_PATH', BAKERY_WIDGETS_PATH);
define('WHW_PLUGIN_URL', BAKERY_WIDGETS_URL);

const BAKERY_WIDGETS_MIN_PHP = '8.1.0';
const BAKERY_WIDGETS_MIN_ELEMENTOR = '3.13.0';

spl_autoload_register(static function (string $class): void {
    // WHW\  -> includes/          (ویجت تعطیلات هفته)
    // Bakery_Credit\ -> includes/Credit/  (اعتبار ماهانه — منطق پول، لایه‌بندی‌شده و تست‌دار)
    $prefixes = [
        'WHW\\' => 'includes/',
        'Bakery_Credit\\' => 'includes/Credit/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = BAKERY_WIDGETS_PATH . $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

register_activation_hook(__FILE__, static function (): void {
    if (version_compare(PHP_VERSION, BAKERY_WIDGETS_MIN_PHP, '<')) {
        return;
    }

    \WHW\Plugin::instance()->cron()->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    if (version_compare(PHP_VERSION, BAKERY_WIDGETS_MIN_PHP, '<')) {
        return;
    }

    \WHW\Plugin::instance()->cron()->deactivate();
});

/**
 * بارگذاری افزونه بعد از لود شدن همه افزونه‌ها تا بتوانیم وجود المنتور را بررسی کنیم.
 */
function bakery_widgets_init() {

    // ترجمه‌ها روی init لود می‌شوند (نه زودتر) تا نوتیس _load_textdomain_just_in_time ندهد
    add_action('init', static function () {
        load_plugin_textdomain('bakery-widgets', false, dirname(plugin_basename(BAKERY_WIDGETS_FILE)) . '/languages');
        load_plugin_textdomain('weekly-holidays-widget', false, dirname(plugin_basename(BAKERY_WIDGETS_FILE)) . '/languages');
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

    require_once BAKERY_WIDGETS_PATH . 'includes/bakery/plugin.php';
    \Bakery_Widgets\Plugin::instance();

    \WHW\Plugin::instance()->init();

    // اعتبار ماهانه فقط با ووکامرس معنا دارد — بدون آن نه درگاهی هست و
    // نه سبدی که سقفش را محدود کند.
    if (class_exists('\WooCommerce')) {
        \Bakery_Credit\Plugin::instance()->init();
    }
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
