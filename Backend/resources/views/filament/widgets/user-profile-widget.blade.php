<x-filament-widgets::widget>
    <div class="fi-wi-user-profile">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <div class="flex items-center gap-4">
                    {{-- Avatar --}}
                    <div class="flex-shrink-0">
                        @if($this->getUser()?->photo)
                            <img 
                                src="{{ asset( 'storage/' .$this->getUser()->photo) }}" 
                                alt="{{ $this->getUser()->name }}"
                                class="h-11 w-11 rounded-full object-cover ring-2 ring-white dark:ring-gray-900 shadow-lg"
                            >
                        @else
                            <div class="h-14 w-14 rounded-full bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-600 flex items-center justify-center shadow-lg ring-2 ring-white dark:ring-gray-900">
                                <span class="text-white font-bold text-lg">
                                    {{ strtoupper(substr($this->getUser()->name, 0, 1)) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- User Information dengan gap yang lebih baik --}}
                    <div class="flex-1 min-w-0">
                        <div class="space-y-1">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
                                {{ $this->getUser()->name }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $this->getUser()->email }}
                            </p>
                            @if($this->getUser()->role ?? false)
                                <div class="mt-2">
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/20">
                                        {{ ucfirst($this->getUser()->role) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Status Online --}}
                    <div class="flex-shrink-0">
                        <div class="flex items-center">
                            <div class="h-2 w-2 rounded-full bg-green-400"></div>
                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>