<?php

declare(strict_types=1);

namespace Bakery_Widgets\Tests;

use Bakery_Sheet\Number;
use Bakery_Sheet\Reader;
use Bakery_Sheet\Writer;
use Bakery_Widgets\Mobile_Login;
use Bakery_Widgets\Tests\Fakes\WordPress;
use Bakery_Widgets\Users_Sheet;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes/functions.php';

/**
 * ورودی اکسل کاربران، روی یک وردپرس ساختگی.
 *
 * سناریوها از همان چیزهایی آمده‌اند که در عمل خراب می‌شوند: اکسل صفر
 * ابتدایی را می‌خورد، مدیر یک سطر را کپی می‌کند و شماره‌اش را عوض
 * نمی‌کند، ستونی را خالی می‌گذارد و انتظار ندارد چیزی پاک شود.
 */
final class UsersSheetTest extends TestCase
{
    private const HEADER = ['نام', 'نام خانوادگی', 'شماره تماس', 'کد ملی', 'کد پرسنلی'];

    protected function setUp(): void
    {
        WordPress::reset();
    }

    /** @param array<int, array<int, string>> $rows */
    private function plan(array $rows, array $header = self::HEADER, array $resolutions = []): array
    {
        return Users_Sheet::plan(array_merge([$header], $rows), $resolutions);
    }

    private function apply(array $rows, array $header = self::HEADER, array $resolutions = []): array
    {
        $plan = $this->plan($rows, $header, $resolutions);

        foreach ($plan['rows'] as $row) {
            if ('error' !== $row['action']) {
                Users_Sheet::apply($row);
            }
        }

        return $plan;
    }

    /* ------------------------------------------------------------ سنجش */

    public function test_a_row_for_an_unknown_national_id_creates_a_user(): void
    {
        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', 'A-1']]);

        self::assertSame('', $plan['fatal']);
        self::assertSame('create', $plan['rows'][0]['action']);
        self::assertSame([], $plan['rows'][0]['errors']);
    }

    public function test_a_row_matching_an_existing_national_id_updates_that_user(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678'], [Mobile_Login::META_NATIONAL_ID => '0012345678']);

        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', '']]);

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame($id, $plan['rows'][0]['user_id']);
    }

    /**
     * اکسل ستونی که فقط رقم دارد را عدد می‌فهمد و صفرهای ابتدایی را
     * می‌اندازد. چون طول کد ملی همیشه ده رقم است، این‌جا برمی‌گردند.
     */
    public function test_excel_stripping_leading_zeros_from_a_national_id_is_undone(): void
    {
        $plan = $this->plan([['علی', 'رضایی', '09121234567', '12345678', '']]);

        self::assertSame([], $plan['rows'][0]['errors']);
        self::assertSame('0012345678', $plan['rows'][0]['values'][Mobile_Login::META_NATIONAL_ID]);
    }

    public function test_a_national_id_that_is_too_long_is_refused(): void
    {
        $plan = $this->plan([['علی', 'رضایی', '09121234567', '00123456789012', '']]);

        self::assertSame('error', $plan['rows'][0]['action']);
    }

    public function test_a_row_without_a_national_id_is_an_error_not_a_new_user(): void
    {
        $plan = $this->plan([['علی', 'رضایی', '09121234567', '', '']]);

        self::assertSame('error', $plan['rows'][0]['action']);
    }

    public function test_a_file_with_neither_key_column_is_refused_whole(): void
    {
        $plan = $this->plan([['علی', 'رضایی']], ['نام', 'نام خانوادگی']);

        self::assertNotSame('', $plan['fatal']);
        self::assertSame([], $plan['rows']);
    }

    /** مدیر یک سطر را کپی می‌کند و یادش می‌رود شماره را عوض کند. */
    public function test_two_rows_sharing_a_mobile_number_collide_with_each_other(): void
    {
        $plan = $this->plan([
            ['علی', 'رضایی', '09121234567', '0012345678', ''],
            ['مریم', 'احمدی', '09121234567', '1234567890', ''],
        ]);

        self::assertSame('create', $plan['rows'][0]['action']);
        self::assertSame('error', $plan['rows'][1]['action']);
        self::assertStringContainsString('سطر 2', $plan['rows'][1]['errors'][0]);
    }

    /**
     * شمارهٔ تماس هم کلید است، پس سطری که فقط با آن می‌خورد کاربر تازه
     * نمی‌سازد — همان کاربر را کامل می‌کند.
     */
    public function test_a_row_found_only_by_its_phone_number_updates_that_user(): void
    {
        $id = WordPress::seedUser(['user_login' => 'other'], [Mobile_Login::META_MOBILE => '09121234567']);

        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', '']]);

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame($id, $plan['rows'][0]['user_id']);
    }

    /** همان شماره روی همان کاربر، تکراری نیست. */
    public function test_a_user_keeping_their_own_mobile_number_is_not_a_duplicate(): void
    {
        WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
        ]);

        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', '']]);

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame([], $plan['rows'][0]['errors']);
    }

    public function test_a_new_user_without_a_name_is_refused(): void
    {
        $plan = $this->plan([['', '', '09121234567', '0012345678', '']]);

        self::assertSame('error', $plan['rows'][0]['action']);
    }

    /** برای کاربر موجود، ستون خالی یعنی «عوض نکن» و نه «پاک کن». */
    public function test_an_existing_user_may_be_updated_with_most_columns_blank(): void
    {
        WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            'first_name' => 'علی',
        ]);

        $plan = $this->plan([['', '', '', '0012345678', 'A-9']]);

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame([], $plan['rows'][0]['errors']);
    }

    /** شمارهٔ موبایل با ارقام فارسی و پیشوند +۹۸ همان شمارهٔ همیشگی است. */
    public function test_persian_digits_and_country_prefixes_normalise(): void
    {
        $plan = $this->plan([['علی', 'رضایی', '+۹۸۹۱۲۱۲۳۴۵۶۷', '۰۰۱۲۳۴۵۶۷۸', '']]);

        self::assertSame([], $plan['rows'][0]['errors']);
        self::assertSame('09121234567', $plan['rows'][0]['values'][Mobile_Login::META_MOBILE]);
        self::assertSame('0012345678', $plan['rows'][0]['values'][Mobile_Login::META_NATIONAL_ID]);
    }

    /* ----------------------------------------------------- تطبیق دو کلیدی */

    /**
     * دلیل وجودِ تطبیق دو کلیدی.
     *
     * تا وقتی فقط کد ملی کلید بود، کد ملیِ اشتباهِ ثبت‌شده از راه فایل
     * اصلاح‌شدنی نبود: مدیر درستش می‌کرد و نتیجه یک کاربر *تازه* بود،
     * چون سطر دیگر به هیچ‌کس نمی‌خورد. حالا شمارهٔ تماس همان سطر را پیدا
     * می‌کند و کد ملی فقط یک مقدار است که نوشته می‌شود.
     */
    public function test_a_national_id_can_be_corrected_because_the_phone_still_matches(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        $plan = $this->apply([['علی', 'رضایی', '09121234567', '9998887776', '']]);

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame([], $plan['rows'][0]['errors']);
        self::assertCount(1, WordPress::$users);
        self::assertSame('9998887776', WordPress::meta($id, Mobile_Login::META_NATIONAL_ID));
    }

    /** و در جهت مخالف: شمارهٔ عوض‌شده با کد ملی پیدا می‌شود. */
    public function test_a_phone_number_can_be_corrected_because_the_national_id_still_matches(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
        ]);

        $this->apply([['علی', 'رضایی', '09350000000', '0012345678', '']]);

        self::assertCount(1, WordPress::$users);
        self::assertSame('09350000000', WordPress::meta($id, Mobile_Login::META_MOBILE));
    }

    /**
     * کد ملی به یک نفر می‌خورد و شماره به یکی دیگر.
     *
     * حدس‌زدن این‌جا خطرناک است — هر انتخابی یعنی نوشتن روی حسابی که
     * ممکن است اشتباه باشد. معمول‌ترین علتش جابه‌جا شدن دو سطر است.
     */
    public function test_a_row_pointing_at_two_different_users_is_refused(): void
    {
        WordPress::seedUser(['user_login' => 'a', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
        ]);
        WordPress::seedUser(['user_login' => 'b', 'display_name' => 'مریم احمدی'], [
            Mobile_Login::META_MOBILE => '09121234567',
        ]);

        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', '']]);

        self::assertSame('error', $plan['rows'][0]['action']);
        self::assertStringContainsString('علی رضایی', $plan['rows'][0]['errors'][0]);
        self::assertStringContainsString('مریم احمدی', $plan['rows'][0]['errors'][0]);
    }

    /**
     * دو سطری که شماره‌هایشان جابه‌جا تایپ شده — هر دو رد می‌شوند و هیچ
     * حسابی روی دیگری نوشته نمی‌شود.
     */
    public function test_two_rows_with_swapped_phone_numbers_are_both_refused(): void
    {
        WordPress::seedUser(['user_login' => 'a', 'display_name' => 'علی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121111111',
        ]);
        WordPress::seedUser(['user_login' => 'b', 'display_name' => 'مریم'], [
            Mobile_Login::META_NATIONAL_ID => '1234567890',
            Mobile_Login::META_MOBILE => '09122222222',
        ]);

        $plan = $this->plan([
            ['علی', 'رضایی', '09122222222', '0012345678', ''],
            ['مریم', 'احمدی', '09121111111', '1234567890', ''],
        ]);

        self::assertSame('error', $plan['rows'][0]['action']);
        self::assertSame('error', $plan['rows'][1]['action']);
    }

    /** کد پرسنلی کلید نیست: مال سازمان است و می‌تواند به کارمند تازه برسد. */
    public function test_a_personnel_code_alone_never_matches_an_existing_user(): void
    {
        WordPress::seedUser(['user_login' => 'old'], [
            Mobile_Login::META_NATIONAL_ID => '1111111111',
            Mobile_Login::META_PERSONNEL => 'A-1',
        ]);

        $plan = $this->plan([['رضا', 'کریمی', '09359999999', '2222222222', 'A-2']]);

        self::assertSame('create', $plan['rows'][0]['action']);
    }

    /** فایل هیچ ستون شناسه‌ای ندارد؛ روی هر نصبی همان معنا را می‌دهد. */
    public function test_the_export_carries_no_internal_identifier(): void
    {
        WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
        ]);

        $labels = array_map(
            static fn ($column): string => $column->label,
            Users_Sheet::sheetColumns()
        );

        self::assertNotContains('شناسه', $labels);
        self::assertSame('نام', $labels[0]);
        self::assertSame('0012345678', Users_Sheet::exportRows()[0][3]);
    }

    /* ------------------------------------- وقتی هر دو کلید عوض شده‌اند */

    /**
     * هر دو کلید یک نفر در یک ویرایش عوض شده‌اند.
     *
     * هیچ نخی بین سطر و رکورد قبلی نمانده، پس تطبیق خودکار ممکن نیست.
     * ولی «آدم تازه» هم قطعی نیست — و بی‌صدا ساختن کاربر دوم یعنی یک
     * نفر دوبار در سایت باشد و رکورد قبلی‌اش یتیم بماند. پس سطر با
     * کاربرِ شبیه به خودش نشان داده می‌شود تا مدیر تصمیم بگیرد.
     */
    public function test_changing_both_keys_at_once_surfaces_the_user_it_probably_means(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        $plan = $this->plan([['علی', 'رضایی', '09350000000', '9998887776', '']]);

        self::assertSame('create', $plan['rows'][0]['action']);
        self::assertArrayHasKey($id, $plan['rows'][0]['similar']);
        self::assertStringContainsString('علی رضایی', $plan['rows'][0]['similar'][$id]['label']);
        // برچسب باید کلیدهای قبلی را هم بگوید، وگرنه دو هم‌نام قابل
        // تشخیص نیستند — و اصلاً به‌خاطر هم‌نامی پیشنهاد شده‌اند.
        self::assertStringContainsString('0012345678', $plan['rows'][0]['similar'][$id]['label']);
    }

    /** مدیر می‌گوید «همان کاربر است» و هر دو کلید روی همان رکورد می‌نشینند. */
    public function test_the_admin_can_say_it_is_the_same_person_and_both_keys_move(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        $plan = $this->apply(
            [['علی', 'رضایی', '09350000000', '9998887776', '']],
            self::HEADER,
            [2 => $id]
        );

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertCount(1, WordPress::$users);
        self::assertSame('9998887776', WordPress::meta($id, Mobile_Login::META_NATIONAL_ID));
        self::assertSame('09350000000', WordPress::meta($id, Mobile_Login::META_MOBILE));
    }

    /** و اگر واقعاً آدم تازه‌ای باشد، پیش‌فرض همان ساختِ کاربر است. */
    public function test_declining_the_suggestion_creates_the_new_user(): void
    {
        WordPress::seedUser(['user_login' => '0012345678', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        $this->apply([['علی', 'رضایی', '09350000000', '9998887776', '']]);

        self::assertCount(2, WordPress::$users);
    }

    /**
     * انتخابِ دست‌کاری‌شده در فرم نباید هیچ کاربری را هدف بگیرد.
     *
     * فرم HTML است و هرکسی می‌تواند عددش را عوض کند؛ تنها دفاع این است
     * که سنجش فقط انتخابی را بپذیرد که *خودش* برای همان سطر پیشنهاد
     * داده باشد.
     */
    public function test_a_resolution_the_plan_never_offered_is_ignored(): void
    {
        $victim = WordPress::seedUser(['user_login' => 'other', 'display_name' => 'مریم احمدی'], [
            Mobile_Login::META_NATIONAL_ID => '1111111111',
            Mobile_Login::META_MOBILE => '09129999999',
            'first_name' => 'مریم',
            'last_name' => 'احمدی',
        ]);

        $plan = $this->apply(
            [['رضا', 'کریمی', '09350000000', '9998887776', '']],
            self::HEADER,
            [2 => $victim]
        );

        self::assertSame('create', $plan['rows'][0]['action']);
        self::assertSame('1111111111', WordPress::meta($victim, Mobile_Login::META_NATIONAL_ID));
        self::assertCount(2, WordPress::$users);
    }

    /** کاربری که سطر خودش را در فایل دارد یتیم نیست و پیشنهاد نمی‌شود. */
    public function test_a_user_the_file_already_accounts_for_is_not_offered(): void
    {
        WordPress::seedUser(['user_login' => '0012345678', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        // سطر اول همان کاربر است؛ سطر دوم یک هم‌نامِ واقعاً تازه.
        $plan = $this->plan([
            ['علی', 'رضایی', '09121234567', '0012345678', ''],
            ['علی', 'رضایی', '09350000000', '9998887776', ''],
        ]);

        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame('create', $plan['rows'][1]['action']);
        self::assertSame([], $plan['rows'][1]['similar']);
    }

    /** کد پرسنلی هم نشانه است — حتی وقتی نام‌ها فرق دارند. */
    public function test_a_shared_personnel_code_is_enough_to_raise_the_question(): void
    {
        $id = WordPress::seedUser(['user_login' => 'old', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            Mobile_Login::META_PERSONNEL => 'A-1',
        ]);

        $plan = $this->plan([['علی', 'رضاییان', '09350000000', '9998887776', 'A-1']]);

        self::assertArrayHasKey($id, $plan['rows'][0]['similar']);
    }

    /* ---------------------------------------------------------- سرستون */

    public function test_headers_match_through_aliases_and_zero_width_spaces(): void
    {
        $header = ['نام', "نام\u{200C}خانوادگی", 'موبایل', 'کدملی', 'کد پرسنلی'];

        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', 'A-1']], $header);

        self::assertSame([], $plan['rows'][0]['errors']);
        self::assertSame('رضایی', $plan['rows'][0]['values']['last_name']);
    }

    public function test_an_unknown_extra_column_is_ignored_rather_than_fatal(): void
    {
        $header = array_merge(self::HEADER, ['توضیحات واحد']);

        $plan = $this->plan([['علی', 'رضایی', '09121234567', '0012345678', '', 'هرچیزی']], $header);

        self::assertSame('create', $plan['rows'][0]['action']);
    }

    /* ----------------------------------------------------------- نوشتن */

    public function test_a_created_user_is_named_by_their_national_id_and_shown_by_their_full_name(): void
    {
        $this->apply([['علی', 'رضایی', '09121234567', '0012345678', 'A-1']]);

        $id = username_exists('0012345678');

        self::assertIsInt($id);
        self::assertSame('علی رضایی', WordPress::$users[$id]['display_name']);
        self::assertSame('09121234567', WordPress::meta($id, Mobile_Login::META_MOBILE));
        self::assertSame('A-1', WordPress::meta($id, Mobile_Login::META_PERSONNEL));
        self::assertSame('0012345678', WordPress::meta($id, Mobile_Login::META_NATIONAL_ID));
    }

    /** ستون خالی روی کاربر موجود هیچ چیزی را پاک نمی‌کند. */
    public function test_blank_cells_never_erase_what_the_user_already_has(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678', 'display_name' => 'علی رضایی'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            Mobile_Login::META_PERSONNEL => 'A-1',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        $this->apply([['', '', '', '0012345678', '']]);

        self::assertSame('09121234567', WordPress::meta($id, Mobile_Login::META_MOBILE));
        self::assertSame('A-1', WordPress::meta($id, Mobile_Login::META_PERSONNEL));
        self::assertSame('علی', WordPress::meta($id, 'first_name'));
    }

    /**
     * حسابی که قبلاً دستی ساخته شده و نام کاربری‌اش کد ملی است ولی متای
     * کد ملی را ندارد. wp_insert_user این‌جا فقط «نام کاربری تکراری»
     * می‌داد و مدیر هیچ راهی جز باز کردن تک‌تک پروفایل‌ها نداشت.
     */
    public function test_an_account_that_predates_the_identity_fields_is_completed_not_refused(): void
    {
        $id = WordPress::seedUser(['user_login' => '0012345678']);

        $this->apply([['علی', 'رضایی', '09121234567', '0012345678', '']]);

        self::assertCount(1, WordPress::$users);
        self::assertSame('0012345678', WordPress::meta($id, Mobile_Login::META_NATIONAL_ID));
        self::assertSame('علی رضایی', WordPress::$users[$id]['display_name']);
    }

    public function test_the_second_import_of_the_same_file_changes_nothing(): void
    {
        $rows = [['علی', 'رضایی', '09121234567', '0012345678', 'A-1']];

        $this->apply($rows);
        $first = WordPress::$users;

        $second = $this->apply($rows);

        self::assertSame($first, WordPress::$users);
        self::assertSame('update', $second['rows'][0]['action']);
        self::assertSame([], $second['rows'][0]['errors']);
    }

    /* ------------------------------------------------- رفت‌وبرگشت کامل */

    /**
     * مدیر سایت و حساب‌های سرویس کد ملی ندارند و در خروجی نمی‌آیند.
     *
     * وگرنه همان فایل موقع برگشتن، به‌ازای هرکدام یک سطر «کد ملی خالی
     * است» می‌داد — خطایی که مدیر هیچ کاری برایش نمی‌تواند بکند و هر
     * بار باید نادیده‌اش بگیرد.
     */
    public function test_a_user_without_a_national_id_is_left_out_of_the_export(): void
    {
        WordPress::seedUser(['user_login' => 'admin'], ['first_name' => 'مدیر']);
        WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            'first_name' => 'علی',
        ]);

        $rows = Users_Sheet::exportRows();

        self::assertCount(1, $rows);
        self::assertContains('0012345678', $rows[0]);
    }

    /**
     * وعده‌ای که صفحهٔ ادمین می‌دهد: «خروجی بگیر، در اکسل ویرایش کن،
     * دوباره وارد کن.» این تست همان را از سر تا ته اجرا می‌کند — سرستون
     * خروجی باید همانی باشد که ورودی می‌شناسد، و کد ملیِ صفردار باید از
     * فایل xlsx دست‌نخورده برگردد.
     */
    public function test_an_exported_file_is_understood_by_the_importer(): void
    {
        if (!Writer::canWriteXlsx()) {
            self::markTestSkipped('افزونهٔ zip در دسترس نیست.');
        }

        WordPress::seedUser(['user_login' => '0012345678'], [
            Mobile_Login::META_NATIONAL_ID => '0012345678',
            Mobile_Login::META_MOBILE => '09121234567',
            Mobile_Login::META_PERSONNEL => '007',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'bkw') . '.xlsx';

        try {
            Writer::xlsx($path, Users_Sheet::sheetColumns(), Users_Sheet::exportRows(), 'کاربران');
            $plan = Users_Sheet::plan(Reader::grid($path, 'xlsx'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::assertSame('', $plan['fatal']);
        self::assertCount(1, $plan['rows']);
        self::assertSame('update', $plan['rows'][0]['action']);
        self::assertSame([], $plan['rows'][0]['errors']);
        self::assertSame('0012345678', $plan['rows'][0]['values'][Mobile_Login::META_NATIONAL_ID]);
        self::assertSame('007', $plan['rows'][0]['values'][Mobile_Login::META_PERSONNEL]);
    }

    /* ------------------------------------------------------------ ستون */

    /** ستونی که ماژول اعتبار با فیلتر اضافه می‌کند، در همان مسیر می‌نشیند. */
    public function test_a_filtered_column_is_read_written_and_matched_like_the_rest(): void
    {
        $written = [];

        add_filter('bkw_user_sheet_columns', static function (array $columns) use (&$written): array {
            $columns['bkw_credit_allowance'] = [
                'label' => 'سقف اعتبار',
                'aliases' => ['اعتبار'],
                'store' => 'custom',
                'required' => false,
                'unique' => false,
                'hint' => '',
                'read' => static fn (int $id): string => '0',
                'parse' => static fn (string $raw): ?string => Number::amount($raw),
                'write' => static function (int $id, string $value) use (&$written): void {
                    $written[$id] = $value;
                },
            ];

            return $columns;
        });

        $this->apply(
            [['علی', 'رضایی', '09121234567', '0012345678', '', '۱٬۲۰۰٬۰۰۰']],
            array_merge(self::HEADER, ['اعتبار'])
        );

        self::assertSame(['1200000'], array_values($written));
    }
}
