<?php
namespace Bakery_Widgets\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «آیکون + عنوان + توضیحات»
 *
 * سه بخش کاملاً مستقل: آیکون (تصویر/SVG)، عنوان (با تگ قابل‌انتخاب) و
 * توضیحات (با تگ قابل‌انتخاب). چیدمان و استایل هر بخش از تب استایل
 * به‌طور کامل قابل مدیریت است (تایپوگرافی، رنگ عادی/هاور، پس‌زمینه،
 * حاشیه، سایه، فاصله‌ها و چیدمان فلکس ریسپانسیو).
 */
class Icon_Box extends Widget_Base {

    public function get_name(): string {
        return 'bakery-icon-box';
    }

    public function get_title(): string {
        return __('آیکون، عنوان و توضیحات بیکری عظام', 'bakery-widgets');
    }

    public function get_icon(): string {
        return 'eicon-icon-box';
    }

    public function get_categories(): array {
        return ['bakery'];
    }

    public function get_keywords(): array {
        return ['آیکون', 'عنوان', 'توضیحات', 'icon', 'box', 'title', 'description', 'بیکری', 'عظام'];
    }

    public function get_style_depends(): array {
        return ['bakery-widgets'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    protected function register_controls(): void {
        // تب محتوا
        $this->register_content_controls();
        $this->register_layout_controls();
        $this->register_link_controls();

        // تب استایل
        $this->register_box_style_controls();
        $this->register_icon_style_controls();
        $this->register_title_style_controls();
        $this->register_description_style_controls();
    }

    /* =====================================================================
     * محتوا — آیکون / عنوان / توضیحات
     * =================================================================== */

    private function register_content_controls(): void {
        $this->start_controls_section('section_content', [
            'label' => __('محتوا', 'bakery-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('icon_image', [
            'label'       => __('آیکون', 'bakery-widgets'),
            'type'        => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'dynamic'     => ['active' => true],
            'description' => __('تصویر یا SVG انتخاب کنید. SVG به‌صورت inline رندر می‌شود و رنگش از تب استایل قابل تغییر است.', 'bakery-widgets'),
        ]);

        $this->add_control('icon_position', [
            'label'       => __('جایگاه آیکون', 'bakery-widgets'),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'title',
            'options'     => [
                'title'       => __('کنار عنوان', 'bakery-widgets'),
                'description' => __('کنار توضیحات', 'bakery-widgets'),
            ],
            'condition'   => ['icon_image[url]!' => ''],
        ]);

        $this->add_control('heading_title', [
            'label'     => __('عنوان', 'bakery-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('title', [
            'label'       => __('متن عنوان', 'bakery-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('عنوان را اینجا بنویسید', 'bakery-widgets'),
            'dynamic'     => ['active' => true],
            'label_block' => true,
        ]);

        $this->add_control('title_tag', [
            'label'   => __('تگ عنوان', 'bakery-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h3',
            'options' => [
                'h1'   => 'H1',
                'h2'   => 'H2',
                'h3'   => 'H3',
                'h4'   => 'H4',
                'h5'   => 'H5',
                'h6'   => 'H6',
                'div'  => 'div',
                'span' => 'span',
                'p'    => 'p',
            ],
        ]);

        $this->add_control('heading_description', [
            'label'     => __('توضیحات', 'bakery-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('description', [
            'label'       => __('متن توضیحات', 'bakery-widgets'),
            'type'        => Controls_Manager::TEXTAREA,
            'default'     => __('توضیحات کوتاهی دربارهٔ این بخش اینجا نوشته می‌شود.', 'bakery-widgets'),
            'dynamic'     => ['active' => true],
            'rows'        => 4,
            'label_block' => true,
        ]);

        $this->add_control('description_tag', [
            'label'   => __('تگ توضیحات', 'bakery-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'p',
            'options' => [
                'p'    => 'p',
                'div'  => 'div',
                'span' => 'span',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — چیدمان
     * =================================================================== */

    private function register_layout_controls(): void {
        $this->start_controls_section('section_layout', [
            'label' => __('چیدمان', 'bakery-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        /*
         * ساختار ویجت دو‌سطری است: سطر اول = آیکون + عنوان (کنار هم)،
         * سطر دوم = توضیحات (زیر سطر اول). این دو بخش عمداً جدا از هم
         * کنترل می‌شوند تا آیکون هیچ‌وقت با ارتفاعش وارد سطر توضیحات
         * نشود و همیشه فقط با عنوان هم‌تراز بماند.
         */
        $this->add_control('heading_header_layout', [
            'label' => __('سطر آیکون و عنوان', 'bakery-widgets'),
            'type'  => Controls_Manager::HEADING,
        ]);

        $this->add_responsive_control('box_direction', [
            'label'     => __('جهت', 'bakery-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'default'   => 'row',
            'options'   => $this->direction_options(),
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box__header' => 'flex-direction: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('box_align', [
            'label'     => __('تراز عمودی آیکون با متن', 'bakery-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'default'   => 'center',
            'options'   => $this->align_options(),
            'selectors' => [
                // همزمان روی هر دو سطر اعمال می‌شود؛ فقط سطری که آیکون واقعاً
                // در آن است (بسته به «جایگاه آیکون») یک آیکون برای تراز کردن دارد.
                '{{WRAPPER}} .bkw-icon-box__header, {{WRAPPER}} .bkw-icon-box__description-row' => 'align-items: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('box_justify', [
            'label'       => __('تراز کردن محتوا', 'bakery-widgets'),
            'type'        => Controls_Manager::CHOOSE,
            'label_block' => true,
            'options'     => $this->justify_options(),
            'selectors'   => [
                '{{WRAPPER}} .bkw-icon-box__header' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('box_gap', [
            'label'      => __('فاصله آیکون تا متن', 'bakery-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 100]],
            'default'    => ['size' => 16, 'unit' => 'px'],
            'selectors'  => [
                // متغیر CSS هم برای «هم‌تراز کردن توضیحات» (پایین‌تر) استفاده می‌شود
                '{{WRAPPER}} .bkw-icon-box' => '--bkw-box-gap: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .bkw-icon-box__header, {{WRAPPER}} .bkw-icon-box__description-row' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('box_wrap', [
            'label'     => __('شکستن به سطر بعد (wrap)', 'bakery-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => $this->wrap_options(),
            'default'   => 'wrap',
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box__header' => 'flex-wrap: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_content_layout', [
            'label'     => __('چیدمان کلی', 'bakery-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('content_align', [
            'label'     => __('چینش متن', 'bakery-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'right'  => ['title' => __('راست', 'bakery-widgets'), 'icon' => 'eicon-text-align-right'],
                'center' => ['title' => __('وسط', 'bakery-widgets'), 'icon' => 'eicon-text-align-center'],
                'left'   => ['title' => __('چپ', 'bakery-widgets'), 'icon' => 'eicon-text-align-left'],
            ],
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('content_gap', [
            'label'      => __('فاصله سطر آیکون/عنوان تا توضیحات', 'bakery-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'default'    => ['size' => 8, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box' => 'row-gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('description_offset', [
            'label'        => __('هم‌تراز کردن توضیحات زیر عنوان', 'bakery-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'description'  => __('روشن: توضیحات دقیقاً از زیر عنوان شروع می‌شود (به‌اندازه عرض آیکون + فاصله، از راست/چپ عقب می‌رود). خاموش: توضیحات از همان لبهٔ سطر اول شروع می‌شود (تمام‌عرض).', 'bakery-widgets'),
            // فقط وقتی معنا دارد که آیکون واقعاً کنار عنوان باشد؛ اگر آیکون
            // کنار توضیحات است یا اصلاً آیکونی نیست، هر دو سطر از همان ابتدا
            // هم‌تراز هستند و عقب بردن توضیحات غلط از آب درمی‌آید. محاسبهٔ
            // نهایی در render() انجام می‌شود، نه اینجا — چون به وجود واقعی
            // آیکون هم نیاز دارد، نه فقط مقدار این کنترل.
            'condition'    => ['box_direction' => 'row', 'icon_position' => 'title'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — لینک
     * =================================================================== */

    private function register_link_controls(): void {
        $this->start_controls_section('section_link', [
            'label' => __('لینک', 'bakery-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('link_scope', [
            'label'   => __('چه چیزی لینک شود؟', 'bakery-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'none',
            'options' => [
                'none'  => __('هیچ‌کدام', 'bakery-widgets'),
                'box'   => __('کل باکس', 'bakery-widgets'),
                'title' => __('فقط عنوان', 'bakery-widgets'),
            ],
        ]);

        $this->add_control('link', [
            'label'     => __('لینک', 'bakery-widgets'),
            'type'      => Controls_Manager::URL,
            'dynamic'   => ['active' => true],
            'condition' => ['link_scope!' => 'none'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — باکس (پس‌زمینه، حاشیه، سایه، پدینگ)
     * =================================================================== */

    private function register_box_style_controls(): void {
        $this->start_controls_section('section_style_box', [
            'label' => __('باکس', 'bakery-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('box_padding', [
            'label'      => __('پدینگ', 'bakery-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('box_style_tabs');

        $this->start_controls_tab('box_style_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name'     => 'box_background',
            'label'    => __('پس‌زمینه', 'bakery-widgets'),
            'types'    => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-icon-box',
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'box_border',
            'label'    => __('حاشیه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-icon-box',
        ]);

        $this->add_responsive_control('box_radius', [
            'label'      => __('رادیوس', 'bakery-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'box_shadow',
            'label'    => __('سایه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-icon-box',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('box_style_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name'     => 'box_background_hover',
            'label'    => __('پس‌زمینه', 'bakery-widgets'),
            'types'    => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-icon-box:hover',
        ]);

        $this->add_control('box_border_color_hover', [
            'label'     => __('رنگ حاشیه', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'box_shadow_hover',
            'label'    => __('سایه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-icon-box:hover',
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control('box_transition_divider', [
            'type' => Controls_Manager::DIVIDER,
        ]);

        $this->add_control('box_transition', [
            'label'     => __('مدت زمان انیمیشن (میلی‌ثانیه)', 'bakery-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0, 'max' => 2000]],
            'default'   => ['size' => 300],
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box, {{WRAPPER}} .bkw-icon-box__icon, {{WRAPPER}} .bkw-icon-box__icon svg *, {{WRAPPER}} .bkw-icon-box__title, {{WRAPPER}} .bkw-icon-box__description' => 'transition: all {{SIZE}}ms ease;',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — آیکون
     * =================================================================== */

    private function register_icon_style_controls(): void {
        $this->start_controls_section('section_style_icon', [
            'label'     => __('آیکون', 'bakery-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['icon_image[url]!' => ''],
        ]);

        $this->add_responsive_control('icon_size', [
            'label'      => __('سایز آیکون', 'bakery-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range'      => ['px' => ['min' => 8, 'max' => 300]],
            'default'    => ['size' => 40, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box' => '--bkw-icon-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_padding', [
            'label'      => __('پدینگ داخلی آیکون', 'bakery-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box__icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_radius', [
            'label'      => __('رادیوس', 'bakery-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box__icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('icon_style_tabs');

        $this->start_controls_tab('icon_style_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name'     => 'icon_background',
            'label'    => __('پس‌زمینه', 'bakery-widgets'),
            'types'    => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-icon-box__icon',
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'icon_border',
            'label'    => __('حاشیه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-icon-box__icon',
        ]);

        /*
         * رنگ SVG خودکار اعمال می‌شود: «رنگ آیکون» فقط بخش‌های دارای fill
         * را بازرنگ می‌کند و «رنگ خطوط آیکون» فقط بخش‌های دارای stroke را.
         * خالی بماند تا رنگ اصلی فایل SVG حفظ شود.
         */
        $this->add_control('icon_fill_color', [
            'label'       => __('رنگ آیکون', 'bakery-widgets'),
            'type'        => Controls_Manager::COLOR,
            'description' => __('بخش‌های توپُر (fill) فایل SVG را بازرنگ می‌کند.', 'bakery-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .bkw-icon-box__icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .bkw-icon-box__icon svg [fill]:not([fill="none"]):not([fill="transparent"])' => 'fill: {{VALUE}};',
                '{{WRAPPER}} .bkw-icon-box__icon svg :is(path,circle,rect,ellipse,polygon,polyline,line):not([fill]):not([stroke])' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('icon_stroke_color', [
            'label'       => __('رنگ خطوط آیکون', 'bakery-widgets'),
            'type'        => Controls_Manager::COLOR,
            'description' => __('بخش‌های خطی (stroke) فایل SVG را بازرنگ می‌کند.', 'bakery-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .bkw-icon-box__icon svg [stroke]:not([stroke="none"]):not([stroke="transparent"])' => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('icon_style_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name'     => 'icon_background_hover',
            'label'    => __('پس‌زمینه', 'bakery-widgets'),
            'types'    => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon',
        ]);

        $this->add_control('icon_border_color_hover', [
            'label'     => __('رنگ حاشیه', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('icon_fill_color_hover', [
            'label'     => __('رنگ آیکون', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon svg [fill]:not([fill="none"]):not([fill="transparent"])' => 'fill: {{VALUE}};',
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon svg :is(path,circle,rect,ellipse,polygon,polyline,line):not([fill]):not([stroke])' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('icon_stroke_color_hover', [
            'label'     => __('رنگ خطوط آیکون', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon svg [stroke]:not([stroke="none"]):not([stroke="transparent"])' => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->add_control('icon_transform_hover', [
            'label'     => __('چرخش آیکون (درجه)', 'bakery-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => -180, 'max' => 180]],
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__icon' => 'transform: rotate({{SIZE}}deg);',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — عنوان
     * =================================================================== */

    private function register_title_style_controls(): void {
        $this->start_controls_section('section_style_title', [
            'label'     => __('عنوان', 'bakery-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['title!' => ''],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .bkw-icon-box__title',
        ]);

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'     => 'title_shadow',
            'selector' => '{{WRAPPER}} .bkw-icon-box__title',
        ]);

        $this->add_responsive_control('title_spacing', [
            'label'      => __('فاصله پایین', 'bakery-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 100]],
            'selectors'  => [
                '{{WRAPPER}} .bkw-icon-box__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('title_color_tabs');

        $this->start_controls_tab('title_color_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_control('title_color', [
            'label'     => __('رنگ', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box__title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('title_color_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_control('title_color_hover', [
            'label'     => __('رنگ', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — توضیحات
     * =================================================================== */

    private function register_description_style_controls(): void {
        $this->start_controls_section('section_style_description', [
            'label'     => __('توضیحات', 'bakery-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['description!' => ''],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'description_typography',
            'selector' => '{{WRAPPER}} .bkw-icon-box__description',
        ]);

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'     => 'description_shadow',
            'selector' => '{{WRAPPER}} .bkw-icon-box__description',
        ]);

        $this->start_controls_tabs('description_color_tabs');

        $this->start_controls_tab('description_color_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_control('description_color', [
            'label'     => __('رنگ', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box__description' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('description_color_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_control('description_color_hover', [
            'label'     => __('رنگ', 'bakery-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-icon-box:hover .bkw-icon-box__description' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * گزینه‌های مشترک کنترل‌های چیدمان
     * ------------------------------------------------------------------- */

    private function direction_options(): array {
        $start = is_rtl() ? 'right' : 'left';
        $end   = is_rtl() ? 'left' : 'right';

        return [
            'row' => [
                'title' => __('سطری', 'bakery-widgets'),
                'icon'  => 'eicon-arrow-' . $end,
            ],
            'column' => [
                'title' => __('ستونی', 'bakery-widgets'),
                'icon'  => 'eicon-arrow-down',
            ],
            'row-reverse' => [
                'title' => __('سطری معکوس', 'bakery-widgets'),
                'icon'  => 'eicon-arrow-' . $start,
            ],
            'column-reverse' => [
                'title' => __('ستونی معکوس', 'bakery-widgets'),
                'icon'  => 'eicon-arrow-up',
            ],
        ];
    }

    private function justify_options(): array {
        return [
            'flex-start'    => ['title' => __('ابتدا', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-start-h'],
            'center'        => ['title' => __('وسط', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-center-h'],
            'flex-end'      => ['title' => __('انتها', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-end-h'],
            'space-between' => ['title' => __('فاصله بین', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-space-between-h'],
        ];
    }

    private function align_options(): array {
        return [
            'flex-start' => ['title' => __('ابتدا', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-start-v'],
            'center'     => ['title' => __('وسط', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-center-v'],
            'flex-end'   => ['title' => __('انتها', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-end-v'],
            'stretch'    => ['title' => __('کشیده', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-stretch-v'],
        ];
    }

    private function wrap_options(): array {
        return [
            'nowrap' => ['title' => __('در یک خط بماند', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-nowrap'],
            'wrap'   => ['title' => __('به چند خط تقسیم شود', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-wrap'],
        ];
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $has_icon        = !empty($settings['icon_image']['url']);
        $has_title       = '' !== trim((string) $settings['title']);
        $has_description = '' !== trim((string) $settings['description']);

        if (!$has_icon && !$has_title && !$has_description) {
            return;
        }

        // آیکون یکی است؛ فقط کنار عنوان یا کنار توضیحات نشان داده می‌شود، نه هر دو.
        $icon_at_title       = $has_icon && 'title' === ($settings['icon_position'] ?? 'title');
        $icon_at_description = $has_icon && 'description' === ($settings['icon_position'] ?? 'title');

        $scope = $settings['link_scope'];
        $link  = !empty($settings['link']['url']) ? $settings['link'] : null;

        $box_tag = 'div';
        $this->add_render_attribute('box', 'class', 'bkw-icon-box');
        if ('box' === $scope && $link) {
            $box_tag = 'a';
            $this->add_link_attributes('box', $link);
        }

        $title_tag       = Utils::validate_html_tag($settings['title_tag']);
        $description_tag = Utils::validate_html_tag($settings['description_tag']);

        /*
         * توضیحات فقط وقتی زیر عنوان هم‌تراز می‌شود که: آیکون واقعاً کنار
         * عنوان باشد (نه کنار توضیحات، نه غایب)، سطر اول جهت سطری داشته
         * باشد، و کاربر از تب چیدمان این را روشن نگه داشته باشد. محاسبه
         * اینجا و نه با یک selector ثابت انجام می‌شود چون به وجود واقعی
         * آیکون نیاز دارد — وگرنه بدون هیچ آیکونی هم یک فاصلهٔ ناخواسته
         * (بر اساس اندازهٔ پیش‌فرض آیکون در CSS) به توضیحات اضافه می‌شد.
         */
        $offset_description = $icon_at_title
            && 'row' === ($settings['box_direction'] ?? 'row')
            && 'yes' === $settings['description_offset'];

        $this->add_render_attribute('description-row', 'class', 'bkw-icon-box__description-row');
        if ($offset_description) {
            $this->add_render_attribute('description-row', 'style', 'margin-inline-start: calc(var(--bkw-icon-size) + var(--bkw-box-gap));');
        }

        ?>
        <<?php echo $box_tag; // phpcs:ignore ?> <?php $this->print_render_attribute_string('box'); ?>>

            <?php if ($icon_at_title || $has_title) : ?>
                <div class="bkw-icon-box__header">
                    <?php if ($icon_at_title) : ?>
                        <span class="bkw-icon-box__icon"><?php $this->render_icon($settings); ?></span>
                    <?php endif; ?>

                    <?php if ($has_title) : ?>
                        <<?php echo $title_tag; // phpcs:ignore ?> class="bkw-icon-box__title">
                            <?php if ('title' === $scope && $link) : ?>
                                <?php $this->add_link_attributes('title-link', $link); ?>
                                <a <?php $this->print_render_attribute_string('title-link'); ?>><?php echo esc_html($settings['title']); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($settings['title']); ?>
                            <?php endif; ?>
                        </<?php echo $title_tag; // phpcs:ignore ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($icon_at_description || $has_description) : ?>
                <div <?php $this->print_render_attribute_string('description-row'); ?>>
                    <?php if ($icon_at_description) : ?>
                        <span class="bkw-icon-box__icon"><?php $this->render_icon($settings); ?></span>
                    <?php endif; ?>

                    <?php if ($has_description) : ?>
                        <<?php echo $description_tag; // phpcs:ignore ?> class="bkw-icon-box__description">
                            <?php echo esc_html($settings['description']); ?>
                        </<?php echo $description_tag; // phpcs:ignore ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </<?php echo $box_tag; // phpcs:ignore ?>>
        <?php
    }

    /** رندر آیکون؛ SVG همیشه inline و پاک‌سازی‌شده درج می‌شود */
    private function render_icon(array $settings): void {
        $url    = $settings['icon_image']['url'];
        $is_svg = 'svg' === strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        $svg = '';
        if ($is_svg && !empty($settings['icon_image']['id'])) {
            $svg = \Bakery_Widgets\Svg::from_attachment((int) $settings['icon_image']['id']);
        }

        if ($svg) {
            echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- پاک‌سازی در Svg::sanitize() انجام می‌شود
        } else {
            printf('<img src="%s" alt="">', esc_url($url));
        }
    }
}
