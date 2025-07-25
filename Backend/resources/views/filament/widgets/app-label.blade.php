<x-filament::widget>
    <div class="fi-wi-user-profile">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 min-h-[112px]">
            <div class="fi-section-content p-6">
                <div class="flex items-center gap-4">
                    {{-- Logo Aplikasi --}}
                    <div class="flex-shrink-0">
                        <img 
                            src="{{ asset('images/Logo.png') }}" 
                            alt="Logo Aplikasi"
                            class="h-11 w-11 rounded-full object-cover ring-2 ring-white dark:ring-gray-900 shadow-lg"
                        >
                    </div>

                    {{-- Informasi Aplikasi --}}
                    <div class="flex-1 min-w-0">
                        <div class="space-y-1">
                            <h1 class="text-base font-bold leading-8 text-gray-900 dark:text-white italic">
                                Presence.
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Sistem Presensi Karyawan
                            </p>
                        </div>
                    </div>

                    {{-- Versi / Branding --}}
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-400/10 dark:text-indigo-400 dark:ring-indigo-400/20">
                            v1.0
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament::widget>
