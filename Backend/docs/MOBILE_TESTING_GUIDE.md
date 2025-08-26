# Panduan Uji Coba Notifikasi dengan Mobile Developer

## 🎯 **Tujuan Uji Coba**
- Memastikan notifikasi otomatis berfungsi dengan FCM tokens dari mobile app
- Test jadwal notifikasi presensi (07:30, 08:00, 08:15, 16:30, 17:00)
- Verifikasi pesan notifikasi diterima di mobile app

## 📱 **Langkah-langkah untuk Mobile Developer**

### **1. Setup FCM Token di Mobile App**

#### **A. Dapatkan FCM Token**
```javascript
// Di mobile app (Flutter/React Native)
// Pastikan sudah setup Firebase dan dapat FCM token
const fcmToken = await getFCMToken();
console.log('FCM Token:', fcmToken);
```

#### **B. Kirim FCM Token ke Backend**
```javascript
// POST ke backend untuk menyimpan token
const response = await fetch('http://your-backend-url/api/notifications/fcm-token/firestore', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_AUTH_TOKEN'
  },
  body: JSON.stringify({
    fcm_token: fcmToken,
    device_id: 'device_unique_id',
    platform: 'android' // atau 'ios'
  })
});
```

### **2. Test Manual Notifikasi**

#### **A. Test Single Notification**
```bash
# Di backend, test kirim notifikasi manual
curl -X POST http://your-backend-url/api/notifications/test-firestore \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_AUTH_TOKEN" \
  -d '{
    "employee_uid": "employee_uid_dari_mobile",
    "title": "Test Notifikasi",
    "body": "Ini adalah test notifikasi dari backend"
  }'
```

#### **B. Test via Command**
```bash
# Test dengan employee UID tertentu
php artisan fcm:test-firestore-tokens --employee-uid=employee_uid_dari_mobile
```

## ⏰ **Test Jadwal Otomatis**

### **1. Setup Waktu Test**

#### **A. Ubah Waktu Server/Backend**
```bash
# Di server backend, set waktu ke 07:30 WIB
sudo date -s "2025-08-26 07:30:00"
```

#### **B. Atau Test dengan Waktu Spesifik**
```bash
# Test reminder check-in (07:30)
php artisan notifications:automated-presence --type=reminder --time=07:30 --test

# Test check-in (08:00)
php artisan notifications:automated-presence --type=check-in --time=08:00 --test

# Test late notification (08:15)
php artisan notifications:automated-presence --type=late --time=08:15 --test
```

### **2. Jalankan Scheduler**
```bash
# Jalankan scheduler manual
php artisan schedule:run

# Atau tunggu cron job berjalan otomatis
```

## 🔍 **Monitoring & Debugging**

### **1. Cek FCM Tokens di Firestore**
```bash
# Cek tokens yang tersimpan
php artisan fcm:test-firestore-tokens --employee-uid=employee_uid_dari_mobile
```

### **2. Monitor Notifications**
```bash
# Monitor real-time
php artisan fcm:live-monitor --interval=5 --duration=300

# Monitor 24 jam terakhir
php artisan fcm:monitor --period=24 --detailed
```

### **3. Cek Logs**
```bash
# Cek log Laravel
tail -f storage/logs/laravel.log

# Cek log FCM
grep -i "fcm\|notification" storage/logs/laravel.log
```

## 📋 **Checklist Uji Coba**

### **✅ Pre-Test Checklist**
- [ ] Mobile app sudah setup Firebase
- [ ] FCM token berhasil didapat
- [ ] Token berhasil dikirim ke backend
- [ ] Token tersimpan di Firestore
- [ ] Backend timezone sudah Asia/Jakarta
- [ ] Cron job sudah setup

### **✅ Test Scenarios**
- [ ] **07:30** - Reminder check-in notification
- [ ] **08:00** - Check-in time notification
- [ ] **08:15** - Late notification
- [ ] **16:30** - Reminder check-out notification
- [ ] **17:00** - Check-out time notification

### **✅ Verification**
- [ ] Notifikasi diterima di mobile app
- [ ] Pesan sesuai dengan jadwal
- [ ] Data payload lengkap
- [ ] Notifikasi muncul di notification center
- [ ] Tap notification membuka app

## 🚨 **Troubleshooting**

### **1. Notifikasi Tidak Diterima**
```bash
# Cek FCM token valid
php artisan fcm:test-valid-token --employee=51 --token=FCM_TOKEN_DARI_MOBILE

# Cek token di Firestore
php artisan fcm:test-firestore-tokens --employee-uid=employee_uid
```

### **2. Token Tidak Tersimpan**
```bash
# Cek API endpoint
curl -X GET http://your-backend-url/api/notifications/fcm-tokens/firestore \
  -H "Authorization: Bearer YOUR_AUTH_TOKEN"
```

### **3. Scheduler Tidak Berjalan**
```bash
# Cek cron job
crontab -l

# Test scheduler manual
php artisan schedule:run

# Cek scheduled tasks
php artisan schedule:list
```

### **4. Timezone Issues**
```bash
# Test timezone
php test_timezone.php

# Cek waktu server
date
php -r "echo date('Y-m-d H:i:s');"
```

## 📞 **Komunikasi dengan Mobile Developer**

### **1. Informasi yang Diperlukan**
- Employee UID dari mobile app
- FCM token yang valid
- Platform (Android/iOS)
- Device ID

### **2. Koordinasi Waktu Test**
- Set waktu server ke waktu test (07:30, 08:00, dll)
- Konfirmasi mobile app siap menerima notifikasi
- Jalankan test dan monitor hasil

### **3. Feedback Loop**
- Mobile developer konfirmasi notifikasi diterima
- Backend developer monitor delivery status
- Debug jika ada masalah

## 🎯 **Expected Results**

### **1. Success Criteria**
- ✅ Notifikasi diterima tepat waktu
- ✅ Pesan sesuai dengan jadwal
- ✅ Data payload lengkap
- ✅ Notifikasi bisa di-tap
- ✅ App terbuka saat notifikasi di-tap

### **2. Performance Metrics**
- Delivery time < 5 detik
- Success rate > 95%
- No duplicate notifications
- Proper error handling

## 📱 **Mobile App Integration**

### **1. Notification Handling**
```javascript
// Handle notification received
messaging.onMessage((payload) => {
  console.log('Notification received:', payload);
  // Show local notification
  showLocalNotification(payload);
});

// Handle notification tap
messaging.onNotificationOpenedApp((payload) => {
  console.log('Notification tapped:', payload);
  // Navigate to specific screen
  navigateToScreen(payload.data);
});
```

### **2. Token Refresh**
```javascript
// Refresh token when needed
messaging.onTokenRefresh(() => {
  messaging.getToken().then((refreshedToken) => {
    // Send new token to backend
    sendTokenToBackend(refreshedToken);
  });
});
```

## 🔄 **Continuous Testing**

### **1. Daily Test**
- Test setiap jenis notifikasi
- Monitor delivery success rate
- Cek error logs

### **2. Weekly Maintenance**
- Cleanup old FCM tokens
- Monitor system performance
- Update documentation

### **3. Monthly Review**
- Analyze notification patterns
- Optimize message content
- Review user feedback
