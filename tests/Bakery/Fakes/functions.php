<?php

declare(strict_types=1);

use Bakery_Widgets\Tests\Fakes\WordPress;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(private string $code = '', private string $message = '')
        {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $args = 1): bool
    {
        WordPress::$filters[$tag][] = $callback;

        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        foreach (WordPress::$filters[$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/', ' ', strip_tags($value)));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $value): string
    {
        return trim($value);
    }
}

if (!function_exists('is_email')) {
    function is_email(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special = true, bool $extra = false): string
    {
        return str_repeat('x', $length);
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta(int $userId, string $key = '', bool $single = false): mixed
    {
        return WordPress::$meta[$userId][$key] ?? '';
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta(int $userId, string $key, mixed $value): bool
    {
        WordPress::$meta[$userId][$key] = (string) $value;

        return true;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata(int $userId): object|false
    {
        return isset(WordPress::$users[$userId]) ? (object) WordPress::$users[$userId] : false;
    }
}

if (!function_exists('username_exists')) {
    function username_exists(string $login): int|false
    {
        foreach (WordPress::$users as $id => $user) {
            if ($user['user_login'] === $login) {
                return $id;
            }
        }

        return false;
    }
}

if (!function_exists('email_exists')) {
    function email_exists(string $email): int|false
    {
        foreach (WordPress::$users as $id => $user) {
            if ('' !== $email && $user['user_email'] === $email) {
                return $id;
            }
        }

        return false;
    }
}

if (!function_exists('get_users')) {
    function get_users(array $args = []): array
    {
        $exclude = array_map('intval', (array) ($args['exclude'] ?? []));
        $found = [];

        foreach (array_keys(WordPress::$users) as $id) {
            if (in_array($id, $exclude, true)) {
                continue;
            }

            if (isset($args['meta_key']) && WordPress::meta($id, (string) $args['meta_key']) !== (string) $args['meta_value']) {
                continue;
            }

            foreach ($args['meta_query'] ?? [] as $clause) {
                if (is_array($clause) && WordPress::meta($id, (string) $clause['key']) !== (string) $clause['value']) {
                    continue 2;
                }
            }

            $found[] = $id;

            if (isset($args['number']) && count($found) >= (int) $args['number']) {
                break;
            }
        }

        return $found;
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user(array $data): int|WP_Error
    {
        if (false !== username_exists((string) ($data['user_login'] ?? ''))) {
            return new WP_Error('existing_user_login', 'نام کاربری تکراری است.');
        }

        if ('' !== (string) ($data['user_email'] ?? '') && false !== email_exists((string) $data['user_email'])) {
            return new WP_Error('existing_user_email', 'ایمیل تکراری است.');
        }

        $id = WordPress::seedUser([
            'user_login' => (string) ($data['user_login'] ?? ''),
            'user_email' => (string) ($data['user_email'] ?? ''),
            'display_name' => (string) ($data['display_name'] ?? ($data['user_login'] ?? '')),
        ]);

        foreach (['first_name', 'last_name', 'nickname'] as $key) {
            if (isset($data[$key])) {
                update_user_meta($id, $key, (string) $data[$key]);
            }
        }

        return $id;
    }
}

if (!function_exists('wp_update_user')) {
    function wp_update_user(array $data): int|WP_Error
    {
        $id = (int) ($data['ID'] ?? 0);

        if (!isset(WordPress::$users[$id])) {
            return new WP_Error('invalid_user_id', 'کاربر یافت نشد.');
        }

        foreach (['user_email', 'display_name'] as $key) {
            if (isset($data[$key])) {
                WordPress::$users[$id][$key] = (string) $data[$key];
            }
        }

        foreach (['first_name', 'last_name', 'nickname'] as $key) {
            if (isset($data[$key])) {
                update_user_meta($id, $key, (string) $data[$key]);
            }
        }

        return $id;
    }
}

require_once __DIR__ . '/../../../includes/bakery/mobile-login.php';
require_once __DIR__ . '/../../../includes/bakery/users-sheet.php';
