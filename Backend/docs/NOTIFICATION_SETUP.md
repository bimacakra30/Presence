# Setup Sistem Notifikasi Mobile

Dokumentasi ini menjelaskan cara setup dan konfigurasi sistem notifikasi untuk aplikasi mobile.

## Prerequisites

1. **Firebase Project**: Pastikan sudah memiliki Firebase project
2. **FCM Server Key**: Dapatkan FCM Server Key dari Firebase Console
3. **Firebase Credentials**: File JSON credentials untuk Firebase Admin SDK

## 1. Konfigurasi Environment Variables

Tambahkan variabel berikut ke file `.env`:

```env
# Firebase Configuration
FIREBASE_PROJECT_ID=your-project-id

# Queue Configuration (untuk async processing)
QUEUE_CONNECTION=database
```

## 2. Setup Firebase

### 2.1 Download Firebase Credentials
1. Buka [Firebase Console](https://console.firebase.google.com/)
2. Pilih project Anda
3. Buka **Project Settings** > **Service Accounts**
4. Klik **Generate New Private Key**
5. Simpan file JSON ke `storage/app/firebase/firebase_credentials.json`

### 2.2 Download Service Account Key
1. Di Firebase Console, buka **Project Settings** > **Service accounts**
2. Klik **"Generate new private key"**
3. Download file JSON
4. Simpan ke `storage/app/firebase/firebase_credentials.json`

## 3. Database Migration

Jalankan migration untuk membuat tabel notifications:

```bash
php artisan migrate
```

## 4. Queue Setup

### 4.1 Buat tabel jobs
```bash
php artisan queue:table
php artisan migrate
```

### 4.2 Jalankan queue worker
```bash
php artisan queue:work
```

Untuk production, gunakan supervisor atau systemd untuk menjalankan queue worker secara otomatis.

## 5. Scheduled Tasks

### 5.1 Tambahkan ke crontab
```bash
crontab -e
```

Tambahkan baris berikut:
```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### 5.2 Setup Kernel untuk scheduled notifications
Edit file `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Process scheduled notifications every minute
    $schedule->command('notifications:process-scheduled')->everyMinute();
}
```

## 6. Testing

### 6.1 Test Notification Service
```bash
php artisan tinker
```

```php
use App\Services\NotificationService;
use App\Models\Employee;

$notificationService = new NotificationService();
$employee = Employee::first();

// Test send notification
$result = $notificationService->sendToRecipient(
    $employee,
    'Test Notification',
    'This is a test notification',
    ['test' => 'data'],
    ['type' => 'general', 'priority' => 'normal']
);

var_dump($result);
```

### 6.2 Test Command
```bash
php artisan notifications:process-scheduled
```

## 7. Filament Admin Panel

Setelah setup selesai, Anda dapat mengakses panel admin untuk mengelola notifikasi:

1. Buka `/admin`
2. Login dengan credentials admin
3. Navigasi ke **Communication** > **Notifications**
4. Buat, edit, dan kelola notifikasi

## 8. Mobile App Integration

### 8.1 Flutter Setup

1. **Install dependencies**:
```yaml
dependencies:
  firebase_messaging: ^14.7.10
  firebase_core: ^2.24.2
```

2. **Initialize Firebase**:
```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  runApp(MyApp());
}
```

3. **Request permission dan get token**:
```dart
class NotificationService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  Future<void> initialize() async {
    // Request permission
    NotificationSettings settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      // Get token
      String? token = await _messaging.getToken();
      if (token != null) {
        await updateFcmToken(token);
      }

      // Listen for token refresh
      _messaging.onTokenRefresh.listen((token) {
        updateFcmToken(token);
      });
    }
  }

  Future<void> updateFcmToken(String token) async {
    // Call your API to update FCM token
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

4. **Handle background messages**:
```dart
// In main.dart
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print('Handling a background message: ${message.messageId}');
}

void main() async {
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
  // ... rest of initialization
}
```

5. **Handle foreground messages**:
```dart
class _MyHomePageState extends State<MyHomePage> {
  @override
  void initState() {
    super.initState();
    
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      // Show local notification
      showLocalNotification(message);
    });

    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      // Handle notification tap when app is in background
      handleNotificationTap(message);
    });
  }

  void showLocalNotification(RemoteMessage message) {
    // Use flutter_local_notifications package
    // Implementation depends on your notification package
  }

  void handleNotificationTap(RemoteMessage message) {
    // Navigate to specific screen based on notification data
    final data = message.data;
    if (data['action'] == 'view_presence') {
      // Navigate to presence detail
    } else if (data['action'] == 'view_permit') {
      // Navigate to permit detail
    }
  }
}
```

### 8.2 React Native Setup

1. **Install dependencies**:
```bash
npm install @react-native-firebase/app @react-native-firebase/messaging
```

2. **Initialize Firebase**:
```javascript
import messaging from '@react-native-firebase/messaging';

async function requestUserPermission() {
  const authStatus = await messaging().requestPermission();
  const enabled =
    authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
    authStatus === messaging.AuthorizationStatus.PROVISIONAL;

  if (enabled) {
    console.log('Authorization status:', authStatus);
  }
}

async function getFCMToken() {
  const fcmToken = await messaging().getToken();
  if (fcmToken) {
    console.log('FCM Token:', fcmToken);
    // Send token to your server
    await updateFcmToken(fcmToken);
  }
}
```

## 9. Monitoring dan Logging

### 9.1 Log Files
Sistem notifikasi akan mencatat log di:
- `storage/logs/laravel.log` - General Laravel logs
- Queue logs (jika menggunakan database queue)

### 9.2 Monitoring Commands
```bash
# Check queue status
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### 9.3 Statistics
Gunakan NotificationService untuk mendapatkan statistik:
```php
$notificationService = new NotificationService();
$stats = $notificationService->getStatistics();
```

## 10. Troubleshooting

### 10.1 FCM Token Issues
- Pastikan FCM Server Key benar
- Check apakah token valid di Firebase Console
- Verify network connectivity

### 10.2 Queue Issues
- Pastikan queue worker berjalan
- Check failed jobs: `php artisan queue:failed`
- Verify database connection

### 10.3 Permission Issues
- Pastikan file `storage/app/firebase/firebase_credentials.json` dapat diakses
- Check file permissions: `chmod 600 storage/app/firebase/firebase_credentials.json`

### 10.4 Common Errors

**"FCM send failed"**
- Check FCM Server Key
- Verify Firebase project configuration
- Check network connectivity

**"No FCM token for notification"**
- Pastikan mobile app sudah mengirim FCM token
- Check apakah employee memiliki FCM token

**"Queue timeout"**
- Increase queue timeout di `config/queue.php`
- Check server resources

## 11. Production Checklist

- [ ] FCM Server Key dikonfigurasi
- [ ] Firebase credentials file ada dan aman
- [ ] Queue worker berjalan dengan supervisor/systemd
- [ ] Crontab dikonfigurasi untuk scheduled tasks
- [ ] Log rotation dikonfigurasi
- [ ] Monitoring dan alerting setup
- [ ] Backup strategy untuk database
- [ ] SSL certificate untuk HTTPS
- [ ] Rate limiting dikonfigurasi
- [ ] Error tracking (Sentry, dll) setup

## 12. Security Considerations

1. **FCM Server Key**: Jangan expose ke public
2. **Firebase Credentials**: Simpan dengan permission yang tepat
3. **API Rate Limiting**: Implement rate limiting untuk API endpoints
4. **Token Validation**: Validate FCM tokens di mobile app
5. **HTTPS**: Gunakan HTTPS untuk semua API calls
6. **Input Validation**: Validate semua input dari mobile app
