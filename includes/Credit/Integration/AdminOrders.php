<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Service\CreditAccount;
use WC_Order;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سفارشی که ادمین از پنل مدیریت وردپرس دستی می‌سازد یا ویرایش می‌کند
 * هرگز از Gateway::process_payment() رد نمی‌شود — آن متد فقط در چک‌اوت
 * واقعی جلوی مشتری صدا زده می‌شود؛ صفحهٔ سفارش در wp-admin صرفاً وضعیت
 * و روش پرداخت را به‌عنوان متادیتا ذخیره می‌کند. این کلاس همان کسر را
 * برای همین مسیر هم انجام می‌دهد، تا سفارشی که ادمین با روش پرداخت
 * «اعتبار ماهانه» تکمیل می‌کند همیشه واقعاً از اعتبار همان کاربر کم
 * شود — درست مثل چک‌اوت واقعی.
 *
 * ایمن در برابر اجرای دوباره: اگر همین سفارش قبلاً (مثلاً از چک‌اوت
 * واقعی) کسر شده باشد، Storage\Ledger::tryDebit() آن را با قید یکتایی
 * idempotent می‌بیند و کاری نمی‌کند — پس این قلاب با Gateway بی‌خطر
 * هم‌زیستی دارد و به هیچ‌کدام نمی‌گوید کدام یکی «مالک» کسر است.
 *
 * اگر اعتبار کافی نباشد، سفارش را بلاک نمی‌کند — چون تا این نقطه ادمین
 * از قبل وضعیتش را در پنل «تکمیل/در حال انجام» کرده و برگرداندنش دخالت
 * پیچیده‌ای می‌خواهد. به‌جایش یک یادداشت صریح روی سفارش می‌گذارد تا این
 * خرید بدون اعتبار کافی، آگاهانه و قابل‌پیگیری بماند، نه اینکه بی‌صدا و
 * گم‌شده جا بماند.
 */
final class AdminOrders
{
    private const PAID_STATUSES = ['processing', 'completed'];

    public function __construct(private readonly CreditAccount $account)
    {
    }

    public function register(): void
    {
        add_action('woocommerce_order_status_changed', [$this, 'maybe_debit'], 10, 4);
    }

    public function maybe_debit(int $orderId, string $from, string $to, WC_Order $order): void
    {
        if (!in_array($to, self::PAID_STATUSES, true) || Gateway::ID !== $order->get_payment_method()) {
            return;
        }

        $userId = (int) $order->get_customer_id();
        if ($userId <= 0) {
            return;
        }

        $unlimited = CreditExemption::forUser($userId);
        $debited = $this->account->debit($userId, (float) $order->get_total(), $orderId, Clock::now(), $unlimited);

        if (!$debited) {
            $order->add_order_note(__('این سفارش با روش «اعتبار ماهانه» تکمیل شد ولی اعتبار کاربر برایش کافی نبود؛ اعتباری کسر نشد.', 'bakery-widgets'));
        }
    }
}
