<?php

namespace App\Http\Controllers;

use App\Models\Lahan;
use App\Models\Panen;
use App\Models\Cuaca;
use Illuminate\Http\Request;

class PetaniController extends Controller
{
    // DASHBOARD PETANI
    public function dashboard(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'message' => 'user_id wajib dikirim'
            ], 400);
        }

        $totalLahan = Lahan::where('user_id', $userId)->count();

        $totalPanen = Panen::whereHas('lahan', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->sum('hasil');

        $panenTerbaru = Panen::with('lahan')
            ->whereHas('lahan', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->latest('tanggal')
            ->first();

        $prediksiSementara = $panenTerbaru
            ? round($panenTerbaru->hasil * 1.05, 2)
            : 0;

        $lahan = $panenTerbaru ? $panenTerbaru->lahan : null;

        $cuacaTerbaru = null;

        if ($lahan) {
            $cuacaTerbaru = Cuaca::where('kecamatan', $lahan->kecamatan)
                ->when($lahan->desa, function ($query) use ($lahan) {
                    return $query->where('desa', $lahan->desa);
                })
                ->latest('tanggal')
                ->first();

            if (!$cuacaTerbaru) {
                $cuacaTerbaru = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->latest('tanggal')
                    ->first();
            }
        }

        $analisisSuhu = $this->analisisSuhu($cuacaTerbaru->suhu ?? null);

        $aktivitas = Panen::with('lahan')
            ->whereHas('lahan', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->latest('tanggal')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tanggal' => $item->tanggal,
                    'nama_lahan' => $item->lahan->nama ?? '-',
                    'kecamatan' => $item->lahan->kecamatan ?? '-',
                    'desa' => $item->lahan->desa ?? '-',
                    'hasil' => (float) $item->hasil,
                    'keterangan' => $item->keterangan,
                ];
            });

        return response()->json([
            'message' => 'Data dashboard petani berhasil diambil',
            'data' => [
                'total_lahan' => $totalLahan,
                'total_panen' => round($totalPanen, 2),
                'prediksi_panen' => $prediksiSementara,
                'status_prediksi' => 'Sementara',
                'rekomendasi_prediksi' => 'Prediksi masih menggunakan simulasi sementara. Nanti akan diganti dengan hasil metode TES.',

                'panen_terbaru' => [
                    'tanggal' => $panenTerbaru?->tanggal,
                    'nama_lahan' => $lahan->nama ?? '-',
                    'hasil' => $panenTerbaru ? (float) $panenTerbaru->hasil : 0,
                    'keterangan' => $panenTerbaru?->keterangan,
                ],

                'cuaca' => [
                    'tanggal' => $cuacaTerbaru?->tanggal,
                    'suhu' => $cuacaTerbaru ? (float) $cuacaTerbaru->suhu : null,
                    'kelembaban' => $cuacaTerbaru && $cuacaTerbaru->kelembaban !== null
                        ? (float) $cuacaTerbaru->kelembaban
                        : null,
                    'kondisi' => $cuacaTerbaru?->kondisi,
                    'kategori_suhu' => $analisisSuhu['kategori'],
                    'rekomendasi_suhu' => $analisisSuhu['rekomendasi'],
                ],

                'aktivitas' => $aktivitas
            ]
        ]);
    }

    // RIWAYAT PRODUKSI PETANI
    public function riwayatProduksi(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'message' => 'user_id wajib dikirim'
            ], 400);
        }

        $query = Panen::with('lahan')
            ->whereHas('lahan', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderBy('tanggal', 'desc');

        if ($request->has('lahan_id') && $request->lahan_id !== '') {
            $query->where('lahan_id', $request->lahan_id);
        }

        if ($request->has('kecamatan') && $request->kecamatan !== '') {
            $query->whereHas('lahan', function ($q) use ($request, $userId) {
                $q->where('user_id', $userId)
                    ->where('kecamatan', $request->kecamatan);
            });
        }

        $data = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'lahan_id' => $item->lahan_id,
                'nama_lahan' => $item->lahan->nama ?? '-',
                'luas' => $item->lahan ? (float) $item->lahan->luas : null,
                'kecamatan' => $item->lahan->kecamatan ?? '-',
                'desa' => $item->lahan->desa ?? '-',
                'tanggal' => $item->tanggal,
                'hasil' => (float) $item->hasil,
                'keterangan' => $item->keterangan,
            ];
        });

        $totalPanen = $data->sum('hasil');
        $jumlahData = $data->count();
        $rataRata = $jumlahData > 0 ? $totalPanen / $jumlahData : 0;

        return response()->json([
            'message' => 'Riwayat produksi berhasil diambil',
            'summary' => [
                'total_panen' => round($totalPanen, 2),
                'rata_rata' => round($rataRata, 2),
                'jumlah_data' => $jumlahData,
            ],
            'data' => $data
        ]);
    }

    // KONDISI LINGKUNGAN PETANI
    public function kondisiLingkungan(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'message' => 'user_id wajib dikirim'
            ], 400);
        }

        $dataLahan = Lahan::where('user_id', $userId)
            ->latest()
            ->get();

        $data = $dataLahan->map(function ($lahan) {
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

            $analisisSuhu = $this->analisisSuhu($cuaca->suhu ?? null);

            return [
                'id_lahan' => $lahan->id,
                'nama_lahan' => $lahan->nama,
                'luas' => (float) $lahan->luas,
                'kecamatan' => $lahan->kecamatan,
                'desa' => $lahan->desa,

                'tanggal_cuaca' => $cuaca?->tanggal,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca?->kondisi,
                'keterangan_cuaca' => $cuaca?->keterangan,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'rekomendasi_suhu' => $analisisSuhu['rekomendasi'],
            ];
        });

        $jumlahData = $data->count();
        $jumlahSesuai = $data->where('kategori_suhu', 'Sesuai')->count();

        $jumlahRisiko = $data->filter(function ($item) {
            return $item['kategori_suhu'] === 'Risiko Suhu Tinggi' ||
                $item['kategori_suhu'] === 'Risiko Suhu Rendah';
        })->count();

        return response()->json([
            'message' => 'Data kondisi lingkungan petani berhasil diambil',
            'summary' => [
                'jumlah_lahan' => $jumlahData,
                'jumlah_suhu_sesuai' => $jumlahSesuai,
                'jumlah_risiko_suhu' => $jumlahRisiko,
            ],
            'data' => $data
        ]);
    }

    // REKOMENDASI PETANI
    public function rekomendasi(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json([
                'message' => 'user_id wajib dikirim'
            ], 400);
        }

        $dataLahan = Lahan::where('user_id', $userId)
            ->latest()
            ->get();

        $data = $dataLahan->map(function ($lahan) {
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

            $analisisSuhu = $this->analisisSuhu($cuaca->suhu ?? null);
            $statusRisiko = $this->statusRisiko($analisisSuhu['kategori']);

            return [
                'id_lahan' => $lahan->id,
                'nama_lahan' => $lahan->nama,
                'kecamatan' => $lahan->kecamatan,
                'desa' => $lahan->desa,

                'tanggal_cuaca' => $cuaca?->tanggal,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca?->kondisi,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'status_risiko' => $statusRisiko,
                'rekomendasi_sistem' => $analisisSuhu['rekomendasi'],
                'saran_tindakan' => $this->saranTindakan($analisisSuhu['kategori']),
            ];
        });

        $totalRekomendasi = $data->count();
        $jumlahNormal = $data->where('status_risiko', 'Normal')->count();
        $jumlahPerluPerhatian = $data->where('status_risiko', 'Perlu Perhatian')->count();

        return response()->json([
            'message' => 'Data rekomendasi petani berhasil diambil',
            'summary' => [
                'total_rekomendasi' => $totalRekomendasi,
                'jumlah_normal' => $jumlahNormal,
                'jumlah_perlu_perhatian' => $jumlahPerluPerhatian,
            ],
            'data' => $data
        ]);
    }

    private function statusRisiko($kategoriSuhu)
    {
        if ($kategoriSuhu === 'Sesuai') {
            return 'Normal';
        }

        if ($kategoriSuhu === 'Risiko Suhu Tinggi' || $kategoriSuhu === 'Risiko Suhu Rendah') {
            return 'Perlu Perhatian';
        }

        return 'Data Belum Lengkap';
    }

    private function saranTindakan($kategoriSuhu)
    {
        if ($kategoriSuhu === 'Sesuai') {
            return 'Pertahankan pola pemeliharaan lahan, pemantauan rutin, dan pengairan sesuai kebutuhan tanaman.';
        }

        if ($kategoriSuhu === 'Risiko Suhu Tinggi') {
            return 'Periksa ketersediaan air, lakukan pengaturan irigasi, dan pantau kondisi tanaman untuk mencegah stres panas.';
        }

        if ($kategoriSuhu === 'Risiko Suhu Rendah') {
            return 'Pantau pertumbuhan tanaman secara berkala karena suhu rendah dapat memperlambat pertumbuhan padi.';
        }

        return 'Lengkapi data cuaca terlebih dahulu agar sistem dapat memberikan rekomendasi yang lebih akurat.';
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
}