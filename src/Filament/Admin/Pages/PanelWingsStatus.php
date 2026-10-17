<?php

namespace Justpita\PanelWingsStatus\Filament\Admin\Pages;

use Filament\Pages\Page;
use Justpita\PanelWingsStatus\Services\StatusService;

class PanelWingsStatus extends Page
{
    protected string $view = 'panel-wings-status::status';

    protected static ?string $slug = 'panel-wings-status';

    protected static ?int $navigationSort = 99;

    /** @var array<int, array<string, mixed>> */
    public array $machines = [];

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        try {
            $this->machines = StatusService::getAll();
        } catch (\Throwable $e) {
            report($e);
            $this->machines = [];
        }
    }

    public static function getNavigationIcon(): ?string
    {
        return 'tabler-heart-rate-monitor';
    }

    public static function getNavigationLabel(): string
    {
        return trans('panel-wings-status::strings.navigation');
    }

    public function getTitle(): string
    {
        return trans('panel-wings-status::strings.title');
    }

    protected function getViewData(): array
    {
        return ['machines' => $this->machines];
    }
}
