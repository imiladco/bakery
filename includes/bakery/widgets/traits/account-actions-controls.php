<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets\Traits;

use Bakery_Widgets\Svg;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use WC_Cart;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * محتوا/استایل/رندر مشترک «سبد خرید + کاربر + خروج» — بین ویجت مستقل
 * Account_Bar و بخش اکشن‌های ویجت Header عیناً یکی است (همان کنترل‌ها،
 * همان کلاس‌های CSS، همان منطق موجودی/نام/تعداد سبد)؛ به‌جای کپی این
 * حجم کنترل در دو کلاس، یک‌بار این‌جا تعریف شده و هر دو ویجت با `use`
 * آن را می‌گیرند. شناسهٔ کنترل‌ها بین دو ویجت تداخلی ندارد چون المنتور
 * کنترل‌ها را جدا به ازای هر کلاس ویجت نگه می‌دارد.
 *
 * خروجی `render_account_actions()` همیشه یک `.bkw-account-bar` کامل
 * است (سه پیل) — چه به‌عنوان تنها محتوای Account_Bar، چه تودرتو داخل
 * مارک‌آپ Header — تا CSS و کنترل‌های order/gap بدون تغییر روی هر دو
 * زمینه کار کند.
 */
trait Account_Actions_Controls
{
    /* =====================================================================
     * ثبت کنترل‌ها — تب محتوا
     * =================================================================== */

    private function register_account_actions_content_controls(): void
    {
        $this->register_cart_controls();
        $this->register_user_controls();
        $this->register_logout_controls();
        $this->register_account_actions_layout_controls();
    }

    /* =====================================================================
     * ثبت کنترل‌ها — تب استایل
     * =================================================================== */

    private function register_account_actions_style_controls(): void
    {
        $this->register_common_box_style_controls();
        $this->register_cart_icon_style_controls();
        $this->register_badge_style_controls();
        $this->register_logout_icon_style_controls();
        $this->register_name_style_controls();
        $this->register_separator_style_controls();
        $this->register_balance_style_controls();
        $this->register_compact_user_style_controls();
    }

    /* =====================================================================
     * محتوا — سبد خرید
     * =================================================================== */

    private function register_cart_controls(): void
    {
        $this->start_controls_section('section_cart', [
            'label' => __('سبد خرید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_cart', [
            'label' => __('نمایش این بخش', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('cart_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/cart.svg'],
            'description' => __('برای جایگزینی، تصویر یا SVG دلخواه آپلود کنید.', 'bakery-widgets'),
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('cart_url', [
            'label' => __('لینک', 'bakery-widgets'),
            'type' => Controls_Manager::URL,
            'description' => __('خالی بماند تا این پیل فعلاً قابل‌کلیک نباشد.', 'bakery-widgets'),
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('heading_cart_badge', [
            'label' => __('بج تعداد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('show_cart_badge', [
            'label' => __('نمایش بج تعداد', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('cart_badge_show_zero', [
            'label' => __('نمایش حتی وقتی سبد خالی است', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_cart' => 'yes', 'show_cart_badge' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — کاربر
     * =================================================================== */

    private function register_user_controls(): void
    {
        $this->start_controls_section('section_user', [
            'label' => __('کاربر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_user', [
            'label' => __('نمایش این بخش', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('guest_name_fallback', [
            'label' => __('نام جایگزین', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('کاربر مهمان', 'bakery-widgets'),
            'description' => __('وقتی نام و نام‌خانوادگی کاربر در حساب کاربری ثبت نشده باشد نمایش داده می‌شود.', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes'],
        ]);

        /*
         * «فشرده» رفرنس مجزای فیگما برای مصرف تک‌آیتمِ کیف‌پول (مثلاً
         * نسخهٔ موبایل) است — نه فقط اندازهٔ کوچک‌تر همین پیل، بلکه
         * ترکیب محتوای متفاوت: یک سطر خوش‌آمدگویی + یک رشتهٔ کامل موجودی
         * به‌جای نام/جداکننده/برچسب/عدد/واحد پول جدا. برای همین یک حالت
         * نمایش مجزا شد، نه یک بریک‌پوینت ریسپانسیو — چون ممکن است
         * ادمین بخواهد همین حالت را در دسکتاپ هم برای یک ویجت تک‌آیتم
         * جدا (کنار خود نوار اصلی) به کار ببرد.
         */
        $this->add_control('user_layout', [
            'label' => __('نوع نمایش', 'bakery-widgets'),
            'type' => Controls_Manager::SELECT,
            'default' => 'full',
            'options' => [
                'full' => __('کامل (نام، جداکننده، موجودی جداگانه)', 'bakery-widgets'),
                'compact' => __('فشرده (یک سطر خوش‌آمدگویی + موجودی) — تک‌آیتم/موبایل', 'bakery-widgets'),
            ],
            'condition' => ['show_user' => 'yes'],
        ]);

        $this->add_control('greeting_suffix', [
            'label' => __('پسوند خوش‌آمدگویی', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('خوش آمدید', 'bakery-widgets'),
            'description' => __('بعد از نام کاربر می‌آید: «سارا احمدی خوش آمدید».', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes', 'user_layout' => 'compact'],
        ]);

        $this->add_control('heading_balance', [
            'label' => __('موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_user' => 'yes'],
        ]);

        $this->add_control('show_balance', [
            'label' => __('نمایش موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_user' => 'yes'],
        ]);

        $this->add_control('separator_text', [
            'label' => __('جداکننده', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => '-',
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes', 'user_layout' => 'full'],
        ]);

        $this->add_control('balance_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('موجودی:', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $this->add_control('balance_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تومان', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        /*
         * موجودی هنوز به هیچ منبع واقعی وصل نیست — این عدد فقط ورودیِ
         * فالبک فیلتر `bkw_account_balance` است (رجوع کن به resolve_balance()).
         * وقتی منبع واقعی مشخص شود، همین‌جا می‌ماند و فقط دیگر استفاده
         * نمی‌شود، چون فیلتر مقدار واقعی را جایگزین می‌کند.
         */
        $this->add_control('balance_fallback', [
            'label' => __('عدد فرضی (تا وصل‌شدن منبع واقعی)', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 10000000,
            'min' => 0,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — خروج
     * =================================================================== */

    private function register_logout_controls(): void
    {
        $this->start_controls_section('section_logout', [
            'label' => __('خروج', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_logout', [
            'label' => __('نمایش این بخش', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('logout_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/logout.svg'],
            'description' => __('برای جایگزینی، تصویر یا SVG دلخواه آپلود کنید.', 'bakery-widgets'),
            'condition' => ['show_logout' => 'yes'],
        ]);

        $this->add_control('logout_redirect_url', [
            'label' => __('مقصد بعد از خروج', 'bakery-widgets'),
            'type' => Controls_Manager::URL,
            'description' => __('خالی = صفحهٔ اصلی سایت.', 'bakery-widgets'),
            'condition' => ['show_logout' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — چیدمان (فاصله بین پیل‌ها و ترتیب نمایش هر پیل)
     * =================================================================== */

    private function register_account_actions_layout_controls(): void
    {
        $this->start_controls_section('section_actions_layout', [
            'label' => __('چیدمان اکشن‌ها', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('bar_full_width', [
            'label' => __('عرض کامل', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'description' => __('برای استفادهٔ تک‌آیتم (مثلاً فقط سبد خرید یا فقط کیف‌پول در یک ستون باریک) روشن کنید تا پیل تمام عرض جا را بگیرد.', 'bakery-widgets'),
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar' => 'width: 100%;',
            ],
        ]);

        $this->add_responsive_control('bar_gap', [
            'label' => __('فاصله بین پیل‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['size' => 12, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('bar_wrap', [
            'label' => __('شکستن به سطر بعد (wrap)', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'nowrap' => ['title' => __('در یک خط بماند', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-nowrap'],
                'wrap' => ['title' => __('به چند خط تقسیم شود', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-wrap'],
            ],
            'default' => 'nowrap',
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar' => 'flex-wrap: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_order', [
            'label' => __('ترتیب نمایش پیل‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'description' => __('عدد کوچک‌تر، ابتدای سطر (سمت راست) نمایش داده می‌شود.', 'bakery-widgets'),
        ]);

        $this->add_control('order_logout', [
            'label' => __('خروج', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 1,
            'condition' => ['show_logout' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__logout' => 'order: {{VALUE}};',
            ],
        ]);

        $this->add_control('order_user', [
            'label' => __('کاربر', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 2,
            'condition' => ['show_user' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__user' => 'order: {{VALUE}};',
            ],
        ]);

        $this->add_control('order_cart', [
            'label' => __('سبد خرید', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 3,
            'condition' => ['show_cart' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__cart' => 'order: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — کادر مشترک هر پیل
     *
     * هر سه پیل در رفرنس فیگما دقیقاً یک ظاهر دارند (سفید، بردر یکسان،
     * رادیوس ۱۶، پدینگ ۱۶/۱۰)، پس یک سکشن مشترک روی کلاس __item هر سه
     * را با هم کنترل می‌کند — به‌جای سه سکشن مشابه و تکراری.
     * =================================================================== */

    private function register_common_box_style_controls(): void
    {
        $this->start_controls_section('section_style_box', [
            'label' => __('کادر پیل‌ها', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('item_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'default' => ['top' => '10', 'right' => '16', 'bottom' => '10', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
            // مطابق رفرنس‌های «تک‌آیتم سبد خرید» و «تک‌آیتم موبایل کیف‌پول» در فیگما
            'mobile_default' => ['top' => '8', 'right' => '12', 'bottom' => '8', 'left' => '12', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('item_gap', [
            'label' => __('فاصله داخلی محتوا', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['size' => 12, 'unit' => 'px'],
            'mobile_default' => ['size' => 8, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('box_style_tabs');

        $this->start_controls_tab('box_style_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'item_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-account-bar__item',
            'fields_options' => ['background' => ['default' => 'classic'], 'color' => ['default' => '#ffffff']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'item_border',
            'label' => __('حاشیه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-account-bar__item',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_responsive_control('item_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => '12', 'right' => '12', 'bottom' => '12', 'left' => '12', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'item_shadow',
            'label' => __('سایه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-account-bar__item',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('box_style_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'item_background_hover',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-account-bar__item:hover',
        ]);

        $this->add_control('item_border_color_hover', [
            'label' => __('رنگ حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item:hover' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'item_shadow_hover',
            'label' => __('سایه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-account-bar__item:hover',
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control('item_transition', [
            'label' => __('مدت زمان انیمیشن (میلی‌ثانیه)', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0, 'max' => 2000]],
            'default' => ['size' => 200],
            'separator' => 'before',
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'transition: all {{SIZE}}ms ease;',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — آیکون سبد خرید
     * =================================================================== */

    private function register_cart_icon_style_controls(): void
    {
        $this->start_controls_section('section_style_cart_icon', [
            'label' => __('آیکون سبد خرید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_cart' => 'yes'],
        ]);

        // مطابق رفرنس «تک‌آیتم سبد خرید»: آیکون در موبایل ۱۸px است، نه ۲۰px
        $this->register_icon_style_controls('cart', '.bkw-account-bar__cart', 18);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — بج تعداد سبد
     * =================================================================== */

    private function register_badge_style_controls(): void
    {
        $this->start_controls_section('section_style_badge', [
            'label' => __('بج تعداد', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_cart' => 'yes', 'show_cart_badge' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-account-bar__badge';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'badge_typography',
            'selector' => $selector,
        ]);

        $this->add_control('badge_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->add_control('badge_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('badge_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default' => ['top' => '2', 'right' => '8', 'bottom' => '2', 'left' => '8', 'unit' => 'px', 'isLinked' => false],
            'mobile_default' => ['top' => '1', 'right' => '6', 'bottom' => '1', 'left' => '6', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('badge_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('badge_min_width', [
            'label' => __('حداقل عرض', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'selectors' => [$selector => 'min-width: {{SIZE}}{{UNIT}}; box-sizing: border-box;'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — آیکون خروج
     * =================================================================== */

    private function register_logout_icon_style_controls(): void
    {
        $this->start_controls_section('section_style_logout_icon', [
            'label' => __('آیکون خروج', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_logout' => 'yes'],
        ]);

        $this->register_icon_style_controls('logout', '.bkw-account-bar__logout');

        $this->end_controls_section();
    }

    /**
     * کنترل‌های مشترک استایل هر آیکون (سبد/خروج): اندازه، رنگ fill،
     * رنگ/ضخامت stroke، قرینهٔ افقی — عادی و هاور. هر دو آیکون فیگما
     * فقط stroke دارند (fill="none")، پس «رنگ آیکون» روی fill هم اثر
     * می‌گذارد تا اگر کاربر بعداً یک SVG توپُر جایگزین کرد، همان کنترل
     * برایش هم کار کند — دقیقاً مثل آیکون‌باکس.
     */
    private function register_icon_style_controls(string $prefix, string $item_class, ?int $mobile_size = null): void
    {
        $icon_selector = "{{WRAPPER}} {$item_class} .bkw-account-bar__icon";

        $size_control = [
            'label' => __('اندازه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 20],
            'selectors' => [
                "{$icon_selector} svg, {$icon_selector} img" => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ];

        if (null !== $mobile_size) {
            $size_control['mobile_default'] = ['unit' => 'px', 'size' => $mobile_size];
        }

        $this->add_responsive_control($prefix . '_icon_size', $size_control);

        $this->add_control($prefix . '_icon_flip', [
            'label' => __('قرینهٔ افقی', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'selectors' => [
                $icon_selector => 'transform: scaleX(-1);',
            ],
        ]);

        $this->start_controls_tabs($prefix . '_icon_style_tabs');

        $this->start_controls_tab($prefix . '_icon_style_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_control($prefix . '_icon_fill_color', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'description' => __('بخش‌های توپُر (fill) فایل SVG را بازرنگ می‌کند.', 'bakery-widgets'),
            'selectors' => [
                "{$icon_selector} svg [fill]:not([fill=\"none\"]):not([fill=\"transparent\"])" => 'fill: {{VALUE}};',
                "{$icon_selector} svg :is(path,circle,rect,ellipse,polygon,polyline,line):not([fill]):not([stroke])" => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_stroke_color', [
            'label' => __('رنگ خطوط آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'description' => __('بخش‌های خطی (stroke) فایل SVG را بازرنگ می‌کند.', 'bakery-widgets'),
            'selectors' => [
                "{$icon_selector} svg [stroke]:not([stroke=\"none\"]):not([stroke=\"transparent\"])" => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_stroke_width', [
            'label' => __('ضخامت خط', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0.5,
            'max' => 6,
            'step' => 0.5,
            'default' => 2,
            'selectors' => [
                "{$icon_selector} svg [stroke]:not([stroke=\"none\"])" => 'stroke-width: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab($prefix . '_icon_style_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_control($prefix . '_icon_fill_color_hover', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                "{{WRAPPER}} {$item_class}:hover .bkw-account-bar__icon svg [fill]:not([fill=\"none\"]):not([fill=\"transparent\"])" => 'fill: {{VALUE}};',
                "{{WRAPPER}} {$item_class}:hover .bkw-account-bar__icon svg :is(path,circle,rect,ellipse,polygon,polyline,line):not([fill]):not([stroke])" => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_stroke_color_hover', [
            'label' => __('رنگ خطوط آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                "{{WRAPPER}} {$item_class}:hover .bkw-account-bar__icon svg [stroke]:not([stroke=\"none\"]):not([stroke=\"transparent\"])" => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();
    }

    /* =====================================================================
     * استایل — نام کاربر
     * =================================================================== */

    private function register_name_style_controls(): void
    {
        $this->start_controls_section('section_style_name', [
            'label' => __('نام کاربر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-account-bar__name';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'name_typography',
            'selector' => $selector,
        ]);

        $this->add_control('name_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — جداکننده
     * =================================================================== */

    private function register_separator_style_controls(): void
    {
        $this->start_controls_section('section_style_separator', [
            'label' => __('جداکننده', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes', 'user_layout' => 'full'],
        ]);

        $selector = '{{WRAPPER}} .bkw-account-bar__separator';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'separator_typography',
            'selector' => $selector,
        ]);

        $this->add_control('separator_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — موجودی (برچسب / عدد / واحد پول مستقل)
     * =================================================================== */

    private function register_balance_style_controls(): void
    {
        $this->start_controls_section('section_style_balance', [
            'label' => __('موجودی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes', 'user_layout' => 'full'],
        ]);

        $this->add_responsive_control('balance_gap', [
            'label' => __('فاصله بین برچسب/عدد/واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 24]],
            'default' => ['size' => 4, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__balance' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_balance_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('balance_label', '{{WRAPPER}} .bkw-account-bar__balance-label', '#615249');

        $this->add_control('heading_balance_amount', [
            'label' => __('عدد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('balance_amount', '{{WRAPPER}} .bkw-account-bar__balance-amount', '#2a1e17');

        $this->add_control('heading_balance_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('balance_currency', '{{WRAPPER}} .bkw-account-bar__balance-currency', '#615249');

        $this->end_controls_section();
    }

    private function register_balance_part_style_controls(string $prefix, string $selector, string $default_color): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => $prefix . '_typography',
            'selector' => $selector,
        ]);

        $this->add_control($prefix . '_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => $default_color,
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);
    }

    /* =====================================================================
     * استایل — حالت فشرده کاربر (خوش‌آمدگویی + موجودی یک‌سطری)
     * =================================================================== */

    private function register_compact_user_style_controls(): void
    {
        $this->start_controls_section('section_style_compact_user', [
            'label' => __('نسخهٔ فشرده کاربر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes', 'user_layout' => 'compact'],
        ]);

        $this->add_control('heading_compact_greeting', [
            'label' => __('خوش‌آمدگویی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);
        $this->register_balance_part_style_controls('compact_greeting', '{{WRAPPER}} .bkw-account-bar__greeting', '#2a1e17');

        $this->add_control('heading_compact_balance', [
            'label' => __('موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('compact_balance', '{{WRAPPER}} .bkw-account-bar__balance-compact', '#615249');

        $this->end_controls_section();
    }

    /* =====================================================================
     * منطق — کاربر، موجودی، سبد
     * =================================================================== */

    /** نام و نام‌خانوادگی کاربر لاگین‌کرده؛ خالی‌بودن هردو یعنی display_name، بعد فالبک تنظیمات */
    private function resolve_display_name(WP_User $user, string $fallback): string
    {
        if (0 === $user->ID) {
            return $fallback;
        }

        $full_name = trim(trim((string) $user->first_name) . ' ' . trim((string) $user->last_name));
        if ('' !== $full_name) {
            return $full_name;
        }

        $display_name = trim((string) $user->display_name);

        return '' !== $display_name ? $display_name : $fallback;
    }

    /**
     * `$fallback` (از تب محتوا) فقط وقتی استفاده می‌شود که ماژول اعتبار
     * ماهانه فعال نباشد؛ در غیر این صورت فیلتر عدد واقعی را برمی‌گرداند. از
     * این فیلتر عبور می‌کند تا وقتی منبع واقعی مشخص شد، بدون تغییر این
     * ویجت فقط با یک `add_filter()` جایگزین شود.
     */
    private function resolve_balance(float $fallback, int $user_id): float
    {
        return (float) apply_filters('bkw_account_balance', $fallback, $user_id);
    }

    private function cart_count(): int
    {
        if (!function_exists('WC')) {
            return 0;
        }

        $cart = WC()->cart;

        return $cart instanceof WC_Cart ? $cart->get_cart_contents_count() : 0;
    }

    private function to_persian_digits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    /**
     * خروجی همیشه یک `.bkw-account-bar` کامل (سه پیل مستقل) است — چه
     * تنها محتوای Account_Bar باشد، چه تودرتو داخل مارک‌آپ ویجت Header.
     */
    private function render_account_actions(array $settings): void
    {
        $show_cart = 'yes' === $settings['show_cart'];
        $show_user = 'yes' === $settings['show_user'];
        $show_logout = 'yes' === $settings['show_logout'];

        if (!$show_cart && !$show_user && !$show_logout) {
            return;
        }

        echo '<div class="bkw-account-bar" dir="rtl">';

        if ($show_cart) {
            $this->render_cart_item($settings);
        }

        if ($show_user) {
            $this->render_user_item($settings);
        }

        if ($show_logout) {
            $this->render_logout_item($settings);
        }

        echo '</div>';
    }

    private function render_cart_item(array $settings): void
    {
        $url = !empty($settings['cart_url']['url']) ? $settings['cart_url'] : null;
        $tag = $url ? 'a' : 'div';

        $this->add_render_attribute('cart', 'class', ['bkw-account-bar__item', 'bkw-account-bar__cart']);
        if ($url) {
            $this->add_link_attributes('cart', $url);
        }

        $show_badge = 'yes' === ($settings['show_cart_badge'] ?? 'yes');
        $show_zero = 'yes' === ($settings['cart_badge_show_zero'] ?? 'no');
        $count = $this->cart_count();
        $hidden = 0 === $count && !$show_zero;

        printf('<%1$s %2$s>', esc_html($tag), $this->get_render_attribute_string('cart')); // phpcs:ignore WordPress.Security.EscapeOutput -- render attributes are Elementor-escaped

        // ترتیب DOM عمداً برعکس فیگماست: زیر dir="rtl"، اولین فرزند سمت
        // راست می‌نشیند؛ چون در رفرنس آیکون سمت راست بج است، آیکون باید
        // اول بیاید (رجوع کن به یادداشت بالای کلاس Account_Bar).
        echo '<span class="bkw-account-bar__icon">';
        $this->render_icon_field($settings['cart_icon'] ?? []);
        echo '</span>';

        /*
         * حتی وقتی تعداد صفر است و باید مخفی بماند، بج هنوز در DOM هست
         * (فقط با display:none) — نه غایب کامل — چون bakery-add-to-cart.js
         * و bakery-cart-sidebar.js بعد از هر افزودن/کاهش AJAX همین
         * data-bkw-cart-badge را پیدا و به‌روز می‌کنند؛ اگر عنصر اصلاً
         * وجود نداشت، اولین افزودن به سبد بدون رفرش صفحه نمی‌توانست
         * جایی برای نمایش شمارنده بسازد.
         */
        if ($show_badge) {
            printf(
                '<span class="bkw-account-bar__badge" data-bkw-cart-badge data-show-zero="%s"%s>%s</span>',
                $show_zero ? '1' : '0',
                $hidden ? ' style="display:none;"' : '',
                esc_html($this->to_persian_digits((string) $count))
            );
        }

        printf('</%s>', esc_html($tag));
    }

    private function render_user_item(array $settings): void
    {
        $user = wp_get_current_user();
        $fallback_name = (string) $settings['guest_name_fallback'];
        $name = $this->resolve_display_name($user, '' !== trim($fallback_name) ? $fallback_name : __('کاربر مهمان', 'bakery-widgets'));

        $show_balance = 'yes' === ($settings['show_balance'] ?? 'yes');
        $compact = $show_balance && 'compact' === ($settings['user_layout'] ?? 'full');

        if ($compact) {
            $this->render_user_item_compact($settings, $user, $name);
            return;
        }

        echo '<div class="bkw-account-bar__item bkw-account-bar__user">';

        printf('<span class="bkw-account-bar__name">%s</span>', esc_html($name));

        if ($show_balance) {
            $amount = $this->format_balance($settings, $user);

            printf('<span class="bkw-account-bar__separator">%s</span>', esc_html((string) $settings['separator_text']));

            echo '<span class="bkw-account-bar__balance">';
            printf('<span class="bkw-account-bar__balance-label">%s</span>', esc_html((string) $settings['balance_label']));
            printf('<span class="bkw-account-bar__balance-amount">%s</span>', esc_html($amount));
            printf('<span class="bkw-account-bar__balance-currency">%s</span>', esc_html((string) $settings['balance_currency']));
            echo '</span>';
        }

        echo '</div>';
    }

    /**
     * حالت «فشرده» — رفرنس فیگما «نسخه موبایل تک آیتم کیف پول»: یک سطر
     * خوش‌آمدگویی («سارا احمدی خوش آمدید») + یک رشتهٔ کامل موجودی
     * («موجودی: ۲,۵۰۰,۰۰۰ تومان»)، به‌جای پنج بخش جداگانهٔ حالت کامل.
     * ترتیب DOM عمداً خوش‌آمدگویی را اول می‌آورد: زیر dir="rtl"، فرزند
     * اول سمت راست می‌نشیند — همان‌جایی که در حالت کامل هم نام است.
     */
    private function render_user_item_compact(array $settings, WP_User $user, string $name): void
    {
        $amount = $this->format_balance($settings, $user);
        $balance_text = trim(sprintf(
            '%s %s %s',
            (string) $settings['balance_label'],
            $amount,
            (string) $settings['balance_currency'],
        ));

        $greeting = trim($name . ' ' . (string) $settings['greeting_suffix']);

        echo '<div class="bkw-account-bar__item bkw-account-bar__user bkw-account-bar__user--compact">';
        printf('<span class="bkw-account-bar__greeting">%s</span>', esc_html($greeting));
        printf('<span class="bkw-account-bar__balance-compact">%s</span>', esc_html($balance_text));
        echo '</div>';
    }

    private function format_balance(array $settings, WP_User $user): string
    {
        $balance = $this->resolve_balance((float) $settings['balance_fallback'], $user->ID);

        return $this->to_persian_digits(number_format($balance, 0, '.', ','));
    }

    private function render_logout_item(array $settings): void
    {
        printf(
            '<a class="bkw-account-bar__item bkw-account-bar__logout" href="%s">',
            esc_url($this->resolve_logout_url($settings)),
        );

        echo '<span class="bkw-account-bar__icon">';
        $this->render_icon_field($settings['logout_icon'] ?? []);
        echo '</span>';

        echo '</a>';
    }

    /**
     * جدا از render_logout_item() چون Header::render_mobile_panel() برای
     * دکمهٔ خروج پنل موبایل مارک‌آپ کاملاً متفاوتی می‌خواهد (پیل حاشیه‌دار
     * قرمز، نه یکی از سه پیل استاندارد account-bar) ولی باید همان منطق
     * محاسبهٔ مقصد را به‌اشتراک بگذارد.
     */
    private function resolve_logout_url(array $settings): string
    {
        $redirect = !empty($settings['logout_redirect_url']['url']) ? (string) $settings['logout_redirect_url']['url'] : home_url('/');

        return wp_logout_url($redirect);
    }

    /**
     * رندر یک فیلد MEDIA (کنترل مستقیم یا یک خانهٔ ریپیتر): آپلود
     * انتخابی کاربر اگر SVG معتبر یا تصویر معمولی باشد؛ وگرنه — یعنی
     * کنترل هنوز روی مقدار پیش‌فرض باندل‌شدهٔ خودِ افزونه است (آدرسش با
     * BAKERY_WIDGETS_URL شروع می‌شود) — همان فایل مستقیم از دیسک خوانده
     * و پاک‌سازی می‌شود، بدون درخواست شبکه به آن URL پیش‌فرض.
     */
    private function render_icon_field(array $image_field): void
    {
        $id = !empty($image_field['id']) ? (int) $image_field['id'] : 0;
        $url = (string) ($image_field['url'] ?? '');

        if ($id > 0) {
            $svg = Svg::from_attachment($id);
            if ('' !== $svg) {
                echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized by Svg::sanitize()
                return;
            }

            if ('' !== $url) {
                printf('<img src="%s" alt="">', esc_url($url));
                return;
            }
        }

        if ('' !== $url && str_starts_with($url, BAKERY_WIDGETS_URL)) {
            $path = BAKERY_WIDGETS_PATH . substr($url, strlen(BAKERY_WIDGETS_URL));
            $svg = is_readable($path) ? Svg::sanitize((string) file_get_contents($path)) : '';

            if ('' !== $svg) {
                echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized by Svg::sanitize()
                return;
            }
        }

        if ('' !== $url) {
            printf('<img src="%s" alt="">', esc_url($url));
        }
    }
}
