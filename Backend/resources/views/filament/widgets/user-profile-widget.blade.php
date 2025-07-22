<x-filament::widget>
    <x-filament::card class="p-4">
        <div class="flex items-center justify-between space-x-4">
            <!-- Left Section: Profile Info -->
            <div class="flex items-center space-x-4 min-w-0 flex-1">
                <!-- Profile Picture -->
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800">
                        @if($this->getUser()?->photo)
                            <img 
                                src="{{ asset('storage/' . $this->getUser()->photo) }}" 
                                alt="Profile Photo" 
                                class="w-full h-full object-cover"
                            >
                        @else
                            <div class="w-full h-full bg-primary-500 flex items-center justify-center">
                                <x-heroicon-o-user class="w-5 h-5 text-white" />
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ $this->getUser()->name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        {{ $this->getUser()->email }}
                    </div>
                </div>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>