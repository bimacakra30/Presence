<?php

namespace App\Observers;

use App\Models\Presence;
use App\Services\NotificationService;
use App\Models\Notification;

class PresenceObserver
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Presence "created" event.
     */
    public function created(Presence $presence): void
    {
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        // Notifikasi check-in
        if ($presence->check_in) {
            $this->notificationService->sendToRecipient(
                $employee,
                'Check-in Berhasil',
                "Anda telah berhasil check-in pada " . $presence->check_in->format('H:i'),
                [
                    'presence_id' => $presence->id,
                    'action' => 'view_presence',
                    'date' => $presence->date,
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }

        // Notifikasi check-out
        if ($presence->check_out) {
            $this->notificationService->sendToRecipient(
                $employee,
                'Check-out Berhasil',
                "Anda telah berhasil check-out pada " . $presence->check_out->format('H:i'),
                [
                    'presence_id' => $presence->id,
                    'action' => 'view_presence',
                    'date' => $presence->date,
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }

        // Notifikasi keterlambatan
        if ($presence->is_late) {
            $this->notificationService->sendToRecipient(
                $employee,
                'Keterlambatan Terdeteksi',
                "Anda terlambat " . $presence->late_duration . " menit pada hari ini",
                [
                    'presence_id' => $presence->id,
                    'action' => 'view_presence',
                    'date' => $presence->date,
                    'late_duration' => $presence->late_duration,
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_HIGH,
                ]
            );
        }
    }

    /**
     * Handle the Presence "updated" event.
     */
    public function updated(Presence $presence): void
    {
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        // Jika check-out baru ditambahkan
        if ($presence->wasChanged('check_out') && $presence->check_out) {
            $this->notificationService->sendToRecipient(
                $employee,
                'Check-out Berhasil',
                "Anda telah berhasil check-out pada " . $presence->check_out->format('H:i'),
                [
                    'presence_id' => $presence->id,
                    'action' => 'view_presence',
                    'date' => $presence->date,
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }

        // Jika status berubah
        if ($presence->wasChanged('status')) {
            $statusMessages = [
                'approved' => 'Kehadiran Anda telah disetujui',
                'rejected' => 'Kehadiran Anda ditolak',
                'pending' => 'Kehadiran Anda sedang dalam review',
            ];

            if (isset($statusMessages[$presence->status])) {
                $this->notificationService->sendToRecipient(
                    $employee,
                    'Status Kehadiran Diperbarui',
                    $statusMessages[$presence->status],
                    [
                        'presence_id' => $presence->id,
                        'action' => 'view_presence',
                        'date' => $presence->date,
                        'status' => $presence->status,
                    ],
                    [
                        'type' => Notification::TYPE_PRESENCE,
                        'priority' => $presence->status === 'rejected' ? Notification::PRIORITY_HIGH : Notification::PRIORITY_NORMAL,
                    ]
                );
            }
        }
    }

    /**
     * Handle the Presence "deleted" event.
     */
    public function deleted(Presence $presence): void
    {
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        $this->notificationService->sendToRecipient(
            $employee,
            'Data Kehadiran Dihapus',
            "Data kehadiran Anda pada " . $presence->date->format('d/m/Y') . " telah dihapus",
            [
                'date' => $presence->date,
                'action' => 'view_presence_history',
            ],
            [
                'type' => Notification::TYPE_PRESENCE,
                'priority' => Notification::PRIORITY_HIGH,
            ]
        );
    }

    /**
     * Handle the Presence "restored" event.
     */
    public function restored(Presence $presence): void
    {
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        $this->notificationService->sendToRecipient(
            $employee,
            'Data Kehadiran Dipulihkan',
            "Data kehadiran Anda pada " . $presence->date->format('d/m/Y') . " telah dipulihkan",
            [
                'presence_id' => $presence->id,
                'action' => 'view_presence',
                'date' => $presence->date,
            ],
            [
                'type' => Notification::TYPE_PRESENCE,
                'priority' => Notification::PRIORITY_NORMAL,
            ]
        );
    }

    /**
     * Handle the Presence "force deleted" event.
     */
    public function forceDeleted(Presence $presence): void
    {
        $employee = $presence->employee;
        
        if (!$employee) {
            return;
        }

        $this->notificationService->sendToRecipient(
            $employee,
            'Data Kehadiran Dihapus Permanen',
            "Data kehadiran Anda pada " . $presence->date->format('d/m/Y') . " telah dihapus secara permanen",
            [
                'date' => $presence->date,
                'action' => 'view_presence_history',
            ],
            [
                'type' => Notification::TYPE_PRESENCE,
                'priority' => Notification::PRIORITY_HIGH,
            ]
        );
    }
}
