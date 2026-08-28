<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Site_Gate;
use Bakery_Widgets\Svg;
use Bakery_Widgets\Widgets\Traits\Terms_Modal_Controls;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «ورود» — دو مرحله: (۱) کد ملی + شماره موبایل، (۲) کد تأیید
 * پیامکی. طبق درخواست صریح، **فقط ظاهر** است — نه اعتبارسنجی واقعی کد
 * ملی/موبایل، نه ارسال واقعی پیامک، نه ساخت نشست ورود واقعی وردپرس.
 * جابه‌جایی بین دو مرحله، پیش‌رفتن خودکار خانه‌های کد تأیید، شمارش
 * معکوس و «ارسال مجدد» همه فقط با JS سمت کاربر شبیه‌سازی می‌شوند
 * (assets/js/bakery-login.js) — هیچ درخواست شبکه‌ای نمی‌رود.
 *
 * «کاربر فرضاً لاگین‌شده به حساب بیاید»: چون نشست واقعی ساخته نمی‌شود
 * (این خودِ «ساختار»ی است که گفتید بعداً می‌گویید)، کلیک روی «تأیید و
 * ورود» صرفاً به آدرس تنظیم‌شده (پیش‌فرض: صفحهٔ اصلی) هدایت می‌کند. اگر
 * آن صفحه ویجت هدر/نوار حساب کاربری همین افزونه را داشته باشد، چون
 * کاربر واقعی وارد نشده، همان فالبک موجود («کاربر مهمان» —
 * Traits\Account_Actions_Controls::resolve_display_name()) خودش نمایش
 * داده می‌شود؛ نیازی به مکانیزم جدید نبود.
 *
 * مودال «قوانین و مقررات» (Traits\Terms_Modal_Controls) همین‌جا، داخل
 * همین صفحهٔ ورود، تعبیه شده — نه به‌عنوان ویجت جداگانه روی صفحهٔ مقصد.
 * بعد از کلیک روی دکمهٔ مرحلهٔ ۲، اول این مودال (پنهان‌شده با ویژگی
 * HTML `hidden`) نمایان می‌شود؛ کاربر باید چک‌باکس را بزند و دکمهٔ
 * تأیید مودال را بزند تا واقعاً به redirect_url منتقل شود. اگر قبلاً
 * (در همان مرورگر) پذیرفته باشد، مودال اصلاً نمایان نمی‌شود و مستقیم
 * منتقل می‌شود — رجوع کن به assets/js/bakery-login.js.
 */
final class Login extends Widget_Base
{
    use Terms_Modal_Controls;

    #[\Override]
    public function get_name(): string
    {
        return 'bakery-login';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('ورود بیکری عظام', 'bakery-widgets');
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
        return ['ورود', 'لاگین', 'کد تایید', 'otp', 'login', 'auth', 'verify', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    public function get_script_depends(): array
    {
        return ['bakery-login', 'bakery-terms-modal'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        // تب محتوا
        $this->register_brand_controls();
        $this->register_step1_controls();
        $this->register_step2_controls();
        $this->register_behavior_controls();
        $this->register_page_background_controls();
        $this->register_terms_modal_content_controls();

        // تب استایل
        $this->register_page_background_style_controls();
        $this->register_card_style_controls();
        $this->register_brand_style_controls();
        $this->register_divider_style_controls();
        $this->register_heading_style_controls();
        $this->register_input_style_controls();
        $this->register_button_style_controls();
        $this->register_otp_style_controls();
        $this->register_timer_style_controls();
        $this->register_edit_number_style_controls();
        $this->register_footer_note_style_controls();
        $this->register_terms_modal_style_controls();
    }

    /* =====================================================================
     * محتوا — برند
     * =================================================================== */

    private function register_brand_controls(): void
    {
        $this->start_controls_section('section_brand', [
            'label' => __('برند', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('brand_logo', [
            'label' => __('آیکون برند', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/logo-badge.svg'],
        ]);

        $this->add_control('brand_text', [
            'label' => __('نام برند', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('بیکری عظام', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('brand_subtitle', [
            'label' => __('زیرعنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تسهیلات ویژه پرسنل', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — مرحلهٔ ۱: کد ملی + موبایل
     * =================================================================== */

    private function register_step1_controls(): void
    {
        $this->start_controls_section('section_step1', [
            'label' => __('مرحلهٔ ۱ — ورود اطلاعات', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('step1_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('خوش آمدید', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('step1_description', [
            'label' => __('توضیح', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('برای ورود نام کاربری خود را وارد کنید', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('heading_national_id', [
            'label' => __('فیلد کد ملی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('national_id_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('کد ملی', 'bakery-widgets'),
        ]);

        $this->add_control('national_id_placeholder', [
            'label' => __('راهنما (placeholder)', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('مثلا: 0030250648', 'bakery-widgets'),
        ]);

        $this->add_control('heading_mobile', [
            'label' => __('فیلد شماره موبایل', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('mobile_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('شماره موبایل', 'bakery-widgets'),
        ]);

        $this->add_control('mobile_placeholder', [
            'label' => __('راهنما (placeholder)', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('مثلا: 09013004000', 'bakery-widgets'),
        ]);

        $this->add_control('mobile_not_found_text', [
            'label' => __('پیام «شماره ثبت نشده»', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('این شماره برای هیچ حسابی در سایت ثبت نشده است.', 'bakery-widgets'),
            'label_block' => true,
            'description' => __('وقتی کاربری این شماره را ندارد نشان داده می‌شود (رجوع کن به Mobile_Login::find_user_id). شماره‌ها را فقط مدیر از صفحهٔ ویرایش هر کاربر تعریف می‌کند — ثبت‌نام خودکار وجود ندارد.', 'bakery-widgets'),
        ]);

        $this->add_control('heading_step1_button', [
            'label' => __('دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step1_button_text', [
            'label' => __('متن (دسکتاپ)', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ورود به حساب کاربری', 'bakery-widgets'),
        ]);

        $this->add_control('step1_button_text_mobile', [
            'label' => __('متن (موبایل)', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ورود', 'bakery-widgets'),
            'description' => __('رفرنس فیگما در موبایل متن کوتاه‌تری برای همین دکمه دارد؛ هر دو رندر می‌شوند و فقط یکی بسته به عرض صفحه نمایش داده می‌شود.', 'bakery-widgets'),
        ]);

        $this->add_control('step1_footer_note', [
            'label' => __('یادداشت پایین فرم', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('در صورت نداشتن حساب کاربری، یک حساب جدید برای شما ساخته خواهد شد.', 'bakery-widgets'),
            'rows' => 2,
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — مرحلهٔ ۲: کد تأیید
     * =================================================================== */

    private function register_step2_controls(): void
    {
        $this->start_controls_section('section_step2', [
            'label' => __('مرحلهٔ ۲ — کد تأیید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('step2_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('کد تأیید', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('otp_length', [
            'label' => __('تعداد رقم کد تأیید', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 4,
            'min' => 3,
            'max' => 8,
        ]);

        $this->add_control('step2_description', [
            'label' => __('توضیح', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('کد تأیید %d رقمی ارسال شده به شماره همراه را وارد کنید', 'bakery-widgets'),
            'description' => __('اگر می‌خواهید تعداد رقم داخل متن هم بیاید، جای آن %d بگذارید — خودکار با «تعداد رقم کد تأیید» بالا جایگزین می‌شود.', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('edit_number_label', [
            'label' => __('برچسب «ویرایش شماره»', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ویرایش شماره', 'bakery-widgets'),
            'description' => __('کلیک روی این لینک کاربر را به مرحلهٔ ۱ برمی‌گرداند.', 'bakery-widgets'),
        ]);

        $this->add_control('heading_step2_button', [
            'label' => __('دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step2_button_text', [
            'label' => __('متن (دسکتاپ)', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تأیید و ورود به حساب', 'bakery-widgets'),
        ]);

        $this->add_control('step2_button_text_mobile', [
            'label' => __('متن (موبایل)', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تأیید و ورود', 'bakery-widgets'),
        ]);

        $this->add_control('heading_timer', [
            'label' => __('تایمر ارسال مجدد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('resend_label', [
            'label' => __('برچسب ارسال مجدد', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ارسال مجدد کد', 'bakery-widgets'),
        ]);

        $this->add_control('countdown_seconds', [
            'label' => __('مدت شمارش معکوس (ثانیه)', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 105,
            'min' => 10,
            'max' => 600,
            'description' => __('۱۰۵ ثانیه = ۰۱:۴۵، مطابق رفرنس فیگما.', 'bakery-widgets'),
        ]);

        $this->add_control('step2_footer_note', [
            'label' => __('یادداشت پایین فرم', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('در صورت عدم دریافت پیامک، پوشه اسپم یا مسدودسازی پیامک‌های تبلیغاتی خود را بررسی کنید.', 'bakery-widgets'),
            'rows' => 2,
            'separator' => 'before',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — رفتار پس از تأیید
     * =================================================================== */

    private function register_behavior_controls(): void
    {
        $this->start_controls_section('section_behavior', [
            'label' => __('رفتار پس از تأیید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('behavior_notice', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => __('این ویجت فعلاً فقط ظاهری است — کد ملی/موبایل و کد تأیید واقعاً بررسی نمی‌شوند و پیامکی ارسال نمی‌شود. با کلیک روی دکمهٔ مرحلهٔ ۲، کاربر مستقیم به آدرس زیر منتقل می‌شود.', 'bakery-widgets'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->add_control('redirect_url', [
            'label' => __('مقصد پس از ورود', 'bakery-widgets'),
            'type' => Controls_Manager::URL,
            'description' => __('خالی = صفحهٔ اصلی سایت.', 'bakery-widgets'),
        ]);

        $this->add_responsive_control('card_max_width', [
            'label' => __('حداکثر عرض کارت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 280, 'max' => 640]],
            'default' => ['unit' => 'px', 'size' => 480],
            'mobile_default' => ['unit' => 'px', 'size' => 400],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__card' => 'max-width: {{SIZE}}{{UNIT}}; margin-left: auto; margin-right: auto;',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — پس‌زمینهٔ صفحه (رفرنس‌های موبایل فیگما یک تصویر تمام‌صفحه
     * پشت کارت دارند — نه بخشی از خودِ کارت؛ اختیاری و پیش‌فرض خاموش،
     * چون در رفرنس دسکتاپ اصلاً وجود ندارد)
     * =================================================================== */

    private function register_page_background_controls(): void
    {
        $this->start_controls_section('section_page_background', [
            'label' => __('پس‌زمینهٔ صفحه', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('page_background_notice', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => __('اگر این ویجت به‌صورت یک صفحهٔ کامل ورود استفاده می‌شود (رفرنس موبایل فیگما یک عکس پشت کارت دارد)، اینجا تصویر پس‌زمینه را انتخاب کنید. برای استفادهٔ داخل یک صفحهٔ معمولی (مثلاً وسط یک سکشن)، خالی بگذارید.', 'bakery-widgets'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->add_control('page_background_image', [
            'label' => __('تصویر پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image'],
        ]);

        $this->add_control('page_background_full_bleed', [
            'label' => __('تمام‌صفحه (پشت کل ویوپورت)', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('روشن: تصویر کل صفحه را می‌پوشاند (position: fixed) — مناسب صفحهٔ اختصاصی ورود. خاموش: فقط پشت همین ویجت را می‌پوشاند.', 'bakery-widgets'),
            'condition' => ['page_background_image[url]!' => ''],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پس‌زمینهٔ صفحه
     * =================================================================== */

    private function register_page_background_style_controls(): void
    {
        $this->start_controls_section('section_style_page_background', [
            'label' => __('پس‌زمینهٔ صفحه', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['page_background_image[url]!' => ''],
        ]);

        $this->add_control('page_background_overlay_color', [
            'label' => __('رنگ پردهٔ روی تصویر', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(26, 19, 14, 0.55)',
            'description' => __('برای خواناتر شدن کارت روی عکس، یک پردهٔ رنگی تیره روی تصویر کشیده می‌شود.', 'bakery-widgets'),
            'selectors' => [
                '{{WRAPPER}} .bkw-login__backdrop-overlay' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('page_background_blur', [
            'label' => __('میزان بلور تصویر', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 4],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__backdrop' => 'filter: blur({{SIZE}}{{UNIT}});',
            ],
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

        $this->add_responsive_control('card_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '48', 'right' => '48', 'bottom' => '48', 'left' => '48', 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_gap', [
            'label' => __('فاصله بین بخش‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 32],
            'mobile_default' => ['unit' => 'px', 'size' => 24],
            'tablet_default' => ['unit' => 'px', 'size' => 24],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__step' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 32],
            'mobile_default' => ['unit' => 'px', 'size' => 28],
            'tablet_default' => ['unit' => 'px', 'size' => 28],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__card' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
            ],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'card_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-login__card',
            'fields_options' => ['color' => ['default' => '#ffffff']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => '{{WRAPPER}} .bkw-login__card',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .bkw-login__card',
            'fields_options' => [
                'box_shadow_type' => ['default' => 'yes'],
                'box_shadow' => ['default' => [
                    'horizontal' => 0, 'vertical' => 12, 'blur' => 16, 'spread' => 0,
                    'color' => 'rgba(42, 30, 23, 0.04)',
                ]],
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — برند
     * =================================================================== */

    private function register_brand_style_controls(): void
    {
        $this->start_controls_section('section_style_brand', [
            'label' => __('برند', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('brand_logo_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 24, 'max' => 100]],
            'default' => ['unit' => 'px', 'size' => 64],
            'mobile_default' => ['unit' => 'px', 'size' => 48],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__brand-logo svg, {{WRAPPER}} .bkw-login__brand-logo img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('brand_gap', [
            'label' => __('فاصله بین آیکون/نام/زیرعنوان', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 16],
            'mobile_default' => ['unit' => 'px', 'size' => 8],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__brand-group' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_brand_name', [
            'label' => __('نام برند', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'brand_name_typography',
            'selector' => '{{WRAPPER}} .bkw-login__brand-name',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 24], 'mobile_default' => ['unit' => 'px', 'size' => 18]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('brand_name_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => ['{{WRAPPER}} .bkw-login__brand-name' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_brand_subtitle', [
            'label' => __('زیرعنوان', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'brand_subtitle_typography',
            'selector' => '{{WRAPPER}} .bkw-login__brand-subtitle',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 15], 'mobile_default' => ['unit' => 'px', 'size' => 12]],
                'font_weight' => ['default' => '500'],
            ],
        ]);

        $this->add_control('brand_subtitle_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-login__brand-subtitle' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — خط جداکنندهٔ زیر برند (فقط موبایل، مثل خط تزئینی
     * section-title: همیشه در DOM، عرضش پیش‌فرض در دسکتاپ صفر است)
     * =================================================================== */

    private function register_divider_style_controls(): void
    {
        $this->start_controls_section('section_style_divider', [
            'label' => __('خط جداکننده (فقط موبایل)', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        /*
         * tablet_default صریح لازم است: کنترل‌های ریسپانسیو المنتور اگر
         * برای یک بریک‌پوینت مقداری نداشته باشند، از بریک‌پوینت بزرگ‌تر
         * بعدی ارث می‌برند — یعنی بدون این خط، در بازهٔ تبلت (که خیلی
         * وقت‌ها یک لپ‌تاپ نیمه‌بازشده هم داخلش می‌افتد) این خط دوباره
         * به مقدار صفرِ دسکتاپ برمی‌گشت و «گم» می‌شد.
         */
        $this->add_responsive_control('divider_height', [
            'label' => __('ضخامت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 4]],
            'default' => ['unit' => 'px', 'size' => 0],
            'tablet_default' => ['unit' => 'px', 'size' => 1],
            'mobile_default' => ['unit' => 'px', 'size' => 1],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__divider' => 'height: {{SIZE}}{{UNIT}} !important; display: block !important;',
            ],
        ]);

        $this->add_control('divider_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => ['{{WRAPPER}} .bkw-login__divider' => 'background-color: {{VALUE}} !important;'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — سرتیتر هر مرحله (عنوان + توضیح، مشترک بین دو مرحله)
     * =================================================================== */

    private function register_heading_style_controls(): void
    {
        $this->start_controls_section('section_style_heading', [
            'label' => __('سرتیتر مرحله', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('heading_step_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'step_title_typography',
            'selector' => '{{WRAPPER}} .bkw-login__step-title',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 28], 'mobile_default' => ['unit' => 'px', 'size' => 22]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('step_title_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => ['{{WRAPPER}} .bkw-login__step-title' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_step_description', [
            'label' => __('توضیح', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'step_description_typography',
            'selector' => '{{WRAPPER}} .bkw-login__step-description',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 15], 'mobile_default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '500'],
            ],
        ]);

        $this->add_control('step_description_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-login__step-description' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — فیلدهای ورودی (کد ملی / موبایل)
     * =================================================================== */

    private function register_input_style_controls(): void
    {
        $this->start_controls_section('section_style_input', [
            'label' => __('فیلدهای ورودی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'input_label_typography',
            'label' => __('تایپوگرافی برچسب', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-login__label',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('input_label_color', [
            'label' => __('رنگ برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => ['{{WRAPPER}} .bkw-login__label' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('input_gap', [
            'label' => __('فاصله بین فیلدها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 24],
            'mobile_default' => ['unit' => 'px', 'size' => 20],
            'selectors' => [
                '{{WRAPPER}} .bkw-login__fields' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_input_box', [
            'label' => __('کادر ورودی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'input_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-login__input-wrap',
            'fields_options' => ['color' => ['default' => '#faf8f5']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'input_border',
            'selector' => '{{WRAPPER}} .bkw-login__input-wrap',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_control('input_border_color_focus', [
            'label' => __('رنگ حاشیه هنگام فوکوس', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-login__input-wrap:focus-within' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_control('input_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => ['{{WRAPPER}} .bkw-login__input-wrap' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('input_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '14', 'right' => '16', 'bottom' => '14', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
            'selectors' => ['{{WRAPPER}} .bkw-login__input-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('heading_input_text', [
            'label' => __('متن ورودی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('input_text_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => ['{{WRAPPER}} .bkw-login__input' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('input_placeholder_color', [
            'label' => __('رنگ راهنما (placeholder)', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(97, 82, 73, 0.5)',
            'selectors' => ['{{WRAPPER}} .bkw-login__input::placeholder' => 'color: {{VALUE}}; opacity: 1;'],
        ]);

        $this->add_control('heading_input_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('input_icon_size', [
            'label' => __('اندازه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 10, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 18],
            'selectors' => ['{{WRAPPER}} .bkw-login__input-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('input_icon_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-login__input-icon svg [stroke]' => 'stroke: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — دکمهٔ اصلی (مشترک بین هر دو مرحله)
     * =================================================================== */

    private function register_button_style_controls(): void
    {
        $this->start_controls_section('section_style_button', [
            'label' => __('دکمهٔ اصلی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $selector = '{{WRAPPER}} .bkw-login__submit';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => $selector,
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 16]],
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

        $this->add_control('button_bg_color_hover', [
            'label' => __('رنگ پس‌زمینه هنگام هاور', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#77492f',
            'selectors' => ["{$selector}:hover" => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '14', 'right' => '32', 'bottom' => '14', 'left' => '32', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('button_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [$selector => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'button_shadow',
            'selector' => $selector,
            'fields_options' => [
                'box_shadow_type' => ['default' => 'yes'],
                'box_shadow' => ['default' => [
                    'horizontal' => 0, 'vertical' => 6, 'blur' => 8, 'spread' => 0,
                    'color' => 'rgba(140, 88, 58, 0.13)',
                ]],
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — ردیف کد تأیید
     * =================================================================== */

    private function register_otp_style_controls(): void
    {
        $this->start_controls_section('section_style_otp', [
            'label' => __('ردیف کد تأیید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $selector = '{{WRAPPER}} .bkw-login__otp-input';

        $this->add_responsive_control('otp_box_size', [
            'label' => __('اندازهٔ خانه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 32, 'max' => 90]],
            'default' => ['unit' => 'px', 'size' => 72],
            'mobile_default' => ['unit' => 'px', 'size' => 56],
            'selectors' => [$selector => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('otp_gap', [
            'label' => __('فاصله بین خانه‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 16],
            'mobile_default' => ['unit' => 'px', 'size' => 12],
            'selectors' => ['{{WRAPPER}} .bkw-login__otp-row' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('otp_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 16],
            'mobile_default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [$selector => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'otp_digit_typography',
            'label' => __('تایپوگرافی رقم واردشده', 'bakery-widgets'),
            'selector' => $selector,
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 28], 'mobile_default' => ['unit' => 'px', 'size' => 22]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('otp_digit_color', [
            'label' => __('رنگ رقم واردشده', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->add_control('otp_placeholder_color', [
            'label' => __('رنگ خط جای‌گزین (خانهٔ خالی)', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(42, 30, 23, 0.3)',
            'selectors' => ["{$selector}::placeholder" => 'color: {{VALUE}}; opacity: 1;'],
        ]);

        $this->add_control('otp_caret_color', [
            'label' => __('رنگ نشانگر تایپ (caret)', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [$selector => 'caret-color: {{VALUE}};'],
        ]);

        $this->start_controls_tabs('otp_state_tabs');

        $this->start_controls_tab('otp_state_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_control('otp_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#faf8f5',
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('otp_border_color', [
            'label' => __('رنگ حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => [$selector => 'border-color: {{VALUE}}; border-style: solid; border-width: 1.5px;'],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('otp_state_focus', ['label' => __('فوکوس', 'bakery-widgets')]);

        $this->add_control('otp_bg_color_focus', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ["{$selector}:focus" => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('otp_border_color_focus', [
            'label' => __('رنگ حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ["{$selector}:focus" => 'border-color: {{VALUE}}; border-style: solid; border-width: 2px;'],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — ردیف تایمر
     * =================================================================== */

    private function register_timer_style_controls(): void
    {
        $this->start_controls_section('section_style_timer', [
            'label' => __('ردیف تایمر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'resend_typography',
            'label' => __('تایپوگرافی «ارسال مجدد»', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-login__resend',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('resend_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-login__resend' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('resend_disabled_opacity', [
            'label' => __('شفافیت هنگام غیرفعال (در حال شمارش)', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
            'default' => ['size' => 0.5],
            'selectors' => ['{{WRAPPER}} .bkw-login__resend:disabled' => 'opacity: {{SIZE}};'],
        ]);

        $this->add_control('heading_countdown', [
            'label' => __('شمارش معکوس', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'countdown_typography',
            'selector' => '{{WRAPPER}} .bkw-login__countdown-value',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('countdown_color', [
            'label' => __('رنگ عدد', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-login__countdown-value' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('countdown_icon_size', [
            'label' => __('اندازهٔ آیکون ساعت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 10, 'max' => 24]],
            'default' => ['unit' => 'px', 'size' => 14],
            'selectors' => ['{{WRAPPER}} .bkw-login__countdown-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('countdown_icon_color', [
            'label' => __('رنگ آیکون ساعت', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-login__countdown-icon svg [stroke]' => 'stroke: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — لینک «ویرایش شماره»
     * =================================================================== */

    private function register_edit_number_style_controls(): void
    {
        $this->start_controls_section('section_style_edit_number', [
            'label' => __('لینک «ویرایش شماره»', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'edit_number_typography',
            'selector' => '{{WRAPPER}} .bkw-login__edit-number, {{WRAPPER}} .bkw-login__edit-number-value',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
            ],
        ]);

        $this->add_control('edit_number_link_color', [
            'label' => __('رنگ لینک', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-login__edit-number' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('edit_number_value_color', [
            'label' => __('رنگ شماره', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(97, 82, 73, 0.8)',
            'selectors' => ['{{WRAPPER}} .bkw-login__edit-number-value' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — یادداشت پایین فرم (مشترک بین دو مرحله)
     * =================================================================== */

    private function register_footer_note_style_controls(): void
    {
        $this->start_controls_section('section_style_footer_note', [
            'label' => __('یادداشت پایین فرم', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'footer_note_typography',
            'selector' => '{{WRAPPER}} .bkw-login__footer-note',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 12], 'mobile_default' => ['unit' => 'px', 'size' => 11]],
            ],
        ]);

        $this->add_control('footer_note_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(97, 82, 73, 0.8)',
            'selectors' => ['{{WRAPPER}} .bkw-login__footer-note' => 'color: {{VALUE}};'],
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

        // به دروازهٔ سطح سایت (Site_Gate) اعلام می‌کند این صفحه، صفحهٔ
        // ورود است — بدون این، دروازه نمی‌داند بازدیدکنندگان بدون دسترسی
        // را به کجا بفرستد. رجوع کن به includes/bakery/site-gate.php.
        Site_Gate::remember_login_page((int) get_the_ID());

        $otp_length = max(3, min(8, (int) $settings['otp_length']));
        $countdown = max(10, (int) $settings['countdown_seconds']);
        $redirect_url = !empty($settings['redirect_url']['url']) ? (string) $settings['redirect_url']['url'] : home_url('/');

        $bg_url = (string) ($settings['page_background_image']['url'] ?? '');
        $full_bleed = '' !== $bg_url && 'yes' === $settings['page_background_full_bleed'];

        printf(
            '<div class="bkw-login%1$s" dir="rtl" data-otp-length="%2$d" data-countdown-seconds="%3$d" data-redirect-url="%4$s">',
            $full_bleed ? ' bkw-login--full-bleed' : '',
            $otp_length,
            $countdown,
            esc_attr($redirect_url),
        );

        if ('' !== $bg_url) {
            printf('<div class="bkw-login__backdrop" style="background-image:url(%s);"></div>', esc_url($bg_url));
            echo '<div class="bkw-login__backdrop-overlay"></div>';
        }

        echo '<div class="bkw-login__card">';
        $this->render_step1($settings);
        $this->render_step2($settings, $otp_length);
        echo '</div>';

        // مودال قوانین همین‌جا تعبیه می‌شود (پنهان با hidden)؛ فقط بعد از
        // کلیک روی دکمهٔ مرحلهٔ ۲ با JS نمایان می‌شود و تنها بعد از تأیید
        // خودش (چک‌باکس + دکمه) به redirect_url منتقل می‌کند.
        $this->render_terms_modal($settings, $redirect_url, false);

        echo '</div>';
    }

    private function render_brand_group(array $settings): void
    {
        echo '<div class="bkw-login__brand-group">';

        echo '<span class="bkw-login__brand-logo">';
        $this->render_icon_field($settings['brand_logo'] ?? []);
        echo '</span>';

        $text = trim((string) $settings['brand_text']);
        if ('' !== $text) {
            printf('<p class="bkw-login__brand-name">%s</p>', esc_html($text));
        }

        $subtitle = trim((string) $settings['brand_subtitle']);
        if ('' !== $subtitle) {
            printf('<p class="bkw-login__brand-subtitle">%s</p>', esc_html($subtitle));
        }

        echo '</div>';
    }

    private function render_step1(array $settings): void
    {
        echo '<div class="bkw-login__step" data-step="1">';

        $this->render_brand_group($settings);
        echo '<div class="bkw-login__divider"></div>';

        echo '<div class="bkw-login__step-heading">';
        printf('<p class="bkw-login__step-title">%s</p>', esc_html((string) $settings['step1_title']));
        printf('<p class="bkw-login__step-description">%s</p>', esc_html((string) $settings['step1_description']));
        echo '</div>';

        echo '<div class="bkw-login__fields">';
        $this->render_input_field('national-id', (string) $settings['national_id_label'], (string) $settings['national_id_placeholder']);
        $this->render_input_field('mobile', (string) $settings['mobile_label'], (string) $settings['mobile_placeholder']);

        printf(
            '<p class="bkw-login__field-error" data-bkw-login-error hidden>%s</p>',
            esc_html((string) $settings['mobile_not_found_text']),
        );

        echo '<button type="button" class="bkw-login__submit" data-bkw-login-step1-submit>';
        printf('<span class="bkw-login__submit-text-full">%s</span>', esc_html((string) $settings['step1_button_text']));
        printf('<span class="bkw-login__submit-text-short">%s</span>', esc_html((string) $settings['step1_button_text_mobile']));
        echo '</button>';
        echo '</div>';

        $footer_note = trim((string) $settings['step1_footer_note']);
        if ('' !== $footer_note) {
            printf('<p class="bkw-login__footer-note">%s</p>', esc_html($footer_note));
        }

        echo '</div>';
    }

    private function render_step2(array $settings, int $otp_length): void
    {
        echo '<div class="bkw-login__step" data-step="2" hidden>';

        $this->render_brand_group($settings);
        echo '<div class="bkw-login__divider"></div>';

        $description = (string) $settings['step2_description'];
        $description = str_contains($description, '%d')
            ? sprintf($description, $otp_length)
            : $description;

        echo '<div class="bkw-login__step-heading">';
        printf('<p class="bkw-login__step-title">%s</p>', esc_html((string) $settings['step2_title']));
        printf('<p class="bkw-login__step-description">%s</p>', esc_html($description));

        echo '<div class="bkw-login__edit-number-row">';
        printf(
            '<button type="button" class="bkw-login__edit-number" data-bkw-login-edit-number>%s</button>',
            esc_html((string) $settings['edit_number_label']),
        );
        echo '<span class="bkw-login__edit-number-value" data-bkw-login-number-display></span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="bkw-login__otp-row">';
        for ($i = 1; $i <= $otp_length; $i++) {
            printf(
                '<input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" placeholder="–" class="bkw-login__otp-input" data-bkw-otp-digit aria-label="%s">',
                esc_attr(sprintf(
                    /* translators: %d: digit position */
                    __('رقم %d کد تأیید', 'bakery-widgets'),
                    $i,
                )),
            );
        }
        echo '</div>';

        echo '<div class="bkw-login__timer-row">';
        echo '<div class="bkw-login__countdown">';
        echo '<span class="bkw-login__countdown-value" data-bkw-countdown-value></span>';
        echo '<span class="bkw-login__countdown-icon">';
        $this->render_icon_field(['url' => BAKERY_WIDGETS_URL . 'assets/icons/clock.svg']);
        echo '</span>';
        echo '</div>';
        printf(
            '<button type="button" class="bkw-login__resend" data-bkw-login-resend disabled>%s</button>',
            esc_html((string) $settings['resend_label']),
        );
        echo '</div>';

        echo '<div class="bkw-login__actions">';
        echo '<button type="button" class="bkw-login__submit" data-bkw-login-step2-submit>';
        printf('<span class="bkw-login__submit-text-full">%s</span>', esc_html((string) $settings['step2_button_text']));
        printf('<span class="bkw-login__submit-text-short">%s</span>', esc_html((string) $settings['step2_button_text_mobile']));
        echo '</button>';

        $footer_note = trim((string) $settings['step2_footer_note']);
        if ('' !== $footer_note) {
            printf('<p class="bkw-login__footer-note">%s</p>', esc_html($footer_note));
        }
        echo '</div>';

        echo '</div>';
    }

    private function render_input_field(string $key, string $label, string $placeholder): void
    {
        echo '<div class="bkw-login__field">';

        if ('' !== trim($label)) {
            printf('<label class="bkw-login__label" for="bkw-login-%1$s-%2$s">%3$s</label>', esc_attr($key), esc_attr((string) $this->get_id()), esc_html($label));
        }

        echo '<span class="bkw-login__input-wrap">';

        echo '<span class="bkw-login__input-icon">';
        $this->render_icon_field(['url' => BAKERY_WIDGETS_URL . 'assets/icons/user.svg']);
        echo '</span>';

        printf(
            '<input type="text" inputmode="%1$s" class="bkw-login__input" id="bkw-login-%2$s-%3$s" placeholder="%4$s" data-bkw-login-field="%2$s">',
            'mobile' === $key ? 'tel' : 'numeric',
            esc_attr($key),
            esc_attr((string) $this->get_id()),
            esc_attr($placeholder),
        );

        echo '</span>';
        echo '</div>';
    }

    /**
     * رندر یک فیلد MEDIA: آپلود کاربر اگر SVG معتبر یا تصویر باشد؛ وگرنه
     * (روی مقدار پیش‌فرض باندل‌شدهٔ خود افزونه) مستقیم از دیسک خوانده و
     * پاک‌سازی می‌شود — همان الگوی Traits\Account_Actions_Controls،
     * تکرارش شده چون این ویجت آن تریت را (بدون سبد/کاربر/خروج) استفاده
     * نمی‌کند.
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
