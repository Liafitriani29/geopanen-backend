<?php

namespace App\Http\Controllers;

use App\Models\Panen;
use App\Models\Cuaca;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $query = Panen::with('lahan')->orderBy('tanggal', 'desc');

        // Filter kecamatan jika dikirim dari frontend
        if ($request->has('kecamatan') && $request->kecamatan !== '') {
            $query->whereHas('lahan', function ($q) use ($request) {
                $q->where('kecamatan', $request->kecamatan);
            });
        }

        $dataPanen = $query->get();

        $data = $dataPanen->map(function ($panen) {
            $lahan = $panen->lahan;

            $cuaca = null;

            if ($lahan) {
                // Cari cuaca berdasarkan kecamatan dan desa
                $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->when($lahan->desa, function ($query) use ($lahan) {
                        return $query->where('desa', $lahan->desa);
                    })
                    ->latest('tanggal')
                    ->first();

                // Jika tidak ditemukan berdasarkan desa, cari berdasarkan kecamatan saja
                if (!$cuaca) {
                    $cuaca = Cuaca::where('kecamatan', $lahan->kecamatan)
                        ->latest('tanggal')
                        ->first();
                }
            }

            $analisisSuhu = $this->analisisSuhu($cuaca->suhu ?? null);

            return [
                'id_panen' => $panen->id,
                'id_lahan' => $lahan->id ?? null,
                'nama_lahan' => $lahan->nama ?? '-',
                'luas' => $lahan ? (float) $lahan->luas : null,
                'kecamatan' => $lahan->kecamatan ?? '-',
                'desa' => $lahan->desa ?? '-',

                'tanggal_panen' => $panen->tanggal,
                'hasil_panen' => (float) $panen->hasil,
                'keterangan_panen' => $panen->keterangan,

                'tanggal_cuaca' => $cuaca->tanggal ?? null,
                'suhu' => $cuaca ? (float) $cuaca->suhu : null,
                'kelembaban' => $cuaca && $cuaca->kelembaban !== null
                    ? (float) $cuaca->kelembaban
                    : null,
                'kondisi_cuaca' => $cuaca->kondisi ?? null,
                'keterangan_cuaca' => $cuaca->keterangan ?? null,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'rekomendasi_suhu' => $analisisSuhu['rekomendasi'],
                'status_monitoring' => $this->statusMonitoring($cuaca, $analisisSuhu['kategori']),
            ];
        });

        $totalPanen = $data->sum('hasil_panen');
        $jumlahData = $data->count();
        $rataRata = $jumlahData > 0 ? $totalPanen / $jumlahData : 0;

        $jumlahNormal = $data->where('status_monitoring', 'Normal')->count();
        $jumlahPerluPerhatian = $data->where('status_monitoring', 'Perlu perhatian')->count();

        $daftarKecamatan = Panen::with('lahan')
            ->get()
            ->pluck('lahan.kecamatan')
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'message' => 'Data laporan berhasil diambil',
            'summary' => [
                'total_panen' => round($totalPanen, 2),
                'rata_rata' => round($rataRata, 2),
                'jumlah_data' => $jumlahData,
                'jumlah_normal' => $jumlahNormal,
                'jumlah_perlu_perhatian' => $jumlahPerluPerhatian,
            ],
            'filter' => [
                'kecamatan_aktif' => $request->kecamatan ?? '',
                'daftar_kecamatan' => $daftarKecamatan,
            ],
            'data' => $data->values()
        ]);
    }

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

    private function statusMonitoring($cuaca, $kategoriSuhu)
    {
        if (!$cuaca) {
            return 'Data cuaca belum tersedia';
        }

        if ($kategoriSuhu === 'Risiko Suhu Tinggi' || $kategoriSuhu === 'Risiko Suhu Rendah') {
            return 'Perlu perhatian';
        }

        return 'Normal';
    }
}