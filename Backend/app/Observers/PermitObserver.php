<?php

namespace App\Observers;

use App\Models\Permit;
use App\Services\NotificationService;
use App\Models\Notification;

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
        $employee = $permit->employee;
        
        if (!$employee) {
            return;
        }

        // Notifikasi pengajuan izin
        $this->notificationService->sendToRecipient(
            $employee,
            'Pengajuan Izin Berhasil',
            "Pengajuan izin Anda untuk tanggal " . $permit->start_date->format('d/m/Y') . " telah berhasil diajukan",
            [
                'permit_id' => $permit->id,
                'action' => 'view_permit',
                'start_date' => $permit->start_date,
                'end_date' => $permit->end_date,
                'type' => $permit->type,
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
        $employee = $permit->employee;
        
        if (!$employee) {
            return;
        }

        // Jika status berubah
        if ($permit->wasChanged('status')) {
            $statusMessages = [
                'approved' => 'Pengajuan izin Anda telah disetujui',
                'rejected' => 'Pengajuan izin Anda ditolak',
                'pending' => 'Pengajuan izin Anda sedang dalam review',
            ];

            if (isset($statusMessages[$permit->status])) {
                $this->notificationService->sendToRecipient(
                    $employee,
                    'Status Pengajuan Izin Diperbarui',
                    $statusMessages[$permit->status],
                    [
                        'permit_id' => $permit->id,
                        'action' => 'view_permit',
                        'start_date' => $permit->start_date,
                        'end_date' => $permit->end_date,
                        'type' => $permit->type,
                        'status' => $permit->status,
                    ],
                    [
                        'type' => Notification::TYPE_PERMIT,
                        'priority' => $permit->status === 'rejected' ? Notification::PRIORITY_HIGH : Notification::PRIORITY_NORMAL,
                    ]
                );
            }
        }

        // Jika ada perubahan tanggal
        if ($permit->wasChanged('start_date') || $permit->wasChanged('end_date')) {
            $this->notificationService->sendToRecipient(
                $employee,
                'Tanggal Izin Diperbarui',
                "Tanggal izin Anda telah diperbarui menjadi " . $permit->start_date->format('d/m/Y') . " - " . $permit->end_date->format('d/m/Y'),
                [
                    'permit_id' => $permit->id,
                    'action' => 'view_permit',
                    'start_date' => $permit->start_date,
                    'end_date' => $permit->end_date,
                    'type' => $permit->type,
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
        $employee = $permit->employee;
        
        if (!$employee) {
            return;
        }

        $this->notificationService->sendToRecipient(
            $employee,
            'Pengajuan Izin Dihapus',
            "Pengajuan izin Anda untuk tanggal " . $permit->start_date->format('d/m/Y') . " telah dihapus",
            [
                'start_date' => $permit->start_date,
                'end_date' => $permit->end_date,
                'type' => $permit->type,
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
        $employee = $permit->employee;
        
        if (!$employee) {
            return;
        }

        $this->notificationService->sendToRecipient(
            $employee,
            'Pengajuan Izin Dipulihkan',
            "Pengajuan izin Anda untuk tanggal " . $permit->start_date->format('d/m/Y') . " telah dipulihkan",
            [
                'permit_id' => $permit->id,
                'action' => 'view_permit',
                'start_date' => $permit->start_date,
                'end_date' => $permit->end_date,
                'type' => $permit->type,
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
        $employee = $permit->employee;
        
        if (!$employee) {
            return;
        }

        $this->notificationService->sendToRecipient(
            $employee,
            'Pengajuan Izin Dihapus Permanen',
            "Pengajuan izin Anda untuk tanggal " . $permit->start_date->format('d/m/Y') . " telah dihapus secara permanen",
            [
                'start_date' => $permit->start_date,
                'end_date' => $permit->end_date,
                'type' => $permit->type,
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
        // Get supervisors/admins (you can customize this based on your role system)
        $supervisors = \App\Models\Employee::where('position', 'like', '%supervisor%')
            ->orWhere('position', 'like', '%manager%')
            ->orWhere('position', 'like', '%admin%')
            ->get();

        foreach ($supervisors as $supervisor) {
            $this->notificationService->sendToRecipient(
                $supervisor,
                'Pengajuan Izin Baru',
                "Karyawan {$permit->employee->name} mengajukan izin untuk tanggal " . $permit->start_date->format('d/m/Y'),
                [
                    'permit_id' => $permit->id,
                    'action' => 'review_permit',
                    'employee_name' => $permit->employee->name,
                    'start_date' => $permit->start_date,
                    'end_date' => $permit->end_date,
                    'type' => $permit->type,
                ],
                [
                    'type' => Notification::TYPE_PERMIT,
                    'priority' => Notification::PRIORITY_HIGH,
                ]
            );
        }
    }
}
