<x-filament-panels::page>
    @php
        $s = fn (string $key, array $r = []) => trans("panel-wings-status::strings.$key", $r);
        $bar = function (float $p) {
            $c = $p > 85 ? 'rgb(220, 38, 38)' : ($p > 65 ? 'rgb(245, 158, 11)' : 'rgb(5, 150, 105)');
            return [$c, "color-mix(in srgb, {$c} 15%, transparent)"];
        };
    @endphp

    <div wire:poll.30s="refreshStatus">
        <x-filament::section>
            <x-slot name="heading">{{ $s('heading') }}</x-slot>
            <x-slot name="description">{{ $s('description', ['count' => count($machines), 'time' => now()->format('H:i:s')]) }}</x-slot>

            {{-- inline grid: utility classes such as md:grid-cols-2 are not always compiled in the panel CSS --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: 1rem;">
                @forelse($machines as $m)
                    @php
                        [$badge, $label] = !($m['online'] ?? false)
                            ? ['rgb(220, 38, 38)', $s('offline')]
                            : (($m['maintenance'] ?? false) ? ['rgb(245, 158, 11)', $s('maintenance')] : ['rgb(5, 150, 105)', $s('online')]);
                    @endphp
                    <x-filament::section compact>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center gap-2">
                                <p class="text-lg font-bold">{{ $m['name'] }}</p>
                                <span class="text-xs font-semibold rounded-full px-2 py-1"
                                      style="color: {{ $badge }}; background-color: color-mix(in srgb, {{ $badge }} 15%, transparent);">{{ $label }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $m['url'] ?? '' }}</p>

                            @if(!($m['online'] ?? false))
                                <p class="text-sm" style="color: rgb(220, 38, 38);">{{ $m['error'] ?? $s('unreachable') }}</p>
                            @else
                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $m['subtitle'] }}</p>
                                <p class="text-sm">{{ $m['servers_text'] }}</p>

                                @foreach([
                                    [$s('cpu'), $m['cpu'], $m['cpu'] . ' %'],
                                    [$s('memory'), $m['memory']['percent'], $m['memory']['used'] . ' / ' . $m['memory']['total']],
                                    [$s('disk'), $m['disk']['percent'], $m['disk']['used'] . ' / ' . $m['disk']['total']],
                                ] as [$name, $pct, $txt])
                                    @php [$fg, $bg] = $bar((float) $pct); @endphp
                                    <div class="space-y-1">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-medium">{{ $name }}</span>
                                            <span class="text-xs">{{ $txt }}</span>
                                        </div>
                                        <div class="relative rounded-full overflow-hidden w-full" style="height: 0.5rem; background-color: {{ $bg }};">
                                            <div class="h-full rounded-full" style="width: {{ min(100, $pct) }}%; background-color: {{ $fg }};"></div>
                                        </div>
                                    </div>
                                @endforeach

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $s('load') }} : {{ $m['load'][0] }} / {{ $m['load'][1] }} / {{ $m['load'][2] }}
                                </p>
                            @endif
                        </div>
                    </x-filament::section>
                @empty
                    <p class="text-sm text-gray-500">{{ $s('empty') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
