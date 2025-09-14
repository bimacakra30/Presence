<?php

namespace App\Observers;

use App\Models\Permit;
use Illuminate\Support\Facades\Log;

class PermitObserver
{

    /**
     * Handle the Permit "created" event.
     */
    public function created(Permit $permit): void
    {
        // Semua notifikasi Permit dihapus - akan ditangani di aplikasi local
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            Log::warning("Employee not found for UID: {$permit->uid}");
            return;
        }

        Log::info("Permit created for employee {$employee->name} (UID: {$permit->uid}) - notifications handled locally");
    }

    /**
     * Handle the Permit "updated" event.
     */
    public function updated(Permit $permit): void
    {
        // Semua notifikasi Permit dihapus - akan ditangani di aplikasi local
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            Log::warning("Employee not found for UID: {$permit->uid}");
            return;
        }

        Log::info("Permit updated for employee {$employee->name} (UID: {$permit->uid}) - notifications handled locally");
    }

    /**
     * Handle the Permit "deleted" event.
     */
    public function deleted(Permit $permit): void
    {
        // Semua notifikasi Permit dihapus - akan ditangani di aplikasi local
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            Log::warning("Employee not found for UID: {$permit->uid}");
            return;
        }

        Log::info("Permit deleted for employee {$employee->name} (UID: {$permit->uid}) - notifications handled locally");
    }

    /**
     * Handle the Permit "restored" event.
     */
    public function restored(Permit $permit): void
    {
        // Semua notifikasi Permit dihapus - akan ditangani di aplikasi local
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            Log::warning("Employee not found for UID: {$permit->uid}");
            return;
        }

        Log::info("Permit restored for employee {$employee->name} (UID: {$permit->uid}) - notifications handled locally");
    }

    /**
     * Handle the Permit "force deleted" event.
     */
    public function forceDeleted(Permit $permit): void
    {
        // Semua notifikasi Permit dihapus - akan ditangani di aplikasi local
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            Log::warning("Employee not found for UID: {$permit->uid}");
            return;
        }

        Log::info("Permit force deleted for employee {$employee->name} (UID: {$permit->uid}) - notifications handled locally");
    }
}