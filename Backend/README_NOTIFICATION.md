# Sistem Notifikasi Mobile - Laravel Filament 3.3

Implementasi sistem notifikasi komprehensif untuk aplikasi mobile menggunakan Laravel Filament 3.3 dengan Firebase Cloud Messaging (FCM).

## 🚀 Fitur Utama

### ✅ Notifikasi Push Mobile
- Firebase Cloud Messaging (FCM) integration
- Support untuk Android dan iOS
- Rich notifications dengan gambar dan action URL
- Priority levels (low, normal, high, urgent)

### ✅ Admin Panel (Filament)
- Interface lengkap untuk mengelola notifikasi
- Bulk operations (send, mark as read, delete)
- Filtering dan searching
- Statistics dashboard
- Scheduled notifications

### ✅ API Endpoints
- RESTful API untuk aplikasi mobile
- Authentication dengan Laravel Sanctum
- Pagination dan filtering
- FCM token management

### ✅ Automated Notifications
- Observer pattern untuk event-driven notifications
- Automatic notifications untuk presence (check-in/out)
- Automatic notifications untuk permit requests
- Scheduled notifications

### ✅ Queue & Background Processing
- Asynchronous notification sending
- Failed job handling
- Scheduled task processing
- Performance optimization

## 📁 Struktur File

```
app/
├── Models/
│   └── Notification.php                 # Model notifikasi
├── Services/
│   └── NotificationService.php          # Service untuk mengirim notifikasi
├── Http/Controllers/Api/
│   └── NotificationController.php       # API controller untuk mobile
├── Filament/Resources/
│   └── NotificationResource.php         # Filament resource
├── Observers/
│   ├── PresenceObserver.php             # Observer untuk presence events
│   └── PermitObserver.php               # Observer untuk permit events
├── Jobs/
│   └── SendNotificationJob.php          # Job untuk async processing
├── Console/Commands/
│   └── ProcessScheduledNotifications.php # Command untuk scheduled notifications
└── Providers/
    └── AppServiceProvider.php           # Register observers

database/migrations/
├── 2025_08_25_041123_create_notifications_table.php
└── 2025_08_25_041321_add_fcm_token_to_employees_table.php

routes/
└── api.php                              # API routes

config/
└── services.php                         # FCM configuration

docs/
├── API_NOTIFICATION.md                  # API documentation
└── NOTIFICATION_SETUP.md                # Setup guide

examples/
└── notification_examples.php            # Usage examples
```

## 🛠️ Installation & Setup

### 1. Prerequisites
```bash
# Pastikan package sudah terinstall
composer require kreait/firebase-php
composer require kreait/laravel-firebase
composer require laravel-notification-channels/fcm
```

### 2. Environment Variables
```env
# Firebase Configuration
FIREBASE_PROJECT_ID=your-project-id
FCM_SERVER_KEY=your-fcm-server-key

# Queue Configuration
QUEUE_CONNECTION=database
```

### 3. Database Migration
```bash
php artisan migrate
```

### 4. Firebase Setup
1. Download Firebase credentials dari Firebase Console
2. Simpan ke `storage/app/firebase/firebase_credentials.json`
3. Dapatkan FCM Server Key dari Project Settings > Cloud Messaging

### 5. Queue Setup
```bash
# Buat tabel jobs
php artisan queue:table
php artisan migrate

# Jalankan queue worker
php artisan queue:work
```

### 6. Scheduled Tasks
```bash
# Tambahkan ke crontab
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 📱 Mobile App Integration

### Flutter Example
```dart
import 'package:firebase_messaging/firebase_messaging.dart';

class NotificationService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  Future<void> initialize() async {
    // Request permission
    NotificationSettings settings = await _messaging.requestPermission();
    
    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      // Get token
      String? token = await _messaging.getToken();
      if (token != null) {
        await updateFcmToken(token);
      }
    }
  }

  Future<void> updateFcmToken(String token) async {
    await http.post(
      Uri.parse('$baseUrl/notifications/fcm-token'),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Content-Type': 'application/json',
      },
      body: json.encode({'fcm_token': token}),
    );
  }
}
```

### API Endpoints
```bash
# Get notifications
GET /api/notifications

# Mark as read
PATCH /api/notifications/{id}/read

# Update FCM token
POST /api/notifications/fcm-token

# Get statistics
GET /api/notifications/statistics
```

## 🎯 Usage Examples

### Send Single Notification
```php
use App\Services\NotificationService;

$notificationService = new NotificationService();
$employee = Employee::find(1);

$result = $notificationService->sendToRecipient(
    $employee,
    'Check-in Berhasil',
    'Anda telah berhasil check-in pada 08:00',
    [
        'presence_id' => 123,
        'action' => 'view_presence',
    ],
    [
        'type' => 'presence',
        'priority' => 'normal',
    ]
);
```

### Send to All Employees
```php
$result = $notificationService->sendToAllEmployees(
    'Pengumuman Penting',
    'Besok kantor tutup untuk libur nasional',
    ['action' => 'announcement'],
    ['type' => 'announcement', 'priority' => 'high']
);
```

### Scheduled Notification
```php
$scheduledTime = now()->addHours(2);
$notification = $notificationService->scheduleNotification(
    $employee,
    'Reminder Check-out',
    'Jangan lupa check-out sebelum pulang',
    $scheduledTime,
    ['action' => 'check_out_reminder'],
    ['type' => 'presence']
);
```

## 🔧 Admin Panel Features

### Notification Management
- ✅ Create, edit, delete notifications
- ✅ Bulk operations (send, mark as read, delete)
- ✅ Filter by type, status, priority
- ✅ Search functionality
- ✅ Statistics dashboard

### Automated Notifications
- ✅ Presence events (check-in, check-out, late)
- ✅ Permit requests and status updates
- ✅ System announcements
- ✅ Scheduled reminders

## 📊 Monitoring & Analytics

### Statistics
```php
$stats = $notificationService->getStatistics();
// Returns: total, sent, pending, failed, scheduled, unread
```

### Logging
- All notification activities are logged
- Failed notifications are tracked
- Performance metrics available

### Queue Monitoring
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## 🔒 Security Features

- ✅ Authentication required for all API endpoints
- ✅ FCM token validation
- ✅ Rate limiting support
- ✅ Input validation
- ✅ HTTPS enforcement
- ✅ Secure credential storage

## 🚀 Performance Optimization

- ✅ Asynchronous processing with queues
- ✅ Batch notifications (up to 1000 per request)
- ✅ Database indexing for fast queries
- ✅ Caching support
- ✅ Background job processing

## 📋 Notification Types

| Type | Description | Use Case |
|------|-------------|----------|
| `general` | Notifikasi umum | Pengumuman, info |
| `presence` | Kehadiran | Check-in, check-out, keterlambatan |
| `permit` | Izin | Pengajuan, approval, rejection |
| `salary` | Gaji | Transfer gaji, slip gaji |
| `announcement` | Pengumuman | Event, meeting, holiday |
| `system` | Sistem | Maintenance, update |

## 🎨 Priority Levels

| Priority | Description | Use Case |
|----------|-------------|----------|
| `low` | Prioritas rendah | Info umum |
| `normal` | Prioritas normal | Check-in, reminder |
| `high` | Prioritas tinggi | Approval, rejection |
| `urgent` | Prioritas sangat tinggi | Emergency, system down |

## 🔄 Status Tracking

| Status | Description |
|--------|-------------|
| `pending` | Menunggu pengiriman |
| `sent` | Berhasil dikirim |
| `failed` | Gagal dikirim |
| `scheduled` | Terjadwal untuk dikirim |

## 📚 Documentation

- [API Documentation](docs/API_NOTIFICATION.md)
- [Setup Guide](docs/NOTIFICATION_SETUP.md)
- [Usage Examples](examples/notification_examples.php)

## 🧪 Testing

### Manual Testing
```bash
# Test notification service
php artisan tinker
```

```php
use App\Services\NotificationService;
$service = new NotificationService();
$employee = App\Models\Employee::first();
$result = $service->sendToRecipient($employee, 'Test', 'Test message');
```

### Command Testing
```bash
# Test scheduled notifications
php artisan notifications:process-scheduled
```

## 🐛 Troubleshooting

### Common Issues

1. **FCM Token Issues**
   - Check FCM Server Key
   - Verify Firebase project configuration
   - Check network connectivity

2. **Queue Issues**
   - Ensure queue worker is running
   - Check failed jobs
   - Verify database connection

3. **Permission Issues**
   - Check Firebase credentials file permissions
   - Verify file path and access

### Debug Commands
```bash
# Check queue status
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed

# Test FCM connection
php artisan tinker
```

## 📈 Production Checklist

- [ ] FCM Server Key configured
- [ ] Firebase credentials file secured
- [ ] Queue worker running with supervisor/systemd
- [ ] Crontab configured for scheduled tasks
- [ ] Log rotation configured
- [ ] Monitoring and alerting setup
- [ ] Backup strategy for database
- [ ] SSL certificate for HTTPS
- [ ] Rate limiting configured
- [ ] Error tracking (Sentry, etc.) setup

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📄 License

This project is licensed under the MIT License.

## 🆘 Support

For support and questions:
- Create an issue in the repository
- Check the documentation
- Review troubleshooting guide

---

**Happy Coding! 🚀**
