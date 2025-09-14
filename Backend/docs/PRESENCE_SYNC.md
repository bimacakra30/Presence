# Sinkronisasi Data Presensi dari Firestore

## Deskripsi
Fitur ini memungkinkan untuk mengambil seluruh data presensi dari collection 'presence' di Firestore dan menyinkronkannya dengan database lokal MySQL.

## Cara Penggunaan

### 1. Melalui Admin Panel (Filament)
1. Buka halaman "Presensi Karyawan" di admin panel
2. Klik tombol **"Muat Data Presensi"** di bagian atas tabel
3. Konfirmasi dialog yang muncul
4. Tunggu proses sinkronisasi selesai
5. Lihat notifikasi hasil sinkronisasi

### 2. Melalui Command Line
```bash
# Sinkronisasi normal
php artisan presence:sync

# Sinkronisasi paksa (update semua data)
php artisan presence:sync --force
```

## Fitur

### Data yang Disinkronisasi
- **UID**: Identitas unik karyawan
- **Nama**: Nama karyawan
- **Tanggal**: Tanggal presensi
- **Clock In/Out**: Waktu masuk dan pulang
- **Foto**: Foto dokumentasi masuk dan pulang
- **Status**: Status terlambat atau tidak
- **Durasi Keterlambatan**: Durasi keterlambatan dalam menit
- **Early Clock Out**: Status pulang lebih awal
- **Alasan Pulang Lebih Awal**: Alasan jika pulang lebih awal
- **Nama Lokasi**: Lokasi presensi

### Mapping Data Firestore ke Database
| Firestore Field | Database Field | Keterangan |
|----------------|----------------|------------|
| `uid` | `uid` | UID karyawan |
| `name` | `nama` | Nama karyawan |
| `date` | `tanggal` | Tanggal presensi |
| `clockIn` | `clock_in` | Waktu masuk |
| `clockOut` | `clock_out` | Waktu pulang |
| `fotoClockIn` | `foto_clock_in` | URL foto masuk |
| `fotoClockInPublicId` | `public_id_clock_in` | Public ID Cloudinary |
| `fotoClockOut` | `foto_clock_out` | URL foto pulang |
| `fotoClockOutPublicId` | `public_id_clock_out` | Public ID Cloudinary |
| `late` | `status` | Status terlambat (boolean) |
| `earlyClockOut` | `early_clock_out` | Status pulang lebih awal |
| `earlyClockOutReason` | `early_clock_out_reason` | Alasan pulang lebih awal |
| `locationName` | `location_name` | Nama lokasi |

### Logika Sinkronisasi
1. **Data Baru**: Jika `firestore_id` belum ada di database lokal
2. **Data Diperbarui**: Jika ada perubahan pada field yang relevan
3. **Data Tidak Berubah**: Jika tidak ada perubahan
4. **Error**: Jika terjadi kesalahan (misal: karyawan tidak ditemukan)

### Validasi Data
- Memastikan karyawan dengan UID yang sesuai ada di database lokal
- Validasi format tanggal dan waktu
- Konversi tipe data yang sesuai (boolean, datetime, dll)

## Monitoring dan Logging

### Log Files
Semua aktivitas sinkronisasi dicatat di `storage/logs/laravel.log` dengan prefix:
- `Presence sync: Starting sync all presence data from Firestore`
- `Presence sync: Retrieved presence data from Firestore`
- `Presence sync: Presence created/updated from Firestore`

### Cache
- Waktu sinkronisasi terakhir disimpan di cache dengan key `last_presence_sync_time`
- Cache berlaku selama 24 jam

### Status Sinkronisasi
Gunakan method `getSyncStatus()` untuk melihat:
- Jumlah data lokal vs Firestore
- Persentase sinkronisasi
- Aktivitas terbaru (24 jam terakhir)
- Waktu sinkronisasi terakhir

## Troubleshooting

### Error: "Employee tidak ditemukan untuk UID"
- Pastikan data karyawan sudah tersinkronisasi terlebih dahulu
- Jalankan sinkronisasi karyawan: `php artisan employee:sync`

### Error: "Firebase credentials file not found"
- Pastikan file `storage/app/firebase/firebase_credentials.json` ada
- Periksa konfigurasi Firebase di `.env`

### Error: "Collection 'presence' not found"
- Pastikan collection 'presence' ada di Firestore
- Periksa nama project Firebase di konfigurasi

## Penghapusan Data

### Fitur Penghapusan Otomatis
Ketika data presence dihapus dari database lokal, sistem akan secara otomatis:
1. **Menghapus data dari Firestore** collection 'presence'
2. **Menghapus foto dari Cloudinary** (jika ada)
3. **Mencatat log** semua aktivitas penghapusan

### Cara Penghapusan
1. **Melalui Admin Panel**: Hapus data presence dari tabel
2. **Melalui Bulk Action**: Hapus multiple data sekaligus
3. **Melalui Command**: `php artisan presence:test-deletion`

### Testing Penghapusan
```bash
# Test penghapusan dengan data yang ada
php artisan presence:test-deletion

# Test penghapusan dengan Firestore ID spesifik
php artisan presence:test-deletion --firestore-id=0I0eArjpRsw8UOU1MYwr
```

### Logging Penghapusan
Semua aktivitas penghapusan dicatat di `storage/logs/laravel.log`:
- `Presence sync: Deleting presence from Firestore`
- `Presence sync: Successfully deleted presence from Firestore`
- `Berhasil menghapus data Firestore dengan ID: {firestore_id}`

## Performance Tips
- Gunakan `--force` hanya jika diperlukan
- Sinkronisasi berjalan dalam transaksi database
- Data besar akan memakan waktu lebih lama
- Monitor log untuk performa dan error
- Penghapusan otomatis berjalan di background
