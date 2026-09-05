<?php

declare(strict_types=1);

namespace Bakery_Credit\Admin;

use Bakery_Credit\Domain\PeriodSummary;
use Bakery_Credit\Service\PeriodReport;
use Bakery_Sheet\Column;
use Bakery_Sheet\Number;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;
use WHW\Admin\PersianCalendarFormat;
use WHW\Service\Clock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ «کاربران ← گزارش اعتبار ماهانه» و خروجی اکسلش.
 *
 * سؤالی که این گزارش جواب می‌دهد: «در فلان ماه، هر کاربر چقدر از
 * اعتبارش را خرج کرد؟» — و «فلان ماه» می‌تواند ماهی باشد که گذشته.
 *
 * ستون‌های هویت از این‌جا نمی‌آیند. با فیلتر
 * `bkw_credit_report_identity` پرسیده می‌شوند و ماژول ویجت‌ها جوابشان
 * را می‌دهد، دقیقاً برعکسِ `bkw_user_sheet_columns` که آن‌جا این ماژول
 * ستون سقف اعتبار را اضافه می‌کند. هیچ‌کدام نام کلاس آن‌یکی را نمی‌برد؛
 * اگر ویجت‌ها نباشند، گزارش بدون نام و کد ملی ولی با اعداد درست کار
 * می‌کند.
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

    /* ---------------------------------------------------------------------
     * نمایش
     * ------------------------------------------------------------------- */

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('اجازهٔ دسترسی به این صفحه را ندارید.', 'bakery-widgets'));
        }

        $periods = $this->report->periods(Clock::now());

        echo '<div class="wrap"><h1>' . esc_html__('گزارش اعتبار ماهانه', 'bakery-widgets') . '</h1>';

        $this->render_picker($periods, $this->chosen_period($periods));

        echo '</div>';
    }

    /** @param array<int, string> $periods */
    private function render_picker(array $periods, string $chosen): void
    {
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
                            <?php echo esc_html(self::period_label($period)); ?>
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
    }

    /* ---------------------------------------------------------------------
     * خروجی
     * ------------------------------------------------------------------- */

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

        $definitions = $this->definitions($period);
        $columns = array_map(static fn (array $definition): Column => $definition['spec'], $definitions);
        $rows = $this->rows($this->report->summaries($period), $definitions);
        $name = 'bakery-credit-' . $period;

        if (Writer::canWriteXlsx()) {
            $path = wp_tempnam($name . '.xlsx');
            $body = '';

            try {
                Writer::xlsx($path, $columns, $rows, self::period_label($period));
                $body = (string) file_get_contents($path);
            } catch (SheetError $error) {
                wp_die(esc_html($error->getMessage()));
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            $this->send($body, $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        $this->send(Writer::csv($columns, $rows), $name . '.csv', 'text/csv; charset=utf-8');
    }

    /**
     * ستون‌ها: هویت (از ماژول ویجت‌ها) به‌علاوهٔ اعداد اعتبار.
     *
     * خوانندهٔ همهٔ ستون‌ها یک امضا دارد و PeriodSummary می‌گیرد.
     * ستون‌های هویت شناسهٔ کاربر می‌خواهند، پس همین‌جا پیچیده می‌شوند —
     * وگرنه ساختِ سطرها باید موقع اجرا حدس می‌زد کدام خواننده چه
     * می‌خواهد، که یعنی یک شرط شکننده در داغ‌ترین حلقهٔ گزارش.
     *
     * @param string $period دورهٔ گزارش؛ نامش در سرستون ستون مصرف می‌نشیند
     * @return array<int, array{spec: Column, read: callable(PeriodSummary): string}>
     */
    private function definitions(string $period): array
    {
        $definitions = [];

        /** @var array<int, array{label: string, read: callable(int): string, width?: int}> $identity */
        $identity = (array) apply_filters('bkw_credit_report_identity', []);

        foreach ($identity as $column) {
            if (!isset($column['label'], $column['read']) || !is_callable($column['read'])) {
                continue;
            }

            $read = $column['read'];

            $definitions[] = [
                'spec' => new Column((string) $column['label'], width: (int) ($column['width'] ?? 20)),
                'read' => static fn (PeriodSummary $summary): string => (string) $read($summary->userId),
            ];
        }

        // اعداد همه numeric‌اند تا جداکنندهٔ سه‌رقمی بگیرند و در اکسل
        // قابل جمع‌بستن و مرتب‌سازی باشند.
        $numbers = [
            [__('سقف اعتبار ماهانه', 'bakery-widgets'), 18, static fn (PeriodSummary $s): string => Number::format($s->allowance)],
            // نام ماه داخل خودِ سرستون می‌آید. فایلی که به بخش مالی
            // می‌رسد باید بدون هیچ توضیح همراهی بگوید مال کدام ماه است؛
            // «اعتبار مصرفی» تنها، روی میز کسی که سه فایل جلویش دارد
            // هیچ چیزی نمی‌گوید.
            [
                sprintf(
                    /* translators: %s: Jalali month name */
                    __('اعتبار مصرفی %s ماه', 'bakery-widgets'),
                    self::month_name($period)
                ),
                24,
                static fn (PeriodSummary $s): string => Number::format($s->consumed),
            ],
            [__('تعداد سفارش', 'bakery-widgets'), 14, static fn (PeriodSummary $s): string => (string) $s->orders],
        ];

        foreach ($numbers as [$label, $width, $read]) {
            $definitions[] = ['spec' => new Column($label, numeric: true, width: $width), 'read' => $read];
        }

        return $definitions;
    }

    /**
     * @param array<int, PeriodSummary> $summaries
     * @param array<int, array{spec: Column, read: callable(PeriodSummary): string}> $definitions
     * @return array<int, array<int, string>>
     */
    private function rows(array $summaries, array $definitions): array
    {
        $rows = [];

        foreach ($summaries as $summary) {
            $row = [];

            foreach ($definitions as $definition) {
                $row[] = ($definition['read'])($summary);
            }

            $rows[] = $row;
        }

        return $rows;
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

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

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

    /** «۱۴۰۵-۰۶» → «شهریور» */
    private static function month_name(string $periodKey): string
    {
        [, $month] = array_map('intval', explode('-', $periodKey) + [0, 0]);

        return PersianCalendarFormat::monthName($month);
    }

    /** «۱۴۰۵-۰۶» → «شهریور ۱۴۰۵» */
    private static function period_label(string $periodKey): string
    {
        [$year, $month] = array_map('intval', explode('-', $periodKey) + [0, 0]);

        return trim(PersianCalendarFormat::monthName($month) . ' ' . PersianCalendarFormat::digits((string) $year));
    }
}
