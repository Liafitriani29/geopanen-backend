<?php

namespace App\Http\Controllers;

use App\Services\TesService;
use Illuminate\Support\Facades\DB;

class MonitoringPrediksiController extends Controller
{
    private function getMonitoringInfo(float $nilai, float $rataRata): array
    {
        if ($rataRata <= 0) {
            return [
                'kategori' => 'Belum Dinilai',
                'status_monitoring' => 'Belum tersedia',
                'rekomendasi' => 'Data prediksi belum tersedia.',
            ];
        }

        if ($nilai >= $rataRata * 1.2) {
            return [
                'kategori' => 'Tinggi',
                'status_monitoring' => 'Potensi Panen Tinggi',
                'rekomendasi' => 'Siapkan tenaga panen, alat panen, dan rencana distribusi hasil panen.',
            ];
        }

        if ($nilai <= $rataRata * 0.8) {
            return [
                'kategori' => 'Rendah',
                'status_monitoring' => 'Perlu Perhatian',
                'rekomendasi' => 'Perlu pemantauan kondisi lahan, irigasi, hama, dan faktor cuaca.',
            ];
        }

        return [
            'kategori' => 'Sedang',
            'status_monitoring' => 'Produksi Stabil',
            'rekomendasi' => 'Produksi diperkirakan stabil. Tetap lakukan pemantauan rutin.',
        ];
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
                    'message' => 'Data historis belum cukup untuk monitoring prediksi. Minimal diperlukan 24 data bulanan.',
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

            $monitoring = array_map(function ($item) use ($rataRataPrediksi) {
                $info = $this->getMonitoringInfo((float) $item['prediksi'], $rataRataPrediksi);

                return [
                    'periode' => $item['periode'],
                    'tahun' => $item['tahun'],
                    'bulan' => $item['bulan'],
                    'prediksi' => $item['prediksi'],
                    'kategori' => $info['kategori'],
                    'status_monitoring' => $info['status_monitoring'],
                    'rekomendasi' => $info['rekomendasi'],
                ];
            }, $prediksiMendatang);

            $jumlahTinggi = count(array_filter($monitoring, function ($item) {
                return $item['kategori'] === 'Tinggi';
            }));

            $jumlahSedang = count(array_filter($monitoring, function ($item) {
                return $item['kategori'] === 'Sedang';
            }));

            $jumlahRendah = count(array_filter($monitoring, function ($item) {
                return $item['kategori'] === 'Rendah';
            }));

            $prediksiTertinggi = null;
            $prediksiTerendah = null;

            foreach ($monitoring as $item) {
                if ($prediksiTertinggi === null || $item['prediksi'] > $prediksiTertinggi['prediksi']) {
                    $prediksiTertinggi = $item;
                }

                if ($prediksiTerendah === null || $item['prediksi'] < $prediksiTerendah['prediksi']) {
                    $prediksiTerendah = $item;
                }
            }

            return response()->json([
                'message' => 'Data monitoring prediksi berhasil diambil',
                'data' => [
                    'ringkasan' => $hasilTes['ringkasan'],
                    'statistik' => [
                        'jumlahPeriode' => count($monitoring),
                        'jumlahTinggi' => $jumlahTinggi,
                        'jumlahSedang' => $jumlahSedang,
                        'jumlahRendah' => $jumlahRendah,
                        'rataRataPrediksi' => round($rataRataPrediksi, 2),
                        'prediksiTertinggi' => $prediksiTertinggi,
                        'prediksiTerendah' => $prediksiTerendah,
                    ],
                    'monitoring' => $monitoring,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil monitoring prediksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}