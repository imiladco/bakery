<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Cart_Ajax;
use Bakery_Widgets\Purchase_Limit;
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
 * ویجت «افزودن به سبد» — فقط محصول ساده (Simple Product) پشتیبانی می‌شود.
 *
 * سه حالت که با AJAX (بدون رفرش صفحه) بین‌شان جابه‌جا می‌شود:
 *   ۱) پیش‌فرض: دکمهٔ «افزودن به سبد» + آیکون پلاس.
 *   ۲) بعد از افزودن: کنترل تعداد (+ / عدد / −)، تا سقفِ موجودی فعلی
 *      محصول؛ به محض رسیدن به سقف، دکمهٔ «+» غیرفعال می‌شود.
 *   ۳) ناموجود: کل دکمه با متن «ناموجود» و غیرفعال — نه دکمهٔ افزودن، نه
 *      کنترل تعداد.
 *
 * هر دو حالت تعاملی، یک لایهٔ بلورِ سفیدِ کم‌رنگ روی خودشان دارند که فقط
 * حین درخواست AJAX نمایان است (mask «در حال انجام عملیات»)، تا کاربر قبل
 * از رسیدن پاسخ دوباره کلیک نکند.
 */
final class Add_To_Cart extends Widget_Base
{
    #[\Override]
    public function get_name(): string
    {
        return 'bakery-add-to-cart';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('افزودن به سبد بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-product-add-to-cart';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['سبد خرید', 'افزودن', 'خرید', 'cart', 'add to cart', 'woocommerce', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    public function get_script_depends(): array
    {
        return ['bakery-add-to-cart'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        // تب محتوا
        $this->register_product_controls();
        $this->register_text_controls();

        // تب استایل
        $this->register_button_style_controls();
        $this->register_quantity_style_controls();
        $this->register_out_of_stock_style_controls();
        $this->register_loading_style_controls();
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
     * محتوا — متن و آیکون
     * =================================================================== */

    private function register_text_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('متن و آیکون', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('add_text', [
            'label' => __('متن دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('افزودن به سبد', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('show_icon', [
            'label' => __('نمایش آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('heading_oos', [
            'label' => __('ناموجود', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('out_of_stock_text', [
            'label' => __('متن ناموجود', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ناموجود', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — دکمهٔ افزودن
     * =================================================================== */

    private function register_button_style_controls(): void
    {
        $this->start_controls_section('section_style_button', [
            'label' => __('دکمهٔ افزودن', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'button_typography',
            'selector' => '{{WRAPPER}} .bkw-atc__btn--add .bkw-atc__label',
        ]);

        $this->add_control('button_color', [
            'label' => __('رنگ متن و آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--add' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('icon_size', [
            'label' => __('سایز آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 40]],
            'default' => ['size' => 14, 'unit' => 'px'],
            'condition' => ['show_icon' => 'yes'],
            'selectors' => ['{{WRAPPER}} .bkw-atc' => '--bkw-atc-icon-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('button_gap', [
            'label' => __('فاصله آیکون تا متن', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['size' => 6, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--add' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('button_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default' => ['top' => '8', 'right' => '14', 'bottom' => '8', 'left' => '14', 'unit' => 'px', 'isLinked' => false],
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--add' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('button_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '100', 'right' => '100', 'bottom' => '100', 'left' => '100', 'unit' => 'px', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--add' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'button_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-atc__btn--add',
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'button_border',
            'label' => __('حاشیه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-atc__btn--add',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — کنترل تعداد
     * =================================================================== */

    private function register_quantity_style_controls(): void
    {
        $this->start_controls_section('section_style_quantity', [
            'label' => __('کنترل تعداد', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('heading_qty_box', [
            'label' => __('محفظه', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'qty_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-atc__qty',
        ]);

        $this->add_responsive_control('qty_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '100', 'right' => '100', 'bottom' => '100', 'left' => '100', 'unit' => 'px', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-atc__qty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('qty_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default' => ['top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-atc__qty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('qty_gap', [
            'label' => __('فاصله بین عناصر', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['size' => 12, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .bkw-atc__qty' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('heading_qty_steps', [
            'label' => __('دکمه‌های + / −', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('step_size', [
            'label' => __('سایز دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 16, 'max' => 60]],
            'default' => ['size' => 28, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .bkw-atc' => '--bkw-atc-step-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('step_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'unit' => '%', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-atc__step' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'step_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-atc__step',
        ]);

        $this->add_control('step_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__step' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_step_hover', [
            'label' => __('حالت هاور', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step_hover_background', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(255, 255, 255, 0.4)',
            'selectors' => ['{{WRAPPER}} .bkw-atc__step:not(:disabled):hover' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('step_hover_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__step:not(:disabled):hover' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_step_disabled', [
            'label' => __('حالت غیرفعال (رسیدن به سقف موجودی)', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step_disabled_background', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__step:disabled' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('step_disabled_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__step:disabled' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_qty_number', [
            'label' => __('عدد تعداد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'qty_typography',
            'selector' => '{{WRAPPER}} .bkw-atc__count',
        ]);

        $this->add_control('qty_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__count' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — ناموجود
     * =================================================================== */

    private function register_out_of_stock_style_controls(): void
    {
        $this->start_controls_section('section_style_oos', [
            'label' => __('ناموجود', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'oos_typography',
            'selector' => '{{WRAPPER}} .bkw-atc__btn--oos .bkw-atc__label',
        ]);

        $this->add_control('oos_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--oos' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('oos_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default' => ['top' => '8', 'right' => '14', 'bottom' => '8', 'left' => '14', 'unit' => 'px', 'isLinked' => false],
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--oos' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('oos_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '100', 'right' => '100', 'bottom' => '100', 'left' => '100', 'unit' => 'px', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-atc__btn--oos' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'oos_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-atc__btn--oos',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — لایهٔ «در حال انجام»
     * =================================================================== */

    private function register_loading_style_controls(): void
    {
        $this->start_controls_section('section_style_loading', [
            'label' => __('لایهٔ «در حال انجام»', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'description' => __('حین ارسال درخواست به سبد خرید (افزودن/افزایش/کاهش)، این لایه روی دکمه نمایان می‌شود تا کاربر بداند عملیات در حال انجام است.', 'bakery-widgets'),
        ]);

        $this->add_control('loading_background', [
            'label' => __('رنگ لایه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(255, 255, 255, 0.45)',
            'selectors' => ['{{WRAPPER}} .bkw-atc' => '--bkw-atc-loading-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('loading_blur', [
            'label' => __('میزان بلور', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 20]],
            'default' => ['size' => 4, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .bkw-atc' => '--bkw-atc-loading-blur: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('loading_duration', [
            'label' => __('مدت زمان محو شدن (میلی‌ثانیه)', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0, 'max' => 1000]],
            'default' => ['size' => 180],
            'selectors' => ['{{WRAPPER}} .bkw-atc' => '--bkw-atc-loading-duration: {{SIZE}}ms;'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * منطق محصول — همان قاعدهٔ ویجت قیمت («خالی = محصول جاری»)
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

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        $is_editor = ElementorPlugin::$instance->editor->is_edit_mode();

        if (!function_exists('wc_get_product')) {
            if ($is_editor) {
                echo '<div class="bkw-atc__notice">' . esc_html__('این ویجت به ووکامرس فعال نیاز دارد.', 'bakery-widgets') . '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        $product = $this->resolve_product($settings);

        if (!$product instanceof WC_Product || !$product->is_type('simple')) {
            if ($is_editor) {
                echo '<div class="bkw-atc__notice">' . esc_html__('این ویجت فقط از محصول ساده (Simple Product) پشتیبانی می‌کند.', 'bakery-widgets') . '</div>';
            }
            return;
        }

        $in_stock = $product->is_purchasable() && $product->is_in_stock();
        $max = Purchase_Limit::for_product($product); // -1 = نامحدود
        $qty = $in_stock ? $this->cart_quantity($product->get_id()) : 0;

        $add_text = trim((string) ($settings['add_text'] ?? '')) ?: __('افزودن به سبد', 'bakery-widgets');
        $oos_text = trim((string) ($settings['out_of_stock_text'] ?? '')) ?: __('ناموجود', 'bakery-widgets');
        $show_icon = 'yes' === ($settings['show_icon'] ?? 'yes');

        $this->add_render_attribute('root', 'class', 'bkw-atc');
        $this->add_render_attribute('root', 'data-product-id', (string) $product->get_id());
        $this->add_render_attribute('root', 'data-nonce', wp_create_nonce(Cart_Ajax::NONCE_ACTION));
        $this->add_render_attribute('root', 'data-max', (string) $max);
        $this->add_render_attribute('root', 'data-qty', (string) $qty);
        $this->add_render_attribute('root', 'data-state', match (true) {
            !$in_stock => 'oos',
            $qty > 0 => 'qty',
            default => 'add',
        });

        ?>
        <div <?php $this->print_render_attribute_string('root'); ?>>
            <?php if (!$in_stock) : ?>
                <button type="button" class="bkw-atc__btn bkw-atc__btn--oos" disabled>
                    <span class="bkw-atc__label"><?php echo esc_html($oos_text); ?></span>
                </button>
            <?php else : ?>
                <button type="button" class="bkw-atc__btn bkw-atc__btn--add">
                    <?php if ($show_icon) : ?>
                        <span class="bkw-atc__icon" aria-hidden="true"><?php $this->render_plus_icon(); ?></span>
                    <?php endif; ?>
                    <span class="bkw-atc__label"><?php echo esc_html($add_text); ?></span>
                    <span class="bkw-atc__overlay" aria-hidden="true"></span>
                </button>

                <div class="bkw-atc__qty">
                    <button type="button" class="bkw-atc__step bkw-atc__step--plus" aria-label="<?php esc_attr_e('افزایش تعداد', 'bakery-widgets'); ?>">+</button>
                    <span class="bkw-atc__count"><?php echo esc_html((string) $qty); ?></span>
                    <button type="button" class="bkw-atc__step bkw-atc__step--minus" aria-label="<?php esc_attr_e('کاهش تعداد', 'bakery-widgets'); ?>">&minus;</button>
                    <span class="bkw-atc__overlay" aria-hidden="true"></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** تعداد فعلی این محصول (بدون تنوع) در سبد جاری — صفر اگر سبد در دسترس نباشد یا محصول در آن نباشد */
    private function cart_quantity(int $product_id): int
    {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if ((int) $item['product_id'] === $product_id && empty($item['variation_id'])) {
                return (int) $item['quantity'];
            }
        }

        return 0;
    }

    /** آیکون پلاس ثابت طبق طرح — همیشه inline، رنگش با رنگ متن دکمه (currentColor) هماهنگ می‌ماند */
    private function render_plus_icon(): void
    {
        ?>
        <svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M4.25 7H12.42M8.33 2.92V11.08" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <?php
    }
}
