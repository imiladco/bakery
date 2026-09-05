<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Widgets\Traits\Account_Actions_Controls;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «نوار حساب کاربری» — سه پیل مستقل: سبد خرید (+ بج تعداد)، کاربر
 * (نام + موجودی)، خروج. هر سه از یک ورودی سایت با ورود اجباری استفاده
 * می‌کنند (بدون حالت مهمان)، پس همیشه کاربر واقعی لاگین فرض می‌شود.
 *
 * هر پیل با یک سوییچر مستقل قابل حذف است تا بشود «تک‌مورد» یا هر
 * ترکیبی نمایش داد؛ جایگاه نهایی‌شان هم با کنترل عددی «ترتیب نمایش»
 * (CSS order) مستقل تعیین می‌شود، دقیقاً مثل ویجت قیمت — نه با ترتیب
 * DOM. مقدار پیش‌فرض این سه عدد طوری است که خروجی با رفرنس فیگما یکی
 * شود: خروج ۱ (سمت راست/ابتدای RTL)، کاربر ۲، سبد ۳ (سمت چپ/انتها).
 *
 * موجودی از فیلتر `bkw_account_balance` می‌آید — با فعال بودن ماژول
 * اعتبار ماهانه (Bakery_Credit) عدد واقعی، وگرنه مقدار فرضی کنترل
 * می‌آید که با یک عدد فرضی (تنظیم‌پذیر از تب محتوا) پیش‌فرض می‌خورد؛
 * وقتی منبع واقعی مشخص شد، فقط باید به این فیلتر هوک زد، چیزی در این
 * ویجت عوض نمی‌شود. کلیک روی سبد هم عمداً بدون مقصد است (هنوز تصمیم
 * گرفته نشده چه اتفاقی بیفتد) — اگر «لینک سبد خرید» خالی بماند، پیل
 * به‌جای <a> یک <div> غیرقابل‌کلیک است.
 *
 * تمام کنترل‌ها و منطق رندر این سه پیل در Traits\Account_Actions_Controls
 * تعریف شده‌اند — همان تریت را ویجت Header هم برای بخش اکشن‌های خودش
 * استفاده می‌کند (رجوع کن به مستندات آن فایل)، تا این حجم کنترل بین دو
 * ویجت تکرار نشود.
 */
final class Account_Bar extends Widget_Base
{
    use Account_Actions_Controls;

    #[\Override]
    public function get_name(): string
    {
        return 'bakery-account-bar';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('نوار حساب کاربری بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-user-circle-o';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['حساب کاربری', 'سبد خرید', 'خروج', 'موجودی', 'account', 'cart', 'logout', 'profile', 'wallet', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    protected function register_controls(): void
    {
        $this->register_account_actions_content_controls();
        $this->register_account_actions_style_controls();
    }

    #[\Override]
    protected function render(): void
    {
        $this->render_account_actions($this->get_settings_for_display());
    }
}
