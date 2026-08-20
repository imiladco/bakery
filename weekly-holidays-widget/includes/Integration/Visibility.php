<?php

declare(strict_types=1);

namespace WHW\Integration;

use DateTimeImmutable;
use Elementor\Controls_Manager;
use Elementor\Element_Base;
use Elementor\Plugin as ElementorPlugin;
use WHW\Domain\HolidayStatus;
use WHW\Service\Clock;
use WHW\Service\TodayStatus;
use WHW\Storage\HolidaysSource;
use WHW\Storage\OverrideSource;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "WHW Visibility Condition" — deliberately not named a native Elementor
 * Pro Display Condition, because no publicly verifiable API for
 * registering a custom Pro condition type exists in the inspected core
 * source (elementor/elementor stable 3.35.x / dev 4.3.0). This is our own
 * implementation, built entirely on public, documented, stable core APIs:
 *
 *  - Control injection: `elementor/element/{stack}/{section}/before_section_end`
 *    (controls-stack.php) — same mechanism the core's own Cache Settings
 *    and (Pro-gated) Display Conditions promo control use.
 *  - Render gate: `elementor/frontend/{type}/should_render`, a public
 *    filter documented since 2.3.3 (element-base.php). It reliably
 *    suppresses the final emitted HTML but does not prevent the element's
 *    own render-time computation, since content is buffered via ob_start()
 *    before this filter runs — see filterShouldRender()'s docblock.
 *  - Cache exemption: `elementor/element/is_dynamic_content` — makes
 *    Elementor's built-in element cache (`_elementor_element_cache`,
 *    active by default) skip elements this condition is active on, so a
 *    cached page never serves a stale visibility decision.
 */
final class Visibility
{
    private const string CONTROL_NAME = 'whw_visibility';

    /** @var array<string, string> stack name => the Advanced-tab section id to attach to */
    private const array INJECTION_POINTS = [
        'common' => '_section_style',   // every Widget_Base (shared common controls)
        'section' => 'section_advanced',
        'column' => 'section_advanced',
        'container' => 'section_layout',
    ];

    private const array RENDER_TYPES = ['widget', 'section', 'column', 'container'];

    private ?HolidayStatus $todayCache = null;

    public function __construct(
        private readonly HolidaysSource $holidays,
        private readonly OverrideSource $override,
    ) {
    }

    public function register(): void
    {
        foreach (self::INJECTION_POINTS as $stackName => $sectionId) {
            add_action(
                "elementor/element/{$stackName}/{$sectionId}/before_section_end",
                [$this, 'injectControl'],
                10,
                2,
            );
        }

        foreach (self::RENDER_TYPES as $type) {
            add_filter("elementor/frontend/{$type}/should_render", [$this, 'filterShouldRender'], 10, 2);
        }

        add_filter('elementor/element/is_dynamic_content', [$this, 'filterIsDynamicContent'], 10, 2);
    }

    /** @param array<string, mixed> $args */
    public function injectControl(Element_Base $element, array $args): void
    {
        $element->add_control(self::CONTROL_NAME, [
            'label' => __('نمایش شرطی تعطیلات هفته', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SELECT,
            'separator' => 'before',
            'default' => '',
            'options' => [
                '' => __('غیرفعال', 'weekly-holidays-widget'),
                'holiday' => __('فقط وقتی امروز تعطیل است نمایش بده', 'weekly-holidays-widget'),
                'normal' => __('فقط وقتی امروز عادی است نمایش بده', 'weekly-holidays-widget'),
            ],
            'description' => __('این تنظیم صرفاً نمایشی است، نه یک مکانیزم کنترل دسترسی؛ محتوای مخفی‌شده در ادیتور المنتور همچنان قابل ویرایش می‌ماند.', 'weekly-holidays-widget'),
        ]);
    }

    /**
     * Cooperative gate: never re-enables an element another plugin, Pro,
     * or Elementor core already decided to hide. Skips entirely inside
     * the editor/preview so a conditionally-hidden element stays visible
     * and editable while building the page — suppression is frontend-only
     * (this is presentation logic, not access control).
     */
    public function filterShouldRender(bool $shouldRender, Element_Base $element): bool
    {
        if (!$shouldRender) {
            return false;
        }

        if ($this->isEditorOrPreview()) {
            return $shouldRender;
        }

        $mode = (string) ($element->get_settings_for_display(self::CONTROL_NAME) ?? '');

        if ('' === $mode) {
            return $shouldRender;
        }

        $required = 'holiday' === $mode ? HolidayStatus::Holiday : HolidayStatus::Normal;

        return $this->todayStatus() === $required;
    }

    /**
     * @param array{settings?: array<string, mixed>} $rawData
     */
    public function filterIsDynamicContent(bool $isDynamic, array $rawData): bool
    {
        if ($isDynamic) {
            return true;
        }

        $mode = (string) ($rawData['settings'][self::CONTROL_NAME] ?? '');

        return '' !== $mode;
    }

    private function todayStatus(): HolidayStatus
    {
        if (null === $this->todayCache) {
            $resolver = new TodayStatus($this->holidays, $this->override);
            $this->todayCache = $resolver->resolve($this->now());
        }

        return $this->todayCache;
    }

    private function now(): DateTimeImmutable
    {
        return Clock::now();
    }

    private function isEditorOrPreview(): bool
    {
        if (!did_action('elementor/loaded')) {
            return false;
        }

        $plugin = ElementorPlugin::$instance;

        $isEdit = isset($plugin->editor) && $plugin->editor->is_edit_mode();
        $isPreview = isset($plugin->preview) && $plugin->preview->is_preview_mode();

        return $isEdit || $isPreview;
    }
}
