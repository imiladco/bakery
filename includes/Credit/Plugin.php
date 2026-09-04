<?php

declare(strict_types=1);

namespace Bakery_Credit;

use Bakery_Credit\Admin\UserField;
use Bakery_Credit\Integration\AdminOrders;
use Bakery_Credit\Integration\BalanceFilter;
use Bakery_Credit\Integration\CheckoutGuard;
use Bakery_Credit\Integration\DirectCheckout;
use Bakery_Credit\Integration\Gateway;
use Bakery_Credit\Integration\PurchaseLimit;
use Bakery_Credit\Integration\Registration;
use Bakery_Credit\Integration\Reversals;
use Bakery_Credit\Integration\SheetColumn;
use Bakery_Credit\Service\CreditAccount;
use Bakery_Credit\Storage\Allowance;
use Bakery_Credit\Storage\Ledger;
use Bakery_Credit\Storage\Schema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ریشهٔ ترکیب سیستم اعتبار — تنها جایی که اجزا به هم وصل می‌شوند.
 *
 * همهٔ اتصال‌ها به بیرون از راه فیلترند و هیچ‌کدام از ویجت‌های موجود از
 * وجود این ماژول خبر ندارند: `bkw_account_balance` عدد موجودی سه ویجت
 * نمایشی را زنده می‌کند و `bkw_max_purchase_quantity` سقف دکمهٔ + را.
 * اگر این ماژول اصلاً بارگذاری نشود، آن ویجت‌ها به رفتار قبلی‌شان
 * برمی‌گردند.
 */
final class Plugin
{
    private static ?self $instance = null;

    private readonly CreditAccount $account;
    private readonly Allowance $allowances;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->allowances = new Allowance();
        $this->account = new CreditAccount($this->allowances, new Ledger());
    }

    public function account(): CreditAccount
    {
        return $this->account;
    }

    public function init(): void
    {
        // جدول عمداً به قلاب فعال‌سازی وابسته نیست: افزونه ممکن است با
        // آپلود فایل به‌روزرسانی شود و آن قلاب هرگز اجرا نشود.
        add_action('init', [Schema::class, 'maybeInstall'], 5);

        (new Registration())->register();
        (new BalanceFilter($this->account))->register();
        (new PurchaseLimit($this->account))->register();
        (new CheckoutGuard($this->account))->register();
        (new Reversals($this->account))->register();

        // ستون «سقف اعتبار» در فایل ورودی/خروجی کاربران. عمداً بیرون از
        // شرط is_admin نیست چون هر دو سرِ آن (صفحهٔ اکسل و این ستون) فقط
        // در پیشخوان اجرا می‌شوند — ولی فیلترش باید پیش از رندر آن صفحه
        // ثبت شده باشد، و ثبتش هزینه‌ای ندارد.
        (new SheetColumn($this->allowances))->register();

        // مسیر اصلی پرداخت: یک کلیک از داخل سایدبار سبد، بدون صفحهٔ
        // تسویه‌حساب. عمداً بیرون از شرط is_admin ثبت می‌شود — اکشن روی
        // admin-ajax می‌نشیند ولی کاربرِ فرانت آن را صدا می‌زند.
        (new DirectCheckout($this->account))->register();

        add_filter('woocommerce_payment_gateways', [$this, 'register_gateway']);

        if (is_admin()) {
            (new UserField($this->allowances, $this->account))->register();
            (new AdminOrders($this->account))->register();
        }
    }

    /**
     * درگاه به‌صورت نمونهٔ ساخته‌شده اضافه می‌شود، نه نام کلاس.
     *
     * ووکامرس هر دو را می‌پذیرد، ولی نام کلاس یعنی خودش با سازندهٔ بدون
     * آرگومان می‌سازدش — و این درگاه سرویس اعتبار را از بیرون می‌گیرد تا
     * وابستگی‌اش صریح بماند و همان یک نمونهٔ مشترک استفاده شود.
     *
     * @param array<int, mixed> $gateways
     * @return array<int, mixed>
     */
    public function register_gateway(array $gateways): array
    {
        $gateways[] = new Gateway($this->account);

        return $gateways;
    }
}
