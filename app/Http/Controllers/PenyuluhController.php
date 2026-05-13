<?php

namespace App\Http\Controllers;

use App\Models\Lahan;
use App\Models\Panen;
use App\Models\Cuaca;
use Illuminate\Http\Request;

class PenyuluhController extends Controller
{
    // DASHBOARD PENYULUH PERTANIAN
    public function dashboard()
    {
        $totalLahan = Lahan::count();
        $totalPanen = Panen::sum('hasil');

        $dataPanen = Panen::with('lahan')
            ->orderBy('tanggal', 'desc')
            ->get();

        $dataMonitoring = $dataPanen->map(function ($panen) {
            $lahan = $panen->lahan;

            $cuaca = null;

            if ($lahan) {
                $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->when($lahan->desa, function ($query) use ($lahan) {
                        return $query->where('desa', $lahan->desa);
                    })
                    ->latest('tanggal')
                    ->first();

                if (!$cuaca) {
                    $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                        ->latest('tanggal')
                        ->first();
                }
            }

            $hasilAktual = (float) $panen->hasil;

            // Prediksi sementara sebelum TES
            // Nanti bagian ini diganti dengan hasil metode Triple Exponential Smoothing
            $prediksiSementara = round($hasilAktual * 1.05, 2);

            // Deviasi sementara sebelum TES final
            $deviasi = $prediksiSementara > 0
                ? round(abs($hasilAktual - $prediksiSementara) / $prediksiSementara * 100, 2)
                : 0;

            $kategoriDeviasi = $this->kategoriDeviasi($deviasi);
            $analisisSuhu = $this->analisisSuhu($cuaca?->suhu);

            return [
                'id_panen' => $panen->id,
                'id_lahan' => $lahan?->id,
                'nama_lahan' => $lahan->nama ?? '-',
                'luas' => $lahan ? (float) $lahan->luas : null,
                'kecamatan' => $lahan->kecamatan ?? '-',
                'desa' => $lahan->desa ?? '-',

                'tanggal_panen' => $panen->tanggal,
                'hasil_aktual' => $hasilAktual,
                'prediksi' => $prediksiSementara,
                'deviasi_persen' => $deviasi,
                'kategori_deviasi' => $kategoriDeviasi,

                'tanggal_cuaca' => $cuaca?->tanggal,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca?->kondisi,
                'kategori_suhu' => $analisisSuhu['kategori'],

                'status' => $this->statusMonitoring(
                    $kategoriDeviasi,
                    $analisisSuhu['kategori']
                ),
                'rekomendasi_sistem' => $this->rekomendasiPenyuluh(
                    $kategoriDeviasi,
                    $analisisSuhu['kategori']
                ),
            ];
        });

        $jumlahData = $dataMonitoring->count();

        $rataPrediksi = $jumlahData > 0
            ? round($dataMonitoring->avg('prediksi'), 2)
            : 0;

        $rataDeviasi = $jumlahData > 0
            ? round($dataMonitoring->avg('deviasi_persen'), 2)
            : 0;

        $jumlahNormal = $dataMonitoring->where('status', 'Normal')->count();

        $jumlahPerluEvaluasi = $dataMonitoring->filter(function ($item) {
            return $item['status'] === 'Perlu Evaluasi' ||
                $item['status'] === 'Bahaya';
        })->count();

        return response()->json([
            'message' => 'Data dashboard penyuluh berhasil diambil',
            'summary' => [
                'total_lahan' => $totalLahan,
                'total_panen_aktual' => round($totalPanen, 2),
                'rata_rata_prediksi' => $rataPrediksi,
                'rata_rata_deviasi' => $rataDeviasi,
                'jumlah_data_monitoring' => $jumlahData,
                'jumlah_normal' => $jumlahNormal,
                'jumlah_perlu_evaluasi' => $jumlahPerluEvaluasi,
                'status_tes' => 'Simulasi sementara, belum menggunakan TES final',
            ],
            'data' => $dataMonitoring
        ]);
    }

    // MONITORING WILAYAH PENYULUH
    public function monitoringWilayah(Request $request)
    {
        $query = Lahan::with(['panen' => function ($q) {
            $q->orderBy('tanggal', 'desc');
        }])->latest();

        if ($request->has('kecamatan') && $request->kecamatan !== '') {
            $query->where('kecamatan', $request->kecamatan);
        }

        $dataLahan = $query->get();

        $data = $dataLahan->map(function ($lahan) {
            $panenTerbaru = $lahan->panen->first();

            $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                ->when($lahan->desa, function ($query) use ($lahan) {
                    return $query->where('desa', $lahan->desa);
                })
                ->latest('tanggal')
                ->first();

            if (!$cuaca) {
                $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->latest('tanggal')
                    ->first();
            }

            $analisisSuhu = $this->analisisSuhu($cuaca?->suhu);

            $status = $this->statusMonitoringWilayah(
                $panenTerbaru,
                $analisisSuhu['kategori']
            );

            return [
                'id_lahan' => $lahan->id,
                'nama_lahan' => $lahan->nama,
                'luas' => (float) $lahan->luas,
                'kecamatan' => $lahan->kecamatan,
                'desa' => $lahan->desa,

                'tanggal_panen' => $panenTerbaru?->tanggal,
                'hasil_panen' => $panenTerbaru ? (float) $panenTerbaru->hasil : null,
                'keterangan_panen' => $panenTerbaru?->keterangan,

                'tanggal_cuaca' => $cuaca?->tanggal,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca?->kondisi,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'rekomendasi_suhu' => $analisisSuhu['rekomendasi'],
                'status_monitoring' => $status,
            ];
        });

        $totalLahan = $data->count();

        $totalPanen = $data->sum(function ($item) {
            return $item['hasil_panen'] ?? 0;
        });

        $jumlahNormal = $data->where('status_monitoring', 'Normal')->count();

        $jumlahPerluPerhatian = $data->filter(function ($item) {
            return $item['status_monitoring'] === 'Perlu Perhatian' ||
                $item['status_monitoring'] === 'Data Belum Lengkap' ||
                $item['status_monitoring'] === 'Data Panen Belum Ada';
        })->count();

        $daftarKecamatan = Lahan::select('kecamatan')
            ->whereNotNull('kecamatan')
            ->distinct()
            ->pluck('kecamatan')
            ->values();

        return response()->json([
            'message' => 'Data monitoring wilayah berhasil diambil',
            'summary' => [
                'total_lahan' => $totalLahan,
                'total_panen_terbaru' => round($totalPanen, 2),
                'jumlah_normal' => $jumlahNormal,
                'jumlah_perlu_perhatian' => $jumlahPerluPerhatian,
            ],
            'filter' => [
                'kecamatan_aktif' => $request->kecamatan ?? '',
                'daftar_kecamatan' => $daftarKecamatan,
            ],
            'data' => $data
        ]);
    }

    // REKOMENDASI PENYULUH BERDASARKAN SUHU
    public function rekomendasi(Request $request)
    {
        $query = Lahan::with(['panen' => function ($q) {
            $q->orderBy('tanggal', 'desc');
        }])->latest();

        if ($request->has('kecamatan') && $request->kecamatan !== '') {
            $query->where('kecamatan', $request->kecamatan);
        }

        $dataLahan = $query->get();

        $data = $dataLahan->map(function ($lahan) {
            $panenTerbaru = $lahan->panen->first();

            $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                ->when($lahan->desa, function ($query) use ($lahan) {
                    return $query->where('desa', $lahan->desa);
                })
                ->latest('tanggal')
                ->first();

            if (!$cuaca) {
                $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->latest('tanggal')
                    ->first();
            }

            $analisisSuhu = $this->analisisSuhu($cuaca?->suhu);

            $statusMonitoring = $this->statusMonitoringWilayah(
                $panenTerbaru,
                $analisisSuhu['kategori']
            );

            $prioritas = $this->prioritasRekomendasi(
                $statusMonitoring,
                $analisisSuhu['kategori']
            );

            return [
                'id_lahan' => $lahan->id,
                'nama_lahan' => $lahan->nama,
                'luas' => (float) $lahan->luas,
                'kecamatan' => $lahan->kecamatan,
                'desa' => $lahan->desa,

                'tanggal_panen' => $panenTerbaru?->tanggal,
                'hasil_panen' => $panenTerbaru ? (float) $panenTerbaru->hasil : null,
                'keterangan_panen' => $panenTerbaru?->keterangan,

                'tanggal_cuaca' => $cuaca?->tanggal,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca?->kondisi,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'status_monitoring' => $statusMonitoring,
                'prioritas' => $prioritas,

                'rekomendasi_sistem' => $analisisSuhu['rekomendasi'],
                'rekomendasi_penyuluh' => $this->saranTindakanPenyuluh(
                    $statusMonitoring,
                    $analisisSuhu['kategori']
                ),
            ];
        });

        $totalRekomendasi = $data->count();

        $jumlahPrioritasTinggi = $data->where('prioritas', 'Tinggi')->count();
        $jumlahPrioritasSedang = $data->where('prioritas', 'Sedang')->count();
        $jumlahPrioritasRendah = $data->where('prioritas', 'Rendah')->count();

        $daftarKecamatan = Lahan::select('kecamatan')
            ->whereNotNull('kecamatan')
            ->distinct()
            ->pluck('kecamatan')
            ->values();

        return response()->json([
            'message' => 'Data rekomendasi penyuluh berhasil diambil',
            'summary' => [
                'total_rekomendasi' => $totalRekomendasi,
                'prioritas_tinggi' => $jumlahPrioritasTinggi,
                'prioritas_sedang' => $jumlahPrioritasSedang,
                'prioritas_rendah' => $jumlahPrioritasRendah,
            ],
            'filter' => [
                'kecamatan_aktif' => $request->kecamatan ?? '',
                'daftar_kecamatan' => $daftarKecamatan,
            ],
            'data' => $data
        ]);
    }

    // LAPORAN PENYULUH
    public function laporan(Request $request)
    {
        $query = Lahan::with(['panen' => function ($q) {
            $q->orderBy('tanggal', 'desc');
        }])->latest();

        if ($request->has('kecamatan') && $request->kecamatan !== '') {
            $query->where('kecamatan', $request->kecamatan);
        }

        $dataLahan = $query->get();

        $data = $dataLahan->map(function ($lahan) {
            $panenTerbaru = $lahan->panen->first();

            $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                ->when($lahan->desa, function ($query) use ($lahan) {
                    return $query->where('desa', $lahan->desa);
                })
                ->latest('tanggal')
                ->first();

            if (!$cuaca) {
                $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->latest('tanggal')
                    ->first();
            }

            $analisisSuhu = $this->analisisSuhu($cuaca?->suhu);

            $statusMonitoring = $this->statusMonitoringWilayah(
                $panenTerbaru,
                $analisisSuhu['kategori']
            );

            $prioritas = $this->prioritasRekomendasi(
                $statusMonitoring,
                $analisisSuhu['kategori']
            );

            return [
                'id_lahan' => $lahan->id,
                'nama_lahan' => $lahan->nama,
                'luas' => (float) $lahan->luas,
                'kecamatan' => $lahan->kecamatan,
                'desa' => $lahan->desa,

                'tanggal_panen' => $panenTerbaru?->tanggal,
                'hasil_panen' => $panenTerbaru ? (float) $panenTerbaru->hasil : null,
                'keterangan_panen' => $panenTerbaru?->keterangan,

                'tanggal_cuaca' => $cuaca?->tanggal,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca?->kondisi,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'status_monitoring' => $statusMonitoring,
                'prioritas' => $prioritas,
                'rekomendasi_penyuluh' => $this->saranTindakanPenyuluh(
                    $statusMonitoring,
                    $analisisSuhu['kategori']
                ),
            ];
        });

        $totalLahan = $data->count();

        $totalPanen = $data->sum(function ($item) {
            return $item['hasil_panen'] ?? 0;
        });

        $jumlahNormal = $data->where('status_monitoring', 'Normal')->count();
        $jumlahPerluPerhatian = $data->where('status_monitoring', 'Perlu Perhatian')->count();
        $jumlahDataBelumLengkap = $data->where('status_monitoring', 'Data Belum Lengkap')->count();
        $jumlahDataPanenBelumAda = $data->where('status_monitoring', 'Data Panen Belum Ada')->count();

        $prioritasTinggi = $data->where('prioritas', 'Tinggi')->count();
        $prioritasSedang = $data->where('prioritas', 'Sedang')->count();
        $prioritasRendah = $data->where('prioritas', 'Rendah')->count();

        $daftarKecamatan = Lahan::select('kecamatan')
            ->whereNotNull('kecamatan')
            ->distinct()
            ->pluck('kecamatan')
            ->values();

        return response()->json([
            'message' => 'Data laporan penyuluh berhasil diambil',
            'summary' => [
                'total_lahan' => $totalLahan,
                'total_panen_terbaru' => round($totalPanen, 2),
                'jumlah_normal' => $jumlahNormal,
                'jumlah_perlu_perhatian' => $jumlahPerluPerhatian,
                'jumlah_data_belum_lengkap' => $jumlahDataBelumLengkap,
                'jumlah_data_panen_belum_ada' => $jumlahDataPanenBelumAda,
                'prioritas_tinggi' => $prioritasTinggi,
                'prioritas_sedang' => $prioritasSedang,
                'prioritas_rendah' => $prioritasRendah,
            ],
            'filter' => [
                'kecamatan_aktif' => $request->kecamatan ?? '',
                'daftar_kecamatan' => $daftarKecamatan,
            ],
            'data' => $data
        ]);
    }

    // ANALISIS SUHU UNTUK RULE-BASED SYSTEM
    private function analisisSuhu($suhu)
    {
        if ($suhu === null) {
            return [
                'kategori' => 'Data Suhu Belum Ada',
                'rekomendasi' => 'Data suhu belum tersedia untuk wilayah ini.'
            ];
        }

        if ($suhu < 20) {
            return [
                'kategori' => 'Risiko Suhu Rendah',
                'rekomendasi' => 'Pantau pertumbuhan padi karena suhu rendah dapat menghambat pertumbuhan tanaman.'
            ];
        }

        if ($suhu >= 20 && $suhu <= 35) {
            return [
                'kategori' => 'Sesuai',
                'rekomendasi' => 'Suhu masih mendukung pertumbuhan padi.'
            ];
        }

        return [
            'kategori' => 'Risiko Suhu Tinggi',
            'rekomendasi' => 'Pantau irigasi dan kondisi tanaman karena suhu tinggi dapat menyebabkan stres panas.'
        ];
    }

    private function kategoriDeviasi($deviasi)
    {
        if ($deviasi < 10) {
            return 'Normal';
        }

        if ($deviasi >= 10 && $deviasi <= 20) {
            return 'Sedang';
        }

        return 'Tinggi';
    }

    private function statusMonitoring($kategoriDeviasi, $kategoriSuhu)
    {
        if ($kategoriDeviasi === 'Normal' && $kategoriSuhu === 'Sesuai') {
            return 'Normal';
        }

        if (
            $kategoriDeviasi === 'Tinggi' ||
            $kategoriSuhu === 'Risiko Suhu Tinggi' ||
            $kategoriSuhu === 'Risiko Suhu Rendah'
        ) {
            return 'Bahaya';
        }

        return 'Perlu Evaluasi';
    }

    private function statusMonitoringWilayah($panen, $kategoriSuhu)
    {
        if (!$panen && $kategoriSuhu === 'Data Suhu Belum Ada') {
            return 'Data Belum Lengkap';
        }

        if (!$panen) {
            return 'Data Panen Belum Ada';
        }

        if (
            $kategoriSuhu === 'Risiko Suhu Tinggi' ||
            $kategoriSuhu === 'Risiko Suhu Rendah'
        ) {
            return 'Perlu Perhatian';
        }

        return 'Normal';
    }

    private function prioritasRekomendasi($statusMonitoring, $kategoriSuhu)
    {
        if (
            $statusMonitoring === 'Data Belum Lengkap' ||
            $statusMonitoring === 'Perlu Perhatian' ||
            $kategoriSuhu === 'Risiko Suhu Tinggi' ||
            $kategoriSuhu === 'Risiko Suhu Rendah'
        ) {
            return 'Tinggi';
        }

        if ($statusMonitoring === 'Data Panen Belum Ada') {
            return 'Sedang';
        }

        return 'Rendah';
    }

    private function saranTindakanPenyuluh($statusMonitoring, $kategoriSuhu)
    {
        if ($statusMonitoring === 'Data Belum Lengkap') {
            return 'Penyuluh perlu memastikan kelengkapan data panen dan data cuaca agar analisis kondisi lahan dapat dilakukan dengan lebih akurat.';
        }

        if ($statusMonitoring === 'Data Panen Belum Ada') {
            return 'Penyuluh perlu meminta atau memverifikasi data panen terbaru dari petani agar perkembangan produksi dapat dipantau.';
        }

        if ($kategoriSuhu === 'Risiko Suhu Tinggi') {
            return 'Penyuluh perlu menyarankan pengecekan irigasi, ketersediaan air, dan kondisi tanaman karena suhu tinggi dapat menyebabkan stres panas.';
        }

        if ($kategoriSuhu === 'Risiko Suhu Rendah') {
            return 'Penyuluh perlu memantau pertumbuhan tanaman karena suhu rendah dapat memperlambat perkembangan padi.';
        }

        return 'Kondisi lahan relatif normal. Penyuluh dapat menyarankan petani untuk mempertahankan pola pemeliharaan, pemantauan rutin, dan pengairan sesuai kebutuhan.';
    }

    private function rekomendasiPenyuluh($kategoriDeviasi, $kategoriSuhu)
    {
        if ($kategoriDeviasi === 'Normal' && $kategoriSuhu === 'Sesuai') {
            return 'Pertahankan pola tanam dan pemeliharaan lahan.';
        }

        if ($kategoriDeviasi === 'Tinggi') {
            return 'Lakukan evaluasi terhadap data produksi, kondisi lahan, irigasi, serta kemungkinan gangguan hama atau cuaca.';
        }

        if ($kategoriSuhu === 'Risiko Suhu Tinggi') {
            return 'Periksa ketersediaan air dan lakukan pemantauan irigasi karena suhu tinggi dapat memengaruhi tanaman.';
        }

        if ($kategoriSuhu === 'Risiko Suhu Rendah') {
            return 'Pantau pertumbuhan tanaman karena suhu rendah dapat memperlambat perkembangan padi.';
        }

        return 'Lakukan pemantauan lanjutan terhadap kondisi lahan dan hasil produksi.';
    }
}