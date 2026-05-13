<?php

namespace App\Http\Controllers;

use App\Models\Cuaca;
use App\Services\TesService;
use Illuminate\Support\Facades\DB;

class RekomendasiPrediksiController extends Controller
{
    private function getKategoriPrediksi(float $nilai, float $rataRata): string
    {
        if ($rataRata <= 0) {
            return 'Belum Dinilai';
        }

        if ($nilai >= $rataRata * 1.2) {
            return 'Tinggi';
        }

        if ($nilai <= $rataRata * 0.8) {
            return 'Rendah';
        }

        return 'Sedang';
    }

    private function getStatusCuaca($suhu, $kelembaban, $kondisi): array
    {
        if ($suhu === null && $kelembaban === null && $kondisi === null) {
            return [
                'kategori' => 'Data Cuaca Belum Ada',
                'catatan' => 'Data cuaca terbaru belum tersedia.',
            ];
        }

        if ($suhu !== null && $suhu > 35) {
            return [
                'kategori' => 'Risiko Suhu Tinggi',
                'catatan' => 'Suhu tinggi dapat meningkatkan risiko stres panas pada tanaman.',
            ];
        }

        if ($suhu !== null && $suhu < 20) {
            return [
                'kategori' => 'Risiko Suhu Rendah',
                'catatan' => 'Suhu rendah dapat menghambat pertumbuhan tanaman.',
            ];
        }

        if ($kelembaban !== null && $kelembaban >= 85) {
            return [
                'kategori' => 'Kelembaban Tinggi',
                'catatan' => 'Kelembaban tinggi dapat meningkatkan risiko hama dan penyakit.',
            ];
        }

        $kondisiLower = strtolower($kondisi ?? '');

        if (str_contains($kondisiLower, 'hujan')) {
            return [
                'kategori' => 'Potensi Hujan',
                'catatan' => 'Perlu memastikan drainase dan saluran air berjalan baik.',
            ];
        }

        return [
            'kategori' => 'Cuaca Mendukung',
            'catatan' => 'Kondisi cuaca masih mendukung aktivitas pertanian.',
        ];
    }

    private function buatRekomendasi(
        string $kategoriPrediksi,
        ?float $suhu,
        ?float $kelembaban,
        ?string $kondisiCuaca
    ): string {
        $kondisiLower = strtolower($kondisiCuaca ?? '');

        if ($kategoriPrediksi === 'Tinggi') {
            if (str_contains($kondisiLower, 'hujan')) {
                return 'Prediksi panen tinggi. Siapkan tenaga panen dan perhatikan kondisi cuaca hujan agar proses panen dan distribusi tidak terganggu.';
            }

            return 'Prediksi panen tinggi. Siapkan tenaga panen, alat panen, gudang penyimpanan, dan distribusi hasil panen.';
        }

        if ($kategoriPrediksi === 'Sedang') {
            if ($suhu !== null && $suhu > 35) {
                return 'Prediksi panen stabil, tetapi suhu cukup tinggi. Lakukan pemantauan irigasi dan kondisi tanaman secara berkala.';
            }

            if ($kelembaban !== null && $kelembaban >= 85) {
                return 'Prediksi panen stabil, tetapi kelembaban tinggi. Perlu pemantauan hama dan penyakit tanaman.';
            }

            return 'Prediksi panen berada pada kategori sedang. Lakukan pemantauan rutin terhadap lahan, tanaman, dan kondisi lingkungan.';
        }

        if ($kategoriPrediksi === 'Rendah') {
            if ($suhu !== null && $suhu > 35) {
                return 'Prediksi panen rendah dan suhu tinggi. Prioritaskan pemantauan irigasi, ketersediaan air, dan potensi stres panas pada tanaman.';
            }

            if ($kelembaban !== null && $kelembaban >= 85) {
                return 'Prediksi panen rendah dan kelembaban tinggi. Waspadai serangan hama, jamur, dan penyakit tanaman.';
            }

            if (str_contains($kondisiLower, 'hujan')) {
                return 'Prediksi panen rendah dan terdapat potensi hujan. Periksa saluran drainase dan kondisi lahan agar tidak terjadi genangan.';
            }

            return 'Prediksi panen rendah. Perlu evaluasi kondisi lahan, pola tanam, ketersediaan air, dan faktor lingkungan pendukung.';
        }

        return 'Data prediksi belum cukup untuk menghasilkan rekomendasi.';
    }

    public function index(TesService $tesService)
    {
        try {
            $dataHistoris = DB::table('produksi_bulanan')
                ->select('id', 'kabupaten', 'tahun', 'bulan', 'periode', 'produksi')
                ->where('kabupaten', 'Sukoharjo')
                ->orderBy('periode', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'kabupaten' => $item->kabupaten,
                        'tahun' => (int) $item->tahun,
                        'bulan' => (int) $item->bulan,
                        'periode' => $item->periode,
                        'produksi' => (float) $item->produksi,
                    ];
                })
                ->toArray();

            if (count($dataHistoris) < 24) {
                return response()->json([
                    'message' => 'Data historis belum cukup untuk membuat rekomendasi. Minimal diperlukan 24 data bulanan.',
                ], 400);
            }

            $hasilTes = $tesService->hitung($dataHistoris, [
                'alpha' => 0.3,
                'beta' => 0.2,
                'gamma' => 0.4,
                'seasonLength' => 12,
                'forecastPeriods' => 12,
            ]);

            $prediksiMendatang = $hasilTes['prediksiMendatang'];

            $rataRataPrediksi = count($prediksiMendatang) > 0
                ? array_sum(array_column($prediksiMendatang, 'prediksi')) / count($prediksiMendatang)
                : 0;

            $cuacaTerbaru = Cuaca::orderByDesc('updated_at')
    ->orderByDesc('tanggal')
    ->first();

            $suhu = $cuacaTerbaru ? (float) $cuacaTerbaru->suhu : null;
            $kelembaban = $cuacaTerbaru && $cuacaTerbaru->kelembaban !== null
                ? (float) $cuacaTerbaru->kelembaban
                : null;
            $kondisiCuaca = $cuacaTerbaru->kondisi ?? null;

            $statusCuaca = $this->getStatusCuaca($suhu, $kelembaban, $kondisiCuaca);

            $rekomendasi = array_map(function ($item) use (
                $rataRataPrediksi,
                $suhu,
                $kelembaban,
                $kondisiCuaca
            ) {
                $kategoriPrediksi = $this->getKategoriPrediksi(
                    (float) $item['prediksi'],
                    $rataRataPrediksi
                );

                return [
                    'periode' => $item['periode'],
                    'tahun' => $item['tahun'],
                    'bulan' => $item['bulan'],
                    'prediksi' => $item['prediksi'],
                    'kategori_prediksi' => $kategoriPrediksi,
                    'rekomendasi' => $this->buatRekomendasi(
                        $kategoriPrediksi,
                        $suhu,
                        $kelembaban,
                        $kondisiCuaca
                    ),
                ];
            }, $prediksiMendatang);

            return response()->json([
                'message' => 'Data rekomendasi prediksi berhasil diambil',
                'data' => [
                    'ringkasan' => $hasilTes['ringkasan'],
                    'cuaca_terbaru' => [
                        'tanggal' => $cuacaTerbaru->tanggal ?? null,
                        'kecamatan' => $cuacaTerbaru->kecamatan ?? null,
                        'desa' => $cuacaTerbaru->desa ?? null,
                        'suhu' => $suhu,
                        'kelembaban' => $kelembaban,
                        'kondisi' => $kondisiCuaca,
                        'kategori_cuaca' => $statusCuaca['kategori'],
                        'catatan_cuaca' => $statusCuaca['catatan'],
                    ],
                    'rekomendasi' => $rekomendasi,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil rekomendasi prediksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}