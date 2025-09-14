# Manual Firestore Sync untuk Employee

Dokumentasi ini menjelaskan cara menggunakan sistem sinkronisasi manual Firebase untuk employee yang telah diimplementasikan.

## Fitur yang Tersedia

### 1. Command Line Interface (CLI)

#### Sinkronisasi Semua Employee
```bash
# Sinkronisasi semua employee dari Firestore
php artisan firestore:manual-sync --all

# Dry run - lihat apa yang akan di-sync tanpa melakukan perubahan
php artisan firestore:manual-sync --all --dry-run

# Force sync - sinkronisasi meskipun tidak ada perubahan
php artisan firestore:manual-sync --all --force
```

#### Sinkronisasi Employee Spesifik
```bash
# Sinkronisasi berdasarkan UID
php artisan firestore:manual-sync --uid=abc123

# Sinkronisasi berdasarkan email
php artisan firestore:manual-sync --email=user@example.com

# Dry run untuk employee spesifik
php artisan firestore:manual-sync --uid=abc123 --dry-run
```

#### Cleanup Employee yang Dihapus
```bash
# Hapus employee yang sudah tidak ada di Firestore
php artisan firestore:manual-sync --cleanup

# Dry run untuk cleanup
php artisan firestore:manual-sync --cleanup --dry-run
```

### 2. API Endpoints

Semua endpoint memerlukan autentikasi (`auth:sanctum`).

#### Sinkronisasi Semua Employee
```http
POST /api/manual-sync/all
Content-Type: application/json
Authorization: Bearer {token}
```

Response:
```json
{
    "success": true,
    "message": "Sync completed successfully",
    "data": {
        "duration_seconds": 5,
        "synced": 10,
        "created": 2,
        "updated": 3,
        "errors": [],
        "error_count": 0
    }
}
```

#### Sinkronisasi Employee Spesifik
```http
POST /api/manual-sync/uid
Content-Type: application/json
Authorization: Bearer {token}

{
    "uid": "abc123"
}
```

```http
POST /api/manual-sync/email
Content-Type: application/json
Authorization: Bearer {token}

{
    "email": "user@example.com"
}
```

#### Cleanup Employee
```http
POST /api/manual-sync/cleanup
Content-Type: application/json
Authorization: Bearer {token}
```

#### Full Sync (Sync + Cleanup)
```http
POST /api/manual-sync/full
Content-Type: application/json
Authorization: Bearer {token}
```

#### Status Sinkronisasi
```http
GET /api/manual-sync/status
Authorization: Bearer {token}
```

Response:
```json
{
    "success": true,
    "message": "Status retrieved successfully",
    "data": {
        "local_employees": 25,
        "firestore_employees": 23,
        "synced_employees": 20,
        "unsynced_employees": 5,
        "recent_activity_24h": 3,
        "sync_percentage": 80.0
    }
}
```

#### Dry Run
```http
GET /api/manual-sync/dry-run
Authorization: Bearer {token}
```

Response:
```json
{
    "success": true,
    "message": "Dry run completed successfully",
    "data": {
        "summary": {
            "to_create": 2,
            "to_update": 3,
            "no_change": 18,
            "total_firestore": 23,
            "total_local": 25
        },
        "changes": [
            {
                "action": "create",
                "employee": {
                    "name": "John Doe",
                    "email": "john@example.com",
                    "uid": "abc123"
                }
            }
        ]
    }
}
```

### 3. Filament UI Actions

#### Individual Employee Actions
- **Sync from Firestore**: Sinkronisasi employee individual dari Firestore
- **Force Sync to Firestore**: Kirim employee ke Firestore (jika belum ada)

#### Bulk Actions
- **Sync Selected from Firestore**: Sinkronisasi multiple employee yang dipilih

#### Header Actions
- **Sync All from Firestore**: Sinkronisasi semua employee
- **Full Sync from Firestore**: Sinkronisasi + cleanup
- **Dry Run Sync**: Lihat preview perubahan tanpa melakukan sync
- **Clear Cache**: Hapus cache Firestore

## Cara Penggunaan

### 1. Melalui Command Line

```bash
# Lihat bantuan
php artisan firestore:manual-sync

# Sinkronisasi semua employee
php artisan firestore:manual-sync --all

# Lihat apa yang akan di-sync tanpa melakukan perubahan
php artisan firestore:manual-sync --all --dry-run

# Sinkronisasi employee spesifik
php artisan firestore:manual-sync --uid=abc123

# Cleanup employee yang dihapus
php artisan firestore:manual-sync --cleanup
```

### 2. Melalui API

```javascript
// Contoh menggunakan JavaScript/Fetch
const response = await fetch('/api/manual-sync/all', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    }
});

const result = await response.json();
console.log(result);
```

### 3. Melalui Filament UI

1. Buka halaman Employee di admin panel
2. Pilih employee yang ingin di-sync
3. Klik action "Sync from Firestore" pada employee individual
4. Atau gunakan bulk action untuk multiple employee
5. Atau gunakan header actions untuk operasi global

## Logging dan Monitoring

### Log Files
Semua aktivitas sinkronisasi dicatat di log Laravel dengan prefix "Manual sync:"

```bash
# Lihat log sinkronisasi
tail -f storage/logs/laravel.log | grep "Manual sync:"
```

### Cache Status
Status sinkronisasi terakhir disimpan di cache:
- Key: `last_manual_sync_time`
- TTL: 24 jam

### Error Handling
- Semua error dicatat di log dengan detail lengkap
- API mengembalikan response error yang informatif
- Filament menampilkan notifikasi error/sukses

## Konfigurasi

### Environment Variables
Pastikan konfigurasi Firebase sudah benar di `.env`:
```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CLIENT_EMAIL=your-client-email
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
```

### Service Configuration
Service menggunakan `FirestoreService` yang sudah ada untuk koneksi ke Firestore.

## Troubleshooting

### Common Issues

1. **Employee tidak ditemukan di Firestore**
   - Pastikan UID atau email benar
   - Cek apakah employee ada di Firestore
   - Gunakan dry run untuk melihat data yang tersedia

2. **Error koneksi ke Firestore**
   - Cek konfigurasi Firebase di `.env`
   - Pastikan service account memiliki permission yang benar
   - Cek koneksi internet

3. **Sync tidak berfungsi**
   - Cek log untuk detail error
   - Pastikan model Employee memiliki field yang diperlukan
   - Cek apakah ada constraint database

### Debug Commands

```bash
# Lihat status sync
php artisan firestore:manual-sync --status

# Dry run untuk debug
php artisan firestore:manual-sync --all --dry-run

# Cek log error
tail -f storage/logs/laravel.log | grep -i error
```

## Performance Tips

1. **Gunakan dry run** sebelum melakukan sync besar
2. **Sync individual** untuk employee spesifik
3. **Monitor log** untuk performa dan error
4. **Cache management** - clear cache jika diperlukan
5. **Batch processing** - sistem sudah menggunakan batch untuk efisiensi

## Security

- Semua API endpoint memerlukan autentikasi
- Log tidak menyimpan data sensitif
- Service account Firebase memiliki permission terbatas
- Input validation pada semua endpoint

## Support

Jika mengalami masalah:
1. Cek log file untuk detail error
2. Gunakan dry run untuk debug
3. Pastikan konfigurasi Firebase benar
4. Cek dokumentasi FirestoreService yang ada

