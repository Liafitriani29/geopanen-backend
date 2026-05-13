<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Kecamatan;
use App\Models\ProduksiPadiKecamatan;
use App\Models\LuasLahanKecamatan;

class GisPrediksiController extends Controller
{
    public function prediksiKecamatan(Request $request)
    {
        $tahun = (int) $request->query('tahun', 2024);

        /*
         * Untuk sementara prediksi kabupaten bisa dikirim dari query.
         * Contoh:
         * /api/gis/prediksi-kecamatan?tahun=2024&prediksi_kabupaten=300000
         *
         * Kalau tidak dikirim, sistem memakai total produksi aktual tahun terpilih.
         * Nanti bagian ini bisa disambungkan ke hasil TES kabupaten.
         */
        $prediksiKabupaten = $request->query('prediksi_kabupaten');

        if (!$prediksiKabupaten) {
            $prediksiKabupaten = ProduksiPadiKecamatan::where('tahun', $tahun)
                ->sum('total_produksi_ton');
        }

        $prediksiKabupaten = (float) $prediksiKabupaten;

        // Hitung rata-rata produksi tiap kecamatan 2021-2024 untuk bobot
        $rataProduksi = ProduksiPadiKecamatan::select('nama_kecamatan')
            ->selectRaw('AVG(total_produksi_ton) as rata_produksi')
            ->whereBetween('tahun', [2021, 2024])
            ->groupBy('nama_kecamatan')
            ->get()
            ->keyBy('nama_kecamatan');

        $totalRataProduksi = $rataProduksi->sum('rata_produksi');

        // Data produksi tahun yang dipilih
        $produksiTahun = ProduksiPadiKecamatan::where('tahun', $tahun)
            ->get()
            ->keyBy('nama_kecamatan');

        // Data luas lahan tahun yang dipilih
        $luasLahanTahun = LuasLahanKecamatan::where('tahun', $tahun)
            ->get()
            ->keyBy('nama_kecamatan');

        $features = [];

        $kecamatans = Kecamatan::orderBy('nama_kecamatan')->get();

        foreach ($kecamatans as $kecamatan) {
            $nama = $kecamatan->nama_kecamatan;

            $produksi = $produksiTahun->get($nama);
            $luasLahan = $luasLahanTahun->get($nama);
            $rata = $rataProduksi->get($nama);

            $bobot = 0;

            if ($rata && $totalRataProduksi > 0) {
                $bobot = (float) $rata->rata_produksi / (float) $totalRataProduksi;
            }

            $prediksiKecamatan = $prediksiKabupaten * $bobot;

            $status = $this->statusPrediksi($prediksiKecamatan);

            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'nama_kecamatan' => $nama,
                    'tahun' => $tahun,

                    'luas_panen_sawah_ha' => $produksi ? (float) $produksi->luas_panen_sawah_ha : null,
                    'produktivitas_sawah_kw_ha' => $produksi ? (float) $produksi->produktivitas_sawah_kw_ha : null,
                    'produksi_sawah_ton' => $produksi ? (float) $produksi->produksi_sawah_ton : null,
                    'produksi_gogo_ton' => $produksi ? (float) $produksi->produksi_gogo_ton : null,
                    'total_produksi_ton' => $produksi ? (float) $produksi->total_produksi_ton : null,

                    'irigasi_teknis_ha' => $luasLahan ? (float) $luasLahan->irigasi_teknis_ha : null,
                    'irigasi_setengah_teknis_ha' => $luasLahan ? (float) $luasLahan->irigasi_setengah_teknis_ha : null,
                    'irigasi_sederhana_ha' => $luasLahan ? (float) $luasLahan->irigasi_sederhana_ha : null,
                    'tadah_hujan_ha' => $luasLahan ? (float) $luasLahan->tadah_hujan_ha : null,
                    'total_luas_lahan_ha' => $luasLahan ? (float) $luasLahan->total_luas_lahan_ha : null,

                    'bobot_kecamatan' => round($bobot, 6),
                    'prediksi_kabupaten_ton' => round($prediksiKabupaten, 2),
                    'prediksi_kecamatan_ton' => round($prediksiKecamatan, 2),
                    'status_prediksi' => $status,

                    'latitude' => $kecamatan->latitude ? (float) $kecamatan->latitude : null,
                    'longitude' => $kecamatan->longitude ? (float) $kecamatan->longitude : null,
                ],
                'geometry' => json_decode($kecamatan->geojson, true),
            ];
        }

        return response()->json([
            'message' => 'Data GIS prediksi kecamatan berhasil diambil',
            'tahun' => $tahun,
            'prediksi_kabupaten_ton' => round($prediksiKabupaten, 2),
            'total_kecamatan' => count($features),
            'data' => [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
        ]);
    }

    private function statusPrediksi(float $nilai): string
    {
        if ($nilai >= 40000) {
            return 'Tinggi';
        }

        if ($nilai >= 20000) {
            return 'Sedang';
        }

        return 'Rendah';
    }
}