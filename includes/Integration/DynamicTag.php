<?php

declare(strict_types=1);

namespace WHW\Integration;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;
use WHW\Domain\HolidayStatus;
use WHW\Service\Clock;
use WHW\Service\TodayStatus;
use WHW\Storage\HolidaysSource;
use WHW\Storage\OverrideSource;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Complementary integration only (Architecture V3 §15/V4 §10) — NOT the
 * foundation of visibility, which is Integration\Visibility. Registration
 * via `elementor/dynamic_tags/register` is a public, free-tier API
 * (core/dynamic-tags/manager.php), confirmed from source. Whether
 * Elementor Pro's native Display Conditions accept a third-party Dynamic
 * Tag as a condition value is NOT verifiable without the (closed-source)
 * Pro build, so that specific compatibility is undocumented here — this
 * tag is useful on its own regardless (any control field that accepts
 * dynamic tags, e.g. a text field), independent of that question.
 */
final class DynamicTag extends Tag
{
    public function __construct(
        private readonly HolidaysSource $holidays,
        private readonly OverrideSource $override,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    #[\Override]
    public function get_name(): string
    {
        return 'whw-today-status';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('وضعیت امروز (تعطیلات هفته)', 'weekly-holidays-widget');
    }

    #[\Override]
    public function get_group(): string
    {
        return 'whw';
    }

    #[\Override]
    public function get_categories(): array
    {
        return [TagsModule::TEXT_CATEGORY];
    }

    #[\Override]
    protected function register_controls(): void
    {
        $this->add_control('value_holiday', [
            'label' => __('متن هنگام تعطیل', 'weekly-holidays-widget'),
            'type' => Controls_Manager::TEXT,
            'default' => 'holiday',
        ]);

        $this->add_control('value_normal', [
            'label' => __('متن هنگام عادی', 'weekly-holidays-widget'),
            'type' => Controls_Manager::TEXT,
            'default' => 'normal',
        ]);
    }

    #[\Override]
    public function render(): void
    {
        $resolver = new TodayStatus($this->holidays, $this->override);
        $status = $resolver->resolve(Clock::now());

        $value = HolidayStatus::Holiday === $status
            ? (string) $this->get_settings_for_display('value_holiday')
            : (string) $this->get_settings_for_display('value_normal');

        echo esc_html($value);
    }
}
