# 📱 FCM Notification System - Dokumentasi Lengkap

## 🎯 Overview
Sistem notifikasi FCM (Firebase Cloud Messaging) yang sudah berfungsi untuk mengirim push notification ke device teman Anda.

## ✅ Status Saat Ini
- **FCM Token**: ✅ Berfungsi dengan baik
- **Notifikasi**: ✅ Berhasil dikirim ke device
- **Sistem**: ✅ Stabil dan siap digunakan

## 🚀 Cara Menggunakan

### 1. Script Notifikasi Custom (Recommended)
```bash
# Format dasar
php send_custom_notification.php "Judul Notifikasi" "Isi Pesan"

# Contoh penggunaan
php send_custom_notification.php "Halo! 👋" "Ada kabar apa nih?"
php send_custom_notification.php "Meeting Tim 🚨" "Meeting dalam 30 menit!"
php send_custom_notification.php "Update Status 📊" "Status kehadiran: HADIR"
```

### 2. Script Test Lengkap
```bash
# Test semua jenis notifikasi
php send_real_notification.php

# Test token validity
php get_real_fcm_tokens.php

# Cleanup token expired
php cleanup_expired_fcm_tokens.php
```

## 📱 Jenis Notifikasi yang Tersedia

### A. Notifikasi Sederhana
- **Judul**: Halo Bima! 👋
- **Pesan**: Ini adalah test notifikasi dari sistem Presence
- **Data**: Basic info (timestamp, sender)

### B. Notifikasi dengan Data
- **Judul**: Update Status Kehadiran 📊
- **Pesan**: Status kehadiran Anda hari ini: HADIR
- **Data**: Status, date, time, location, action_url

### C. Notifikasi Penting
- **Judul**: 🚨 PENTING: Meeting Tim
- **Pesan**: Meeting tim dijadwalkan dalam 15 menit
- **Data**: Priority, meeting_time, room, agenda

## 🔧 Troubleshooting

### Jika Notifikasi Gagal
1. **Cek Koneksi Internet**: Device harus terhubung internet
2. **Cek Aplikasi**: Pastikan aplikasi mobile terbuka
3. **Cek FCM Token**: Jalankan `php get_real_fcm_tokens.php`
4. **Cleanup Token**: Jalankan `php cleanup_expired_fcm_tokens.php`

### Error yang Umum
- **404 UNREGISTERED**: Token expired, perlu cleanup
- **400 INVALID_ARGUMENT**: Token tidak valid, perlu refresh
- **Network Error**: Cek koneksi internet dan firewall

## 📊 Monitoring

### Cek Log Laravel
```bash
# Cek log notifikasi terbaru
tail -n 100 storage/logs/laravel.log | grep -i "notification\|fcm\|success\|error"

# Cek log spesifik
tail -n 50 storage/logs/laravel.log | grep "Test Notifikasi Custom"
```

### Cek Status FCM Token
```bash
# Test token validity
php get_real_fcm_tokens.php

# Cleanup expired tokens
php cleanup_expired_fcm_tokens.php
```

## 🎉 Fitur yang Sudah Berfungsi

1. **✅ FCM Token Management**
   - Auto cleanup expired tokens
   - Token validation
   - Multiple device support

2. **✅ Notification Delivery**
   - Push notification ke device
   - Custom title dan message
   - Additional data payload

3. **✅ Error Handling**
   - Duplicate prevention
   - Fallback mechanisms
   - Comprehensive logging

4. **✅ Custom Scripts**
   - Easy-to-use notification script
   - Real-time testing
   - Status monitoring

## 🔮 Pengembangan Selanjutnya

### Fitur yang Bisa Ditambahkan
1. **Scheduled Notifications**: Notifikasi terjadwal
2. **Bulk Notifications**: Kirim ke multiple employees
3. **Notification Templates**: Template notifikasi yang bisa digunakan ulang
4. **Delivery Reports**: Laporan pengiriman notifikasi
5. **Mobile App Integration**: Integrasi dengan aplikasi mobile

### API Endpoints
- `POST /api/notifications/send`: Kirim notifikasi via API
- `GET /api/notifications/status`: Cek status notifikasi
- `DELETE /api/notifications/fcm-token/firestore`: Hapus FCM token

## 📞 Support

Jika ada masalah atau pertanyaan:
1. Cek log Laravel terlebih dahulu
2. Jalankan script troubleshooting
3. Pastikan konfigurasi FCM sudah benar
4. Cek koneksi internet dan firewall

---

**🎯 Sistem sudah siap digunakan untuk mengirim notifikasi real ke device teman Anda!**

