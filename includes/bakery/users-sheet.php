<?php

declare(strict_types=1);

namespace Bakery_Widgets;

use Bakery_Sheet\Column as SheetColumnSpec;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ستون‌های فایل کاربران، و تبدیل یک فایل خوانده‌شده به «چه اتفاقی
 * قرار است بیفتد».
 *
 * این کلاس هیچ چیزی نمی‌نویسد. کارش فقط سنجیدن است: هر سطر فایل را به
 * یک تصمیم (ساخت / به‌روزرسانی / خطا) تبدیل می‌کند تا صفحهٔ ورودی بتواند
 * *قبل از* دست‌زدن به دیتابیس همه‌اش را به مدیر نشان بدهد. جداکردن
 * سنجش از نوشتن تنها راهی‌ست که «ایمپورت نیمه‌کاره» را غیرممکن می‌کند:
 * اگر سطر ۴۰ خطا داشته باشد، مدیر آن را می‌بیند در حالی که هنوز هیچ‌کدام
 * از ۳۹ سطر قبلی نوشته نشده‌اند.
 *
 * ستون‌ها یک‌جا تعریف می‌شوند و هر سه مصرف‌کننده از همین می‌خوانند:
 * خروجی گرفتن، تطبیق سرستون‌های فایل ورودی، و نوشتن. پس ستون تازه یعنی
 * یک ورودی در همین آرایه و نه سه جای پراکنده.
 *
 * هر سطر با کد ملی *یا* شمارهٔ موبایل به کاربرش وصل می‌شود.
 *
 * اولش فقط کد ملی این نقش را داشت و یک محدودیت جدی می‌ساخت: کد ملیِ
 * اشتباهِ ثبت‌شده از راه فایل قابل اصلاح نبود. مدیر آن را در اکسل درست
 * می‌کرد و نتیجه یک کاربر *تازه* بود، چون سطر دیگر به هیچ‌کس نمی‌خورد.
 *
 * راه‌حل بعدی یک ستون «شناسهٔ کاربر وردپرس» بود که کنار گذاشته شد. دو
 * ایراد داشت که هیچ‌کدام با قفل‌کردن ستون حل نمی‌شد: عددی بی‌معنا جلوی
 * چشم مدیر می‌گذاشت، و مهم‌تر، فایل را به یک دیتابیس مشخص گره می‌زد —
 * همان فایل روی سایت آزمایشی یا نصب تازه به کاربرهای دیگری اشاره
 * می‌کرد.
 *
 * حالا تطبیق روی دو فیلدی انجام می‌شود که هر دو یک آدم را می‌شناسانند و
 * هیچ‌وقت بین دو نفر جابه‌جا نمی‌شوند: کد ملی و شمارهٔ موبایل. اصلاح
 * یکی، با آن‌یکی پیدا می‌شود. اگر هر دو به دو کاربر *مختلف* بخورند،
 * سطر خطا می‌گیرد و نوشته نمی‌شود — چون آن‌وقت معلوم نیست منظور کدام
 * بوده. کد پرسنلی عمداً کلید نیست: مال سازمان است و نه شخص، و
 * جابه‌جا شدنش بین دو کارمند اتفاق طبیعی‌ای‌ست.
 *
 * سقف اعتبار عمداً این‌جا نیست. آن مال ماژول اعتبار است و خودش با فیلتر
 * `bkw_user_sheet_columns` ستونش را اضافه می‌کند
 * (Bakery_Credit\Integration\SheetColumn) — همان جهت وابستگیِ همیشگی
 * در این افزونه: ماژول اعتبار به ویجت‌ها قلاب می‌شود، نه برعکس. اگر آن
 * ماژول نباشد، فایل بدون ستون سقف اعتبار کار می‌کند.
 */
final class Users_Sheet
{
    /** کلید ستونی که سطر را به کاربر وصل می‌کند و نام کاربری هم از آن ساخته می‌شود. */
    public const KEY_COLUMN = Mobile_Login::META_NATIONAL_ID;

    /**
     * حافظهٔ جست‌وجوی صاحبِ هر مقدار، به‌ازای یک اجرای plan().
     *
     * @var array<string, array<string, ?int>>
     */
    private static array $owners = [];

    /**
     * @return array<string, array{
     *     label: string,
     *     aliases: array<int, string>,
     *     store: string,
     *     required: bool,
     *     unique: bool,
     *     hint: string,
     *     read: callable(int): string,
     *     parse: callable(string): ?string,
     *     write?: callable(int, string): void
     * }>
     */
    public static function columns(): array
    {
        $columns = [
            'id' => [
                'label' => __('شناسه', 'bakery-widgets'),
                'aliases' => ['شناسه کاربر', 'user_id', 'id'],
                'store' => 'id',
                'required' => false,
                'unique' => false,
                'width' => 9,
                'locked' => true,
                'hint' => __('شناسهٔ کاربر در وردپرس. خروجی خودش پرش می‌کند و در فایل اکسل قفل است — نه لازم است پرش کنید و نه می‌شود. برای کاربر تازه خالی می‌ماند.', 'bakery-widgets'),
                'read' => static fn (int $id): string => (string) $id,
                'parse' => static function (string $raw): ?string {
                    $digits = Mobile_Login::normalize_digits($raw);

                    return '' !== $digits && (int) $digits > 0 ? $digits : null;
                },
            ],
            'first_name' => [
                'label' => __('نام', 'bakery-widgets'),
                'aliases' => ['نام کوچک', 'first_name'],
                'store' => 'user',
                'required' => true,
                'unique' => false,
                'hint' => __('همراه نام خانوادگی، نام نمایشی کاربر را می‌سازد.', 'bakery-widgets'),
                'read' => static fn (int $id): string => (string) get_user_meta($id, 'first_name', true),
                'parse' => static fn (string $raw): ?string => '' === trim($raw) ? null : sanitize_text_field($raw),
            ],
            'last_name' => [
                'label' => __('نام خانوادگی', 'bakery-widgets'),
                'aliases' => ['فامیلی', 'last_name'],
                'store' => 'user',
                'required' => true,
                'unique' => false,
                'hint' => '',
                'read' => static fn (int $id): string => (string) get_user_meta($id, 'last_name', true),
                'parse' => static fn (string $raw): ?string => '' === trim($raw) ? null : sanitize_text_field($raw),
            ],
        ];

        // ترتیب همان چیزی‌ست که مدیر انتظار دارد در فایل ببیند، نه ترتیب
        // داخلی Mobile_Login::fields(). فیلد هویتی که این‌جا نامش نیامده
        // (اگر روزی اضافه شود) ته فهرست می‌آید و بی‌صدا جا نمی‌ماند.
        $order = [Mobile_Login::META_MOBILE, Mobile_Login::META_NATIONAL_ID, Mobile_Login::META_PERSONNEL];
        $identity = Mobile_Login::fields();

        foreach (array_keys($identity) as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        foreach ($order as $key) {
            if (!isset($identity[$key])) {
                continue;
            }

            $columns[$key] = array_merge([
                'label' => $identity[$key]['label'],
                'aliases' => self::identityAliases($key),
                'store' => 'meta',
                // کد ملی کلیدِ تطبیق است و بدونش سطر به هیچ کاربری وصل
                // نمی‌شود. باقی فیلدهای هویت اجباری نیستند مگر موبایل،
                // که بدونش کاربر تازه اصلاً نمی‌تواند وارد شود.
                'required' => self::KEY_COLUMN === $key || Mobile_Login::META_MOBILE === $key,
                'unique' => $identity[$key]['unique'],
                'hint' => $identity[$key]['description'],
                'read' => static fn (int $id): string => (string) get_user_meta($id, $key, true),
                'parse' => static fn (string $raw): ?string => self::parseIdentity($key, $raw),
            ], self::identityRule($key));
        }

        /**
         * ستون‌های بیرونی — امروز فقط سقف اعتبار.
         *
         * @param array<string, array<string, mixed>> $columns
         */
        return (array) apply_filters('bkw_user_sheet_columns', $columns);
    }



    /**
     * ستون‌های هویت، برای گزارش‌هایی که خودشان اعداد را می‌سازند.
     *
     * جواب فیلتر `bkw_credit_report_identity` است. عمداً همان
     * برچسب‌ها و همان خواننده‌های فایل کاربران را می‌دهد تا یک نفر در
     * دو فایل مختلف با دو نام متفاوت ظاهر نشود.
     *
     * ستون شناسه و ستون‌های محاسبه‌شده (سقف اعتبار) بیرون می‌مانند:
     * اولی شمارهٔ داخلی‌ست و به گزارش نمی‌خورد، دومی را خودِ گزارش با
     * مقدار همان ماه حساب می‌کند و نه با مقدار امروز.
     *
     * @param array<int, array<string, mixed>> $columns
     * @return array<int, array{label: string, read: callable(int): string, width: int}>
     */
    public static function identity_columns(array $columns = []): array
    {
        foreach (self::columns() as $key => $column) {
            if ('id' === $key || 'custom' === $column['store']) {
                continue;
            }

            $columns[] = [
                'label' => (string) $column['label'],
                'read' => $column['read'],
                'width' => (int) ($column['width'] ?? 20),
            ];
        }

        return $columns;
    }

    /** @return array<int, array<int, string>> یک سطر به‌ازای هر کاربر سایت */
    public static function exportRows(): array
    {
        $rows = [];
        $columns = self::columns();

        /*
         * فقط کاربرانی که کد ملی دارند.
         *
         * وگرنه هر خروجی، سطرهای بی‌کدملیِ سایت (مدیر، حساب‌های سرویس)
         * را هم می‌آورد و همان فایل موقع برگشتن، به‌ازای هرکدام یک سطر
         * «کد ملی خالی است» می‌داد — خطایی که مدیر کاری نمی‌تواند
         * برایش بکند و هر بار باید نادیده‌اش بگیرد. خروجی همان مجموعه‌ای
         * می‌ماند که ورودی می‌فهمد، پس رفت‌وبرگشت بی‌خطا تمام می‌شود.
         * کاربرِ بدون کد ملی در ستون قرمز «ثبت نشده» فهرست کاربران
         * دیده می‌شود، که جای درستِ پیدا کردنش است.
         */
        $ids = get_users([
            'fields' => 'ID',
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => [['key' => self::KEY_COLUMN, 'compare' => 'EXISTS']],
        ]);

        foreach ($ids as $id) {
            $row = [];

            foreach ($columns as $column) {
                $row[] = (string) ($column['read'])((int) $id);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /* ---------------------------------------------------------------------
     * سنجش فایل
     * ------------------------------------------------------------------- */

    /**
     * سرستون‌های فایل را به کلید ستون‌ها ترجمه می‌کند.
     *
     * تطبیق روی برچسب فارسی، نام‌های مترادف، و خودِ کلید انجام می‌شود —
     * پس هم فایلی که خودمان صادر کرده‌ایم برمی‌گردد، هم فایلی که مدیر
     * از صفر ساخته و سرستونش را «موبایل» نوشته.
     *
     * @param array<int, string> $header
     * @return array<int, string> اندیس ستون در فایل => کلید ستون
     */
    public static function mapHeader(array $header): array
    {
        $lookup = [];

        foreach (self::columns() as $key => $column) {
            foreach (array_merge([$key, $column['label']], $column['aliases']) as $name) {
                $lookup[self::normalizeHeader((string) $name)] = $key;
            }
        }

        $map = [];

        foreach ($header as $index => $cell) {
            $normalized = self::normalizeHeader($cell);

            // ستون ناشناخته بی‌صدا نادیده گرفته می‌شود: فایل‌های واقعی
            // اغلب ستون‌های اضافهٔ خودِ مدیر را هم دارند («واحد»،
            // «توضیحات») و رد کردن کل فایل به‌خاطرشان بی‌معناست.
            if (isset($lookup[$normalized]) && !in_array($lookup[$normalized], $map, true)) {
                $map[$index] = $lookup[$normalized];
            }
        }

        return $map;
    }

    /**
     * هر سطر فایل → یک تصمیم.
     *
     * @param array<int, array<int, string>> $grid شبکهٔ خوانده‌شده، سطر اول سرستون
     * @return array{
     *     columns: array<int, string>,
     *     rows: array<int, array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>}>,
     *     fatal: string
     * }
     */
    /**
     * @param array<int, array<int, string>> $grid شبکهٔ خوانده‌شده، سطر اول سرستون
     * @param array<int, int> $resolutions شمارهٔ سطر => کاربری که مدیر گفته منظورش همان است
     * @return array{
     *     columns: array<int, string>,
     *     rows: array<int, array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>, similar:array<int, array{id:int, label:string}>}>,
     *     fatal: string
     * }
     */
    public static function plan(array $grid, array $resolutions = []): array
    {
        self::$owners = [];

        $header = $grid[0] ?? [];
        $map = self::mapHeader($header);
        $columns = self::columns();

        // یکی از این دو کافی‌ست؛ هیچ‌کدام نباشد، هیچ سطری به هیچ کاربری
        // نمی‌خورد و فایل اصلاً قابل پردازش نیست.
        if ([] === array_intersect(array_merge(['id'], self::matchKeys()), $map)) {
            return [
                'columns' => [],
                'rows' => [],
                'fatal' => sprintf(
                    /* translators: 1: ID column label, 2: national ID column label, 3: mobile column label */
                    __('فایل هیچ‌کدام از ستون‌های «%1$s»، «%2$s» و «%3$s» را ندارد، پس معلوم نیست هر سطر مال کدام کاربر است. یک بار خروجی بگیرید و همان فایل را ویرایش کنید.', 'bakery-widgets'),
                    $columns['id']['label'],
                    $columns[self::KEY_COLUMN]['label'],
                    $columns[Mobile_Login::META_MOBILE]['label']
                ),
            ];
        }

        // گذر یک: فقط خواندن و عادی‌سازی سلول‌ها.
        $parsed = [];

        foreach ($grid as $index => $cells) {
            if (0 === $index || \Bakery_Sheet\Reader::isBlank($cells)) {
                continue;
            }

            $parsed[] = ['line' => $index + 1] + self::parseRow($cells, $map, $columns);
        }

        /*
         * گذر دو: فایل، رویِ‌هم‌رفته، حساب چه کسانی را داده؟
         *
         * این پیش از تصمیم‌گیری لازم است، نه هم‌زمان با آن: تشخیصِ
         * «این سطر شاید همان کاربر باشد» فقط وقتی معنا دارد که بدانیم
         * آن کاربر را هیچ سطر دیگری از همین فایل برنداشته. با محاسبهٔ
         * درجا، سطر ۳ نمی‌دانست سطر ۴۰ صاحب دارد یا نه.
         */
        $taken = [];

        foreach ($parsed as $row) {
            foreach (self::rowUsers($row['values']) as $id) {
                $taken[$id] = $id;
            }
        }

        // گذر سه: تصمیم.
        $rows = [];
        // کلیدهای دیده‌شده در خودِ فایل: دو سطر با یک کد ملی یا یک شمارهٔ
        // موبایل، تکراری‌اند حتی اگر هیچ‌کدام هنوز در دیتابیس نباشند.
        $seen = [];

        foreach ($parsed as $row) {
            $rows[] = self::decide($row['line'], $row['values'], $row['errors'], $columns, $seen, $taken, $resolutions);
        }

        return ['columns' => array_values($map), 'rows' => $rows, 'fatal' => ''];
    }

    /**
     * سلول‌های یک سطر، عادی‌سازی‌شده. هیچ تصمیمی این‌جا گرفته نمی‌شود.
     *
     * @param array<int, string> $cells
     * @param array<int, string> $map
     * @param array<string, array<string, mixed>> $columns
     * @return array{values: array<string, string>, errors: array<int, string>}
     */
    private static function parseRow(array $cells, array $map, array $columns): array
    {
        $values = [];
        $errors = [];

        foreach ($map as $index => $key) {
            $raw = trim((string) ($cells[$index] ?? ''));

            if ('' === $raw) {
                continue;
            }

            $parsed = ($columns[$key]['parse'])($raw);

            if (null === $parsed) {
                $errors[] = sprintf(
                    /* translators: 1: column label, 2: the value found in the file */
                    __('مقدار ستون «%1$s» معتبر نیست: %2$s', 'bakery-widgets'),
                    $columns[$key]['label'],
                    $raw
                );
                continue;
            }

            $values[$key] = $parsed;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * یک سطرِ خوانده‌شده → یک تصمیم.
     *
     * @param array<string, string> $values
     * @param array<int, string> $errors
     * @param array<string, array<string, mixed>> $columns
     * @param array<string, array<string, int>> $seen
     * @param array<int, int> $taken
     * @param array<int, int> $resolutions
     * @return array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>, similar:array<int, array{id:int, label:string}>}
     */
    private static function decide(int $line, array $values, array $errors, array $columns, array &$seen, array $taken, array $resolutions): array
    {
        $nationalId = $values[self::KEY_COLUMN] ?? '';
        $similar = [];

        /*
         * شناسه، اگر باشد، حرف آخر را می‌زند.
         *
         * آن‌وقت کد ملی و شمارهٔ تماس هر دو فقط *مقدار*اند و می‌شود هر
         * دو را در یک ویرایش عوض کرد — همان حالتی که با تطبیق دو کلیدی
         * تنها، به کاربر تازه تبدیل می‌شد. سطرهایی که مدیر خودش اضافه
         * کرده شناسه ندارند و همان مسیر دو کلیدی را می‌روند.
         */
        $explicitId = (int) ($values['id'] ?? 0);

        if ($explicitId > 0) {
            if (!get_userdata($explicitId)) {
                return self::row($line, 'error', 0, '', $nationalId, $values, array_merge($errors, [
                    sprintf(
                        /* translators: %d: user ID from the file */
                        __('کاربری با شناسهٔ %d وجود ندارد. اگر می‌خواهید کاربر تازه ساخته شود، ستون شناسه را خالی بگذارید.', 'bakery-widgets'),
                        $explicitId
                    ),
                ]));
            }

            return self::decided($line, $explicitId, $values, $errors, $columns, $seen, $nationalId);
        }

        $matched = self::matchUsers($values);

        if ([] === $matched && '' === $nationalId) {
            // نه به کاربری خورد و نه کد ملی دارد که با آن ساخته شود.
            return self::row($line, 'error', 0, '', '', $values, array_merge($errors, [
                sprintf(
                    /* translators: 1: national ID column label, 2: mobile column label */
                    __('این سطر نه «%1$s» دارد و نه «%2$s»؛ به هیچ کاربری وصل نمی‌شود.', 'bakery-widgets'),
                    $columns[self::KEY_COLUMN]['label'],
                    $columns[Mobile_Login::META_MOBILE]['label']
                ),
            ]));
        }

        if (count($matched) > 1) {
            /*
             * کد ملی به یک نفر خورده و شماره به یکی دیگر.
             *
             * این‌جا حدس‌زدن خطرناک است: هر انتخابی یعنی نوشتن روی
             * حسابی که ممکن است اشتباه باشد. معمول‌ترین علتش هم جابه‌جا
             * شدن دو سطر است، که با یک پیام صریح در چند ثانیه پیدا
             * می‌شود.
             */
            return self::row($line, 'error', 0, '', $nationalId, $values, array_merge($errors, [
                sprintf(
                    /* translators: %s: comma-separated user names */
                    __('ستون‌های این سطر به بیش از یک کاربر می‌خورند (%s). یعنی یا سطرها جابه‌جا شده‌اند یا مقداری اشتباه است.', 'bakery-widgets'),
                    implode('، ', array_map(static fn (int $id): string => self::describe($id), $matched))
                ),
            ]));
        }

        $userId = (int) (reset($matched) ?: 0);

        /*
         * هیچ کلیدی نخورد، پس این سطر کاربر تازه می‌سازد — مگر اینکه
         * واقعاً همان آدمِ قبلی باشد که *هر دو* کلیدش در همین ویرایش
         * عوض شده. آن حالت با داده قابل تشخیص نیست، چون دیگر هیچ نخی
         * بین سطر و رکورد قبلی نمانده.
         *
         * پس به‌جای حدس‌زدن، کاربرانی که این فایل اصلاً به آن‌ها نخورده
         * و نام یا کد پرسنلی‌شان با این سطر یکی‌ست پیدا می‌شوند و
         * تصمیم به مدیر داده می‌شود. نرم‌افزار هویت را حدس نمی‌زند؛
         * فقط شباهت را نشان می‌دهد.
         */
        if (0 === $userId) {
            $similar = self::findLookalikes($values, $taken);
            $chosen = (int) ($resolutions[$line] ?? 0);

            // فقط انتخابی پذیرفته می‌شود که خودِ همین سنجش پیشنهاد داده
            // باشد — وگرنه یک عدد دست‌کاری‌شده در فرم می‌توانست هر
            // کاربری را هدف بگیرد.
            if ($chosen > 0 && isset($similar[$chosen])) {
                $userId = $chosen;
            }
        }

        return self::decided($line, $userId, $values, $errors, $columns, $seen, $nationalId, $similar);
    }

    /**
     * سنجش‌های مشترکِ هر سطری که تکلیفش روشن شده — چه با شناسه و چه با
     * تطبیق کلیدها.
     *
     * @param array<string, string> $values
     * @param array<int, string> $errors
     * @param array<string, array<string, mixed>> $columns
     * @param array<string, array<string, int>> $seen
     * @param array<int, array{id:int, label:string}> $similar
     * @return array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>, similar:array<int, array{id:int, label:string}>}
     */
    private static function decided(int $line, int $userId, array $values, array $errors, array $columns, array &$seen, string $nationalId, array $similar = []): array
    {
        $isNew = 0 === $userId;

        $errors = array_merge(
            $errors,
            self::duplicateErrors($values, $columns, $userId, $seen, $line),
            $isNew ? self::missingRequired($values, $columns) : []
        );

        $name = trim(
            ($values['first_name'] ?? ($isNew ? '' : (string) get_user_meta($userId, 'first_name', true)))
            . ' '
            . ($values['last_name'] ?? ($isNew ? '' : (string) get_user_meta($userId, 'last_name', true)))
        );

        if ([] !== $errors) {
            return self::row($line, 'error', $userId, $name, $nationalId, $values, $errors, $similar);
        }

        return self::row($line, $isNew ? 'create' : 'update', $userId, $name, $nationalId, $values, [], $isNew ? $similar : []);
    }

    /**
     * کاربرانی که این سطر شاید همان‌ها باشد.
     *
     * فقط میان کسانی می‌گردد که *هیچ سطری از این فایل* به آن‌ها نخورده
     * — کاربری که سطر خودش را دارد یتیم نمی‌ماند و پیشنهاد دادنش فقط
     * سروصداست.
     *
     * نام و کد پرسنلی به‌عنوان نشانه استفاده می‌شوند و نه کلید. تفاوتش
     * همه‌چیز است: کلید یعنی نرم‌افزار خودش می‌نویسد و کد پرسنلیِ
     * واگذارشده به کارمند تازه، بی‌صدا روی رکورد کارمند قبلی می‌نشست.
     * نشانه یعنی مدیر با هر دو نام جلوی چشمش تصمیم می‌گیرد.
     *
     * @param array<string, string> $values
     * @param array<int, int> $taken
     * @return array<int, array{id:int, label:string}> کلید = شناسهٔ کاربر
     */
    private static function findLookalikes(array $values, array $taken): array
    {
        $candidates = [];

        $first = trim($values['first_name'] ?? '');
        $last = trim($values['last_name'] ?? '');

        if ('' !== $first && '' !== $last) {
            $byName = get_users([
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'first_name', 'value' => $first],
                    ['key' => 'last_name', 'value' => $last],
                ],
                'number' => 5,
                'fields' => 'ID',
            ]);

            foreach ($byName as $id) {
                $candidates[(int) $id] = (int) $id;
            }
        }

        $personnel = $values[Mobile_Login::META_PERSONNEL] ?? '';

        if ('' !== $personnel) {
            $owner = self::owner(Mobile_Login::META_PERSONNEL, $personnel);

            if (null !== $owner) {
                $candidates[$owner] = $owner;
            }
        }

        $similar = [];

        foreach ($candidates as $id) {
            if (isset($taken[$id])) {
                continue;
            }

            $similar[$id] = ['id' => $id, 'label' => self::describe($id, true)];
        }

        return $similar;
    }

    /**
     * فیلدهایی که یک سطر را به یک کاربر وصل می‌کنند.
     *
     * هر دو یک آدم را می‌شناسانند و هیچ‌وقت بین دو نفر جابه‌جا نمی‌شوند.
     * کد پرسنلی عمداً این‌جا نیست — مال سازمان است و نه شخص، و اگر کلید
     * می‌شد، کدی که به کارمند تازه واگذار شده سطرش را بی‌صدا روی
     * کارمند قبلی می‌نوشت.
     *
     * @return array<int, string>
     */
    private static function matchKeys(): array
    {
        return [self::KEY_COLUMN, Mobile_Login::META_MOBILE];
    }

    /**
     * کاربرانی که این سطر به آن‌ها می‌خورد — امیدواریم صفر یا یکی.
     *
     * @param array<string, string> $values
     * @return array<int, int> شناسه‌های یکتا
     */
    private static function matchUsers(array $values): array
    {
        $found = [];

        foreach (self::matchKeys() as $key) {
            $value = $values[$key] ?? '';

            if ('' === $value) {
                continue;
            }

            $owner = self::owner($key, $value);

            if (null !== $owner) {
                $found[$owner] = $owner;
            }
        }

        return array_values($found);
    }

    /**
     * همهٔ کاربرانی که یک سطر حسابشان را می‌دهد — شناسه به‌علاوهٔ کلیدها.
     *
     * @param array<string, string> $values
     * @return array<int, int>
     */
    private static function rowUsers(array $values): array
    {
        $found = self::matchUsers($values);
        $explicitId = (int) ($values['id'] ?? 0);

        if ($explicitId > 0 && get_userdata($explicitId)) {
            $found[] = $explicitId;
        }

        return array_values(array_unique($found));
    }

    /**
     * صاحب یک مقدار، با حافظه.
     *
     * هر سطر دو بار سنجیده می‌شود — یک‌بار برای ساختن فهرست «فایل حساب
     * چه کسانی را داده» و یک‌بار موقع تصمیم. بدون این حافظه، تعداد
     * کوئری‌ها دو برابر می‌شد بی‌آنکه چیزی عوض شود. عمرش یک اجرای
     * plan() است، پس داده‌ای کهنه نمی‌ماند.
     */
    private static function owner(string $key, string $value): ?int
    {
        if (!array_key_exists($value, self::$owners[$key] ?? [])) {
            self::$owners[$key][$value] = Mobile_Login::find_by($key, $value);
        }

        return self::$owners[$key][$value];
    }

    /**
     * نام کاربر برای نشان دادن به مدیر؛ اگر نامی نداشت، نام کاربری‌اش.
     *
     * $withKeys برای وقتی‌ست که مدیر باید بین دو نفر تصمیم بگیرد — نام
     * تنها کافی نیست، چون اصلاً به‌خاطر هم‌نام بودن پیشنهاد شده‌اند.
     */
    private static function describe(int $userId, bool $withKeys = false): string
    {
        $user = get_userdata($userId);

        if (!$user) {
            return (string) $userId;
        }

        $name = trim((string) $user->display_name);
        $name = '' !== $name ? $name : (string) $user->user_login;

        if (!$withKeys) {
            return $name;
        }

        $keys = array_filter([
            (string) get_user_meta($userId, self::KEY_COLUMN, true),
            (string) get_user_meta($userId, Mobile_Login::META_MOBILE, true),
        ]);

        return [] === $keys ? $name : $name . ' — ' . implode(' / ', $keys);
    }

    /**
     * @param array<string, string> $values
     * @param array<string, array<string, mixed>> $columns
     * @return array<int, string>
     */
    private static function missingRequired(array $values, array $columns): array
    {
        $errors = [];

        foreach ($columns as $key => $column) {
            if ($column['required'] && '' === ($values[$key] ?? '')) {
                $errors[] = sprintf(
                    /* translators: %s: column label */
                    __('برای ساخت کاربر تازه، ستون «%s» لازم است.', 'bakery-widgets'),
                    $column['label']
                );
            }
        }

        return $errors;
    }

    /**
     * یکتایی، هم در برابر دیتابیس و هم در برابر خودِ فایل.
     *
     * دومی کمتر به فکر می‌رسد و بیشتر پیش می‌آید: مدیر یک سطر را کپی
     * می‌کند و یادش می‌رود شمارهٔ موبایلش را عوض کند. بدون این بررسی،
     * سطر اول نوشته می‌شد و سطر دوم رویش، و هیچ‌کس نمی‌فهمید.
     *
     * @param array<string, string> $values
     * @param array<string, array<string, mixed>> $columns
     * @param array<string, array<string, int>> $seen
     * @return array<int, string>
     */
    private static function duplicateErrors(array $values, array $columns, int $userId, array &$seen, int $line): array
    {
        $errors = [];

        foreach ($values as $key => $value) {
            if (!$columns[$key]['unique']) {
                continue;
            }

            if (isset($seen[$key][$value])) {
                $errors[] = sprintf(
                    /* translators: 1: column label, 2: line number */
                    __('«%1$s» تکراری است؛ در سطر %2$d همین فایل هم آمده.', 'bakery-widgets'),
                    $columns[$key]['label'],
                    $seen[$key][$value]
                );
                continue;
            }

            $seen[$key][$value] = $line;

            if (null !== Mobile_Login::find_by($key, $value, $userId)) {
                $errors[] = sprintf(
                    /* translators: %s: column label */
                    __('«%s» قبلاً برای کاربر دیگری ثبت شده است.', 'bakery-widgets'),
                    $columns[$key]['label']
                );
            }
        }

        return $errors;
    }

    /* ---------------------------------------------------------------------
     * نوشتن
     * ------------------------------------------------------------------- */

    /**
     * تصمیم‌های یک سطر را واقعاً می‌نویسد.
     *
     * @param array{action:string, user_id:int, values:array<string,string>} $row
     * @return int|string شناسهٔ کاربر، یا پیام خطا
     */
    public static function apply(array $row)
    {
        $columns = self::columns();
        $values = $row['values'];
        $userId = (int) $row['user_id'];

        // ستون‌هایی که روی خودِ جدول کاربران می‌نشینند و نه در متا؛
        // این‌ها باید یک‌جا به wp_insert_user/wp_update_user برسند.
        $core = [];

        foreach ($values as $key => $value) {
            if ('user' === $columns[$key]['store']) {
                $core[$key] = $value;
            }
        }

        if ('create' === $row['action']) {
            $userId = self::createUser($values[self::KEY_COLUMN], $core);

            if (is_string($userId)) {
                return $userId;
            }
        } elseif ([] !== $core) {
            $updated = wp_update_user(array_merge(['ID' => $userId], $core));

            if (is_wp_error($updated)) {
                return (string) $updated->get_error_message();
            }
        }

        foreach ($values as $key => $value) {
            match ($columns[$key]['store']) {
                'meta' => update_user_meta($userId, $key, $value),
                'custom' => ($columns[$key]['write'])($userId, $value),
                default => null,
            };
        }

        self::syncDisplayName($userId);

        return $userId;
    }

    /**
     * @param array<string, string> $core
     * @return int|string
     */
    private static function createUser(string $nationalId, array $core)
    {
        /*
         * کاربری که نام کاربری‌اش همین کد ملی است ولی متای کد ملی را
         * ندارد — یعنی قبلاً دستی ساخته شده و فیلد هویتش پر نشده.
         * wp_insert_user این‌جا فقط «نام کاربری تکراری» می‌داد و مدیر
         * هیچ راهی برای درست کردنش نداشت جز باز کردن تک‌تک پروفایل‌ها.
         * به‌جایش همان حساب تکمیل می‌شود.
         */
        $existing = username_exists($nationalId);

        if (is_int($existing) && $existing > 0) {
            if ([] !== $core) {
                $updated = wp_update_user(array_merge(['ID' => $existing], $core));

                if (is_wp_error($updated)) {
                    return (string) $updated->get_error_message();
                }
            }

            return $existing;
        }

        $userId = wp_insert_user(array_merge([
            // نام کاربری همان کد ملی است — چیزی که کاربر از قبل بلد است
            // و در مرحلهٔ اول ورود هم همان را وارد می‌کند.
            'user_login' => $nationalId,
            // رمز عبور هیچ‌جا استفاده نمی‌شود (ورود با پیامک است)، ولی
            // خالی گذاشتنش یعنی حسابی با رمز قابل حدس. تصادفی و
            // دورانداختنی.
            'user_pass' => wp_generate_password(24, true, true),
            'role' => self::newUserRole(),
        ], $core));

        if (is_wp_error($userId)) {
            return (string) $userId->get_error_message();
        }

        return (int) $userId;
    }

    /**
     * نام نمایشی همیشه «نام نام‌خانوادگی» است.
     *
     * پیش‌فرض وردپرس نام کاربری‌ست، که این‌جا کد ملی است — یعنی بدون
     * این، سلامِ بالای سایت و ستون نام در پیشخوان هر دو یک عدد ده‌رقمی
     * نشان می‌دادند.
     */
    private static function syncDisplayName(int $userId): void
    {
        $first = (string) get_user_meta($userId, 'first_name', true);
        $last = (string) get_user_meta($userId, 'last_name', true);
        $full = trim($first . ' ' . $last);

        if ('' === $full) {
            return;
        }

        wp_update_user(['ID' => $userId, 'display_name' => $full, 'nickname' => $full]);
    }

    private static function newUserRole(): string
    {
        $role = class_exists('\WooCommerce') ? 'customer' : 'subscriber';

        return (string) apply_filters('bkw_user_sheet_new_role', $role);
    }

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

    /**
     * کد ملی، با یک تخفیف که فقط در ورودی اکسل معنا دارد.
     *
     * اکسل ستونی که فقط رقم دارد را عدد می‌فهمد و عدد صفرِ ابتدایی
     * ندارد، پس «۰۰۱۲۳۴۵۶۷۸» به «۱۲۳۴۵۶۷۸» تبدیل می‌شود. چون طول کد
     * ملی همیشه دقیقاً ده رقم است، صفرهای رفته قابل بازسازی‌اند و
     * این‌جا برمی‌گردند. Mobile_Login::normalize_national_id عمداً
     * دست‌نخورده می‌ماند: آن‌جا ورودیِ خودِ کاربر است و پرکردنِ حدسیِ
     * صفر یعنی پذیرفتن کد ملی ناقص در لحظهٔ ورود.
     */
    private static function parseIdentity(string $key, string $raw): ?string
    {
        if (self::KEY_COLUMN === $key) {
            $digits = Mobile_Login::normalize_digits($raw);
            $raw = '' !== $digits && strlen($digits) < 10 ? str_pad($digits, 10, '0', STR_PAD_LEFT) : $raw;
        }

        return Mobile_Login::normalize_field($key, $raw);
    }

    /**
     * قاعده‌ای که خودِ اکسل هنگام تایپ بررسی می‌کند.
     *
     * همان دو شرطی که سرور هم می‌گیرد (Mobile_Login::normalize و
     * normalize_national_id)، این‌بار به زبان فرمول اکسل. هدف جایگزینی
     * سنجش سرور نیست — آن همیشه حرف آخر را می‌زند — بلکه جلو آوردن
     * خطاست از «بعد از آپلود کل فایل» به «همان سلولی که تایپ شد».
     *
     * ISNUMBER(--{c}) عمداً کنار LEN آمده: ستون قالب متن دارد، پس
     * ISNUMBER به‌تنهایی روی «۰۹۱۲…» نادرست می‌شود؛ «--» آن را به عدد
     * تبدیل می‌کند و حرف و فاصله را رد می‌کند.
     *
     * @return array<string, mixed>
     */
    private static function identityRule(string $key): array
    {
        return match ($key) {
            self::KEY_COLUMN => [
                'rule' => 'OR({c}="",AND(LEN({c})=10,ISNUMBER(--{c})))',
                'rule_title' => __('کد ملی نامعتبر', 'bakery-widgets'),
                'rule_message' => __('کد ملی باید دقیقاً ۱۰ رقم باشد. صفرِ ابتدایی را حذف نکنید.', 'bakery-widgets'),
                'flag_duplicates' => true,
            ],
            Mobile_Login::META_MOBILE => [
                'rule' => 'OR({c}="",AND(LEN({c})=11,LEFT({c},2)="09",ISNUMBER(--{c})))',
                'rule_title' => __('شمارهٔ تماس نامعتبر', 'bakery-widgets'),
                'rule_message' => __('شماره باید ۱۱ رقم باشد و با ۰۹ شروع شود؛ مثل ۰۹۱۲۱۲۳۴۵۶۷.', 'bakery-widgets'),
                'flag_duplicates' => true,
            ],
            Mobile_Login::META_PERSONNEL => ['flag_duplicates' => true],
            default => [],
        };
    }

    /**
     * ستون‌ها، ترجمه‌شده به چیزی که نویسندهٔ فایل می‌فهمد.
     *
     * لایهٔ فایل نباید بداند «کد ملی» چیست؛ فقط می‌داند این ستون متن
     * است، این قاعده را دارد، و تکراری‌هایش باید قرمز شوند.
     *
     * @return array<int, SheetColumnSpec>
     */
    public static function sheetColumns(): array
    {
        $specs = [];

        foreach (self::columns() as $column) {
            $specs[] = new SheetColumnSpec(
                (string) $column['label'],
                (bool) ($column['numeric'] ?? false),
                isset($column['rule']) ? (string) $column['rule'] : null,
                (string) ($column['rule_title'] ?? ''),
                (string) ($column['rule_message'] ?? ''),
                (bool) ($column['flag_duplicates'] ?? false),
                (int) ($column['width'] ?? 22),
                (bool) ($column['locked'] ?? false),
            );
        }

        return $specs;
    }

    /** @return array<int, string> */
    private static function identityAliases(string $key): array
    {
        return match ($key) {
            Mobile_Login::META_MOBILE => ['شماره تماس', 'شماره موبایل', 'موبایل', 'تلفن همراه', 'mobile'],
            Mobile_Login::META_NATIONAL_ID => ['کدملی', 'شماره ملی', 'national_id'],
            Mobile_Login::META_PERSONNEL => ['کدپرسنلی', 'شماره پرسنلی', 'personnel_code'],
            default => [],
        };
    }

    /**
     * سرستون‌ها با اغماض مقایسه می‌شوند.
     *
     * فاصلهٔ مجازی (نیم‌فاصله) و فاصلهٔ بی‌شکست در متن فارسیِ کپی‌شده
     * فراوان‌اند و چشم فرقشان را با فاصلهٔ ساده نمی‌بیند. بدون این،
     * «نام خانوادگی» با نیم‌فاصله هیچ‌وقت به ستونش نمی‌رسید و مدیر
     * دنبال غلط املایی می‌گشت.
     */
    private static function normalizeHeader(string $raw): string
    {
        $cleaned = str_replace(["\u{200C}", "\u{200F}", "\u{200E}", "\u{00A0}"], ' ', $raw);
        $cleaned = (string) preg_replace('/\s+/u', ' ', $cleaned);

        return mb_strtolower(trim($cleaned), 'UTF-8');
    }

    private static function findUser(string $nationalId): int
    {
        $id = Mobile_Login::find_by(self::KEY_COLUMN, $nationalId);

        return null === $id ? 0 : $id;
    }

    /**
     * @param array<string, string> $values
     * @param array<int, string> $errors
     * @param array<int, array{id:int, label:string}> $similar
     * @return array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>, similar:array<int, array{id:int, label:string}>}
     */
    private static function row(int $line, string $action, int $userId, string $name, string $key, array $values, array $errors, array $similar = []): array
    {
        return [
            'line' => $line,
            'action' => $action,
            'user_id' => $userId,
            'name' => $name,
            'key' => $key,
            'values' => $values,
            'errors' => $errors,
            'similar' => $similar,
        ];
    }
}
