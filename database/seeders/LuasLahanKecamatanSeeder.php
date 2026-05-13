<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LuasLahanKecamatan;

class LuasLahanKecamatanSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/imports/master_luas_lahan_kecamatan.csv');

        if (!file_exists($path)) {
            $this->command->error("File tidak ditemukan: " . $path);
            return;
        }

        $file = fopen($path, 'r');

        // CSV dari Excel Indonesia biasanya pakai titik koma
        $delimiter = ';';

        $header = fgetcsv($file, 0, $delimiter);

        if (!$header) {
            $this->command->error("Header CSV tidak terbaca.");
            return;
        }

        // Bersihkan header dari BOM dan spasi
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

            LuasLahanKecamatan::updateOrCreate(
                [
                    'nama_kecamatan' => trim($data['nama_kecamatan']),
                    'tahun' => (int) $data['tahun'],
                ],
                [
                    'irigasi_teknis_ha' => $this->toNumber($data['irigasi_teknis_ha'] ?? null),
                    'irigasi_setengah_teknis_ha' => $this->toNumber($data['irigasi_setengah_teknis_ha'] ?? null),
                    'irigasi_sederhana_ha' => $this->toNumber($data['irigasi_sederhana_ha'] ?? null),
                    'tadah_hujan_ha' => $this->toNumber($data['tadah_hujan_ha'] ?? null),
                    'total_luas_lahan_ha' => $this->toNumber($data['total_luas_lahan_ha'] ?? null),
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

        // Hapus titik ribuan
        $value = str_replace('.', '', $value);

        // Ubah koma desimal jadi titik
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}