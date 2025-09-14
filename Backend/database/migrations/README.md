# Database Migration Structure

File migration ini dibuat untuk dokumentasi dan referensi struktur database yang terorganisir dengan prinsip **1 file 1 table**.

## Urutan Migration

Migration dibuat dengan urutan yang logis berdasarkan dependensi antar tabel:

### 1. `0001_01_01_000000_create_users_table.php` (Laravel Default)
- **Tabel:** `users`, `password_reset_tokens`, `sessions`
- **Fungsi:** Menyimpan data admin/superadmin yang mengelola sistem + tabel sistem Laravel
- **Dependensi:** Tidak ada (tabel independen)
- **Catatan:** File migration default Laravel yang sudah diupdate dengan field tambahan

### 2. `2025_01_01_000002_create_employees_table.php`
- **Tabel:** `employees`
- **Fungsi:** Menyimpan data karyawan yang akan melakukan presensi
- **Dependensi:** Tidak ada (tabel independen)

### 3. `2025_01_01_000003_create_geo_locators_table.php`
- **Tabel:** `geo_locators`
- **Fungsi:** Menyimpan data lokasi geografis yang valid untuk presensi
- **Dependensi:** Tidak ada (tabel independen)

### 4. `2025_01_01_000004_create_presences_table.php`
- **Tabel:** `presences`
- **Fungsi:** Menyimpan data presensi karyawan (check-in/check-out)
- **Dependensi:** `employees` (melalui field `uid`)

### 5. `2025_01_01_000005_create_permits_table.php`
- **Tabel:** `permits`
- **Fungsi:** Menyimpan data pengajuan izin karyawan
- **Dependensi:** `employees` (melalui field `uid`)

## Fitur Utama

### Indexes untuk Performa
Setiap tabel memiliki indexes yang sesuai untuk:
- Query berdasarkan field yang sering digunakan
- Composite indexes untuk query kompleks
- Foreign key relationships

### Soft Delete
- Tabel `employees` menggunakan soft delete (`deleted_at`)
- Data tidak benar-benar dihapus, hanya ditandai sebagai deleted

### Firestore Integration
- Semua tabel memiliki field `firestore_id` untuk sinkronisasi dengan Firestore
- Field `uid` digunakan untuk referensi antar tabel

### Cloudinary Integration
- Field `public_id_*` untuk menyimpan Cloudinary public ID
- Memungkinkan manajemen file yang efisien

## Catatan Penting

⚠️ **File migration ini dibuat untuk dokumentasi dan referensi struktur database yang benar.**
⚠️ **Tidak perlu menjalankan migration ini karena database sudah ada dan berisi data penting.**
⚠️ **File migration lama telah di-backup di folder `migrations_backup/`**

## Backup Migration Lama

Migration lama telah di-backup di:
```
database/migrations_backup/
```

Migration lama memiliki struktur yang tidak terorganisir dengan banyak file `add_*_to_*_table.php` yang membuat struktur database sulit dipahami.
