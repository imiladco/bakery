<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets\Traits;

use Bakery_Widgets\Svg;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مودال «مطمئنی؟» — کنترل‌ها، استایل و مارکاپ مشترک.
 *
 * دو مصرف دارد و هر دو یک کار می‌کنند: قبل از عملی که برگشتش دست کاربر
 * نیست، یک تأیید نهایی می‌گیرند.
 *   - Cart_Sidebar: پیش از ثبت سفارش، که بی‌درنگ اعتبار کم می‌کند.
 *   - Order_History: پیش از لغو سفارش.
 *
 * چرا تریت و نه دو بار نوشتن: طرح این مودال (فیگما، نود 1:1953) حدود
 * چهل‌وپنج کنترل استایل دارد — پرده و بلور، کارت، آیکون، عنوان، متن و
 * دو دکمه. کپی‌کردنشان یعنی هر اصلاح آینده باید دو جا انجام شود و
 * دیر یا زود یکی‌شان جا می‌ماند. همان مکانیزمی که
 * Terms_Modal_Controls و Account_Actions_Controls برایش وجود دارند.
 *
 * شناسه‌های کنترل عمداً بین دو ویجت یکسان‌اند (confirm_card_width و
 * مانند آن). تداخل ندارند چون شناسه‌ها داخل یک کلاس یکتا هستند نه بین
 * کلاس‌ها، و یکسان ماندنشان یعنی استایل‌هایی که ادمین قبلاً روی سایدبار
 * سبد ذخیره کرده بعد از این تغییر هم اعمال می‌شوند.
 *
 * چیزهایی که بین دو مصرف فرق می‌کنند از بیرون می‌آیند: متن‌ها و
 * رنگ دکمهٔ تأیید از آرایهٔ $defaults، و قلاب داده‌ای و محتوای اضافه
 * (مثل برچسب «در حال ثبت…» یا پاراگراف خطا) از آرایهٔ $args در
 * render_confirm_modal().
 */
trait Confirm_Modal_Controls
{
    private function register_confirm_modal_controls(array $defaults): void
    {
        $this->start_controls_section('section_confirm', [
            'label' => $defaults['section_label'],
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('confirm_notice', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => $defaults['notice'],
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->add_control('confirm_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/icon-circle.svg'],
            'description' => __('آیکون پیش‌فرض دقیقاً همان خروجی فیگماست و دایرهٔ پس‌زمینه داخل خودِ فایل SVG است — برای همین رنگ دایره و رنگ آیکون در تب استایل جدا از هم قابل تنظیم‌اند. اگر آیکون دیگری بگذارید که دایره نداشته باشد، کنترل «رنگ دایره» اثری نخواهد داشت.', 'bakery-widgets'),
        ]);

        $this->add_control('confirm_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => $defaults['title'],
            'label_block' => true,
        ]);

        $this->add_control('confirm_text', [
            'label' => __('توضیح', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => $defaults['text'],
            'rows' => 2,
        ]);

        $this->add_control('confirm_accept_text', [
            'label' => __('متن دکمهٔ تأیید', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => $defaults['accept_text'],
            'label_block' => true,
        ]);

        $this->add_control('confirm_cancel_text', [
            'label' => __('متن دکمهٔ انصراف', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => $defaults['cancel_text'],
        ]);

        $this->end_controls_section();
    }

    private function register_confirm_modal_style_controls(array $defaults): void
    {
        $this->start_controls_section('section_style_confirm', [
            'label' => $defaults['section_label'],
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('heading_confirm_overlay', [
            'label' => __('پرده', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_control('confirm_overlay_color', [
            'label' => __('رنگ پرده', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(26, 19, 14, 0.45)',
            'selectors' => ['{{WRAPPER}} .bkw-confirm' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('confirm_overlay_blur', [
            'label' => __('بلور شیشه‌ای پرده', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 10],
            'selectors' => [
                '{{WRAPPER}} .bkw-confirm' => 'backdrop-filter: blur({{SIZE}}{{UNIT}}); -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}});',
            ],
        ]);

        $this->add_control('heading_confirm_card', [
            'label' => __('کارت', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('confirm_card_width', [
            'label' => __('عرض کارت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 280, 'max' => 640]],
            'default' => ['unit' => 'px', 'size' => 450],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__card' => 'width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('confirm_card_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '40', 'right' => '40', 'bottom' => '40', 'left' => '40', 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => '28', 'right' => '20', 'bottom' => '28', 'left' => '20', 'unit' => 'px', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('confirm_card_gap', [
            'label' => __('فاصلهٔ بخش‌های کارت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 32],
            'mobile_default' => ['unit' => 'px', 'size' => 24],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__card' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('confirm_card_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 32],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__card' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'confirm_card_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-confirm__card',
            'fields_options' => ['color' => ['default' => '#fcf9f5']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'confirm_card_border',
            'selector' => '{{WRAPPER}} .bkw-confirm__card',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'confirm_card_shadow',
            'selector' => '{{WRAPPER}} .bkw-confirm__card',
            'fields_options' => [
                'box_shadow_type' => ['default' => 'yes'],
                'box_shadow' => ['default' => [
                    'horizontal' => 0, 'vertical' => 24, 'blur' => 24, 'spread' => 0,
                    'color' => 'rgba(26, 19, 14, 0.25)',
                ]],
            ],
        ]);

        $this->add_control('heading_confirm_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('confirm_icon_size', [
            'label' => __('اندازهٔ آیکون (با دایره)', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 160]],
            'default' => ['unit' => 'px', 'size' => 72],
            'mobile_default' => ['unit' => 'px', 'size' => 64],
            'selectors' => [
                '{{WRAPPER}} .bkw-confirm__icon svg, {{WRAPPER}} .bkw-confirm__icon img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        /*
         * دایره و خودِ آیکون هر دو داخل یک SVG‌اند (خروجی فیگما)، پس
         * به‌جای پس‌زمینهٔ CSS، مستقیم روی همان دو عنصر داخل SVG تنظیم
         * می‌شوند: rect دایره است و path خطوط آیکون.
         */
        $this->add_control('confirm_plate_color', [
            'label' => __('رنگ دایره', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#faf6f0',
            'selectors' => ['{{WRAPPER}} .bkw-confirm__icon svg rect' => 'fill: {{VALUE}};'],
        ]);

        $this->add_control('confirm_icon_color', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-confirm__icon svg [stroke]' => 'stroke: {{VALUE}};'],
        ]);

        /*
         * بدون واحد خروجی می‌گیرد: stroke-width در SVG بر حسب واحدهای
         * داخلی خودِ آیکون است (viewBox ۷۲×۷۲)، نه پیکسل صفحه — پس با
         * تغییر اندازهٔ آیکون خودش هم متناسب بزرگ/کوچک می‌شود.
         */
        $this->add_control('confirm_icon_stroke', [
            'label' => __('ضخامت خط آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0.5, 'max' => 5, 'step' => 0.1]],
            'default' => ['size' => 2],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__icon svg [stroke]' => 'stroke-width: {{SIZE}};'],
        ]);

        $this->add_responsive_control('confirm_header_gap', [
            'label' => __('فاصلهٔ آیکون تا عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 48]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__header' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('heading_confirm_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'confirm_title_typography',
            'selector' => '{{WRAPPER}} .bkw-confirm__title',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 24], 'mobile_default' => ['unit' => 'px', 'size' => 21]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('confirm_title_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-confirm__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_confirm_text', [
            'label' => __('توضیح', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'confirm_text_typography',
            'selector' => '{{WRAPPER}} .bkw-confirm__text',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 16], 'mobile_default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '700'],
                'line_height' => ['default' => ['unit' => 'em', 'size' => 1.5]],
            ],
        ]);

        $this->add_control('confirm_text_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-confirm__text' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_confirm_buttons', [
            'label' => __('دکمه‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('confirm_buttons_gap', [
            'label' => __('فاصلهٔ دو دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__actions' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('confirm_button_height', [
            'label' => __('ارتفاع دکمه‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 36, 'max' => 90]],
            'default' => ['unit' => 'px', 'size' => 52],
            'mobile_default' => ['unit' => 'px', 'size' => 48],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__actions button' => 'height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('confirm_button_radius', [
            'label' => __('رادیوس دکمه‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__actions button' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'confirm_button_typography',
            'label' => __('تایپوگرافی دکمه‌ها', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-confirm__actions button',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 15]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('confirm_accept_bg', [
            'label' => __('رنگ پس‌زمینهٔ تأیید', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => $defaults['accept_bg'],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__accept' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('confirm_accept_color', [
            'label' => __('رنگ متن تأیید', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => $defaults['accept_color'],
            'selectors' => ['{{WRAPPER}} .bkw-confirm__accept' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('confirm_cancel_bg', [
            'label' => __('رنگ پس‌زمینهٔ انصراف', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-confirm__cancel' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('confirm_cancel_color', [
            'label' => __('رنگ متن انصراف', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#7d7065',
            'selectors' => ['{{WRAPPER}} .bkw-confirm__cancel' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'confirm_cancel_border',
            'selector' => '{{WRAPPER}} .bkw-confirm__cancel',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1.5', 'right' => '1.5', 'bottom' => '1.5', 'left' => '1.5', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->end_controls_section();
    }

    /**
     * مارکاپ مودال.
     *
     * @param array<string,mixed> $settings تنظیمات ویجت
     * @param array{hook:string,pending_text?:string,extra_html?:string} $args
     *        hook: نامی که در data-bkw-confirm می‌نشیند و جاوااسکریپت
     *        هر ویجت با آن مودال خودش را پیدا می‌کند.
     */
    private function render_confirm_modal(array $settings, array $args): void
    {
        $hook = $args['hook'];

        printf(
            '<div class="bkw-confirm" data-bkw-confirm="%1$s" role="dialog" aria-modal="true" aria-label="%2$s" hidden>',
            esc_attr($hook),
            esc_attr((string) $settings['confirm_title'])
        );

        echo '<div class="bkw-confirm__card">';

        /*
         * آیکون و عنوان یک بلوک‌اند (فاصلهٔ ۱۶ بینشان) و کل کارت فاصلهٔ
         * ۳۲ بین بلوک‌هایش دارد — همان ساختار modal-header در فیگما، نه
         * سه عنصر هم‌سطح با مارجین.
         */
        echo '<div class="bkw-confirm__header">';
        echo '<span class="bkw-confirm__icon">';
        $this->render_confirm_icon($settings['confirm_icon'] ?? []);
        echo '</span>';
        printf('<p class="bkw-confirm__title">%s</p>', esc_html((string) $settings['confirm_title']));
        echo '</div>';

        printf('<p class="bkw-confirm__text">%s</p>', esc_html((string) $settings['confirm_text']));

        echo '<div class="bkw-confirm__actions">';

        echo '<button type="button" class="bkw-confirm__accept" data-bkw-confirm-accept>';
        printf('<span class="bkw-confirm__accept-label">%s</span>', esc_html((string) $settings['confirm_accept_text']));

        // برچسب «در حال انجام» فقط وقتی رندر می‌شود که ویجت یکی داشته
        // باشد؛ CSS با کلاس is-pending بینشان جابه‌جا می‌کند.
        if (isset($args['pending_text']) && '' !== $args['pending_text']) {
            printf('<span class="bkw-confirm__accept-pending">%s</span>', esc_html($args['pending_text']));
        }

        echo '</button>';

        printf(
            '<button type="button" class="bkw-confirm__cancel" data-bkw-confirm-cancel>%s</button>',
            esc_html((string) $settings['confirm_cancel_text'])
        );

        echo '</div>';

        // جای پاراگراف خطا یا هر چیز دیگری که فقط یکی از دو ویجت دارد.
        // خودِ فراخوان مسئول escape کردنش است.
        echo $args['extra_html'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput -- فراخوان از قبل escape کرده

        echo '</div>';
        echo '</div>';
    }

    /**
     * رندر فیلد MEDIA آیکون.
     *
     * SVG به‌صورت inline (و پاک‌سازی‌شده با Svg::sanitize) درج می‌شود نه
     * داخل img، وگرنه کنترل‌های «رنگ دایره» و «رنگ آیکون» در تب استایل
     * هیچ اثری ندارند — CSS به داخل یک img نفوذ نمی‌کند.
     *
     * نام این متد عمداً render_confirm_icon است و نه render_icon_field:
     * چند ویجت از قبل نسخهٔ خودشان از آن را دارند و متد کلاس همیشه بر
     * متد تریت مقدم می‌شود، پس هم‌نام بودن یعنی رفتار این مودال بی‌صدا
     * از ویجتی به ویجت دیگر فرق کند.
     *
     * @param array<string,mixed> $icon
     */
    private function render_confirm_icon(array $icon): void
    {
        $id = !empty($icon['id']) ? (int) $icon['id'] : 0;
        $url = (string) ($icon['url'] ?? '');

        if ($id > 0) {
            $svg = Svg::from_attachment($id);
            if ('' !== $svg) {
                echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized by Svg::sanitize()
                return;
            }
        }

        // آیکون پیش‌فرض از خودِ افزونه می‌آید و شناسهٔ پیوست ندارد؛ فقط
        // فایل‌های داخل همین افزونه از دیسک خوانده می‌شوند.
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
