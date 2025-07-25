<x-filament-panels::page>
    <x-filament-panels::form wire:submit="updatePassword">
        <x-filament::card class="shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-white-700 dark:text-gray-300 mb-4">
                    Ubah Kata Sandi
                </h2>
                {{ $this->passwordForm }}
            </div>
            <div class="p-6 rounded-b-lg">
                <div class="flex justify-end">
                    <x-filament::button 
                        type="submit" 
                        color="danger" 
                        icon="heroicon-o-lock-closed"
                        class="px-6 py-2"
                    >
                        Simpan Kata Sandi
                    </x-filament::button>
                </div>
            </div>
        </x-filament::card>
    </x-filament-panels::form>
</x-filament-panels::page>
