<?php

namespace App\Exports;

use App\Models\Presence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PresenceExport implements FromCollection, WithHeadings, WithMapping, WithDrawings, WithStyles
{
    protected $tanggal_dari;
    protected $tanggal_sampai;
    protected $data;
    protected $tempFiles = [];

    public function __construct($tanggal_dari = null, $tanggal_sampai = null)
    {
        $this->tanggal_dari = $tanggal_dari;
        $this->tanggal_sampai = $tanggal_sampai;
    }

    public function __destruct()
    {
        // Hapus semua file temp
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function collection()
    {
        $query = Presence::select([
            'uid',
            'nama',
            'tanggal',
            'clock_in',
            'foto_clock_in',
            'clock_out',
            'foto_clock_out',
            'status',
        ]);

        if ($this->tanggal_dari) {
            $query->whereDate('tanggal', '>=', $this->tanggal_dari);
        }
        if ($this->tanggal_sampai) {
            $query->whereDate('tanggal', '<=', $this->tanggal_sampai);
        }

        $this->data = $query->get();
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'UID',
            'Nama',
            'Tanggal',
            'Jam Masuk',
            'Foto Clock In',
            'Jam Pulang',
            'Foto Clock Out',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row->uid,
            $row->nama,
            $row->tanggal,
            $row->clock_in,
            '', // Gambar
            $row->clock_out,
            '', // Gambar
            $row->status ? 'Terlambat' : 'Tidak',
        ];
    }

    public function drawings()
    {
        $drawings = [];

        foreach ($this->data as $index => $presence) {
            $rowNumber = $index + 2; // karena header ada di baris 1

            // === Clock In Photo ===
            if ($presence->foto_clock_in) {
                $localPath = $this->downloadImageFromUrl($presence->foto_clock_in);
                if ($localPath) {
                    $drawingIn = new Drawing();
                    $drawingIn->setName('Foto Clock In');
                    $drawingIn->setPath($localPath);
                    $drawingIn->setHeight(100);
                    $drawingIn->setWidth(100);
                    $drawingIn->setCoordinates('E' . $rowNumber);
                    $drawingIn->setOffsetX(5);
                    $drawingIn->setOffsetY(5);
                    $drawings[] = $drawingIn;
                }
            }

            // === Clock Out Photo ===
            if ($presence->foto_clock_out) {
                $localPath = $this->downloadImageFromUrl($presence->foto_clock_out);
                if ($localPath) {
                    $drawingOut = new Drawing();
                    $drawingOut->setName('Foto Clock Out');
                    $drawingOut->setPath($localPath);
                    $drawingOut->setHeight(100);
                    $drawingOut->setWidth(100);
                    $drawingOut->setCoordinates('G' . $rowNumber);
                    $drawingOut->setOffsetX(5);
                    $drawingOut->setOffsetY(5);
                    $drawings[] = $drawingOut;
                }
            }
        }

        return $drawings;
    }

    protected function downloadImageFromUrl($url)
    {
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = storage_path('app/temp_' . Str::uuid() . '.' . $extension);

                file_put_contents($filename, $response->body());

                $this->tempFiles[] = $filename;

                return $filename;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Gagal mengunduh gambar dari URL: {$url}, Error: {$e->getMessage()}");
        }

        return null;
    }

    public function styles(Worksheet $sheet)
    {
        // Styling header (baris 1)
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4CAF50'], // Hijau
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => Color::COLOR_BLACK],
                ],
            ],
        ]);

        // Atur tinggi baris header
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Atur lebar kolom
        $sheet->getColumnDimension('A')->setWidth(15); // UID
        $sheet->getColumnDimension('B')->setWidth(25); // Nama
        $sheet->getColumnDimension('C')->setWidth(15); // Tanggal
        $sheet->getColumnDimension('D')->setWidth(15); // Jam Masuk
        $sheet->getColumnDimension('E')->setWidth(40); // Foto Clock In
        $sheet->getColumnDimension('F')->setWidth(15); // Jam Pulang
        $sheet->getColumnDimension('G')->setWidth(40); // Foto Clock Out
        $sheet->getColumnDimension('H')->setWidth(15); // Status

        // Styling untuk semua sel data (rata tengah)
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A2:H' . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => Color::COLOR_BLACK],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Format kolom tanggal
        $sheet->getStyle('C2:C' . $highestRow)->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        // Format kolom jam
        $sheet->getStyle('D2:D' . $highestRow)->getNumberFormat()->setFormatCode('hh:mm:ss');
        $sheet->getStyle('F2:F' . $highestRow)->getNumberFormat()->setFormatCode('hh:mm:ss');

        // Conditional formatting untuk kolom Status (H)
        foreach (range(2, $highestRow) as $row) {
            $status = $sheet->getCell('H' . $row)->getValue();
            if ($status === 'Terlambat') {
                $sheet->getStyle('H' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFFFCDD2'], // Merah muda
                    ],
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            } else {
                $sheet->getStyle('H' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFC8E6C9'], // Hijau muda
                    ],
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            }
        }

        // Atur tinggi baris untuk data agar gambar besar terlihat rapi
        for ($row = 2; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(110);
        }
    }
}