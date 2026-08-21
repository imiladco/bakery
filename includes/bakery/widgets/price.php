<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Base;
use WC_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «قیمت» — فقط نمایش قیمت محصول (بدون افزودن به سبد و بدون بج تخفیف).
 *
 * سه بخش مستقل: قیمت فعلی، قیمت پیشین (خط‌خورده، فقط هنگام فروش ویژه) و
 * واحد پول — هرکدام یک عنصر flex جدا با کنترل «ترتیب نمایش» (CSS order)
 * مستقل، تا جایگاه و توالی‌شان از تب چیدمان کاملاً قابل تغییر باشد، بدون
 * نیاز به مارک‌آپ متفاوت برای هر حالت.
 */
final class Price extends Widget_Base
{
    #[\Override]
    public function get_name(): string
    {
        return 'bakery-price';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('قیمت بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-price-list';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['قیمت', 'محصول', 'price', 'product', 'woocommerce', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        // تب محتوا
        $this->register_product_controls();
        $this->register_price_content_controls();
        $this->register_layout_controls();

        // تب استایل
        $this->register_now_style_controls();
        $this->register_old_style_controls();
        $this->register_currency_style_controls();
    }

    /* =====================================================================
     * محتوا — محصول
     * =================================================================== */

    private function register_product_controls(): void
    {
        $this->start_controls_section('section_product', [
            'label' => __('محصول', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('product_id', [
            'label' => __('شناسه محصول', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0,
            'description' => __('خالی = محصول جاری (در صفحه/قالب محصول).', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — قیمت
     * =================================================================== */

    private function register_price_content_controls(): void
    {
        $this->start_controls_section('section_price', [
            'label' => __('قیمت', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_old', [
            'label' => __('نمایش قیمت پیش از تخفیف', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('فقط هنگامی نمایش داده می‌شود که محصول فروش ویژه داشته باشد.', 'bakery-widgets'),
        ]);

        $this->add_control('heading_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('show_currency', [
            'label' => __('نمایش واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('currency_text', [
            'label' => __('متن واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تومان', 'bakery-widgets'),
            'placeholder' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : __('تومان', 'bakery-widgets'),
            'description' => __('خالی = نماد پیش‌فرض ووکامرس.', 'bakery-widgets'),
            'condition' => ['show_currency' => 'yes'],
        ]);

        $this->add_control('free_text', [
            'label' => __('متن «رایگان»', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('رایگان', 'bakery-widgets'),
            'description' => __('وقتی قیمت صفر باشد، این متن به‌جای عدد و واحد پول نمایش داده می‌شود. برای نمایش خودِ صفر، خالی بگذارید.', 'bakery-widgets'),
            'separator' => 'before',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — چیدمان (جهت، تراز، فاصله‌ها و ترتیب نمایش هر عنصر)
     * =================================================================== */

    private function register_layout_controls(): void
    {
        $this->start_controls_section('section_layout', [
            'label' => __('چیدمان', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_responsive_control('price_direction', [
            'label' => __('جهت', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'row',
            'options' => $this->direction_options(),
            'selectors' => [
                '{{WRAPPER}} .bkw-price' => 'flex-direction: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('price_align', [
            'label' => __('تراز عمودی', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'default' => 'baseline',
            'options' => $this->align_options(),
            'selectors' => [
                '{{WRAPPER}} .bkw-price' => 'align-items: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('price_justify', [
            'label' => __('تراز کردن محتوا', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'label_block' => true,
            'options' => $this->justify_options(),
            'selectors' => [
                '{{WRAPPER}} .bkw-price' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('price_gap', [
            'label' => __('فاصله بین عناصر', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['size' => 8, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-price' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('price_wrap', [
            'label' => __('شکستن به سطر بعد (wrap)', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'options' => $this->wrap_options(),
            'default' => 'nowrap',
            'selectors' => [
                '{{WRAPPER}} .bkw-price' => 'flex-wrap: {{VALUE}};',
            ],
        ]);

        /*
         * جهت/تراز فقط چیدمان کلی را کنترل می‌کند؛ برای اینکه واقعاً بشود
         * ترتیب دلخواه ساخت (مثلاً واحد پول بین دو عدد، یا قیمت پیشین بعد
         * از واحد پول)، هر عنصر یک عدد «ترتیب نمایش» مستقل دارد که مستقیم
         * روی CSS order می‌نشیند — بدون نیاز به مارک‌آپ متفاوت.
         */
        $this->add_control('heading_order', [
            'label' => __('ترتیب نمایش عناصر', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'description' => __('عدد کوچک‌تر، زودتر (طبق جهت انتخابی) نمایش داده می‌شود.', 'bakery-widgets'),
        ]);

        $this->add_control('order_now', [
            'label' => __('قیمت فعلی', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 1,
            'selectors' => [
                '{{WRAPPER}} .bkw-price__now' => 'order: {{VALUE}};',
            ],
        ]);

        $this->add_control('order_old', [
            'label' => __('قیمت پیشین', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 2,
            'condition' => ['show_old' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-price__old' => 'order: {{VALUE}};',
            ],
        ]);

        $this->add_control('order_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 3,
            'condition' => ['show_currency' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-price__currency' => 'order: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — بخش‌های تکرارشونده (فعلی/پیشین/واحد پول)
     * =================================================================== */

    private function register_now_style_controls(): void
    {
        $this->start_controls_section('section_style_now', [
            'label' => __('قیمت فعلی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->register_price_part_style_controls('now', '{{WRAPPER}} .bkw-price__now');
        $this->end_controls_section();
    }

    private function register_old_style_controls(): void
    {
        $this->start_controls_section('section_style_old', [
            'label' => __('قیمت پیشین', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_old' => 'yes'],
        ]);

        $this->add_control('old_strike', [
            'label' => __('خط‌خورده', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'selectors' => [
                '{{WRAPPER}} .bkw-price__old' => 'text-decoration: line-through;',
            ],
        ]);

        $this->register_price_part_style_controls('old', '{{WRAPPER}} .bkw-price__old');
        $this->end_controls_section();
    }

    private function register_currency_style_controls(): void
    {
        $this->start_controls_section('section_style_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_currency' => 'yes'],
        ]);
        $this->register_price_part_style_controls('currency', '{{WRAPPER}} .bkw-price__currency');
        $this->end_controls_section();
    }

    /** کنترل‌های مشترک هر بخش: تایپوگرافی، رنگ، و کادر اختیاری (پس‌زمینه/حاشیه/رادیوس/پدینگ) */
    private function register_price_part_style_controls(string $prefix, string $selector): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => $prefix . '_typography',
            'selector' => $selector,
        ]);

        $this->add_control($prefix . '_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_' . $prefix . '_box', [
            'label' => __('کادر', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => $prefix . '_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => $selector,
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => $prefix . '_border',
            'label' => __('حاشیه', 'bakery-widgets'),
            'selector' => $selector,
        ]);

        $this->add_responsive_control($prefix . '_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control($prefix . '_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors' => [$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
    }

    /* ---------------------------------------------------------------------
     * گزینه‌های مشترک کنترل‌های چیدمان
     * ------------------------------------------------------------------- */

    private function direction_options(): array
    {
        $start = is_rtl() ? 'right' : 'left';
        $end = is_rtl() ? 'left' : 'right';

        return [
            'row' => [
                'title' => __('سطری', 'bakery-widgets'),
                'icon' => 'eicon-arrow-' . $end,
            ],
            'row-reverse' => [
                'title' => __('سطری معکوس', 'bakery-widgets'),
                'icon' => 'eicon-arrow-' . $start,
            ],
            'column' => [
                'title' => __('ستونی', 'bakery-widgets'),
                'icon' => 'eicon-arrow-down',
            ],
            'column-reverse' => [
                'title' => __('ستونی معکوس', 'bakery-widgets'),
                'icon' => 'eicon-arrow-up',
            ],
        ];
    }

    private function align_options(): array
    {
        return [
            'flex-start' => ['title' => __('ابتدا', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-start-v'],
            'center' => ['title' => __('وسط', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-center-v'],
            'flex-end' => ['title' => __('انتها', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-end-v'],
            'baseline' => ['title' => __('خط پایه متن', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-align-stretch-v'],
        ];
    }

    private function justify_options(): array
    {
        return [
            'flex-start' => ['title' => __('ابتدا', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-start-h'],
            'center' => ['title' => __('وسط', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-center-h'],
            'flex-end' => ['title' => __('انتها', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-end-h'],
            'space-between' => ['title' => __('فاصله بین', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-justify-space-between-h'],
        ];
    }

    private function wrap_options(): array
    {
        return [
            'nowrap' => ['title' => __('در یک خط بماند', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-nowrap'],
            'wrap' => ['title' => __('به چند خط تقسیم شود', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-wrap'],
        ];
    }

    /* =====================================================================
     * منطق محصول
     * =================================================================== */

    private function resolve_product(array $settings): WC_Product|false
    {
        if (!empty($settings['product_id'])) {
            $product = wc_get_product(absint($settings['product_id']));
            if ($product instanceof WC_Product) {
                return $product;
            }
        }

        global $product;
        if ($product instanceof WC_Product) {
            return $product;
        }

        $current = wc_get_product(get_the_ID());
        return $current instanceof WC_Product ? $current : false;
    }

    /**
     * استخراج داده قیمت. برای محصول متغیر فقط کمترین قیمت نمایش داده
     * می‌شود (بدون قیمت پیشین) — چون یک «قیمت پیشین واحد» برای چند گزینه
     * با قیمت‌های متفاوت معنای درستی ندارد.
     *
     * @return array{current:string,old:string,on_sale:bool,has_price:bool}
     */
    private function get_price_data(WC_Product|false $product): array
    {
        $data = ['current' => '', 'old' => '', 'on_sale' => false, 'has_price' => false];

        if (!$product instanceof WC_Product) {
            return $data;
        }

        if ($product->is_type('variable')) {
            $min = $product->get_variation_price('min', true);
            $data['current'] = ('' === $min || null === $min) ? '' : (string) $min;
            $data['has_price'] = '' !== $data['current'];
            return $data;
        }

        $regular = $product->get_regular_price();
        $sale = $product->get_sale_price();

        if ($product->is_on_sale() && '' !== $sale && null !== $sale) {
            $data['current'] = $this->display_price($product, $sale);
            $data['old'] = $this->display_price($product, $regular);
            $data['on_sale'] = '' !== $data['old'];
        } else {
            $data['current'] = $this->display_price($product, ('' === $regular || null === $regular) ? $product->get_price() : $regular);
        }

        $data['has_price'] = '' !== $data['current'];
        return $data;
    }

    /**
     * قیمت آماده نمایش با احتساب تنظیمات مالیاتی فروشگاه؛ مقدار خامِ
     * get_price/get_regular_price این را نمی‌داند.
     */
    private function display_price(WC_Product $product, $raw): string
    {
        if ('' === $raw || null === $raw) {
            return '';
        }

        if (!function_exists('wc_get_price_to_display')) {
            return (string) $raw;
        }

        return (string) wc_get_price_to_display($product, ['price' => $raw]);
    }

    private function format_amount(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0;
        $dec_sep = function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.';
        $thou_sep = function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',';

        return number_format((float) $value, $decimals, $dec_sep, $thou_sep);
    }

    private function currency_text(array $settings): string
    {
        $text = trim((string) ($settings['currency_text'] ?? ''));
        if ('' !== $text) {
            return $text;
        }

        $symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '';
        return html_entity_decode((string) $symbol, ENT_QUOTES, 'UTF-8');
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        if (!function_exists('wc_get_product')) {
            if (ElementorPlugin::$instance->editor->is_edit_mode()) {
                echo '<div class="bkw-price__notice">' . esc_html__('این ویجت به ووکامرس فعال نیاز دارد.', 'bakery-widgets') . '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        $product = $this->resolve_product($settings);
        $price = $this->get_price_data($product);

        if (!$price['has_price'] && ElementorPlugin::$instance->editor->is_edit_mode()) {
            // نمونه در ادیتور تا پنل استایل روی محتوای واقعی دیده شود
            $price = ['current' => '31500', 'old' => '35000', 'on_sale' => true, 'has_price' => true];
        }

        if (!$price['has_price']) {
            return;
        }

        $free_text = trim((string) ($settings['free_text'] ?? ''));
        $show_currency = 'yes' === ($settings['show_currency'] ?? 'yes');
        $old_on = $price['on_sale'] && 'yes' === ($settings['show_old'] ?? 'yes') && '' !== $price['old'];
        $is_free = 0.0 === (float) $price['current'] && '' !== $free_text;

        ?>
        <div class="bkw-price">
            <span class="bkw-price__now">
                <?php echo $is_free ? esc_html($free_text) : esc_html($this->format_amount($price['current'])); ?>
            </span>

            <?php if ($old_on) : ?>
                <del class="bkw-price__old"><?php echo esc_html($this->format_amount($price['old'])); ?></del>
            <?php endif; ?>

            <?php if ($show_currency && !$is_free) : ?>
                <span class="bkw-price__currency"><?php echo esc_html($this->currency_text($settings)); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}
