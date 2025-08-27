<?php

namespace App\Observers;

use App\Models\Permit;
use App\Services\NotificationService;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PermitObserver
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Permit "created" event.
     */
    public function created(Permit $permit): void
    {
        // Get employee by UID
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            return;
        }

        // Convert string dates to Carbon objects
        $tanggalMulai = \Carbon\Carbon::parse($permit->tanggal_mulai);
        $tanggalSelesai = \Carbon\Carbon::parse($permit->tanggal_selesai);

        // Notifikasi pengajuan izin
        $this->notificationService->sendToEmployeeWithFirestoreTokens(
            $employee->uid,
            'Pengajuan Izin Berhasil',
            "Pengajuan izin {$permit->jenis_perizinan} Anda untuk tanggal " . $tanggalMulai->format('d/m/Y') . " telah berhasil diajukan",
            [
                'permit_id' => $permit->id,
                'action' => 'view_permit',
                'tanggal_mulai' => $permit->tanggal_mulai,
                'tanggal_selesai' => $permit->tanggal_selesai,
                'jenis_perizinan' => $permit->jenis_perizinan,
            ],
            [
                'type' => Notification::TYPE_PERMIT,
                'priority' => Notification::PRIORITY_NORMAL,
            ]
        );

        // Notifikasi ke admin/supervisor (jika ada)
        $this->notifySupervisors($permit);
    }

    /**
     * Handle the Permit "updated" event.
     */
    public function updated(Permit $permit): void
    {
        // Get employee by UID
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            return;
        }

        // Convert string dates to Carbon objects
        $tanggalMulai = \Carbon\Carbon::parse($permit->tanggal_mulai);
        $tanggalSelesai = \Carbon\Carbon::parse($permit->tanggal_selesai);

        // Jika status berubah
        if ($permit->wasChanged('status')) {
            Log::info("PermitObserver: Status changed for permit {$permit->id} from '{$permit->getOriginal('status')}' to '{$permit->status}'");
            
            // Add debouncing for status change notifications
            $cacheKey = "permit_status_change:{$permit->id}:{$permit->status}";
            if (Cache::has($cacheKey)) {
                Log::info("Skipping duplicate status change notification for permit {$permit->id} with status {$permit->status} (cache hit)");
                return;
            }
            
            Log::info("PermitObserver: Proceeding with status change notification for permit {$permit->id} with status {$permit->status}");
            
            $statusMessages = [
                'approved' => 'Pengajuan izin Anda telah disetujui',
                'rejected' => 'Pengajuan izin Anda ditolak',
                'pending' => 'Pengajuan izin Anda sedang dalam review',
            ];

            if (isset($statusMessages[$permit->status])) {
                $this->notificationService->sendToEmployeeWithFirestoreTokens(
                    $employee->uid,
                    'Status Pengajuan Izin Diperbarui',
                    $statusMessages[$permit->status],
                    [
                        'permit_id' => $permit->id,
                        'action' => 'view_permit',
                        'tanggal_mulai' => $permit->tanggal_mulai,
                        'tanggal_selesai' => $permit->tanggal_selesai,
                        'jenis_perizinan' => $permit->jenis_perizinan,
                        'status' => $permit->status,
                    ],
                    [
                        'type' => Notification::TYPE_PERMIT,
                        'priority' => $permit->status === 'rejected' ? Notification::PRIORITY_HIGH : Notification::PRIORITY_NORMAL,
                    ]
                );
                
                // Set cache to prevent duplicate status change notifications (5 minutes)
                Cache::put($cacheKey, true, now()->addMinutes(5));
            }
        }

        // Jika ada perubahan tanggal
        if ($permit->wasChanged('tanggal_mulai') || $permit->wasChanged('tanggal_selesai')) {
            $this->notificationService->sendToEmployeeWithFirestoreTokens(
                $employee->uid,
                'Tanggal Izin Diperbarui',
                "Tanggal izin Anda telah diperbarui menjadi " . $tanggalMulai->format('d/m/Y') . " - " . $tanggalSelesai->format('d/m/Y'),
                [
                    'permit_id' => $permit->id,
                    'action' => 'view_permit',
                    'tanggal_mulai' => $permit->tanggal_mulai,
                    'tanggal_selesai' => $permit->tanggal_selesai,
                    'jenis_perizinan' => $permit->jenis_perizinan,
                ],
                [
                    'type' => Notification::TYPE_PERMIT,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }
    }

    /**
     * Handle the Permit "deleted" event.
     */
    public function deleted(Permit $permit): void
    {
        // Get employee by UID
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            return;
        }

        // Convert string dates to Carbon objects
        $tanggalMulai = \Carbon\Carbon::parse($permit->tanggal_mulai);

        $this->notificationService->sendToEmployeeWithFirestoreTokens(
            $employee->uid,
            'Pengajuan Izin Dihapus',
            "Pengajuan izin {$permit->jenis_perizinan} Anda untuk tanggal " . $tanggalMulai->format('d/m/Y') . " telah dihapus",
            [
                'tanggal_mulai' => $permit->tanggal_mulai,
                'tanggal_selesai' => $permit->tanggal_selesai,
                'jenis_perizinan' => $permit->jenis_perizinan,
                'action' => 'view_permit_history',
            ],
            [
                'type' => Notification::TYPE_PERMIT,
                'priority' => Notification::PRIORITY_HIGH,
            ]
        );
    }

    /**
     * Handle the Permit "restored" event.
     */
    public function restored(Permit $permit): void
    {
        // Get employee by UID
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            return;
        }

        // Convert string dates to Carbon objects
        $tanggalMulai = \Carbon\Carbon::parse($permit->tanggal_mulai);

        $this->notificationService->sendToEmployeeWithFirestoreTokens(
            $employee->uid,
            'Pengajuan Izin Dipulihkan',
            "Pengajuan izin {$permit->jenis_perizinan} Anda untuk tanggal " . $tanggalMulai->format('d/m/Y') . " telah dipulihkan",
            [
                'permit_id' => $permit->id,
                'action' => 'view_permit',
                'tanggal_mulai' => $permit->tanggal_mulai,
                'tanggal_selesai' => $permit->tanggal_selesai,
                'jenis_perizinan' => $permit->jenis_perizinan,
            ],
            [
                'type' => Notification::TYPE_PERMIT,
                'priority' => Notification::PRIORITY_NORMAL,
            ]
        );
    }

    /**
     * Handle the Permit "force deleted" event.
     */
    public function forceDeleted(Permit $permit): void
    {
        // Get employee by UID
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            return;
        }

        // Convert string dates to Carbon objects
        $tanggalMulai = \Carbon\Carbon::parse($permit->tanggal_mulai);

        $this->notificationService->sendToEmployeeWithFirestoreTokens(
            $employee->uid,
            'Pengajuan Izin Dihapus Permanen',
            "Pengajuan izin {$permit->jenis_perizinan} Anda untuk tanggal " . $tanggalMulai->format('d/m/Y') . " telah dihapus secara permanen",
            [
                'tanggal_mulai' => $permit->tanggal_mulai,
                'tanggal_selesai' => $permit->tanggal_selesai,
                'jenis_perizinan' => $permit->jenis_perizinan,
                'action' => 'view_permit_history',
            ],
            [
                'type' => Notification::TYPE_PERMIT,
                'priority' => Notification::PRIORITY_HIGH,
            ]
        );
    }

    /**
     * Notify supervisors about new permit request
     */
    protected function notifySupervisors(Permit $permit): void
    {
        // Get employee by UID
        $employee = null;
        if ($permit->uid) {
            $employee = \App\Models\Employee::where('uid', $permit->uid)->first();
        }
        
        if (!$employee) {
            return;
        }

        // Convert string dates to Carbon objects
        $tanggalMulai = \Carbon\Carbon::parse($permit->tanggal_mulai);

        // Get supervisors/admins (you can customize this based on your role system)
        $supervisors = \App\Models\Employee::where('position', 'like', '%supervisor%')
            ->orWhere('position', 'like', '%manager%')
            ->orWhere('position', 'like', '%admin%')
            ->get();

        foreach ($supervisors as $supervisor) {
            $this->notificationService->sendToEmployeeWithFirestoreTokens(
                $supervisor->uid,
                'Pengajuan Izin Baru',
                "Karyawan {$employee->name} mengajukan izin {$permit->jenis_perizinan} untuk tanggal " . $tanggalMulai->format('d/m/Y'),
                [
                    'permit_id' => $permit->id,
                    'action' => 'review_permit',
                    'employee_name' => $employee->name,
                    'tanggal_mulai' => $permit->tanggal_mulai,
                    'tanggal_selesai' => $permit->tanggal_selesai,
                    'jenis_perizinan' => $permit->jenis_perizinan,
                ],
                [
                    'type' => Notification::TYPE_PERMIT,
                    'priority' => Notification::PRIORITY_HIGH,
                ]
            );
        }
    }
}
