<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use Bakery_Sheet\Reader;
use Bakery_Sheet\SheetError;
use Bakery_Sheet\Writer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ «کاربران ← ورودی و خروجی اکسل».
 *
 * جریان کار عمداً سه‌مرحله‌ای‌ست و نه یک‌مرحله‌ای:
 *
 *   ۱) فایل آپلود می‌شود و فقط *خوانده* می‌شود.
 *   ۲) مدیر یک جدول می‌بیند: کدام سطر کاربر تازه می‌سازد، کدام یکی
 *      موجود را به‌روز می‌کند، و کدام سطر خطا دارد و چرا.
 *   ۳) تازه بعد از تأیید، نوشتن انجام می‌شود.
 *
 * مرحلهٔ دوم گران به نظر می‌رسد ولی همان چیزی‌ست که این ابزار را قابل
 * استفاده می‌کند. ایمپورت یک‌مرحله‌ای روی فایل ۲۰۰ نفره یعنی یا همه‌چیز
 * درست است یا مدیر با دیتابیسی روبه‌روست که نمی‌داند چقدرش عوض شده. با
 * پیش‌نمایش، بدترین حالت این است که فایل را اصلاح کند و دوباره بیاورد.
 *
 * خودِ فایلِ آپلودشده هیچ‌وقت ذخیره نمی‌شود؛ فقط شبکهٔ خوانده‌شده‌اش در
 * یک ترنزینت یک‌ساعته می‌ماند. و در مرحلهٔ سوم، سنجش از نو روی همان
 * شبکه اجرا می‌شود — نه روی تصمیم‌های ذخیره‌شدهٔ مرحلهٔ دوم. اگر بین
 * پیش‌نمایش و تأیید کسی کاربری را عوض کرده باشد، تصمیم‌ها با وضعیت
 * واقعیِ همان لحظه گرفته می‌شوند و نه با عکسی از گذشته.
 */
final class Users_Sheet_Page
{
    public const SLUG = 'bkw-users-sheet';
    private const CAPABILITY = 'create_users';

    private const EXPORT = 'bkw_users_sheet_export';
    private const UPLOAD = 'bkw_users_sheet_upload';
    private const APPLY = 'bkw_users_sheet_apply';

    private const PREVIEW_TTL = HOUR_IN_SECONDS;
    private const MAX_UPLOAD_BYTES = 8 * MB_IN_BYTES;

    /** بیشتر از این تعداد سطر در پیش‌نمایش نشان داده نمی‌شود؛ خلاصه و خطاها همیشه کاملند. */
    private const PREVIEW_LIMIT = 300;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_' . self::EXPORT, [$this, 'handle_export']);
        add_action('admin_post_' . self::UPLOAD, [$this, 'handle_upload']);
        add_action('admin_post_' . self::APPLY, [$this, 'handle_apply']);
    }

    public function register_menu(): void
    {
        add_users_page(
            __('ورودی و خروجی اکسل', 'bakery-widgets'),
            __('ورودی و خروجی اکسل', 'bakery-widgets'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render']
        );
    }

    /* ---------------------------------------------------------------------
     * خروجی
     * ------------------------------------------------------------------- */

    public function handle_export(): void
    {
        $this->authorize(self::EXPORT);

        $format = isset($_GET['format']) && 'csv' === $_GET['format'] ? 'csv' : 'xlsx';
        $header = Users_Sheet::header();
        $rows = Users_Sheet::exportRows();
        $name = 'bakery-users-' . gmdate('Y-m-d');

        if ('xlsx' === $format && Writer::canWriteXlsx()) {
            $path = wp_tempnam($name . '.xlsx');
            $body = '';

            // فایل موقت پیش از فرستادن خوانده و پاک می‌شود، نه در یک
            // بلوک finally: send() با exit تمام می‌شود و exit هیچ
            // finally‌ای را اجرا نمی‌کند — یعنی هر خروجی یک فایل موقت
            // در uploads جا می‌گذاشت.
            try {
                Writer::xlsx($path, $header, $rows, 'کاربران');
                $body = (string) file_get_contents($path);
            } catch (SheetError $error) {
                $this->bail($error->getMessage());
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            $this->send($body, $name . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        $this->send(Writer::csv($header, $rows), $name . '.csv', 'text/csv; charset=utf-8');
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
     * ورودی: آپلود و پیش‌نمایش
     * ------------------------------------------------------------------- */

    public function handle_upload(): void
    {
        $this->authorize(self::UPLOAD);

        $file = $_FILES['bkw_sheet'] ?? null;

        if (!is_array($file) || !isset($file['tmp_name']) || UPLOAD_ERR_OK !== (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
            $this->back(['error' => 'upload']);
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            $this->back(['error' => 'upload']);
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
            $this->back(['error' => 'size']);
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        try {
            $grid = Reader::grid((string) $file['tmp_name'], $extension);
        } catch (SheetError $error) {
            $this->back(['error' => 'read', 'message' => rawurlencode($error->getMessage())]);
        }

        if (count($grid) < 2) {
            $this->back(['error' => 'empty']);
        }

        // فایل خودش هیچ‌وقت ذخیره نمی‌شود؛ فقط چیزی که از آن خوانده شد.
        $token = self::token();
        set_transient('bkw_user_sheet_' . $token, $grid, self::PREVIEW_TTL);

        $this->back(['preview' => $token]);
    }

    /* ---------------------------------------------------------------------
     * ورودی: اعمال
     * ------------------------------------------------------------------- */

    public function handle_apply(): void
    {
        $this->authorize(self::APPLY);

        $token = isset($_POST['token']) ? sanitize_key(wp_unslash($_POST['token'])) : '';
        $grid = '' !== $token ? get_transient('bkw_user_sheet_' . $token) : false;

        if (!is_array($grid)) {
            $this->back(['error' => 'expired']);
        }

        // سنجش دوباره و نه استفاده از تصمیم‌های پیش‌نمایش: بین دیدن و
        // تأیید ممکن است کسی کاربری را عوض کرده باشد.
        $plan = Users_Sheet::plan($grid);

        if ('' !== $plan['fatal']) {
            $this->back(['error' => 'read', 'message' => rawurlencode($plan['fatal'])]);
        }

        $created = 0;
        $updated = 0;
        $failed = [];

        // وردپرس هنگام عوض شدن ایمیل یک کاربر، به نشانی قبلی‌اش خبر
        // می‌دهد. برای یک ایمپورت دویست‌نفره یعنی دویست ایمیل «ایمیل
        // شما تغییر کرد» — که نه کاربر خواسته و نه مدیر.
        add_filter('send_email_change_email', '__return_false');

        foreach ($plan['rows'] as $row) {
            if ('error' === $row['action']) {
                $failed[] = ['line' => $row['line'], 'errors' => $row['errors']];
                continue;
            }

            $result = Users_Sheet::apply($row);

            if (is_string($result)) {
                $failed[] = ['line' => $row['line'], 'errors' => [$result]];
                continue;
            }

            'create' === $row['action'] ? $created++ : $updated++;
        }

        remove_filter('send_email_change_email', '__return_false');

        delete_transient('bkw_user_sheet_' . $token);

        $resultToken = self::token();
        set_transient('bkw_user_sheet_result_' . $resultToken, [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ], self::PREVIEW_TTL);

        $this->back(['done' => $resultToken]);
    }

    /* ---------------------------------------------------------------------
     * نمایش
     * ------------------------------------------------------------------- */

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('اجازهٔ دسترسی به این صفحه را ندارید.', 'bakery-widgets'));
        }

        echo '<div class="wrap"><h1>' . esc_html__('ورودی و خروجی اکسل کاربران', 'bakery-widgets') . '</h1>';

        $this->render_notices();

        $token = isset($_GET['preview']) ? sanitize_key(wp_unslash($_GET['preview'])) : '';
        $done = isset($_GET['done']) ? sanitize_key(wp_unslash($_GET['done'])) : '';

        if ('' !== $done) {
            $this->render_result($done);
        }

        if ('' !== $token) {
            $this->render_preview($token);
        } else {
            $this->render_export_card();
            $this->render_import_card();
            $this->render_columns_card();
        }

        echo '</div>';
    }

    private function render_export_card(): void
    {
        ?>
        <div class="card" style="max-width:820px">
            <h2><?php esc_html_e('خروجی گرفتن', 'bakery-widgets'); ?></h2>
            <p>
                <?php esc_html_e('فایل همهٔ کاربران سایت با همان ستون‌هایی که ورودی می‌پذیرد. بهترین راه شروع همین است: خروجی بگیرید، در اکسل ویرایش کنید، و دوباره همین فایل را وارد کنید.', 'bakery-widgets'); ?>
            </p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($this->action_url(self::EXPORT, ['format' => 'xlsx'])); ?>">
                    <?php esc_html_e('دریافت فایل اکسل (xlsx)', 'bakery-widgets'); ?>
                </a>
                <a class="button" href="<?php echo esc_url($this->action_url(self::EXPORT, ['format' => 'csv'])); ?>">
                    <?php esc_html_e('دریافت CSV', 'bakery-widgets'); ?>
                </a>
            </p>
            <p class="description">
                <?php esc_html_e('فایل xlsx را ترجیح بدهید: در CSV، اکسل صفرِ ابتدایی کد ملی و کد پرسنلی را حذف می‌کند.', 'bakery-widgets'); ?>
                <?php esc_html_e('کاربرانی که هنوز کد ملی ندارند (مثل خودِ مدیر سایت) در خروجی نمی‌آیند؛ آن‌ها را در ستون «کد ملی» فهرست کاربران می‌بینید.', 'bakery-widgets'); ?>
            </p>
        </div>
        <?php
    }

    private function render_import_card(): void
    {
        ?>
        <div class="card" style="max-width:820px">
            <h2><?php esc_html_e('ورودی گرفتن', 'bakery-widgets'); ?></h2>
            <p>
                <?php esc_html_e('کاربرها با «کد ملی» شناخته می‌شوند: اگر کد ملیِ سطر روی کاربری موجود باشد همان کاربر به‌روز می‌شود، وگرنه کاربر تازه ساخته می‌شود. سلول خالی یعنی «این مقدار را عوض نکن» — پس با پاک کردن یک ستون، چیزی پاک نمی‌شود.', 'bakery-widgets'); ?>
            </p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::UPLOAD); ?>">
                <?php wp_nonce_field(self::UPLOAD); ?>
                <p><input type="file" name="bkw_sheet" accept=".xlsx,.xlsm,.csv" required></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('بررسی فایل', 'bakery-widgets'); ?></button></p>
            </form>
            <p class="description">
                <?php
                printf(
                    /* translators: %d: maximum number of rows */
                    esc_html__('حداکثر %d سطر. فایل ذخیره نمی‌شود و تا وقتی تأیید نکنید هیچ تغییری در کاربران داده نمی‌شود.', 'bakery-widgets'),
                    (int) Reader::MAX_ROWS
                );
                ?>
            </p>
        </div>
        <?php
    }

    private function render_columns_card(): void
    {
        ?>
        <div class="card" style="max-width:820px">
            <h2><?php esc_html_e('ستون‌ها', 'bakery-widgets'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('سرستون', 'bakery-widgets'); ?></th>
                        <th><?php esc_html_e('لازم؟', 'bakery-widgets'); ?></th>
                        <th><?php esc_html_e('توضیح', 'bakery-widgets'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (Users_Sheet::columns() as $column) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($column['label']); ?></strong></td>
                        <td>
                            <?php
                            echo $column['required']
                                ? esc_html__('برای کاربر تازه', 'bakery-widgets')
                                : esc_html__('اختیاری', 'bakery-widgets');
                            ?>
                        </td>
                        <td><?php echo esc_html($column['hint']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">
                <?php esc_html_e('نام کاربری هر حساب، کد ملی همان کاربر است و نام نمایشی «نام نام‌خانوادگی». ستون‌های اضافهٔ فایل نادیده گرفته می‌شوند.', 'bakery-widgets'); ?>
            </p>
        </div>
        <?php
    }

    private function render_preview(string $token): void
    {
        $grid = get_transient('bkw_user_sheet_' . $token);

        if (!is_array($grid)) {
            $this->notice('error', __('پیش‌نمایش منقضی شده است. فایل را دوباره آپلود کنید.', 'bakery-widgets'));
            $this->render_import_card();

            return;
        }

        $plan = Users_Sheet::plan($grid);

        if ('' !== $plan['fatal']) {
            $this->notice('error', $plan['fatal']);
            $this->render_import_card();

            return;
        }

        $counts = ['create' => 0, 'update' => 0, 'error' => 0];

        foreach ($plan['rows'] as $row) {
            $counts[$row['action']]++;
        }

        ?>
        <h2><?php esc_html_e('پیش‌نمایش', 'bakery-widgets'); ?></h2>
        <p>
            <?php
            printf(
                /* translators: 1: new users, 2: updated users, 3: rows with errors */
                esc_html__('%1$d کاربر تازه، %2$d به‌روزرسانی، %3$d سطر با خطا.', 'bakery-widgets'),
                (int) $counts['create'],
                (int) $counts['update'],
                (int) $counts['error']
            );
            ?>
        </p>
        <?php if ($counts['error'] > 0) : ?>
            <div class="notice notice-warning inline"><p>
                <?php esc_html_e('سطرهای خطادار نوشته نمی‌شوند؛ بقیه اعمال می‌شوند. اگر می‌خواهید همه با هم اعمال شوند، فایل را اصلاح کنید و دوباره بیاورید.', 'bakery-widgets'); ?>
            </p></div>
        <?php endif; ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:4rem"><?php esc_html_e('سطر', 'bakery-widgets'); ?></th>
                    <th style="width:8rem"><?php esc_html_e('نتیجه', 'bakery-widgets'); ?></th>
                    <th><?php esc_html_e('کاربر', 'bakery-widgets'); ?></th>
                    <th><?php esc_html_e('کد ملی', 'bakery-widgets'); ?></th>
                    <th><?php esc_html_e('توضیح', 'bakery-widgets'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php $shown = $this->preview_rows($plan['rows']); ?>
            <?php foreach ($shown as $row) : ?>
                <tr>
                    <td><?php echo (int) $row['line']; ?></td>
                    <td><?php echo wp_kses_post($this->action_badge($row['action'])); ?></td>
                    <td><?php echo esc_html('' !== $row['name'] ? $row['name'] : '—'); ?></td>
                    <td dir="ltr"><?php echo esc_html('' !== $row['key'] ? $row['key'] : '—'); ?></td>
                    <td><?php echo esc_html(implode(' • ', $row['errors'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($shown) < count($plan['rows'])) : ?>
            <p class="description">
                <?php
                printf(
                    /* translators: 1: shown rows, 2: total rows */
                    esc_html__('%1$d سطر از %2$d سطر نشان داده شده؛ همهٔ سطرهای خطادار در همین فهرست هستند.', 'bakery-widgets'),
                    (int) count($shown),
                    (int) count($plan['rows'])
                );
                ?>
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1rem">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::APPLY); ?>">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <?php wp_nonce_field(self::APPLY); ?>
            <button type="submit" class="button button-primary" <?php disabled(0, $counts['create'] + $counts['update']); ?>>
                <?php esc_html_e('تأیید و اعمال', 'bakery-widgets'); ?>
            </button>
            <a class="button" href="<?php echo esc_url(admin_url('users.php?page=' . self::SLUG)); ?>">
                <?php esc_html_e('انصراف', 'bakery-widgets'); ?>
            </a>
        </form>
        <?php
    }

    /**
     * سطرهای خطادار همیشه کاملند و بقیه تا سقف نمایش.
     *
     * برعکسش (بریدن ساده از اول فهرست) یعنی روی فایل ۵۰۰ نفره، خطای
     * سطر ۴۸۰ اصلاً دیده نمی‌شد — و دقیقاً همان چیزی‌ست که مدیر برای
     * دیدنش آمده.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function preview_rows(array $rows): array
    {
        $errors = array_filter($rows, static fn (array $row): bool => 'error' === $row['action']);
        $rest = array_filter($rows, static fn (array $row): bool => 'error' !== $row['action']);

        $shown = array_merge($errors, array_slice($rest, 0, max(0, self::PREVIEW_LIMIT - count($errors))));

        usort($shown, static fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return $shown;
    }

    private function action_badge(string $action): string
    {
        return match ($action) {
            'create' => '<span style="color:#1a7f37">' . esc_html__('کاربر تازه', 'bakery-widgets') . '</span>',
            'update' => '<span style="color:#2271b1">' . esc_html__('به‌روزرسانی', 'bakery-widgets') . '</span>',
            default => '<span style="color:#b32d2e">' . esc_html__('خطا', 'bakery-widgets') . '</span>',
        };
    }

    private function render_result(string $token): void
    {
        $result = get_transient('bkw_user_sheet_result_' . $token);

        if (!is_array($result)) {
            return;
        }

        delete_transient('bkw_user_sheet_result_' . $token);

        $this->notice('success', sprintf(
            /* translators: 1: created users, 2: updated users */
            __('انجام شد: %1$d کاربر ساخته شد و %2$d کاربر به‌روز شد.', 'bakery-widgets'),
            (int) $result['created'],
            (int) $result['updated']
        ));

        if ([] === $result['failed']) {
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . esc_html__('سطرهای زیر اعمال نشدند:', 'bakery-widgets')
            . '</p><ul style="list-style:disc;margin-inline-start:2em">';

        foreach ($result['failed'] as $failure) {
            printf(
                '<li>%s</li>',
                esc_html(sprintf(
                    /* translators: 1: line number, 2: reasons */
                    __('سطر %1$d — %2$s', 'bakery-widgets'),
                    (int) $failure['line'],
                    implode(' • ', $failure['errors'])
                ))
            );
        }

        echo '</ul></div>';
    }

    private function render_notices(): void
    {
        $error = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';

        if ('' === $error) {
            return;
        }

        $message = match ($error) {
            'upload' => __('فایلی دریافت نشد. دوباره تلاش کنید.', 'bakery-widgets'),
            'size' => __('فایل بزرگ‌تر از حد مجاز است.', 'bakery-widgets'),
            'empty' => __('فایل جز سرستون چیزی ندارد.', 'bakery-widgets'),
            'expired' => __('پیش‌نمایش منقضی شده است. فایل را دوباره آپلود کنید.', 'bakery-widgets'),
            default => isset($_GET['message'])
                ? sanitize_text_field(rawurldecode(wp_unslash($_GET['message'])))
                : __('خواندن فایل ممکن نشد.', 'bakery-widgets'),
        };

        $this->notice('error', $message);
    }

    private function notice(string $type, string $message): void
    {
        printf(
            '<div class="notice notice-%s"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

    /**
     * شناسهٔ پیش‌نمایش، فقط با حروف کوچک و رقم.
     *
     * چرا نه wp_generate_password: خروجی آن حرف بزرگ هم دارد، و این
     * شناسه از مسیر sanitize_key() رد می‌شود که همه‌چیز را کوچک می‌کند.
     * یعنی تقریباً هر پیش‌نمایشی «منقضی شده» اعلام می‌شد، بدون اینکه
     * معلوم باشد چرا.
     */
    private static function token(): string
    {
        return bin2hex(random_bytes(12));
    }

    /** @param array<string, string> $args */
    private function action_url(string $action, array $args = []): string
    {
        return wp_nonce_url(
            add_query_arg(array_merge(['action' => $action], $args), admin_url('admin-post.php')),
            $action
        );
    }

    private function authorize(string $action): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('اجازهٔ انجام این کار را ندارید.', 'bakery-widgets'), '', ['response' => 403]);
        }

        check_admin_referer($action);
    }

    /**
     * @param array<string, string> $args
     * @return never
     */
    private function back(array $args): never
    {
        wp_safe_redirect(add_query_arg(
            array_merge(['page' => self::SLUG], $args),
            admin_url('users.php')
        ));

        exit;
    }

    /** @return never */
    private function bail(string $message): never
    {
        wp_die(esc_html($message));
    }
}
