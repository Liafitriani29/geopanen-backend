<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kecamatan;

class KecamatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = storage_path('app/imports/batas_kecamatan_sukoharjo.geojson');

        if (!file_exists($path)) {
            $this->command->error("File GeoJSON tidak ditemukan: " . $path);
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!$data || !isset($data['features'])) {
            $this->command->error("Format GeoJSON tidak valid atau features tidak ditemukan.");
            return;
        }

        $jumlah = 0;

        foreach ($data['features'] as $feature) {
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;

            // Nama kecamatan dari GeoJSON BIG biasanya ada di WADMKC
            $namaKecamatan = $properties['WADMKC']
                ?? $properties['NAMOBJ']
                ?? $properties['nama_kecamatan']
                ?? null;

            if (!$namaKecamatan || !$geometry) {
                continue;
            }

            $centroid = $this->hitungCentroid($geometry);

            Kecamatan::updateOrCreate(
                [
                    'nama_kecamatan' => trim($namaKecamatan),
                ],
                [
                    'geojson' => json_encode($geometry),
                    'latitude' => $centroid['latitude'],
                    'longitude' => $centroid['longitude'],
                ]
            );

            $jumlah++;
        }

        $this->command->info("Import GeoJSON kecamatan selesai. Total data: {$jumlah}");
    }

    private function hitungCentroid(array $geometry): array
    {
        $points = [];

        if (($geometry['type'] ?? null) === 'Polygon') {
            foreach ($geometry['coordinates'][0] as $coordinate) {
                $points[] = $coordinate;
            }
        }

        if (($geometry['type'] ?? null) === 'MultiPolygon') {
            foreach ($geometry['coordinates'] as $polygon) {
                foreach ($polygon[0] as $coordinate) {
                    $points[] = $coordinate;
                }
            }
        }

        if (count($points) === 0) {
            return [
                'latitude' => null,
                'longitude' => null,
            ];
        }

        $totalLongitude = 0;
        $totalLatitude = 0;

        foreach ($points as $point) {
            $totalLongitude += $point[0];
            $totalLatitude += $point[1];
        }

        return [
            'latitude' => $totalLatitude / count($points),
            'longitude' => $totalLongitude / count($points),
        ];
    }
}