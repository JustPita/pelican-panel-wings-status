<?php

namespace Justpita\PanelWingsStatus\Services;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Builds one status entry for the panel host and one for every Wings node.
 * Every entry has the same shape: name, url, online, maintenance, error,
 * subtitle, servers_text, cpu, memory, disk, load.
 */
class StatusService
{
    /** @return array<int, array<string, mixed>> */
    public static function getAll(): array
    {
        try {
            $panel = self::panel();
        } catch (\Throwable $e) {
            $panel = [
                'name' => 'panel', 'url' => self::host(config('app.url')), 'online' => false,
                'maintenance' => false, 'error' => $e->getMessage(),
            ];
        }

        return array_merge([$panel], self::nodes());
    }

    /** @return array<string, mixed> */
    public static function panel(): array
    {
        $servers = Server::count();
        $nodes = Node::count();
        $users = User::count();

        return [
            'name' => 'panel',
            'url' => self::host(config('app.url')),
            'online' => true,
            'maintenance' => false,
            'error' => null,
            'subtitle' => implode(' • ', array_filter([
                'Pelican ' . config('app.version', '?'),
                self::osName(),
                trans('panel-wings-status::strings.kernel') . ' ' . php_uname('r'),
                trans_choice('panel-wings-status::strings.cores', self::cpuCores(), ['count' => self::cpuCores()]),
                'PHP ' . PHP_VERSION,
            ])),
            'servers_text' => trans('panel-wings-status::strings.panel_counts', [
                'servers' => $servers, 'nodes' => $nodes, 'users' => $users, 'uptime' => self::uptime(),
            ]),
            'cpu' => self::cpuUsage(),
            'load' => array_map(fn ($l) => round($l, 2), array_slice(sys_getloadavg() ?: [0, 0, 0], 0, 3)),
            'memory' => self::memory(),
            'disk' => self::disk(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function nodes(): array
    {
        $out = [];

        foreach (Node::query()->orderBy('name')->get() as $node) {
            $info = $node->systemInformation();
            $stats = $node->statistics();
            $online = empty($info['exception']) && !empty($stats['memory_total']);

            $running = null;
            if ($online) {
                try {
                    $list = Http::daemon($node)->connectTimeout(2)->timeout(3)->get('/api/servers')->json();
                    if (is_array($list)) {
                        $running = collect($list)->where('state', 'running')->count();
                    }
                } catch (\Throwable) {
                    $running = null;
                }
            }

            $out[] = [
                'name' => $node->name,
                'url' => $node->fqdn,
                'online' => $online,
                'maintenance' => $node->isUnderMaintenance(),
                'error' => $info['exception'] ?? ($online ? null : trans('panel-wings-status::strings.unreachable')),
                'subtitle' => implode(' • ', [
                    'Wings ' . ($info['version'] ?? '?'),
                    trans('panel-wings-status::strings.kernel') . ' ' . ($info['kernel_version'] ?? '?'),
                    trans_choice('panel-wings-status::strings.cores', (int) ($info['cpu_count'] ?? 0), ['count' => $info['cpu_count'] ?? '?']),
                ]),
                'servers_text' => trans('panel-wings-status::strings.node_servers', [
                    'running' => $running ?? '?', 'total' => $node->servers()->count(),
                ]),
                'cpu' => round((float) ($stats['cpu_percent'] ?? 0), 1),
                'load' => [
                    round((float) ($stats['load_average1'] ?? 0), 2),
                    round((float) ($stats['load_average5'] ?? 0), 2),
                    round((float) ($stats['load_average15'] ?? 0), 2),
                ],
                'memory' => self::usage($stats['memory_used'] ?? 0, $stats['memory_total'] ?? 0),
                'disk' => self::usage($stats['disk_used'] ?? 0, $stats['disk_total'] ?? 0),
            ];
        }

        return $out;
    }

    // ----------------------------------------------------------------- panel host helpers

    /** Real memory use: MemTotal - MemAvailable (page cache excluded). */
    private static function memory(): array
    {
        $m = ['MemTotal' => 0, 'MemAvailable' => 0];
        foreach (@file('/proc/meminfo') ?: [] as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)/', $line, $x)) {
                $m[$x[1]] = (int) $x[2] * 1024;
            }
        }

        return self::usage($m['MemTotal'] - $m['MemAvailable'], $m['MemTotal']);
    }

    /** Disk use like `df` (reserved blocks not counted as used). */
    private static function disk(): array
    {
        $df = function_exists('shell_exec') ? @shell_exec('df -B1 --output=size,used / 2>/dev/null') : null;
        if ($df && preg_match('/(\d+)\s+(\d+)\s*$/', trim($df), $x)) {
            return self::usage((int) $x[2], (int) $x[1]);
        }
        $total = (float) @disk_total_space('/');

        return self::usage($total - (float) @disk_free_space('/'), $total);
    }

    /** CPU usage over a short sample of /proc/stat. */
    private static function cpuUsage(): float
    {
        $read = function () {
            $l = @file('/proc/stat')[0] ?? '';
            $v = array_map('intval', preg_split('/\s+/', trim(substr($l, 4))));
            $idle = ($v[3] ?? 0) + ($v[4] ?? 0);

            return [array_sum($v), $idle];
        };
        [$t1, $i1] = $read();
        usleep(250000);
        [$t2, $i2] = $read();
        $dt = $t2 - $t1;

        return $dt > 0 ? round(100 * ($dt - ($i2 - $i1)) / $dt, 1) : 0.0;
    }

    private static function cpuCores(): int
    {
        $n = 0;
        foreach (@file('/proc/cpuinfo') ?: [] as $line) {
            if (str_starts_with($line, 'processor')) {
                $n++;
            }
        }

        return max(1, $n);
    }

    private static function uptime(): string
    {
        $s = (int) (float) (explode(' ', (string) @file_get_contents('/proc/uptime'))[0] ?? 0);

        return sprintf('%dd %dh %dm', intdiv($s, 86400), intdiv($s % 86400, 3600), intdiv($s % 3600, 60));
    }

    private static function osName(): string
    {
        $os = @file_get_contents('/etc/os-release') ?: '';
        if (preg_match('/^PRETTY_NAME="?([^"\n]+)/m', $os, $x)) {
            return preg_replace('/\s*\(.*\)$/', '', $x[1]);
        }

        return PHP_OS_FAMILY;
    }

    // ----------------------------------------------------------------- formatting

    private static function host(?string $url): string
    {
        return (string) preg_replace('#^https?://#', '', (string) $url);
    }

    /** @return array{used: string, total: string, percent: float} */
    private static function usage(int|float $used, int|float $total): array
    {
        return [
            'used' => self::bytes($used),
            'total' => self::bytes($total),
            'percent' => $total > 0 ? round($used / $total * 100, 1) : 0.0,
        ];
    }

    private static function bytes(int|float $b): string
    {
        $units = trans('panel-wings-status::strings.units');
        $units = is_array($units) ? $units : ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }

        return round($b, 1) . ' ' . $units[$i];
    }
}
