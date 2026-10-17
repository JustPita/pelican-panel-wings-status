<?php

namespace Justpita\PanelWingsStatus;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PanelWingsStatusPlugin implements Plugin
{
    public function getId(): string
    {
        return 'panel-wings-status';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'admin') {
            return;
        }

        // Folder name is the panel id with a capital first letter (Linux is case-sensitive).
        $id = str($panel->getId())->title();

        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "Justpita\\PanelWingsStatus\\Filament\\$id\\Pages"
        );
    }

    public function boot(Panel $panel): void {}
}
