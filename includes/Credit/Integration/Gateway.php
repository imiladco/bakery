<?php

declare(strict_types=1);

namespace Bakery_Credit\Integration;

use Bakery_Credit\Service\CreditAccount;
use WC_Order;
use WC_Payment_Gateway;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * درگاه پرداخت «اعتبار ماهانه».
 *
 * چرا یک WC_Payment_Gateway واقعی و نه هک‌کردن چک‌اوت: کل چرخهٔ عمر سفارش
 * در ووکامرس — وضعیت‌ها، ایمیل‌ها، مرجوعی، گزارش‌ها، و صفحهٔ تنظیمات
 * پرداخت‌ها — روی وجود یک درگاه بنا شده. هر میان‌بری این‌جا یعنی نیم‌کاره
 * ماندن همهٔ آن‌ها.
 */
final class Gateway extends WC_Payment_Gateway
{
    public const ID = 'bkw_credit';

    public function __construct(private readonly CreditAccount $account)
    {
        $this->id = self::ID;
        $this->method_title = __('اعتبار ماهانه', 'bakery-widgets');
        $this->method_description = __('خرید از محل اعتبار ماهانهٔ کاربر. سقف اعتبار هر کاربر را مدیر تعیین می‌کند و اول هر ماه شمسی از نو برقرار می‌شود.', 'bakery-widgets');
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        // بدون این خط، کلید «فعال‌سازی» در پنل پرداخت‌های ووکامرس ذخیره
        // می‌شد ولی هیچ اثری نداشت: WC_Payment_Gateway::$enabled مقدار
        // پیش‌فرض 'yes' دارد و is_available() همان را می‌خواند، نه
        // تنظیمات ذخیره‌شده را.
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->title = $this->get_option('title', __('پرداخت با اعتبار', 'bakery-widgets'));
        $this->description = $this->get_option('description', '');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    #[\Override]
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('فعال‌سازی', 'bakery-widgets'),
                'type' => 'checkbox',
                'label' => __('پرداخت با اعتبار ماهانه فعال باشد', 'bakery-widgets'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('عنوان', 'bakery-widgets'),
                'type' => 'text',
                'default' => __('پرداخت با اعتبار', 'bakery-widgets'),
                'desc_tip' => true,
                'description' => __('عنوانی که کاربر در صفحهٔ پرداخت می‌بیند.', 'bakery-widgets'),
            ],
            'description' => [
                'title' => __('توضیح', 'bakery-widgets'),
                'type' => 'textarea',
                'default' => '',
            ],
        ];
    }

    /** مهمان اعتبار ندارد، پس درگاه هم برایش معنا ندارد. */
    #[\Override]
    public function is_available(): bool
    {
        return parent::is_available() && is_user_logged_in();
    }

    /**
     * ترتیب عملیات این‌جا حیاتی است: اول کسر، بعد تکمیل سفارش.
     *
     * اگر برعکس بود و کسر شکست می‌خورد (یا وسطش خطایی رخ می‌داد)، سفارشی
     * تکمیل‌شده باقی می‌ماند که اعتباری بابتش کم نشده — یعنی جنس رفته و
     * سقف دست‌نخورده. با این ترتیب، آن حالت اصلاً ممکن نیست.
     *
     * سنجش «آیا اعتبار کافی است» عمداً این‌جا انجام نمی‌شود؛ داخل همان
     * ناحیهٔ قفل‌شدهٔ Storage\Ledger است. اگر این‌جا چک می‌کردیم، دو
     * چک‌اوت هم‌زمان هر دو رد می‌شدند و بیش از سقف خرج می‌شد.
     */
    #[\Override]
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            return $this->fail(__('سفارش یافت نشد.', 'bakery-widgets'));
        }

        $userId = (int) $order->get_customer_id();

        if ($userId <= 0) {
            return $this->fail(__('برای پرداخت با اعتبار باید وارد حساب کاربری شوید.', 'bakery-widgets'));
        }

        $total = (float) $order->get_total();
        $unlimited = CreditExemption::forUser($userId);

        if (!$this->account->debit($userId, $total, (int) $order->get_id(), Clock::now(), $unlimited)) {
            return $this->fail(sprintf(
                /* translators: %s: remaining credit, formatted */
                __('اعتبار شما برای این سفارش کافی نیست. باقی‌مانده: %s', 'bakery-widgets'),
                wp_strip_all_tags(wc_price($this->account->remaining($userId, Clock::now())))
            ));
        }

        $order->payment_complete();
        $order->add_order_note(sprintf(
            /* translators: %s: debited amount, formatted */
            __('مبلغ %s از اعتبار ماهانهٔ کاربر کسر شد.', 'bakery-widgets'),
            wp_strip_all_tags(wc_price($total))
        ));

        WC()->cart->empty_cart();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    /** @return array{result: string} */
    private function fail(string $message): array
    {
        wc_add_notice($message, 'error');

        return ['result' => 'failure'];
    }
}
