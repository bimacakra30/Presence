# Tombol Sinkronisasi Data Presensi

## Deskripsi
Tombol sinkronisasi data presensi telah diimplementasikan di halaman PresenceResource di Filament Admin Panel. Tombol ini memungkinkan admin untuk melakukan sinkronisasi data presensi dari Firestore ke database lokal dengan mudah melalui interface web.

## Lokasi Tombol
- **Halaman**: PresenceResource (Presensi Karyawan)
- **Posisi**: Header Actions (di bagian atas tabel)
- **Label**: "Sinkronisasi Data Presensi"
- **Icon**: Arrow Path (🔄)

## Fitur Tombol

### 1. Konfirmasi Modal
- Tombol memerlukan konfirmasi sebelum menjalankan sinkronisasi
- Modal menampilkan:
  - **Heading**: "Sinkronisasi Data Presensi"
  - **Description**: Penjelasan tentang proses sinkronisasi
  - **Button**: "Ya, Sinkronisasi"

### 2. Proses Sinkronisasi
- Menggunakan `PresenceSyncService` untuk sinkronisasi
- Mengambil semua data presensi dari collection "presence" di Firestore
- Membandingkan dengan data lokal dan melakukan:
  - **Create**: Data baru yang belum ada di database lokal
  - **Update**: Data yang sudah ada tapi ada perubahan
  - **Skip**: Data yang tidak berubah

### 3. Notifikasi Hasil
- **Sukses**: Menampilkan jumlah data yang dibuat, diperbarui, dan tidak berubah
- **Error**: Menampilkan pesan error jika terjadi masalah

## Cara Penggunaan

### Melalui Filament Admin Panel
1. Buka halaman **Presensi Karyawan** di Filament Admin Panel
2. Klik tombol **"Sinkronisasi Data Presensi"** di bagian atas tabel
3. Konfirmasi dengan klik **"Ya, Sinkronisasi"**
4. Tunggu proses selesai dan lihat notifikasi hasil

### Melalui Command Line (Alternatif)
```bash
php artisan presence:sync
```

## Kode Implementasi

### PresenceResource.php
```php
->headerActions([
    Tables\Actions\Action::make('sync_presence')
        ->label('Sinkronisasi Data Presensi')
        ->icon('heroicon-o-arrow-path')
        ->color('success')
        ->requiresConfirmation()
        ->modalHeading('Sinkronisasi Data Presensi')
        ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data presensi dari Firestore? Proses ini akan mengambil semua data presensi terbaru dari Firestore dan menyinkronkannya dengan database lokal.')
        ->modalSubmitActionLabel('Ya, Sinkronisasi')
        ->action(function () {
            try {
                $firestoreService = new FirestoreService();
                $presenceSyncService = new PresenceSyncService($firestoreService);
                
                // Jalankan sinkronisasi
                $result = $presenceSyncService->syncAllPresenceData(false);
                
                // Tampilkan notifikasi sukses
                Notification::make()
                    ->title('Sinkronisasi Berhasil')
                    ->body("Data presensi berhasil disinkronisasi: {$result['created']} data baru, {$result['updated']} data diperbarui, {$result['no_change']} data tidak berubah.")
                    ->success()
                    ->send();
                    
            } catch (\Exception $e) {
                // Tampilkan notifikasi error
                Notification::make()
                    ->title('Sinkronisasi Gagal')
                    ->body('Terjadi kesalahan saat sinkronisasi data presensi: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        })
])
```

## Dependencies
- `App\Services\PresenceSyncService`
- `App\Services\FirestoreService`
- `Filament\Notifications\Notification`

## Logging
Semua aktivitas sinkronisasi dicatat di Laravel log dengan detail:
- Waktu mulai dan selesai sinkronisasi
- Jumlah data yang diproses
- Detail error jika terjadi masalah
- Statistik hasil sinkronisasi

## Error Handling
- **Connection Error**: Jika tidak bisa terhubung ke Firestore
- **Data Error**: Jika ada data yang tidak valid
- **Employee Not Found**: Jika UID employee tidak ditemukan di database lokal
- **Permission Error**: Jika tidak memiliki akses ke Firestore

## Keuntungan
1. **User Friendly**: Interface yang mudah digunakan
2. **Real-time Feedback**: Notifikasi langsung tentang hasil sinkronisasi
3. **Error Handling**: Penanganan error yang baik
4. **Logging**: Pencatatan aktivitas untuk debugging
5. **Consistent**: Menggunakan service yang sama dengan command line

## Catatan Penting
- Pastikan koneksi ke Firestore berfungsi dengan baik
- Pastikan semua employee memiliki UID yang sesuai antara Firestore dan database lokal
- Proses sinkronisasi mungkin memakan waktu beberapa detik tergantung jumlah data
- Data yang sudah ada akan diperbarui jika ada perubahan di Firestore
