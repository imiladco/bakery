<?php

declare(strict_types=1);

namespace WHW;

use WHW\Admin\AdminBar;
use WHW\Admin\DashboardWidget;
use WHW\Admin\Page as AdminPage;
use WHW\Admin\Rest as AdminRest;
use WHW\Integration\DynamicTag;
use WHW\Integration\Visibility;
use WHW\Integration\Widget;
use WHW\Storage\Holidays;
use WHW\Storage\Official;
use WHW\Storage\Override;
use WHW\Storage\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Composition root: wires the WP-facing adapters (Storage) into every
 * consumer (Widget render, Visibility, DynamicTag, Admin, Cron) and
 * registers everything with WordPress/Elementor. No business logic lives
 * here — only construction and hook registration.
 *
 * The Elementor widget category ("bakery") is registered by
 * \Bakery_Widgets\Plugin (includes/bakery/plugin.php), not here — this
 * plugin's widget shares that one category rather than having its own,
 * since all widgets now ship together as one plugin.
 */
final class Plugin
{
    private static ?self $instance = null;

    private readonly Holidays $holidays;
    private readonly Override $override;
    private readonly Official $official;
    private readonly Snapshot $snapshot;
    private readonly Cron $cron;

    private function __construct()
    {
        $this->holidays = new Holidays();
        $this->override = new Override();
        $this->official = new Official();
        $this->snapshot = new Snapshot();
        $this->cron = new Cron($this->holidays, $this->override, $this->snapshot);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function cron(): Cron
    {
        return $this->cron;
    }

    public function init(): void
    {
        add_action('elementor/widgets/register', [$this, 'registerWidget']);
        add_action('elementor/frontend/after_register_styles', [$this, 'registerStyles']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueueEditorStyles']);
        add_action('elementor/dynamic_tags/register', [$this, 'registerDynamicTag']);

        $adminPage = new AdminPage($this->holidays, $this->override, $this->official);

        (new Visibility($this->holidays, $this->override))->register();
        $adminPage->register();
        (new AdminRest($this->holidays, $this->override, $adminPage))->register();
        (new DashboardWidget($this->holidays, $this->override))->register();
        (new AdminBar($this->holidays, $this->override))->register();

        $this->cron->register();
    }

    public function registerWidget($widgetsManager): void
    {
        $widgetsManager->register(new Widget());
    }

    public function registerStyles(): void
    {
        wp_register_style(
            'whw-weekly-holidays',
            WHW_PLUGIN_URL . 'assets/css/whw.css',
            [],
            WHW_PLUGIN_VERSION,
        );
    }

    public function enqueueEditorStyles(): void
    {
        $this->registerStyles();
        wp_enqueue_style('whw-weekly-holidays');
    }

    public function registerDynamicTag($dynamicTagsManager): void
    {
        $dynamicTagsManager->register_group('whw', [
            'title' => __('تعطیلات هفته', 'weekly-holidays-widget'),
        ]);

        $dynamicTagsManager->register(new DynamicTag($this->holidays, $this->override));
    }
}
