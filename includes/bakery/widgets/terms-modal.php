<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Widgets\Traits\Terms_Modal_Controls;
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
 * این ویجت به‌عنوان یک عنصر مستقل روی هر صفحه‌ای که ادمین آن را بگذارد
 * از ابتدا خودکار باز می‌شود (auto_show=true) و فقط قفل همان صفحه را
 * باز می‌کند — جایی هدایت نمی‌کند. تمام کنترل‌ها و منطق رندر مشترک با
 * حالت تعبیه‌شدهٔ همین مودال داخل ویجت Login (بعد از تأیید کد OTP) در
 * Traits\Terms_Modal_Controls تعریف شده تا این حجم کنترل بین دو زمینه
 * تکرار نشود — رجوع کن به مستندات آن فایل.
 */
final class Terms_Modal extends Widget_Base
{
    use Terms_Modal_Controls;

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

    #[\Override]
    protected function register_controls(): void
    {
        $this->register_terms_modal_content_controls();
        $this->register_terms_modal_style_controls();
    }

    #[\Override]
    protected function render(): void
    {
        $this->render_terms_modal($this->get_settings_for_display(), null, true);
    }
}
