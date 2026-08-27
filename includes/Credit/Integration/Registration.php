<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبت‌نام بسته است — کاربران را فقط مدیر تعریف می‌کند.
 *
 * هر سه در ووکامرس جدا از هم‌اند و بستن یکی بقیه را نمی‌بندد: فرم ثبت‌نام
 * صفحهٔ «حساب کاربری من»، ساختن حساب حین تسویه، و گزینهٔ عمومی وردپرس.
 */
final class Registration
{
    public function register(): void
    {
        add_filter('option_users_can_register', '__return_zero', 99);
        add_filter('woocommerce_enable_myaccount_registration', '__return_false', 99);
        add_filter('woocommerce_enable_signup_and_login_from_checkout', '__return_false', 99);
    }
}
