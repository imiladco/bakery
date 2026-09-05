<?php

declare(strict_types=1);

namespace Bakery_Credit\Admin;

use Bakery_Credit\Report\Workbook;
use Bakery_Credit\Service\PeriodReport;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ «کاربران ← گزارش اعتبار ماهانه».
 *
 * سؤالی که این گزارش جواب می‌دهد: «در فلان ماه، هر کاربر چقدر از
 * اعتبارش را خرج کرد؟» — و «فلان ماه» می‌تواند ماهی باشد که گذشته.
 *
 * ستون‌های هویت از این‌جا نمی‌آیند. با فیلتر
 * `bkw_credit_report_identity` پرسیده می‌شوند و ماژول ویجت‌ها جوابشان
 * را می‌دهد، دقیقاً برعکسِ `bkw_user_sheet_columns` که آن‌جا این ماژول
 * ستون سقف اعتبار را به فایل کاربران اضافه می‌کند. هیچ‌کدام نام کلاس
 * آن‌یکی را نمی‌برد.
 *
 * ساختِ خودِ فایل هم این‌جا نیست و در Report\Workbook است، تا اگر روزی
 * همین گزارش از راه دیگری خواسته شد — کرون ماهانه، ایمیل به مالی، یک
 * دستور WP-CLI — بازچینش لازم نداشته باشد.
 */
final class ReportPage
{
    public const SLUG = 'bkw-credit-report';
    private const CAPABILITY = 'list_users';
    private const DOWNLOAD = 'bkw_credit_report_download';

    public function __construct(private readonly PeriodReport $report)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::DOWNLOAD, [$this, 'handle_download']);
    }

    public function register_menu(): void
    {
        add_users_page(
            __('گزارش اعتبار ماهانه', 'bakery-widgets'),
            __('گزارش اعتبار ماهانه', 'bakery-widgets'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('اجازهٔ دسترسی به این صفحه را ندارید.', 'bakery-widgets'));
        }

        $periods = $this->report->periods(Clock::now());
        $chosen = $this->chosen_period($periods);

        echo '<div class="wrap"><h1>' . esc_html__('گزارش اعتبار ماهانه', 'bakery-widgets') . '</h1>';
        ?>
        <div class="card" style="max-width:820px">
            <h2><?php esc_html_e('انتخاب ماه', 'bakery-widgets'); ?></h2>
            <p>
                <?php esc_html_e('گزارش بر اساس ماهی که هر سفارش در آن ثبت شده ساخته می‌شود، نه تاریخ گرفتن گزارش. پس گزارش شهریور را هر وقت بگیرید فقط شهریور است — خریدهای مهر در آن نمی‌آیند، حتی اگر گزارش را بعد از آن‌ها بگیرید.', 'bakery-widgets'); ?>
            </p>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <select name="period">
                    <?php foreach ($periods as $period) : ?>
                        <option value="<?php echo esc_attr($period); ?>" <?php selected($period, $chosen); ?>>
                            <?php echo esc_html(Workbook::periodLabel($period)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e('نمایش', 'bakery-widgets'); ?></button>
                <a class="button button-primary" href="<?php echo esc_url($this->download_url($chosen)); ?>">
                    <?php esc_html_e('دریافت فایل اکسل', 'bakery-widgets'); ?>
                </a>
            </form>
        </div>
        <?php
        echo '</div>';
    }

    public function handle_download(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('اجازهٔ انجام این کار را ندارید.', 'bakery-widgets'), '', ['response' => 403]);
        }

        check_admin_referer(self::DOWNLOAD);

        $period = $this->chosen_period($this->report->periods(Clock::now()));

        // متای همهٔ کاربران گزارش را یک‌جا گرم می‌کند. بدون این، هر ستون
        // هویتی برای هر کاربر یک کوئری جدا می‌شد — روی دویست کاربر و
        // پنج ستون یعنی هزار کوئری برای گزارشی که باید یکی دو تا باشد.
        $userIds = $this->report->userIds($period);

        if ([] !== $userIds) {
            cache_users($userIds);
        }

        $workbook = new Workbook($this->report, (array) apply_filters('bkw_credit_report_identity', []));

        try {
            if (Writer::canWriteXlsx()) {
                $this->send(
                    $workbook->xlsx($period),
                    $workbook->filename($period, 'xlsx'),
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                );
            }

            $this->send($workbook->csv($period), $workbook->filename($period, 'csv'), 'text/csv; charset=utf-8');
        } catch (SheetError $error) {
            wp_die(esc_html($error->getMessage()));
        }
    }

    /** @return never */
    private function send(string $body, string $filename, string $type): never
    {
        nocache_headers();
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($body));

        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- بدنهٔ باینری فایل، نه HTML

        exit;
    }

    /** @param array<int, string> $periods */
    private function chosen_period(array $periods): string
    {
        $requested = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '';

        if (in_array($requested, $periods, true)) {
            return $requested;
        }

        return $this->report->defaultPeriod(Clock::now());
    }

    private function download_url(string $period): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => self::DOWNLOAD, 'period' => $period], admin_url('admin-post.php')),
            self::DOWNLOAD
        );
    }
}
