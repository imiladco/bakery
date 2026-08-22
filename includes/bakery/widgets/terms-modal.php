<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Svg;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Repeater;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «مودال قوانین و مقررات» — پردهٔ تیره + کارت اجباری روی کل صفحه:
 * کاربر باید چک‌باکس موافقت را بزند و دکمه را بزند تا به محتوای صفحه
 * دسترسی پیدا کند. طبق درخواست («اجبارا باید اول ببیند و تایید کند»)
 * نه دکمهٔ بستن دارد، نه با کلیک روی پرده یا کلید Escape بسته می‌شود —
 * تنها راه بستنش چک‌کردن+تأیید است.
 *
 * وضعیت «قبلاً پذیرفته» در localStorage با کلید نسخه‌دار نگه داشته
 * می‌شود (assets/js/bakery-terms-modal.js) تا کاربری که یک‌بار پذیرفته
 * هر بار دوباره گیر نکند؛ وقتی متن قوانین واقعاً عوض شد، ادمین «نسخهٔ
 * قوانین» را عوض می‌کند تا دوباره از همه پرسیده شود. در حالت ویرایش
 * المنتور این رفتار (قفل صفحه/چک localStorage) خاموش است — وگرنه ادمین
 * پشت مودال قفل‌شدهٔ خودش گیر می‌افتاد و نمی‌توانست استایلش را بسازد.
 *
 * برای جلوگیری از پرش/فلش دیده‌شدن مودال برای کسی که قبلاً پذیرفته،
 * پیش‌فرض HTML/CSS «نمایش» است (fail-safe: بدون JS هم قفل می‌ماند) و
 * یک اسکریپت inline کوچک بلافاصله بعد از خودِ مارک‌آپ overlay، پیش از
 * رندر بقیهٔ صفحه، آن را همگام پنهان می‌کند اگر قبلاً پذیرفته شده بود.
 */
final class Terms_Modal extends Widget_Base
{
    #[\Override]
    public function get_name(): string
    {
        return 'bakery-terms-modal';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('مودال قوانین و مقررات بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-lock-user';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['قوانین', 'مقررات', 'مودال', 'موافقت', 'terms', 'modal', 'agreement', 'consent', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    public function get_script_depends(): array
    {
        return ['bakery-terms-modal'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        // تب محتوا
        $this->register_header_controls();
        $this->register_terms_controls();
        $this->register_acceptance_controls();
        $this->register_behavior_controls();

        // تب استایل
        $this->register_overlay_style_controls();
        $this->register_card_style_controls();
        $this->register_header_style_controls();
        $this->register_divider_style_controls();
        $this->register_term_row_style_controls();
        $this->register_acceptance_style_controls();
        $this->register_button_style_controls();
    }

    /* =====================================================================
     * محتوا — سربرگ مودال
     * =================================================================== */

    private function register_header_controls(): void
    {
        $this->start_controls_section('section_header', [
            'label' => __('سربرگ', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('header_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/logo-badge.svg'],
        ]);

        $this->add_control('modal_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('قوانین و مقررات', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('modal_subtitle', [
            'label' => __('زیرعنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('لطفاً شرایط عضویت و ثبت سفارش را به دقت مطالعه فرمایید', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — بندهای قوانین
     * =================================================================== */

    private function register_terms_controls(): void
    {
        $this->start_controls_section('section_terms', [
            'label' => __('بندهای قوانین', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $repeater = new Repeater();

        $repeater->add_control('title', [
            'label' => __('عنوان بند', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('عنوان بند', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $repeater->add_control('body', [
            'label' => __('متن بند', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 4,
            'label_block' => true,
        ]);

        $this->add_control('terms', [
            'label' => __('بندها', 'bakery-widgets'),
            'type' => Controls_Manager::REPEATER,
            'fields' => $repeater->get_controls(),
            'title_field' => '{{{ title }}}',
            'default' => [
                [
                    'title' => __('شرایط استفاده از خدمات', 'bakery-widgets'),
                    'body' => __('بیکری عظام ارائه‌دهنده انواع نان، شیرینی و دسر گرم و تازه است. با ثبت سفارش در این وب‌سایت، شما متعهد می‌شوید که واجد شرایط قانونی لازم جهت معامله آنلاین هستید و اطلاعات هویتی خود را به درستی وارد نموده‌اید.', 'bakery-widgets'),
                ],
                [
                    'title' => __('حریم خصوصی کاربران', 'bakery-widgets'),
                    'body' => __('تمامی اطلاعات شخصی شما از جمله نام، شماره تماس و آدرس نزد بیکری عظام کاملاً محفوظ بوده و به‌صورت رمزنگاری‌شده ذخیره می‌شود. این داده‌ها صرفاً جهت پردازش، آماده‌سازی و ارسال دقیق سفارشات شما استفاده می‌شوند.', 'bakery-widgets'),
                ],
                [
                    'title' => __('شرایط سفارش، آماده‌سازی و ارسال', 'bakery-widgets'),
                    'body' => __('تمام سفارشاتی که تا قبل از ساعت ۱۰ صبح ثبت نهایی شوند، در همان روز پخت و تحویل خواهند شد. سفارشات ثبت‌شده پس از ساعت ۱۰ صبح، به نوبت پخت روز بعد موکول می‌شوند. لغو سفارش تنها تا ۳۰ دقیقه پس از پرداخت موفق از طریق بخش تاریخچه سفارشات میسر است.', 'bakery-widgets'),
                ],
                [
                    'title' => __('شرایط پرداخت و بازگشت وجه', 'bakery-widgets'),
                    'body' => __('نان و محصولات آردی کالای فاسدشدنی تلقی می‌شوند. با این حال، در صورت هرگونه مغایرت سفارش یا نارضایتی مستند از کیفیت محصول، تا ۲۴ ساعت پس از تحویل فرصت دارید موضوع را به پشتیبانی عظام گزارش داده و درخواست بازگشت وجه خود را ثبت کنید.', 'bakery-widgets'),
                ],
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — ردیف پذیرش و دکمه
     * =================================================================== */

    private function register_acceptance_controls(): void
    {
        $this->start_controls_section('section_acceptance', [
            'label' => __('پذیرش و دکمه', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('checkbox_label', [
            'label' => __('متن کنار چک‌باکس', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('قوانین و مقررات را مطالعه کردم و می‌پذیرم', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('button_text', [
            'label' => __('متن دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تأیید و ادامه خرید', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — رفتار
     * =================================================================== */

    private function register_behavior_controls(): void
    {
        $this->start_controls_section('section_behavior', [
            'label' => __('رفتار', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('behavior_notice', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => __('این مودال با بارگذاری صفحه به‌طور خودکار باز است، اسکرول پشت‌صحنه را قفل می‌کند و دکمهٔ بستنی ندارد — فقط با علامت‌زدن چک‌باکس و کلیک روی دکمه بسته می‌شود. بعد از پذیرفتن، در همان مرورگر (localStorage) به خاطر سپرده می‌شود تا بار بعد دوباره پرسیده نشود؛ اگر متن قوانین را عوض کردید، «نسخهٔ قوانین» زیر را عوض کنید تا دوباره از همه پرسیده شود.', 'bakery-widgets'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->add_control('terms_version', [
            'label' => __('نسخهٔ قوانین', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => '1',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پردهٔ پس‌زمینه
     * =================================================================== */

    private function register_overlay_style_controls(): void
    {
        $this->start_controls_section('section_style_overlay', [
            'label' => __('پرده', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('overlay_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(26, 19, 14, 0.8)',
            'selectors' => ['{{WRAPPER}}.bkw-terms-modal-overlay' => 'background-color: {{VALUE}} !important;'],
        ]);

        $this->add_control('overlay_blur', [
            'label' => __('میزان بلور', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 4],
            'selectors' => ['{{WRAPPER}}.bkw-terms-modal-overlay' => 'backdrop-filter: blur({{SIZE}}{{UNIT}});'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — کارت
     * =================================================================== */

    private function register_card_style_controls(): void
    {
        $this->start_controls_section('section_style_card', [
            'label' => __('کارت', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $selector = '{{WRAPPER}} .bkw-terms-modal';

        $this->add_responsive_control('card_max_width', [
            'label' => __('حداکثر عرض', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 320, 'max' => 800]],
            'default' => ['unit' => 'px', 'size' => 640],
            'mobile_default' => ['unit' => 'px', 'size' => 400],
            'tablet_default' => ['unit' => 'px', 'size' => 500],
            'selectors' => [$selector => 'width: {{SIZE}}{{UNIT}}; max-width: 92vw;'],
        ]);

        $this->add_responsive_control('card_max_height', [
            'label' => __('حداکثر ارتفاع', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['vh'],
            'range' => ['vh' => ['min' => 40, 'max' => 95]],
            'default' => ['unit' => 'vh', 'size' => 85],
            'selectors' => [$selector => 'max-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '32', 'right' => '32', 'bottom' => '32', 'left' => '32', 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true],
            'tablet_default' => ['top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;'],
        ]);

        $this->add_responsive_control('card_gap', [
            'label' => __('فاصله بین بخش‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 24],
            'mobile_default' => ['unit' => 'px', 'size' => 16],
            'tablet_default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [$selector => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 50]],
            'default' => ['unit' => 'px', 'size' => 28],
            'mobile_default' => ['unit' => 'px', 'size' => 24],
            'tablet_default' => ['unit' => 'px', 'size' => 24],
            'selectors' => [$selector => 'border-radius: {{SIZE}}{{UNIT}} !important;'],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'card_background',
            'types' => ['classic'],
            'selector' => $selector,
            'fields_options' => ['color' => ['default' => '#faf6f0']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => $selector,
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => $selector,
            'fields_options' => [
                'box_shadow_type' => ['default' => 'yes'],
                'box_shadow' => ['default' => [
                    'horizontal' => 0, 'vertical' => 16, 'blur' => 24, 'spread' => 0,
                    'color' => 'rgba(26, 19, 14, 0.4)',
                ]],
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — سربرگ
     * =================================================================== */

    private function register_header_style_controls(): void
    {
        $this->start_controls_section('section_style_header', [
            'label' => __('سربرگ', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('header_icon_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 24, 'max' => 70]],
            'default' => ['unit' => 'px', 'size' => 44],
            'mobile_default' => ['unit' => 'px', 'size' => 40],
            'selectors' => [
                '{{WRAPPER}} .bkw-terms-modal__icon svg, {{WRAPPER}} .bkw-terms-modal__icon img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .bkw-terms-modal__title',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 22], 'mobile_default' => ['unit' => 'px', 'size' => 18]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('title_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_subtitle', [
            'label' => __('زیرعنوان', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'subtitle_typography',
            'selector' => '{{WRAPPER}} .bkw-terms-modal__subtitle',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 12], 'mobile_default' => ['unit' => 'px', 'size' => 11]],
            ],
        ]);

        $this->add_control('subtitle_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__subtitle' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — خط جداکننده
     * =================================================================== */

    private function register_divider_style_controls(): void
    {
        $this->start_controls_section('section_style_divider', [
            'label' => __('خط جداکننده', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('divider_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__divider' => 'background-color: {{VALUE}} !important;'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — بندهای قوانین (بدنهٔ اسکرول‌شونده)
     * =================================================================== */

    private function register_term_row_style_controls(): void
    {
        $this->start_controls_section('section_style_terms', [
            'label' => __('بندهای قوانین', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('term_row_gap', [
            'label' => __('فاصله بین بندها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 16],
            'mobile_default' => ['unit' => 'px', 'size' => 12],
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__body' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('term_row_border_color', [
            'label' => __('رنگ خط زیر هر بند', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__term' => 'border-bottom-color: {{VALUE}} !important;'],
        ]);

        $this->add_control('heading_term_title', [
            'label' => __('عنوان بند', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'term_title_typography',
            'selector' => '{{WRAPPER}} .bkw-terms-modal__term-title',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 16], 'mobile_default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '800'],
            ],
        ]);

        $this->add_control('term_title_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__term-title' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('term_dot_color', [
            'label' => __('رنگ نقطهٔ کنار عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__term-dot' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('heading_term_body', [
            'label' => __('متن بند', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'term_body_typography',
            'selector' => '{{WRAPPER}} .bkw-terms-modal__term-body',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 13], 'mobile_default' => ['unit' => 'px', 'size' => 11]],
            ],
        ]);

        $this->add_control('term_body_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__term-body' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — ردیف پذیرش (چک‌باکس)
     * =================================================================== */

    private function register_acceptance_style_controls(): void
    {
        $this->start_controls_section('section_style_acceptance', [
            'label' => __('ردیف پذیرش', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'checkbox_label_typography',
            'selector' => '{{WRAPPER}} .bkw-terms-modal__checkbox-label',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14], 'mobile_default' => ['unit' => 'px', 'size' => 12]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('checkbox_label_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-terms-modal__checkbox-label' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_checkbox_box', [
            'label' => __('کادر چک‌باکس', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('checkbox_size', [
            'label' => __('اندازه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 16, 'max' => 36]],
            'default' => ['unit' => 'px', 'size' => 24],
            'selectors' => [
                '{{WRAPPER}} .bkw-terms-modal__checkbox-box' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('checkbox_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 12]],
            'default' => ['unit' => 'px', 'size' => 8],
            'selectors' => [
                '{{WRAPPER}} .bkw-terms-modal__checkbox-box' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('checkbox_border_color', [
            'label' => __('رنگ حاشیه (خالی)', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => [
                '{{WRAPPER}} .bkw-terms-modal__checkbox-box' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('checkbox_checked_bg', [
            'label' => __('رنگ پس‌زمینه (تیک‌خورده)', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [
                '{{WRAPPER}} .bkw-terms-modal__checkbox:checked ~ .bkw-terms-modal__checkbox-box' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — دکمهٔ تأیید
     * =================================================================== */

    private function register_button_style_controls(): void
    {
        $this->start_controls_section('section_style_button', [
            'label' => __('دکمهٔ تأیید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $selector = '{{WRAPPER}} .bkw-terms-modal__accept';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => $selector,
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 16], 'mobile_default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '800'],
            ],
        ]);

        $this->add_control('button_text_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->add_control('button_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('button_bg_disabled', [
            'label' => __('رنگ پس‌زمینه (غیرفعال — قبل از تیک‌زدن)', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#c8beb4',
            'selectors' => ["{$selector}:disabled" => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '16', 'right' => '32', 'bottom' => '16', 'left' => '32', 'unit' => 'px', 'isLinked' => false],
            'mobile_default' => ['top' => '12', 'right' => '16', 'bottom' => '12', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('button_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [$selector => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'button_shadow',
            'selector' => $selector,
            'fields_options' => [
                'box_shadow_type' => ['default' => 'yes'],
                'box_shadow' => ['default' => [
                    'horizontal' => 0, 'vertical' => 4, 'blur' => 6, 'spread' => 0,
                    'color' => 'rgba(140, 88, 58, 0.15)',
                ]],
            ],
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $terms = is_array($settings['terms'] ?? null) ? $settings['terms'] : [];
        $version = sanitize_key((string) $settings['terms_version']);
        $storage_key = 'bkw_terms_accepted_' . ('' !== $version ? $version : '1');
        $is_edit_mode = class_exists(ElementorPlugin::class) && ElementorPlugin::$instance->editor->is_edit_mode();

        printf(
            '<div class="bkw-terms-modal-overlay" data-bkw-terms data-storage-key="%1$s" data-edit-mode="%2$s">',
            esc_attr($storage_key),
            $is_edit_mode ? '1' : '0',
        );

        // بدون این اسکریپت inline، کاربری که قبلاً پذیرفته یک لحظه مودال
        // بسته را می‌دید تا اسکریپت خارجی لود و اجرا شود؛ این اسکریپت
        // همزمان با پارس‌شدن HTML (نه منتظر DOMContentLoaded) اجرا می‌شود.
        if (!$is_edit_mode) {
            echo '<script>(function(){try{var o=document.currentScript.parentElement;if(window.localStorage.getItem(o.getAttribute("data-storage-key"))==="accepted"){o.style.display="none";}}catch(e){}})();</script>';
        }

        echo '<div class="bkw-terms-modal" role="dialog" aria-modal="true" aria-label="' . esc_attr((string) $settings['modal_title']) . '">';

        echo '<div class="bkw-terms-modal__header">';
        echo '<div class="bkw-terms-modal__header-text">';
        printf('<p class="bkw-terms-modal__title">%s</p>', esc_html((string) $settings['modal_title']));
        $subtitle = trim((string) $settings['modal_subtitle']);
        if ('' !== $subtitle) {
            printf('<p class="bkw-terms-modal__subtitle">%s</p>', esc_html($subtitle));
        }
        echo '</div>';
        echo '<span class="bkw-terms-modal__icon">';
        $this->render_icon_field($settings['header_icon'] ?? []);
        echo '</span>';
        echo '</div>';

        echo '<div class="bkw-terms-modal__divider"></div>';

        echo '<div class="bkw-terms-modal__body">';
        foreach ($terms as $term) {
            $title = trim((string) ($term['title'] ?? ''));
            $body = trim((string) ($term['body'] ?? ''));
            if ('' === $title && '' === $body) {
                continue;
            }

            echo '<div class="bkw-terms-modal__term">';
            echo '<div class="bkw-terms-modal__term-row-header">';
            printf('<span class="bkw-terms-modal__term-title">%s</span>', esc_html($title));
            echo '<span class="bkw-terms-modal__term-dot" aria-hidden="true"></span>';
            echo '</div>';
            if ('' !== $body) {
                printf('<p class="bkw-terms-modal__term-body">%s</p>', esc_html($body));
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="bkw-terms-modal__divider bkw-terms-modal__divider--footer"></div>';

        $checkbox_id = 'bkw-terms-accept-' . $this->get_id();

        echo '<div class="bkw-terms-modal__acceptance">';
        printf('<label class="bkw-terms-modal__checkbox-label" for="%s">%s</label>', esc_attr($checkbox_id), esc_html((string) $settings['checkbox_label']));
        printf(
            '<span class="bkw-terms-modal__checkbox-wrap"><input type="checkbox" class="bkw-terms-modal__checkbox" id="%s" data-bkw-terms-checkbox><span class="bkw-terms-modal__checkbox-box" aria-hidden="true">%s</span></span>',
            esc_attr($checkbox_id),
            $this->render_icon_field_string(['url' => BAKERY_WIDGETS_URL . 'assets/icons/check.svg']),
        );
        echo '</div>';

        echo '<div class="bkw-terms-modal__actions">';
        printf(
            '<button type="button" class="bkw-terms-modal__accept" data-bkw-terms-accept disabled>%s</button>',
            esc_html((string) $settings['button_text']),
        );
        echo '</div>';

        echo '</div>'; // .bkw-terms-modal
        echo '</div>'; // .bkw-terms-modal-overlay
    }

    /**
     * رندر یک فیلد MEDIA: آپلود کاربر اگر SVG معتبر یا تصویر باشد؛ وگرنه
     * (روی مقدار پیش‌فرض باندل‌شدهٔ خود افزونه) مستقیم از دیسک خوانده و
     * پاک‌سازی می‌شود — همان الگوی دیگر ویجت‌های این افزونه.
     */
    private function render_icon_field(array $image_field): void
    {
        echo $this->render_icon_field_string($image_field); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized inside render_icon_field_string()
    }

    private function render_icon_field_string(array $image_field): string
    {
        $id = !empty($image_field['id']) ? (int) $image_field['id'] : 0;
        $url = (string) ($image_field['url'] ?? '');

        if ($id > 0) {
            $svg = Svg::from_attachment($id);
            if ('' !== $svg) {
                return $svg;
            }

            if ('' !== $url) {
                return sprintf('<img src="%s" alt="">', esc_url($url));
            }
        }

        if ('' !== $url && str_starts_with($url, BAKERY_WIDGETS_URL)) {
            $path = BAKERY_WIDGETS_PATH . substr($url, strlen(BAKERY_WIDGETS_URL));
            $svg = is_readable($path) ? Svg::sanitize((string) file_get_contents($path)) : '';

            if ('' !== $svg) {
                return $svg;
            }
        }

        if ('' !== $url) {
            return sprintf('<img src="%s" alt="">', esc_url($url));
        }

        return '';
    }
}
