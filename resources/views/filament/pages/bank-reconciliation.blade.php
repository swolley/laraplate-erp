<x-filament-panels::page>
    <form wire:submit="matchSelected">
        {{ $this->form }}

        <div class="mt-4 flex gap-3">
            <x-filament::button type="submit">
                Match
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="ignoreSelected">
                Ignore
            </x-filament::button>
        </div>
    </form>

    <x-filament::section heading="Unmatched statement lines">
        <div class="space-y-2 text-sm">
            @forelse ($this->unmatchedLines() as $lineId => $label)
                <div class="border-b py-2">
                    <div>{{ $label }}</div>

                    @php($suggestions = $this->suggestedPaymentsForLine((int) $lineId))

                    @if ($suggestions !== [])
                        <div class="mt-1 text-xs text-gray-500">
                            Suggested: {{ implode(' | ', array_values($suggestions)) }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-gray-500">
                    No unmatched statement lines.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
