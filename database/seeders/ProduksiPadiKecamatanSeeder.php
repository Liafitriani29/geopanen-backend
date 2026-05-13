<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProduksiPadiKecamatan;

class ProduksiPadiKecamatanSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/imports/master_produksi_padi_kecamatan.csv');

        if (!file_exists($path)) {
            $this->command->error("File tidak ditemukan: " . $path);
            return;
        }

        $file = fopen($path, 'r');

        // CSV dari Excel Indonesia biasanya pakai delimiter titik koma (;)
        $delimiter = ';';

        // Ambil header
        $header = fgetcsv($file, 0, $delimiter);

        if (!$header) {
            $this->command->error("Header CSV tidak terbaca.");
            return;
        }

        // Bersihkan header dari BOM/spasi
        $header = array_map(function ($value) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
            return trim($value);
        }, $header);

        $jumlah = 0;

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {

            // Lewati baris kosong
            if (count(array_filter($row)) === 0) {
                continue;
            }

            // Samakan jumlah kolom row dengan header
            if (count($row) > count($header)) {
                $row = array_slice($row, 0, count($header));
            }

            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), null);
            }

            $data = array_combine($header, $row);

            ProduksiPadiKecamatan::updateOrCreate(
                [
                    'nama_kecamatan' => trim($data['nama_kecamatan']),
                    'tahun' => (int) $data['tahun'],
                ],
                [
                    'luas_panen_sawah_ha' => $this->toNumber($data['luas_panen_sawah_ha'] ?? null),
                    'produktivitas_sawah_kw_ha' => $this->toNumber($data['produktivitas_sawah_kw_ha'] ?? null),
                    'produksi_sawah_ton' => $this->toNumber($data['produksi_sawah_ton'] ?? null),

                    'luas_panen_gogo_ha' => $this->toNumber($data['luas_panen_gogo_ha'] ?? null),
                    'produktivitas_gogo_kw_ha' => $this->toNumber($data['produktivitas_gogo_kw_ha'] ?? null),
                    'produksi_gogo_ton' => $this->toNumber($data['produksi_gogo_ton'] ?? null),

                    'total_produksi_ton' => $this->toNumber($data['total_produksi_ton'] ?? null),
                    'sumber_file' => $data['sumber_file'] ?? null,
                ]
            );

            $jumlah++;
        }

        fclose($file);

        $this->command->info("Import selesai. Total data diproses: {$jumlah}");
    }

    private function toNumber($value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Hapus titik ribuan: 4.885,00 -> 4885,00
        $value = str_replace('.', '', $value);

        // Ubah koma desimal jadi titik: 4885,00 -> 4885.00
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}