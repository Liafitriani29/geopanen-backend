<?php

namespace App\Http\Controllers;

use App\Services\TesService;
use Illuminate\Support\Facades\DB;

class TesController extends Controller
{
    private function getDataHistoris(): array
    {
        return DB::table('produksi_bulanan')
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
    }

    public function prediksi(TesService $tesService)
    {
        try {
            $data = $this->getDataHistoris();

            if (count($data) === 0) {
                return response()->json([
                    'message' => 'Data produksi bulanan belum tersedia.',
                ], 404);
            }

            if (count($data) < 24) {
                return response()->json([
                    'message' => 'Data historis belum cukup untuk TES. Minimal diperlukan 24 data bulanan.',
                ], 400);
            }

            // Tidak kirim parameter lama.
            // Parameter akan memakai default dari TesService:
            // alpha 0.999, beta 0.60432745, gamma 0.00100399
            $hasil = $tesService->hitung($data);

            return response()->json([
                'message' => 'Perhitungan TES berhasil',
                'data' => $hasil,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghitung prediksi TES',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function evaluasiAktual(TesService $tesService)
    {
        try {
            $data = $this->getDataHistoris();

            if (count($data) < 24) {
                return response()->json([
                    'message' => 'Data historis belum cukup untuk evaluasi TES. Minimal diperlukan 24 data bulanan.',
                ], 400);
            }

            // Tidak kirim parameter lama.
            $hasilTes = $tesService->hitung($data);

            $prediksiMendatang = $hasilTes['prediksiMendatang'];

            $evaluasi = [];
            $totalApe = 0;
            $jumlahEvaluasi = 0;

            foreach ($prediksiMendatang as $prediksi) {
                $aktual = DB::table('aktual_produksi_bulanan')
                    ->where('kabupaten', 'Sukoharjo')
                    ->where('periode', $prediksi['periode'])
                    ->first();

                if (!$aktual) {
                    continue;
                }

                $nilaiPrediksi = (float) $prediksi['prediksi'];
                $nilaiAktual = (float) $aktual->produksi_aktual;

                $selisih = $nilaiAktual - $nilaiPrediksi;
                $deviasi = $nilaiAktual != 0 ? ($selisih / $nilaiAktual) * 100 : 0;
                $ape = $nilaiAktual != 0 ? abs($selisih / $nilaiAktual) * 100 : 0;

                if ($ape <= 10) {
                    $status = 'Akurat';
                } elseif ($ape <= 20) {
                    $status = 'Cukup';
                } else {
                    $status = 'Perlu Evaluasi';
                }

                $evaluasi[] = [
                    'periode' => $prediksi['periode'],
                    'tahun' => $prediksi['tahun'],
                    'bulan' => $prediksi['bulan'],
                    'prediksi' => round($nilaiPrediksi, 2),
                    'aktual' => round($nilaiAktual, 2),
                    'selisih' => round($selisih, 2),
                    'deviasi' => round($deviasi, 2),
                    'ape' => round($ape, 2),
                    'status' => $status,
                ];

                $totalApe += $ape;
                $jumlahEvaluasi++;
            }

            if ($jumlahEvaluasi === 0) {
                return response()->json([
                    'message' => 'Belum ada data aktual produksi bulanan yang cocok dengan periode prediksi TES.',
                    'data' => [
                        'ringkasan' => [
                            'jumlahDataEvaluasi' => 0,
                            'mape' => 0,
                            'estimasiAkurasi' => 0,
                            'statusModel' => 'Belum Dievaluasi',
                            'jumlahAkurat' => 0,
                            'jumlahCukup' => 0,
                            'jumlahPerluEvaluasi' => 0,
                        ],
                        'evaluasi' => [],
                    ],
                ], 200);
            }

            $mape = $totalApe / $jumlahEvaluasi;
            $estimasiAkurasi = max(0, 100 - $mape);

            $jumlahAkurat = count(array_filter($evaluasi, fn ($item) => $item['status'] === 'Akurat'));
            $jumlahCukup = count(array_filter($evaluasi, fn ($item) => $item['status'] === 'Cukup'));
            $jumlahPerluEvaluasi = count(array_filter($evaluasi, fn ($item) => $item['status'] === 'Perlu Evaluasi'));

            if ($mape <= 10) {
                $statusModel = 'Akurat';
            } elseif ($mape <= 20) {
                $statusModel = 'Cukup';
            } else {
                $statusModel = 'Perlu Perbaikan';
            }

            return response()->json([
                'message' => 'Evaluasi aktual prediksi TES berhasil dimuat',
                'data' => [
                    'ringkasan' => [
                        'jumlahDataEvaluasi' => $jumlahEvaluasi,
                        'mape' => round($mape, 2),
                        'estimasiAkurasi' => round($estimasiAkurasi, 2),
                        'statusModel' => $statusModel,
                        'jumlahAkurat' => $jumlahAkurat,
                        'jumlahCukup' => $jumlahCukup,
                        'jumlahPerluEvaluasi' => $jumlahPerluEvaluasi,
                    ],
                    'evaluasi' => $evaluasi,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memuat evaluasi aktual TES',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}