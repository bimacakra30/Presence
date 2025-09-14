# 🚀 Presence Realtime Sync System

Script lengkap untuk sistem realtime sync Firestore → MySQL dalam 1 file.

## 📁 File Structure

```
scripts/
├── realtimesync          # Script utama realtime sync
└── README.md            # Dokumentasi ini
```

## 🎯 Fitur Utama

- ✅ **Real-time monitoring** - Deteksi perubahan setiap 3 detik
- ✅ **Employee sync** - Sinkronisasi data employee Firestore → MySQL
- ✅ **Permit sync** - Sinkronisasi data permit Firestore → MySQL
- ✅ **Automatic sync** - Sync otomatis saat ada perubahan
- ✅ **Live statistics** - Monitoring uptime, memory, sync count
- ✅ **Error handling** - Robust error handling & recovery
- ✅ **Webhook support** - Support untuk webhook endpoints
- ✅ **Performance optimization** - Optimized untuk performa tinggi

## 🚀 Cara Penggunaan

### **1. Jalankan Realtime Sync:**
```bash
php realtime:active
# atau
php realtime:active start
```

### **2. Test System:**
```bash
php realtime:active test
```

### **3. Check Status:**
```bash
php realtime:active status
```

### **4. Help:**
```bash
php realtime:active help
```

## ⚡ Cara Kerja

1. **Initialize** - Ambil snapshot awal dari Firestore (employees + permits)
2. **Monitor** - Cek perubahan setiap 3 detik
3. **Detect** - Bandingkan snapshot lama vs baru (employees + permits)
4. **Sync** - Sync perubahan ke MySQL (employees + permits)
5. **Log** - Catat semua aktivitas

## 📊 Monitoring

Script menampilkan informasi real-time:
- 🕐 Uptime
- 💾 Memory usage
- 🔄 Total syncs
- ⚡ Total changes
- ❌ Total errors

## 🎛️ Controls

- **Ctrl+C** - Stop system
- **s + Enter** - Show statistics (planned)
- **m + Enter** - Manual sync (planned)

## 🔧 Prerequisites

- ✅ Database connection (MySQL)
- ✅ Firestore connection
- ✅ Employee collection in Firestore
- ✅ Permit collection in Firestore
- ✅ Webhook endpoints (optional)

## 📝 Logs

Semua aktivitas dicatat di:
- Terminal output (real-time)
- Laravel logs (`storage/logs/laravel.log`)

## 🎉 Keunggulan

- **1 File Solution** - Semua fitur dalam 1 file
- **Easy to Use** - Command sederhana
- **Real-time** - Deteksi perubahan dalam 3 detik
- **Robust** - Error handling yang baik
- **Monitoring** - Live statistics
- **Flexible** - Support multiple commands

## 🚀 Quick Start

```bash
# Test system
php realtime:active test

# Start realtime sync
php realtime:active

# Check status
php realtime:active status
```

## 📞 Support

Jika ada masalah, cek:
1. Database connection
2. Firestore credentials
3. Laravel logs
4. System prerequisites
