# Tips Performa Laravel dengan Firebase

## Cara Mempercepat Startup dan Kinerja

### 1. Clear Cache Firestore
```bash
# Clear cache Firestore saja
php artisan firestore:clear-cache

# Clear semua cache termasuk Firestore
php artisan firestore:clear-cache --all
```

### 2. Optimize Firestore
```bash
# Optimize dengan timeout cache 30 detik (default)
php artisan firestore:optimize

# Optimize dengan timeout cache custom
php artisan firestore:optimize --cache-timeout=60
```

### 3. Quick Start Script
```bash
# Gunakan script untuk startup cepat
./scripts/quick-start.sh
```

### 4. Environment Variables untuk Performa
```env
# Di file .env
FIREBASE_ENABLED=true
FIREBASE_SUPPRESS_LOGS=true
FIREBASE_CONNECTION_TIMEOUT=10
```

### 5. Clear Cache Manual
```bash
# Clear semua cache Laravel
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Troubleshooting Performa

### Startup Lambat
1. **Clear semua cache:**
   ```bash
   php artisan firestore:clear-cache --all
   ```

2. **Disable Firebase sementara:**
   ```env
   FIREBASE_ENABLED=false
   ```

3. **Restart server:**
   ```bash
   ./scripts/quick-start.sh
   ```

### Data Tidak Update
1. **Clear Firestore cache:**
   ```bash
   php artisan firestore:clear-cache
   ```

2. **Check cache timeout:**
   ```bash
   php artisan firestore:optimize --cache-timeout=30
   ```

### Memory Issues
1. **Clear semua cache:**
   ```bash
   php artisan firestore:clear-cache --all
   ```

2. **Restart server dengan fresh cache**

## Command List

| Command | Description |
|---------|-------------|
| `php artisan firestore:clear-cache` | Clear Firestore cache |
| `php artisan firestore:clear-cache --all` | Clear semua cache |
| `php artisan firestore:optimize` | Optimize Firestore |
| `./scripts/quick-start.sh` | Quick start dengan clear cache |

## Tips Harian

1. **Setiap pagi:** Jalankan `php artisan firestore:clear-cache`
2. **Saat data tidak update:** Clear Firestore cache
3. **Saat startup lambat:** Gunakan `./scripts/quick-start.sh`
4. **Untuk development cepat:** Set `FIREBASE_ENABLED=false`

## ✅ Perbaikan yang Telah Diterapkan

### **Masalah yang Diperbaiki:**
- ❌ Firebase dipanggil di setiap action Filament Resource
- ❌ Model observers membuat instance FirestoreService baru
- ❌ GRPC logs spam terminal
- ❌ Startup lambat karena multiple connections

### **Solusi yang Diterapkan:**
- ✅ **Dependency Injection:** Semua `new FirestoreService()` diganti dengan `app(FirestoreService::class)`
- ✅ **Lazy Loading:** Firebase hanya dipanggil saat benar-benar dibutuhkan
- ✅ **GRPC Suppression:** Logs ditekan dengan environment variables
- ✅ **Service Provider:** FirestoreService didaftarkan sebagai singleton

### **File yang Diperbaiki:**
- `app/Filament/Resources/EmployeeResource.php`
- `app/Filament/Resources/PermitResource.php`
- `app/Filament/Resources/PresenceResource/Pages/ListPresences.php`
- `app/Filament/Resources/PermitResource/Pages/ListPermits.php`
- `app/Models/Employee.php`
- `app/Models/Permit.php`
- `app/Models/GeoLocator.php`
- `app/Models/Presence.php`

### **Hasil:**
- ✅ **Startup lebih cepat** (tidak ada lagi delay GRPC)
- ✅ **Navigasi halaman lebih responsif**
- ✅ **Tidak ada lagi spam logs**
- ✅ **Firebase hanya dipanggil saat dibutuhkan**
