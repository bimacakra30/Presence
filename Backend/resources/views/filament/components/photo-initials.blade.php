@props([
    'record' => null,
    'state' => null,
])

<div class="relative w-12 h-12 rounded-full overflow-hidden flex items-center justify-center">
    @if ($state)
        <img src="{{ $state }}" class="w-full h-full object-cover" alt="Profile Photo" />
    @else
        <div class="w-full h-full bg-gray-300 flex items-center justify-center text-white font-bold text-lg">
            {{ $record->name ? strtoupper(implode('', array_map(fn($word) => substr($word, 0, 1), explode(' ', $record->name)))) : 'NA' }}
        </div>
    @endif
</div>