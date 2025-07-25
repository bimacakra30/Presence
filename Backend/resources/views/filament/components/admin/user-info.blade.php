@php
    $user = auth()->user();
@endphp

<div class="px-4 py-6 text-sm border-b dark:border-gray-700">
    <div class="flex items-center space-x-3">
        <img 
            src="{{ $user->photo ? asset('storage/' . $user->photo) : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}" 
            alt="{{ $user->name }}" 
            class="w-10 h-10 rounded-full object-cover"
        >
        <div>
            <div class="font-semibold text-gray-900 dark:text-white">{{ $user->name }}</div>
            <div class="text-gray-600 text-xs dark:text-gray-300">{{ $user->email }}</div>
        </div>
    </div>
</div>
