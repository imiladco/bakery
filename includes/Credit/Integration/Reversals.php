<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Domain\EntryType;
use Bakery_Credit\Service\CreditAccount;
use DateTimeImmutable;
use WC_Order;
use WC_Order_Refund;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * برگرداندن اعتبار هنگام لغو سفارش یا مرجوعی.
 *
 * دو مسیر جدا با دو فضای شمارهٔ مستقل: لغو به شناسهٔ خودِ سفارش ارجاع
 * می‌دهد و مرجوعی به شناسهٔ رکورد مرجوعی. اگر هر دو زیر یک نوع ثبت
 * می‌شدند، یک سفارش و یک مرجوعی با عدد یکسان به‌هم می‌خوردند و قید
 * یکتایی دومی را بی‌صدا رد می‌کرد (رجوع کن به Domain\EntryType).
 *
 * دوره همیشه از تاریخ سفارش اصلی گرفته می‌شود، نه از امروز، تا اعتبار
 * به همان ماهی برگردد که از آن کم شده بود.
 */
final class Reversals
{
    public function __construct(private readonly CreditAccount $account)
    {
    }

    public function register(): void
    {
        add_action('woocommerce_order_status_cancelled', [$this, 'on_cancelled']);
        add_action('woocommerce_order_refunded', [$this, 'on_refunded'], 10, 2);
    }

    public function on_cancelled(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (!$this->paid_with_credit($order)) {
            return;
        }

        $this->account->reverse(
            (int) $order->get_customer_id(),
            (float) $order->get_total(),
            $orderId,
            $this->order_date($order),
            EntryType::Cancel
        );
    }

    public function on_refunded(int $orderId, int $refundId): void
    {
        $order = wc_get_order($orderId);
        $refund = wc_get_order($refundId);

        if (!$this->paid_with_credit($order) || !$refund instanceof WC_Order_Refund) {
            return;
        }

        // مبلغ مرجوعی در ووکامرس منفی ذخیره می‌شود؛ قدرمطلقش را می‌خواهیم.
        $amount = abs((float) $refund->get_amount());

        $this->account->reverse(
            (int) $order->get_customer_id(),
            $amount,
            $refundId,
            $this->order_date($order),
            EntryType::Refund
        );
    }

    /** سفارش‌هایی که با روش دیگری پرداخت شده‌اند اصلاً به این دفتر ربطی ندارند. */
    private function paid_with_credit(mixed $order): bool
    {
        return $order instanceof WC_Order
            && Gateway::ID === $order->get_payment_method()
            && $order->get_customer_id() > 0;
    }

    private function order_date(WC_Order $order): DateTimeImmutable
    {
        $created = $order->get_date_created();

        if (!$created) {
            return new DateTimeImmutable('now', wp_timezone());
        }

        // setTimezone و نه پارامتر سازنده: با فرمت '@timestamp' آرگومان
        // منطقهٔ زمانی بی‌صدا نادیده گرفته می‌شود و نتیجه UTC می‌ماند —
        // که نزدیک مرز ماه، برگشت اعتبار را به ماه اشتباه می‌برد.
        return (new DateTimeImmutable('@' . $created->getTimestamp()))->setTimezone(wp_timezone());
    }
}
