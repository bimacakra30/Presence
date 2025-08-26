# Sistem Notifikasi Otomatis untuk Presensi

Dokumentasi ini menjelaskan sistem notifikasi otomatis yang terintegrasi dengan jadwal presensi karyawan.

## 📅 **Jadwal Notifikasi Otomatis**

### **Check-in (08:00)**
- **07:30** - Reminder check-in (30 menit sebelum)
- **08:00** - Notifikasi waktu check-in
- **08:15** - Notifikasi keterlambatan (15 menit setelah)

### **Check-out (17:00)**
- **16:30** - Reminder check-out (30 menit sebelum)
- **17:00** - Notifikasi waktu check-out

## 🤖 **Command untuk Automated Notifications**

### **1. Test Mode (Tidak mengirim notifikasi sebenarnya)**
```bash
# Test reminder check-in
php artisan notifications:automated-presence --type=reminder --time=07:30 --test

# Test check-in notification
php artisan notifications:automated-presence --type=check-in --time=08:00 --test

# Test late notification
php artisan notifications:automated-presence --type=late --time=08:15 --test

# Test check-out reminder
php artisan notifications:automated-presence --type=reminder --time=16:30 --test

# Test check-out notification
php artisan notifications:automated-presence --type=check-out --time=17:00 --test
```

### **2. Production Mode (Mengirim notifikasi sebenarnya)**
```bash
# Kirim reminder check-in
php artisan notifications:automated-presence --type=reminder --time=07:30

# Kirim check-in notification
php artisan notifications:automated-presence --type=check-in --time=08:00

# Kirim late notification
php artisan notifications:automated-presence --type=late --time=08:15

# Kirim check-out reminder
php artisan notifications:automated-presence --type=reminder --time=16:30

# Kirim check-out notification
php artisan notifications:automated-presence --type=check-out --time=17:00
```

## ⏰ **Setup Cron Job untuk Otomatisasi**

### **1. Tambahkan ke Crontab**
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### **2. Atau gunakan Laravel Scheduler**
Laravel scheduler sudah dikonfigurasi di `app/Console/Kernel.php`:

```php
// Check-in reminder (07:30)
$schedule->command('notifications:automated-presence --type=reminder')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground();

// Check-in notification (08:00)
$schedule->command('notifications:automated-presence --type=check-in')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Late notification (08:15)
$schedule->command('notifications:automated-presence --type=late')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->runInBackground();

// Check-out reminder (16:30)
$schedule->command('notifications:automated-presence --type=reminder')
    ->dailyAt('16:30')
    ->withoutOverlapping()
    ->runInBackground();

// Check-out notification (17:00)
$schedule->command('notifications:automated-presence --type=check-out')
    ->dailyAt('17:00')
    ->withoutOverlapping()
    ->runInBackground();
```

## 📱 **Jenis Notifikasi**

### **1. Reminder Check-in (07:30)**
```
Title: Reminder Check-in
Body: Hai {nama}! Dalam 30 menit lagi adalah waktu check-in. 
      Siapkan diri Anda untuk check-in tepat waktu.
```

### **2. Check-in Notification (08:00)**
```
Title: Waktu Check-in
Body: Selamat pagi {nama}! Sekarang adalah waktu check-in. 
      Silakan lakukan check-in untuk mencatat kehadiran Anda hari ini.
```

### **3. Late Notification (08:15)**
```
Title: Keterlambatan Check-in
Body: Hai {nama}! Anda belum melakukan check-in. 
      Waktu check-in adalah 08:00. Silakan check-in segera untuk menghindari keterlambatan.
```

### **4. Reminder Check-out (16:30)**
```
Title: Reminder Check-out
Body: Hai {nama}! Dalam 30 menit lagi adalah waktu check-out. 
      Selesaikan pekerjaan Anda dan siap untuk check-out.
```

### **5. Check-out Notification (17:00)**
```
Title: Waktu Check-out
Body: Hai {nama}! Sekarang adalah waktu check-out. 
      Jangan lupa untuk melakukan check-out sebelum pulang.
```

## ⚙️ **Konfigurasi Jadwal**

Jadwal dapat dikonfigurasi di `app/Console/Commands/AutomatedPresenceNotificationCommand.php`:

```php
protected $schedule = [
    'check_in_time' => '08:00',
    'check_out_time' => '17:00',
    'reminder_before_check_in' => 30, // minutes before check-in
    'reminder_before_check_out' => 30, // minutes before check-out
    'late_threshold' => 15, // minutes after check-in time
];
```

## 🔧 **Maintenance Commands**

### **1. Cleanup FCM Tokens**
```bash
# Dry run (lihat apa yang akan dihapus)
php artisan fcm:cleanup-tokens --days=30 --dry-run

# Actual cleanup
php artisan fcm:cleanup-tokens --days=30
```

### **2. Monitor Notifications**
```bash
# Monitor 24 jam terakhir
php artisan fcm:monitor --period=24

# Monitor dengan detail
php artisan fcm:monitor --period=24 --detailed

# Export ke CSV
php artisan fcm:monitor --period=24 --export
```

### **3. Live Monitor**
```bash
# Monitor real-time (5 menit)
php artisan fcm:live-monitor --interval=5 --duration=300
```

## 📊 **Monitoring Dashboard**

### **API Endpoints**
```bash
# Dashboard overview
GET /api/monitoring/dashboard

# Notification statistics
GET /api/monitoring/notifications/stats

# Breakdown by type
GET /api/monitoring/notifications/breakdown-by-type

# Hourly distribution
GET /api/monitoring/notifications/hourly-distribution

# Top recipients
GET /api/monitoring/notifications/top-recipients

# Recent notifications
GET /api/monitoring/notifications/recent

# FCM token statistics
GET /api/monitoring/fcm-tokens/stats

# System statistics
GET /api/monitoring/system/stats

# Alerts and recommendations
GET /api/monitoring/alerts
```

## 🚨 **Troubleshooting**

### **1. Notifikasi tidak terkirim**
- Cek apakah cron job berjalan: `crontab -l`
- Cek log Laravel: `tail -f storage/logs/laravel.log`
- Test command manual: `php artisan notifications:automated-presence --type=check-in --test`

### **2. FCM tokens tidak valid**
- Cek FCM tokens di Firestore
- Cleanup tokens lama: `php artisan fcm:cleanup-tokens`
- Pastikan mobile app mengirim token yang valid

### **3. Schedule tidak berjalan**
- Cek Laravel scheduler: `php artisan schedule:list`
- Test scheduler: `php artisan schedule:run`
- Pastikan cron job dikonfigurasi dengan benar

### **4. Performance issues**
- Monitor memory usage: `php artisan fcm:monitor`
- Cek queue jobs: `php artisan queue:work`
- Optimize database queries jika diperlukan

## 📈 **Best Practices**

### **1. Testing**
- Selalu test dengan `--test` flag sebelum production
- Test di environment staging terlebih dahulu
- Monitor hasil test dengan dashboard

### **2. Monitoring**
- Setup alerting untuk failure rate tinggi
- Monitor FCM token validity secara berkala
- Track notification delivery success rate

### **3. Maintenance**
- Cleanup FCM tokens lama secara berkala
- Monitor system performance
- Backup notification data secara regular

### **4. Security**
- Pastikan API endpoints protected dengan authentication
- Monitor untuk abuse atau spam
- Implement rate limiting jika diperlukan

## 🔄 **Customization**

### **1. Custom Schedule**
Untuk mengubah jadwal, edit `app/Console/Kernel.php`:

```php
// Contoh: Ubah check-in time ke 09:00
$schedule->command('notifications:automated-presence --type=check-in')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();
```

### **2. Custom Messages**
Untuk mengubah pesan, edit method di `AutomatedPresenceNotificationCommand.php`:

```php
protected function getCheckInMessage($employee)
{
    return [
        'title' => 'Custom Check-in Title',
        'body' => "Custom message for {$employee->name}"
    ];
}
```

### **3. Custom Conditions**
Untuk menambah kondisi khusus (misal: hanya hari kerja), edit method `getActiveEmployees()`:

```php
protected function getActiveEmployees()
{
    return Employee::where('status', 'aktif')
        ->where('department', 'IT') // Contoh filter
        ->get();
}
```

## 📞 **Support**

Jika ada masalah dengan sistem notifikasi otomatis:

1. Cek log: `storage/logs/laravel.log`
2. Test command manual
3. Monitor dashboard untuk statistik
4. Hubungi tim development jika diperlukan
