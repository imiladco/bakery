<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Service\CreditAccount;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * اعتبار را تنها راه خرید می‌کند و پیش از رسیدن به پرداخت هشدار می‌دهد.
 *
 * این لایهٔ اول دفاع است و عمداً قطعی نیست: بین دیدن پیام در سبد و
 * فشردن دکمهٔ پرداخت، ادمین می‌تواند سقف را عوض کند یا کاربر از یک تب
 * دیگر خرید کند. تصمیم قطعی همیشه کسر اتمیک داخل درگاه است. نقش این‌جا
 * فقط این است که کاربر خطا را زودتر و با زبان روشن‌تر ببیند، نه ته
 * چک‌اوت.
 */
final class CheckoutGuard
{
    public function __construct(private readonly CreditAccount $account)
    {
    }

    public function register(): void
    {
        add_filter('woocommerce_available_payment_gateways', [$this, 'only_credit']);
        add_action('woocommerce_check_cart_items', [$this, 'check_cart_affordable']);
        add_filter('woocommerce_enable_guest_checkout', '__return_false', 99);
    }

    /**
     * بقیهٔ درگاه‌ها کنار گذاشته می‌شوند، طبق تصمیم «تنها روش خرید،
     * اعتبار است». در پنل مدیریت دست‌نخورده می‌ماند تا ادمین بتواند
     * تنظیمات درگاه‌ها را ببیند.
     *
     * وقتی درگاه اعتبار در دسترس نیست (مثلاً بازدیدکننده واقعاً لاگین
     * نیست) خروجی عمداً خالی می‌ماند و بقیهٔ درگاه‌ها برنمی‌گردند: قبلاً
     * در همین حالت کل فهرست دست‌نخورده پس داده می‌شد، یعنی درست در
     * لحظه‌ای که قاعده باید سفت‌ترین باشد، هر درگاه دیگری که روی سایت
     * فعال بود دوباره ظاهر می‌شد.
     *
     * @param array<string, mixed> $gateways
     * @return array<string, mixed>
     */
    public function only_credit(array $gateways): array
    {
        if (is_admin() && !wp_doing_ajax()) {
            return $gateways;
        }

        return isset($gateways[Gateway::ID]) ? [Gateway::ID => $gateways[Gateway::ID]] : [];
    }

    public function check_cart_affordable(): void
    {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $userId = get_current_user_id();

        if ($userId <= 0) {
            wc_add_notice(
                __('برای ثبت سفارش باید وارد حساب کاربری خود شوید.', 'bakery-widgets'),
                'error'
            );

            return;
        }

        // مدیر معاف است — برای اینکه سفارش آزمایشی روی سایتِ زنده ممکن
        // بماند، بدون اینکه سقف خودش را دستکاری کند. رجوع کن به
        // CreditExemption.
        if (CreditExemption::forUser($userId)) {
            return;
        }

        $total = (float) WC()->cart->get_total('edit');
        $remaining = $this->account->remaining($userId, Clock::now());

        if (round($total, 4) > round($remaining, 4)) {
            wc_add_notice(
                sprintf(
                    /* translators: 1: cart total, 2: remaining credit */
                    __('جمع سبد شما %1$s است ولی اعتبار باقی‌مانده‌تان %2$s — لطفاً تعداد را کم کنید.', 'bakery-widgets'),
                    wp_strip_all_tags(wc_price($total)),
                    wp_strip_all_tags(wc_price($remaining))
                ),
                'error'
            );
        }
    }
}
