<?php

/**
 * Contoh Penggunaan Sistem Notifikasi di Backend
 * 
 * File ini menunjukkan cara mengintegrasikan notifikasi
 * ke dalam fitur-fitur backend yang sudah ada
 */

use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\Notification;

// ============================================================================
// CONTOH 1: Notifikasi saat Check-in/Check-out
// ============================================================================

class PresenceController 
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public function checkIn(Request $request)
    {
        $employee = auth()->user();
        
        // Proses check-in
        $presence = Presence::create([
            'employee_id' => $employee->id,
            'check_in_time' => now(),
            'location' => $request->location,
            // ... data lainnya
        ]);
        
        // Kirim notifikasi check-in berhasil
        $this->notificationService->sendToRecipient(
            $employee,
            'Check-in Berhasil',
            'Anda telah berhasil check-in pada ' . now()->format('H:i'),
            [
                'action' => 'view_presence',
                'presence_id' => $presence->id,
                'date' => now()->format('Y-m-d'),
                'check_in_time' => $presence->check_in_time,
            ],
            [
                'type' => Notification::TYPE_PRESENCE,
                'priority' => Notification::PRIORITY_NORMAL,
            ]
        );
        
        return response()->json(['success' => true, 'message' => 'Check-in berhasil']);
    }
    
    public function checkOut(Request $request)
    {
        $employee = auth()->user();
        
        // Proses check-out
        $presence = Presence::where('employee_id', $employee->id)
            ->whereDate('created_at', today())
            ->first();
            
        if ($presence) {
            $presence->update(['check_out_time' => now()]);
            
            // Kirim notifikasi check-out berhasil
            $this->notificationService->sendToRecipient(
                $employee,
                'Check-out Berhasil',
                'Anda telah berhasil check-out pada ' . now()->format('H:i'),
                [
                    'action' => 'view_presence',
                    'presence_id' => $presence->id,
                    'date' => now()->format('Y-m-d'),
                    'check_out_time' => $presence->check_out_time,
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }
        
        return response()->json(['success' => true, 'message' => 'Check-out berhasil']);
    }
}

// ============================================================================
// CONTOH 2: Notifikasi saat Pengajuan Izin
// ============================================================================

class PermitController
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public function submitPermit(Request $request)
    {
        $employee = auth()->user();
        
        // Buat pengajuan izin
        $permit = Permit::create([
            'employee_id' => $employee->id,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);
        
        // Kirim notifikasi ke HR/Manager untuk approval
        $managers = Employee::where('position', 'Manager')
            ->orWhere('position', 'HR')
            ->where('status', 'active')
            ->get();
            
        foreach ($managers as $manager) {
            $this->notificationService->sendToRecipient(
                $manager,
                'Pengajuan Izin Baru',
                "{$employee->name} mengajukan izin {$request->type}",
                [
                    'action' => 'view_permit',
                    'permit_id' => $permit->id,
                    'employee_name' => $employee->name,
                    'permit_type' => $request->type,
                    'start_date' => $request->start_date,
                ],
                [
                    'type' => Notification::TYPE_PERMIT,
                    'priority' => Notification::PRIORITY_HIGH,
                ]
            );
        }
        
        // Kirim notifikasi konfirmasi ke employee
        $this->notificationService->sendToRecipient(
            $employee,
            'Pengajuan Izin Dikirim',
            'Pengajuan izin Anda telah dikirim dan sedang menunggu approval',
            [
                'action' => 'view_permit',
                'permit_id' => $permit->id,
                'status' => 'pending',
            ],
            [
                'type' => Notification::TYPE_PERMIT,
                'priority' => Notification::PRIORITY_NORMAL,
            ]
        );
        
        return response()->json(['success' => true, 'message' => 'Pengajuan izin berhasil dikirim']);
    }
    
    public function approvePermit(Request $request, $permitId)
    {
        $permit = Permit::findOrFail($permitId);
        $permit->update(['status' => 'approved']);
        
        $employee = $permit->employee;
        
        // Kirim notifikasi approval ke employee
        $this->notificationService->sendToRecipient(
            $employee,
            'Izin Disetujui',
            "Pengajuan izin {$permit->type} Anda telah disetujui",
            [
                'action' => 'view_permit',
                'permit_id' => $permit->id,
                'status' => 'approved',
            ],
            [
                'type' => Notification::TYPE_PERMIT,
                'priority' => Notification::PRIORITY_HIGH,
            ]
        );
        
        return response()->json(['success' => true, 'message' => 'Izin berhasil disetujui']);
    }
}

// ============================================================================
// CONTOH 3: Notifikasi Sistem dan Pengumuman
// ============================================================================

class SystemNotificationController
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public function sendAnnouncement(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'target' => 'required|in:all,position,specific',
            'position' => 'required_if:target,position',
            'employee_ids' => 'required_if:target,specific|array',
        ]);
        
        $data = [
            'action' => 'view_announcement',
            'announcement_id' => uniqid(),
            'timestamp' => now()->toISOString(),
        ];
        
        $options = [
            'type' => Notification::TYPE_ANNOUNCEMENT,
            'priority' => Notification::PRIORITY_HIGH,
        ];
        
        switch ($request->target) {
            case 'all':
                $this->notificationService->sendToAllEmployees(
                    $request->title,
                    $request->body,
                    $data,
                    $options
                );
                break;
                
            case 'position':
                $this->notificationService->sendToEmployeesByPosition(
                    $request->position,
                    $request->title,
                    $request->body,
                    $data,
                    $options
                );
                break;
                
            case 'specific':
                $employees = Employee::whereIn('id', $request->employee_ids)->get();
                $this->notificationService->sendToMultipleRecipients(
                    $employees,
                    $request->title,
                    $request->body,
                    $data,
                    $options
                );
                break;
        }
        
        return response()->json(['success' => true, 'message' => 'Pengumuman berhasil dikirim']);
    }
    
    public function sendSystemMaintenanceNotification()
    {
        $this->notificationService->sendToAllEmployees(
            'Maintenance Sistem',
            'Sistem akan down untuk maintenance pada pukul 23:00 - 01:00',
            [
                'action' => 'system_maintenance',
                'start_time' => '23:00',
                'end_time' => '01:00',
                'date' => now()->addDay()->format('Y-m-d'),
            ],
            [
                'type' => Notification::TYPE_SYSTEM,
                'priority' => Notification::PRIORITY_URGENT,
            ]
        );
    }
}

// ============================================================================
// CONTOH 4: Notifikasi Terjadwal
// ============================================================================

class ScheduledNotificationController
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public function scheduleReminders()
    {
        // Reminder check-in pagi
        $morningEmployees = Employee::where('status', 'active')->get();
        
        foreach ($morningEmployees as $employee) {
            $this->notificationService->scheduleNotification(
                $employee,
                'Reminder Check-in',
                'Jangan lupa untuk check-in hari ini',
                now()->setTime(8, 0), // Jam 8 pagi
                [
                    'action' => 'check_in_reminder',
                    'time' => '08:00',
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }
        
        // Reminder check-out sore
        foreach ($morningEmployees as $employee) {
            $this->notificationService->scheduleNotification(
                $employee,
                'Reminder Check-out',
                'Jangan lupa untuk check-out sebelum pulang',
                now()->setTime(17, 0), // Jam 5 sore
                [
                    'action' => 'check_out_reminder',
                    'time' => '17:00',
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }
    }
}

// ============================================================================
// CONTOH 5: Observer untuk Auto Notifikasi
// ============================================================================

class PresenceObserver
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    public function created(Presence $presence)
    {
        // Auto notifikasi saat presence dibuat
        $employee = $presence->employee;
        
        $this->notificationService->sendToRecipient(
            $employee,
            'Presence Recorded',
            'Kehadiran Anda telah tercatat',
            [
                'action' => 'view_presence',
                'presence_id' => $presence->id,
                'date' => $presence->created_at->format('Y-m-d'),
            ],
            [
                'type' => Notification::TYPE_PRESENCE,
                'priority' => Notification::PRIORITY_NORMAL,
            ]
        );
    }
}

// ============================================================================
// CONTOH 6: Command untuk Maintenance
// ============================================================================

class SendDailyReportCommand extends Command
{
    protected $signature = 'notifications:send-daily-report';
    
    public function handle(NotificationService $notificationService)
    {
        $managers = Employee::where('position', 'Manager')->get();
        
        foreach ($managers as $manager) {
            $notificationService->sendToRecipient(
                $manager,
                'Laporan Harian',
                'Laporan kehadiran karyawan hari ini telah siap',
                [
                    'action' => 'view_daily_report',
                    'date' => now()->format('Y-m-d'),
                ],
                [
                    'type' => Notification::TYPE_GENERAL,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );
        }
        
        $this->info('Daily report notifications sent successfully');
    }
}
