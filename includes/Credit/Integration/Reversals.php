<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Domain\EntryType;
use Bakery_Credit\Service\CreditAccount;
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
 * این کلاس هیچ چیزی را از خودِ سفارش حدس نمی‌زند.
 *
 * قبلاً می‌زد: «آیا با اعتبار پرداخت شده؟» را از payment_method
 * می‌خواند، مبلغ را از get_total()، مالک را از customer_id، و ماهِ
 * برگشت را از تاریخ ساخت سفارش. هر چهارتا داده‌های زندهٔ سفارش‌اند و
 * بعد از پرداخت هم قابل تغییرند — کافی بود ادمین در صفحهٔ سفارش روش
 * پرداخت را دست بزند تا لغو، اعتبار را بی‌صدا برنگرداند.
 *
 * حالا هر چهارتا از سطر کسرِ همان سفارش در دفتر خوانده می‌شوند
 * (Service\CreditAccount::reverseDebit). دفتر تنها جایی‌ست که ثبت کرده
 * واقعاً چه مبلغی از اعتبارِ چه کسی در کدام ماه کم شد، و برخلاف سفارش
 * هرگز بازنویسی نمی‌شود.
 *
 * یادداشت روی سفارش هم عمدی‌ست: برگشت اعتبار پولی‌ست و باید در همان
 * جایی دیده شود که ادمین لغو را انجام داده — همان قراردادی که
 * Integration\AdminOrders برای کسرِ ناموفق گذاشته.
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

        if (!$order instanceof WC_Order) {
            return;
        }

        if ($this->account->debitedForOrder($orderId) <= 0.0) {
            /*
             * سفارشی که با اعتبار پرداخت نشده اصلاً به این دفتر ربطی
             * ندارد و سکوت درست است. ولی اگر سفارش خودش می‌گوید با
             * اعتبار پرداخت شده و سطر کسری برایش نیست، چیزی سر جایش
             * نیست و باید دیده شود — وگرنه ادمین فکر می‌کند اعتبار
             * برگشته در حالی که اصلاً کسر نشده بوده.
             */
            if (Gateway::ID === $order->get_payment_method()) {
                $order->add_order_note(__('این سفارش با روش «اعتبار ماهانه» ثبت شده بود ولی سطر کسری برایش در دفتر اعتبار نیست؛ چیزی برای برگرداندن وجود نداشت.', 'bakery-widgets'));
            }

            return;
        }

        $returned = $this->account->reverseDebit($orderId, EntryType::Cancel);

        if ($returned > 0.0) {
            $order->add_order_note(sprintf(
                /* translators: %s: returned amount, formatted */
                __('مبلغ %s با لغو سفارش به اعتبار ماهانهٔ کاربر برگشت.', 'bakery-widgets'),
                wp_strip_all_tags(wc_price($returned))
            ));
        }
    }

    public function on_refunded(int $orderId, int $refundId): void
    {
        $order = wc_get_order($orderId);
        $refund = wc_get_order($refundId);

        if (!$order instanceof WC_Order || !$refund instanceof WC_Order_Refund) {
            return;
        }

        // مبلغ مرجوعی در ووکامرس منفی ذخیره می‌شود؛ قدرمطلقش را می‌خواهیم.
        $amount = abs((float) $refund->get_amount());

        $returned = $this->account->reverseDebit($orderId, EntryType::Refund, $amount, $refundId);

        if ($returned > 0.0) {
            $order->add_order_note(sprintf(
                /* translators: %s: returned amount, formatted */
                __('مبلغ %s بابت مرجوعی به اعتبار ماهانهٔ کاربر برگشت.', 'bakery-widgets'),
                wp_strip_all_tags(wc_price($returned))
            ));
        }
    }
}
