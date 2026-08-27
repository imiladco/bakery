<?php

declare(strict_types=1);

namespace Bakery_Credit\Admin;

use Bakery_Credit\Domain\Period;
use Bakery_Credit\Service\CreditAccount;
use Bakery_Credit\Storage\Allowance;
use WHW\Service\Clock;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فیلد «سقف اعتبار ماهانه» در صفحهٔ ویرایش کاربر، به‌همراه وضعیت زندهٔ
 * همین ماه و تاریخچهٔ تغییرات سقف.
 *
 * تاریخچه این‌جا نمایش داده می‌شود چون سقف یک عدد تکی است و با هر تغییر
 * مقدار قبلی از بین می‌رود؛ بدون این جدول، «کی این را عوض کرد و از چند
 * به چند» هیچ جوابی ندارد.
 */
final class UserField
{
    public function __construct(
        private readonly Allowance $allowances,
        private readonly CreditAccount $account,
    ) {
    }

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'render']);
        add_action('edit_user_profile', [$this, 'render']);
        add_action('personal_options_update', [$this, 'save']);
        add_action('edit_user_profile_update', [$this, 'save']);
    }

    public function render(WP_User $user): void
    {
        if (!current_user_can('edit_users')) {
            return;
        }

        $now = Clock::now();
        $balance = $this->account->balance((int) $user->ID, $now);
        $period = Period::fromDate($now);

        ?>
        <h2><?php esc_html_e('اعتبار ماهانه', 'bakery-widgets'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="bkw_credit_allowance"><?php esc_html_e('سقف ماهانه', 'bakery-widgets'); ?></label></th>
                <td>
                    <input type="number" step="any" min="0" name="bkw_credit_allowance" id="bkw_credit_allowance"
                           value="<?php echo esc_attr((string) $balance->allowance); ?>" class="regular-text">
                    <p class="description">
                        <?php esc_html_e('صفر یعنی این کاربر نمی‌تواند خرید کند. تغییر سقف از همین لحظه اثر می‌کند و ماه‌های بعد هم برقرار می‌ماند.', 'bakery-widgets'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('وضعیت این ماه', 'bakery-widgets'); ?></th>
                <td>
                    <p>
                        <?php
                        printf(
                            /* translators: 1: Jalali period, 2: consumed, 3: remaining */
                            esc_html__('دورهٔ %1$s — مصرف‌شده: %2$s، باقی‌مانده: %3$s', 'bakery-widgets'),
                            esc_html($period->key()),
                            wp_kses_post(wc_price($balance->consumed)),
                            wp_kses_post(wc_price($balance->remaining()))
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <?php $log = $this->allowances->changeLog((int) $user->ID); ?>
            <?php if (!empty($log)) : ?>
                <tr>
                    <th><?php esc_html_e('تاریخچهٔ تغییر سقف', 'bakery-widgets'); ?></th>
                    <td>
                        <table class="widefat striped" style="max-width:640px">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('تاریخ', 'bakery-widgets'); ?></th>
                                    <th><?php esc_html_e('از', 'bakery-widgets'); ?></th>
                                    <th><?php esc_html_e('به', 'bakery-widgets'); ?></th>
                                    <th><?php esc_html_e('توسط', 'bakery-widgets'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($log as $entry) : ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($entry['at'] ?? '')); ?></td>
                                        <td><?php echo wp_kses_post(wc_price((float) ($entry['from'] ?? 0))); ?></td>
                                        <td><?php echo wp_kses_post(wc_price((float) ($entry['to'] ?? 0))); ?></td>
                                        <td>
                                            <?php
                                            $actor = get_userdata((int) ($entry['by'] ?? 0));
                                            echo esc_html($actor ? $actor->display_name : '—');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
        wp_nonce_field('bkw_credit_allowance_' . $user->ID, 'bkw_credit_nonce');
    }

    public function save(int $userId): void
    {
        if (!current_user_can('edit_users') || !isset($_POST['bkw_credit_allowance'])) {
            return;
        }

        $nonce = isset($_POST['bkw_credit_nonce']) ? sanitize_text_field(wp_unslash($_POST['bkw_credit_nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'bkw_credit_allowance_' . $userId)) {
            return;
        }

        $this->allowances->set(
            $userId,
            (float) wc_format_decimal(wp_unslash($_POST['bkw_credit_allowance'])),
            get_current_user_id()
        );
    }
}
