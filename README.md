# Panel & Wings Status

A Pelican Panel plugin that adds one admin page showing a **live status card for the panel and for every Wings node** — at a glance, on the same screen.

## Features

- **One card per machine**: the panel host first, then every node, sorted by name.
- **Status badge**: Online / Maintenance / Offline (with the error when a node is unreachable).
- **Versions**: Pelican version, Wings version, OS, kernel, CPU cores, PHP version.
- **Live usage bars** for CPU, memory and disk — green, orange above 65 %, red above 85 %.
- **Load average** (1 / 5 / 15 min).
- **Servers**: running / total per node (live state from Wings); servers, nodes, users and uptime for the panel.
- **Auto-refresh every 30 seconds** (Livewire polling), no page reload.
- **Accurate numbers inside containers (LXC / Docker)**: memory is `MemTotal − MemAvailable` (page cache excluded) and disk usage is read like `df` (reserved blocks not counted).
- **No extra port or credential**: node data comes from Pelican's own Wings connection (`Node::systemInformation()`, `Node::statistics()`, `/api/servers`).
- Translations: English, French.

## Requirements

- Pelican Panel `1.0.0-beta36` or newer (tested on `1.0.0-rc.1`, Wings `1.0.0-rc.1`).
- Linux panel host (reads `/proc` and `df`).

## Installation

**From the panel**: Admin → Plugins → Import → select `panel-wings-status.zip` (from the [latest release](https://github.com/JustPita/pelican-panel-wings-status/releases/latest)) → Install.

**Manually**:
```bash
cd /var/www/pelican            # your panel directory
unzip panel-wings-status.zip -d plugins/
chown -R www-data:www-data plugins/panel-wings-status
php artisan p:plugin:install panel-wings-status
php artisan optimize:clear && php artisan filament:optimize-clear
```
If your panel runs with cached routes (`php artisan optimize`), rebuild the caches after installing:
`php artisan optimize && php artisan filament:optimize`.

The page appears in the admin sidebar as **Panel & Wings** (`/admin/panel-wings-status`).

## Uninstall

```bash
php artisan p:plugin:uninstall panel-wings-status
```

## Notes

- Panel CPU usage is sampled from `/proc/stat` over 250 ms on each refresh.
- Node statistics are cached by Pelican for a few seconds, so the cards never hammer Wings.
- Nothing is written to the database; the plugin is read-only.

## License

MIT — see [LICENSE](LICENSE).
