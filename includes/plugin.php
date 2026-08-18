<?php
namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس اصلی افزونه: ثبت دسته‌بندی، ویجت‌ها و استایل‌ها
 */
final class Plugin {

    /** نسخه‌ای که آخرین بار روی این سایت اجرا شده */
    const VERSION_OPTION = 'bkw_installed_version';

    private static $instance = null;

    public static function instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        require_once BAKERY_WIDGETS_PATH . 'includes/svg.php';

        add_action('init', [$this, 'maybe_flush_after_update'], 20);

        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_styles']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
    }

    /**
     * بعد از هر به‌روزرسانی افزونه، خروجی‌های کش‌شدهٔ المنتور را دور می‌ریزد.
     *
     * چرا لازم است: المنتور CSS و محتوای رندرشدهٔ هر صفحه را در فایل و
     * ترنزینت نگه می‌دارد و خودش نمی‌داند افزونهٔ ما عوض شده. وقتی نسخهٔ
     * جدید سلکتور یا مارکاپ را تغییر می‌دهد، سایت همچنان نسخهٔ قدیمی را
     * سرو می‌کند. فقط وقتی نسخه واقعاً عوض شده باشد اجرا می‌شود، پس روی
     * بارگذاری‌های عادی هیچ هزینه‌ای ندارد.
     */
    public function maybe_flush_after_update(): void {
        if (get_option(self::VERSION_OPTION) === BAKERY_WIDGETS_VERSION) {
            return;
        }

        update_option(self::VERSION_OPTION, BAKERY_WIDGETS_VERSION, false);

        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    /**
     * دسته‌بندی اختصاصی «بیکری عظام» در پنل ویجت‌های المنتور
     */
    public function register_category($elements_manager): void {
        $elements_manager->add_category('bakery', [
            'title' => __('بیکری عظام', 'bakery-widgets'),
            'icon'  => 'eicon-bakery', // در صورت نبود آیکون اختصاصی، المنتور آیکون پیش‌فرض دسته را نشان می‌دهد
        ]);
    }

    /**
     * ثبت ویجت‌ها با API جدید المنتور (3.5+)
     */
    public function register_widgets($widgets_manager): void {
        require_once BAKERY_WIDGETS_PATH . 'includes/widgets/icon-box.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/widgets/price.php';

        $widgets_manager->register(new Widgets\Icon_Box());
        $widgets_manager->register(new Widgets\Price());
    }

    /**
     * ثبت استایل‌ها؛ ویجت با get_style_depends فقط در صورت استفاده لودشان می‌کند
     */
    public function register_styles(): void {
        wp_register_style(
            'bakery-widgets',
            BAKERY_WIDGETS_URL . 'assets/css/bakery-widgets.css',
            [],
            BAKERY_WIDGETS_VERSION
        );
    }

    /**
     * در ادیتور همیشه استایل لود شود تا پیش‌نمایش درست باشد
     */
    public function enqueue_editor_styles(): void {
        $this->register_styles();
        wp_enqueue_style('bakery-widgets');
    }
}
