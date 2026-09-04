<?php

declare(strict_types=1);

namespace Bakery_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس اصلی افزونه: ثبت دسته‌بندی، ویجت‌ها و استایل‌ها
 */
final class Plugin
{
    /** نسخه‌ای که آخرین بار روی این سایت اجرا شده */
    private const VERSION_OPTION = 'bkw_installed_version';

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/svg.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/purchase-limit.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/account-balance.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/site-gate.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/mobile-login.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/otp-policy.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/otp-schema.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/otp-store.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/otp-settings.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/kavenegar.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/users-sheet.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/users-sheet-page.php';

        // همان الگوی Bakery_Credit\Plugin: نصب/ارتقای جدول روی init و نه
        // روی قلاب فعال‌سازی، چون افزونه ممکن است با آپلود فایل
        // به‌روزرسانی شود و آن قلاب اصلاً اجرا نشود.
        add_action('init', [Otp_Schema::class, 'maybe_install'], 5);

        add_action('init', [$this, 'maybe_flush_after_update'], 20);

        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_styles']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_scripts']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_scripts']);

        (new Site_Gate())->register();
        (new Mobile_Login())->register();
        (new Otp_Settings())->register();

        // ستون‌های هویت گزارش اعتبار ماهانه. جهتش عمداً برعکس
        // bkw_user_sheet_columns است: آن‌جا ماژول اعتبار ستونش را به
        // فایل کاربران اضافه می‌کند و این‌جا ماژول ویجت‌ها ستون‌های
        // هویت را به گزارش آن. هیچ‌کدام نام کلاس آن‌یکی را نمی‌برد.
        add_filter('bkw_credit_report_identity', [Users_Sheet::class, 'identity_columns']);

        // ورودی/خروجی اکسل کاربران — فقط در پیشخوان معنا دارد و همهٔ
        // قلاب‌هایش (منوی کاربران و admin-post) هم همان‌جا هستند.
        if (is_admin()) {
            (new Users_Sheet_Page())->register();
        }

        // فقط وقتی ووکامرس فعال است لازم است — ویجت‌های افزودن به سبد و
        // سایدبار سبد بدون آن اصلاً رندر نمی‌شوند.
        if (class_exists('\WooCommerce')) {
            require_once BAKERY_WIDGETS_PATH . 'includes/bakery/cart-ajax.php';
            require_once BAKERY_WIDGETS_PATH . 'includes/bakery/cart-fragments.php';
            require_once BAKERY_WIDGETS_PATH . 'includes/bakery/order-cancellation.php';
            require_once BAKERY_WIDGETS_PATH . 'includes/bakery/order-statuses.php';

            new Cart_Ajax();
            (new Order_Cancellation())->register();
            (new Order_Statuses())->register();

            // اتصال محتوای زندهٔ سایدبار سبد به همان فیلتر فرگمنت استاندارد
            // ووکامرس — Cart_Ajax و Widgets\Cart_Sidebar هیچ‌کدام از وجود
            // یکدیگر خبر ندارند، سیم‌کشی‌شان فقط همین‌جاست.
            add_filter('woocommerce_add_to_cart_fragments', [Cart_Fragments::class, 'add']);
        }
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
    public function maybe_flush_after_update(): void
    {
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
    public function register_category($elements_manager): void
    {
        $elements_manager->add_category('bakery', [
            'title' => __('بیکری عظام', 'bakery-widgets'),
            'icon' => 'eicon-bakery', // در صورت نبود آیکون اختصاصی، المنتور آیکون پیش‌فرض دسته را نشان می‌دهد
        ]);
    }

    /**
     * ثبت ویجت‌ها با API جدید المنتور (3.5+)
     */
    public function register_widgets($widgets_manager): void
    {
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/traits/account-actions-controls.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/traits/terms-modal-controls.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/traits/confirm-modal-controls.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/icon-box.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/price.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/account-bar.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/section-title.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/header.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/login.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/terms-modal.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/add-to-cart.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/cart-sidebar.php';
        require_once BAKERY_WIDGETS_PATH . 'includes/bakery/widgets/order-history.php';

        $widgets_manager->register(new Widgets\Icon_Box());
        $widgets_manager->register(new Widgets\Price());
        $widgets_manager->register(new Widgets\Account_Bar());
        $widgets_manager->register(new Widgets\Section_Title());
        $widgets_manager->register(new Widgets\Header());
        $widgets_manager->register(new Widgets\Login());
        $widgets_manager->register(new Widgets\Terms_Modal());
        $widgets_manager->register(new Widgets\Add_To_Cart());
        $widgets_manager->register(new Widgets\Cart_Sidebar());
        $widgets_manager->register(new Widgets\Order_History());
    }

    /**
     * ثبت استایل‌ها؛ ویجت با get_style_depends فقط در صورت استفاده لودشان می‌کند
     */
    public function register_styles(): void
    {
        wp_register_style(
            'bakery-widgets',
            BAKERY_WIDGETS_URL . 'assets/css/bakery-widgets.css',
            [],
            BAKERY_WIDGETS_VERSION,
        );
    }

    /**
     * در ادیتور همیشه استایل لود شود تا پیش‌نمایش درست باشد
     */
    public function enqueue_editor_styles(): void
    {
        $this->register_styles();
        wp_enqueue_style('bakery-widgets');
    }

    /**
     * ثبت اسکریپت‌ها؛ ویجت با get_script_depends فقط در صورت استفاده لودشان می‌کند
     */
    public function register_scripts(): void
    {
        wp_register_script(
            'bakery-header',
            BAKERY_WIDGETS_URL . 'assets/js/bakery-header.js',
            [],
            BAKERY_WIDGETS_VERSION,
            true,
        );

        wp_register_script(
            'bakery-login',
            BAKERY_WIDGETS_URL . 'assets/js/bakery-login.js',
            [],
            BAKERY_WIDGETS_VERSION,
            true,
        );

        // برای Mobile_Login::ajax_check/ajax_complete — تشخیص «این شماره
        // متعلق به کدام کاربر واقعی است» و لاگین واقعی همان لحظه.
        wp_localize_script('bakery-login', 'bkwLogin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Mobile_Login::nonce_action()),
        ]);

        wp_register_script(
            'bakery-terms-modal',
            BAKERY_WIDGETS_URL . 'assets/js/bakery-terms-modal.js',
            [],
            BAKERY_WIDGETS_VERSION,
            true,
        );

        wp_register_script(
            'bakery-add-to-cart',
            BAKERY_WIDGETS_URL . 'assets/js/bakery-add-to-cart.js',
            [],
            BAKERY_WIDGETS_VERSION,
            true,
        );

        wp_register_script(
            'bakery-cart-sidebar',
            BAKERY_WIDGETS_URL . 'assets/js/bakery-cart-sidebar.js',
            [],
            BAKERY_WIDGETS_VERSION,
            true,
        );

        // رشتهٔ اکشن نانس مستقیم است (نه ارجاع به Cart_Ajax::NONCE_ACTION) چون این
        // متد صرف‌نظر از فعال بودن ووکامرس اجرا می‌شود، در حالی که آن کلاس فقط
        // وقتی ووکامرس فعال باشد بارگذاری می‌شود. هر دو اسکریپت (افزودن به
        // سبد و سایدبار) روی همان دو اکشن admin-ajax سوار می‌شوند، پس با
        // همان اکشن نانس مستقل از هم لوکالایز می‌شوند.
        wp_localize_script('bakery-add-to-cart', 'bkwAtc', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bkw_atc'),
        ]);

        // نانس پرداخت عمداً از نانس تغییر تعداد جداست: آن یکی سبد را
        // دستکاری می‌کند، این یکی پول خرج می‌کند
        // (Bakery_Credit\Integration\DirectCheckout::NONCE_ACTION — رشتهٔ
        // مستقیم، به همان دلیل بالا: آن کلاس فقط با ووکامرس بارگذاری می‌شود).
        wp_localize_script('bakery-cart-sidebar', 'bkwCartSidebar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bkw_atc'),
            'placeOrderNonce' => wp_create_nonce('bkw_place_order'),
            'genericError' => __('ثبت سفارش ممکن نشد. دوباره تلاش کنید.', 'bakery-widgets'),
        ]);

        wp_register_script(
            'bakery-order-history',
            BAKERY_WIDGETS_URL . 'assets/js/bakery-order-history.js',
            [],
            BAKERY_WIDGETS_VERSION,
            true,
        );

        // رشتهٔ اکشن نانس مستقیم است (نه Order_Cancellation::NONCE_ACTION) به
        // همان دلیل بالا: آن کلاس فقط وقتی ووکامرس فعال باشد بارگذاری می‌شود.
        wp_localize_script('bakery-order-history', 'bkwOrderHistory', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bkw_cancel_order'),
            'genericError' => __('لغو سفارش ممکن نشد. دوباره تلاش کنید.', 'bakery-widgets'),
        ]);
    }

    /**
     * در ادیتور همیشه اسکریپت‌های تعاملی لود شوند تا پیش‌نمایش (پنل هدر،
     * جابه‌جایی مراحل ورود، مودال قوانین) هم درست کار کند
     */
    public function enqueue_editor_scripts(): void
    {
        $this->register_scripts();
        wp_enqueue_script('bakery-header');
        wp_enqueue_script('bakery-login');
        wp_enqueue_script('bakery-terms-modal');
        wp_enqueue_script('bakery-add-to-cart');
        wp_enqueue_script('bakery-cart-sidebar');
        wp_enqueue_script('bakery-order-history');
    }
}
