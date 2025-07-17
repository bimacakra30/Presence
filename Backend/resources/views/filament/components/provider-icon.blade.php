@php
    $value = $getRecord()->provider;

    $icons = [
        'google' => asset('images/providers/google.png'),
        'facebook' => asset('images/providers/facebook.png'),
        'twitter' => asset('images/providers/twitter.png'),
        'github' => asset('images/providers/github.png'),
    ];
@endphp

@if ($value && isset($icons[$value]))
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <img src="{{ $icons[$value] }}" alt="{{ ucfirst($value) }}" style="height: 20px;">
        <span>{{ ucfirst($value) }}</span>
    </div>
@else
    <span class="text-gray-500">Unknown</span>
@endif
