<?php

namespace App\Observers;

use App\Models\Presence;

class PresenceObserver
{

    /**
     * Handle the Presence "created" event.
     */
    public function created(Presence $presence): void
    {
        // Semua notifikasi Presence dihapus - akan ditangani di aplikasi local
        // Get employee by UID untuk logging saja
        $employee = \App\Models\Employee::where('uid', $presence->uid)->first();
        
        if (!$employee) {
            \Illuminate\Support\Facades\Log::warning("Employee not found for UID: {$presence->uid}");
            return;
        }

        \Illuminate\Support\Facades\Log::info("Presence created for employee {$employee->name} (UID: {$presence->uid}) - notifications handled locally");
    }

    /**
     * Handle the Presence "updated" event.
     */
    public function updated(Presence $presence): void
    {
        // Semua notifikasi Presence dihapus - akan ditangani di aplikasi local
        // Get employee by UID untuk logging saja
        $employee = \App\Models\Employee::where('uid', $presence->uid)->first();
        
        if (!$employee) {
            \Illuminate\Support\Facades\Log::warning("Employee not found for UID: {$presence->uid}");
            return;
        }

        \Illuminate\Support\Facades\Log::info("Presence updated for employee {$employee->name} (UID: {$presence->uid}) - notifications handled locally");
    }

    /**
     * Handle the Presence "deleted" event.
     */
    public function deleted(Presence $presence): void
    {
        // Semua notifikasi Presence dihapus - akan ditangani di aplikasi local
        // Get employee by UID untuk logging saja
        $employee = \App\Models\Employee::where('uid', $presence->uid)->first();
        
        if (!$employee) {
            \Illuminate\Support\Facades\Log::warning("Employee not found for UID: {$presence->uid}");
            return;
        }

        \Illuminate\Support\Facades\Log::info("Presence deleted for employee {$employee->name} (UID: {$presence->uid}) - notifications handled locally");
    }

    /**
     * Handle the Presence "restored" event.
     */
    public function restored(Presence $presence): void
    {
        // Semua notifikasi Presence dihapus - akan ditangani di aplikasi local
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        \Illuminate\Support\Facades\Log::info("Presence restored for employee {$employee->name} - notifications handled locally");
    }

    /**
     * Handle the Presence "force deleted" event.
     */
    public function forceDeleted(Presence $presence): void
    {
        // Semua notifikasi Presence dihapus - akan ditangani di aplikasi local
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        \Illuminate\Support\Facades\Log::info("Presence force deleted for employee {$employee->name} - notifications handled locally");
    }
}
