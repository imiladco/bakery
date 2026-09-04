<?php

declare(strict_types=1);

namespace Bakery_Widgets;

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

            $columns[$key] = [
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
            ];
        }

        /**
         * ستون‌های بیرونی — امروز فقط سقف اعتبار.
         *
         * @param array<string, array<string, mixed>> $columns
         */
        return (array) apply_filters('bkw_user_sheet_columns', $columns);
    }

    /** @return array<int, string> سرستون‌های فایل، به همان ترتیب ستون‌ها */
    public static function header(): array
    {
        return array_map(
            static fn (array $column): string => (string) $column['label'],
            array_values(self::columns())
        );
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
    public static function plan(array $grid): array
    {
        $header = $grid[0] ?? [];
        $map = self::mapHeader($header);
        $columns = self::columns();

        if (!in_array(self::KEY_COLUMN, $map, true)) {
            return [
                'columns' => [],
                'rows' => [],
                'fatal' => sprintf(
                    /* translators: %s: column label */
                    __('ستون «%s» در فایل پیدا نشد. بدون آن معلوم نیست هر سطر مال کدام کاربر است. یک بار خروجی بگیرید و همان فایل را ویرایش کنید.', 'bakery-widgets'),
                    $columns[self::KEY_COLUMN]['label']
                ),
            ];
        }

        $rows = [];
        // کلیدهای دیده‌شده در خودِ فایل: دو سطر با یک کد ملی یا یک شمارهٔ
        // موبایل، تکراری‌اند حتی اگر هیچ‌کدام هنوز در دیتابیس نباشند.
        $seen = [];

        foreach ($grid as $index => $cells) {
            if (0 === $index || \Bakery_Sheet\Reader::isBlank($cells)) {
                continue;
            }

            $rows[] = self::planRow($index + 1, $cells, $map, $columns, $seen);
        }

        return ['columns' => array_values($map), 'rows' => $rows, 'fatal' => ''];
    }

    /**
     * @param array<int, string> $cells
     * @param array<int, string> $map
     * @param array<string, array<string, mixed>> $columns
     * @param array<string, array<string, int>> $seen
     * @return array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>}
     */
    private static function planRow(int $line, array $cells, array $map, array $columns, array &$seen): array
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

        $nationalId = $values[self::KEY_COLUMN] ?? '';

        if ('' === $nationalId) {
            return self::row($line, 'error', 0, '', '', $values, array_merge($errors, [
                sprintf(
                    /* translators: %s: column label */
                    __('ستون «%s» خالی است؛ این سطر به هیچ کاربری وصل نمی‌شود.', 'bakery-widgets'),
                    $columns[self::KEY_COLUMN]['label']
                ),
            ]));
        }

        $userId = self::findUser($nationalId);
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
            return self::row($line, 'error', $userId, $name, $nationalId, $values, $errors);
        }

        return self::row($line, $isNew ? 'create' : 'update', $userId, $name, $nationalId, $values, []);
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
     * @return array{line:int, action:string, user_id:int, name:string, key:string, values:array<string,string>, errors:array<int,string>}
     */
    private static function row(int $line, string $action, int $userId, string $name, string $key, array $values, array $errors): array
    {
        return [
            'line' => $line,
            'action' => $action,
            'user_id' => $userId,
            'name' => $name,
            'key' => $key,
            'values' => $values,
            'errors' => $errors,
        ];
    }
}
