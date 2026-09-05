<?php

declare(strict_types=1);

namespace Bakery_Credit\Admin;

use Bakery_Credit\Report\MatrixWorkbook;
use Bakery_Credit\Report\MonthWorkbook;
use Bakery_Credit\Report\Sheet;
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
 * دو خروجی دارد: گزارش یک ماه، و نمای کلیِ همهٔ ماه‌ها به‌صورت جدول
 * متقاطع. ساختِ هر دو در Report\ است و نه این‌جا، تا اگر روزی از راه
 * دیگری خواسته شدند — کرون ماهانه، ایمیل به مالی، یک دستور WP-CLI —
 * بازچینش لازم نداشته باشند.
 */
final class ReportPage
{
    public const SLUG = 'bkw-credit-report';
    private const CAPABILITY = 'list_users';
    private const DOWNLOAD = 'bkw_credit_report_download';
    private const DOWNLOAD_MATRIX = 'bkw_credit_report_matrix';

    public function __construct(private readonly PeriodReport $report)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::DOWNLOAD, [$this, 'handle_download']);
        add_action('admin_post_' . self::DOWNLOAD_MATRIX, [$this, 'handle_matrix_download']);
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
                            <?php echo esc_html(Sheet::periodLabel($period)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e('نمایش', 'bakery-widgets'); ?></button>
                <a class="button button-primary" href="<?php echo esc_url($this->action_url(self::DOWNLOAD, ['period' => $chosen])); ?>">
                    <?php esc_html_e('دریافت فایل اکسل', 'bakery-widgets'); ?>
                </a>
            </form>
        </div>

        <div class="card" style="max-width:820px">
            <h2><?php esc_html_e('نمای کلی همهٔ ماه‌ها', 'bakery-widgets'); ?></h2>
            <p>
                <?php esc_html_e('یک فایل با یک سطر برای هر کاربر و یک ستون برای هر ماه؛ هر خانه، مصرف همان کاربر در همان ماه. برای دیدن روند در طول زمان، که در فایل‌های تک‌ماهه پیدا نیست.', 'bakery-widgets'); ?>
            </p>
            <p>
                <a class="button" href="<?php echo esc_url($this->action_url(self::DOWNLOAD_MATRIX)); ?>">
                    <?php esc_html_e('دریافت نمای کلی', 'bakery-widgets'); ?>
                </a>
            </p>
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

        $this->prime_user_cache($this->report->userIds($period));

        $workbook = new MonthWorkbook($this->report, $this->sheet());

        $this->deliver(
            static fn (): string => $workbook->xlsx($period),
            static fn (): string => $workbook->csv(),
            $workbook->filename($period, 'xlsx'),
            $workbook->filename($period, 'csv')
        );
    }

    public function handle_matrix_download(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('اجازهٔ انجام این کار را ندارید.', 'bakery-widgets'), '', ['response' => 403]);
        }

        check_admin_referer(self::DOWNLOAD_MATRIX);

        $this->prime_user_cache($this->report->allUserIds());

        $workbook = new MatrixWorkbook($this->report, $this->sheet());

        $this->deliver(
            static fn (): string => $workbook->xlsx(),
            static fn (): string => $workbook->csv(),
            $workbook->filename('xlsx'),
            $workbook->filename('csv')
        );
    }

    private function sheet(): Sheet
    {
        return new Sheet((array) apply_filters('bkw_credit_report_identity', []));
    }

    /**
     * xlsx اگر بشود، وگرنه CSV — و پیام خطا اگر هیچ‌کدام.
     *
     * @param callable(): string $xlsx
     * @param callable(): string $csv
     * @return never
     */
    private function deliver(callable $xlsx, callable $csv, string $xlsxName, string $csvName): never
    {
        try {
            if (Writer::canWriteXlsx()) {
                $this->send($xlsx(), $xlsxName, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            }

            $this->send($csv(), $csvName, 'text/csv; charset=utf-8');
        } catch (SheetError $error) {
            wp_die(esc_html($error->getMessage()));
        }
    }

    /**
     * متای همهٔ کاربران گزارش را یک‌جا گرم می‌کند.
     *
     * بدون این، هر ستون هویتی برای هر کاربر یک کوئری جدا می‌شد — روی
     * دویست کاربر و پنج ستون یعنی هزار کوئری برای گزارشی که باید یکی
     * دو تا باشد.
     *
     * @param array<int, int> $userIds
     */
    private function prime_user_cache(array $userIds): void
    {
        if ([] !== $userIds) {
            cache_users($userIds);
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

    /** @param array<string, string> $args */
    private function action_url(string $action, array $args = []): string
    {
        return wp_nonce_url(
            add_query_arg(array_merge(['action' => $action], $args), admin_url('admin-post.php')),
            $action
        );
    }
}
