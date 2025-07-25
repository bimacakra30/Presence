<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Profile Form -->
        <x-filament-panels::form wire:submit="updateProfile">
            <x-filament::card class="shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-white-700 dark:text-gray-300 mb-4">
                        Perbarui Informasi Profil
                    </h2>
                    {{ $this->profileForm }}
                </div>
                <div class="p-6 rounded-b-lg">
                    <div class="flex justify-end">
                        <x-filament::button 
                            type="submit" 
                            color="primary" 
                            icon="heroicon-o-user-circle"
                            class="px-6 py-2"
                        >
                            Simpan Profil
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::card>
        </x-filament-panels::form>
    </div>
</x-filament-panels::page>