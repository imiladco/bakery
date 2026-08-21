<?php

declare(strict_types=1);

namespace WHW\Admin;

use DateTimeImmutable;
use WHW\Domain\JalaliDate;
use WHW\Domain\OverrideState;
use WHW\Service\Clock;
use WHW\Storage\Holidays;
use WHW\Storage\Override;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * `whw/v1` REST routes for the admin calendar's two mutations. REST was
 * chosen over admin-ajax for its built-in per-argument schema validation
 * (Architecture V3 §13/V4) — not because REST is inherently more secure;
 * security here comes entirely from permission_callback + nonce +
 * sanitize/validate, all present on both routes. "Today" for the override
 * route is always computed server-side via Clock::now(), never trusted
 * from the client.
 */
final class Rest
{
    private const NAMESPACE = 'whw/v1';
    private const CAPABILITY = 'manage_options';

    public function __construct(
        private readonly Holidays $holidays,
        private readonly Override $override,
        private readonly Page $page,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/holidays/toggle', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'toggleHoliday'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'jalali_year' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => static fn ($value): bool => is_numeric($value) && (int) $value >= 1300 && (int) $value <= 1500,
                    'sanitize_callback' => 'absint',
                ],
                'jalali_month' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => static fn ($value): bool => is_numeric($value) && (int) $value >= 1 && (int) $value <= 12,
                    'sanitize_callback' => 'absint',
                ],
                'day' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => static fn ($value): bool => is_numeric($value) && (int) $value >= 1 && (int) $value <= 31,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/calendar', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'calendar'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'jalali_year' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => static fn ($value): bool => is_numeric($value) && (int) $value >= 1300 && (int) $value <= 1500,
                    'sanitize_callback' => 'absint',
                ],
                'jalali_month' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => static fn ($value): bool => is_numeric($value) && (int) $value >= 1 && (int) $value <= 12,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/override', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'setOverride'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'state' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => static fn ($value): bool => null !== OverrideState::tryFrom((string) $value),
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can(self::CAPABILITY);
    }

    public function toggleHoliday(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $jalaliYear = (int) $request->get_param('jalali_year');
        $jalaliMonth = (int) $request->get_param('jalali_month');
        $day = (int) $request->get_param('day');

        try {
            $daysInMonth = (new JalaliDate($jalaliYear, $jalaliMonth, 1))->daysInMonth();
        } catch (\InvalidArgumentException) {
            return new WP_Error('whw_invalid_month', __('ماه شمسی نامعتبر است.', 'weekly-holidays-widget'), ['status' => 400]);
        }

        if ($day > $daysInMonth) {
            return new WP_Error('whw_invalid_day', __('روز خارج از بازه‌ی این ماه است.', 'weekly-holidays-widget'), ['status' => 400]);
        }

        $isHoliday = $this->holidays->toggleDay($jalaliYear, $jalaliMonth, $day);

        return new WP_REST_Response(['holiday' => $isHoliday], 200);
    }

    public function calendar(WP_REST_Request $request): WP_REST_Response
    {
        $jalaliYear = (int) $request->get_param('jalali_year');
        $jalaliMonth = (int) $request->get_param('jalali_month');

        $url = add_query_arg(
            ['page' => Page::SLUG, 'whw_y' => $jalaliYear, 'whw_m' => $jalaliMonth],
            admin_url('admin.php'),
        );

        return new WP_REST_Response([
            'calendar_html' => $this->page->renderCalendarFragment($jalaliYear, $jalaliMonth),
            'official_html' => $this->page->renderOfficialHolidaysFragment($jalaliYear, $jalaliMonth),
            'url' => $url,
        ], 200);
    }

    public function setOverride(WP_REST_Request $request): WP_REST_Response
    {
        $state = OverrideState::from((string) $request->get_param('state'));
        $today = $this->todayDate();

        if (OverrideState::Unset === $state) {
            $this->override->clear();
        } else {
            $this->override->set($today, $state);
        }

        return new WP_REST_Response([
            'state' => $state->value,
            'date' => $today->format('Y-m-d'),
        ], 200);
    }

    private function todayDate(): DateTimeImmutable
    {
        return Clock::now();
    }
}
