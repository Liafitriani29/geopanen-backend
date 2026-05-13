<?php

namespace App\Http\Controllers;

use App\Models\Lahan;
use App\Models\Cuaca;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index()
    {
        $dataLahan = Lahan::with('panen')->latest()->get();

        $data = $dataLahan->map(function ($lahan) {
            // Ambil data panen terbaru berdasarkan tanggal
            $panenTerbaru = $lahan->panen
                ->sortByDesc('tanggal')
                ->first();

            // Ambil data cuaca terbaru berdasarkan kecamatan dan desa
            $cuacaTerbaru = Cuaca::where('kecamatan', $lahan->kecamatan)
                ->when($lahan->desa, function ($query) use ($lahan) {
                    return $query->where('desa', $lahan->desa);
                })
                ->latest('tanggal')
                ->first();

            // Jika cuaca berdasarkan desa tidak ada, cari berdasarkan kecamatan saja
            if (!$cuacaTerbaru) {
                $cuacaTerbaru = Cuaca::where('kecamatan', $lahan->kecamatan)
                    ->latest('tanggal')
                    ->first();
            }

            $analisisSuhu = $this->analisisSuhu($cuacaTerbaru->suhu ?? null);

            return [
                'id_lahan' => $lahan->id,
                'nama_lahan' => $lahan->nama,
                'luas' => (float) $lahan->luas,
                'kecamatan' => $lahan->kecamatan,
                'desa' => $lahan->desa,

                'tanggal_panen' => $panenTerbaru->tanggal ?? null,
                'hasil_panen' => $panenTerbaru ? (float) $panenTerbaru->hasil : null,
                'keterangan_panen' => $panenTerbaru->keterangan ?? null,

                'tanggal_cuaca' => $cuacaTerbaru->tanggal ?? null,
                'suhu' => $cuacaTerbaru ? (float) $cuacaTerbaru->suhu : null,
                'kelembaban' => $cuacaTerbaru && $cuacaTerbaru->kelembaban !== null
                    ? (float) $cuacaTerbaru->kelembaban
                    : null,
                'kondisi_cuaca' => $cuacaTerbaru->kondisi ?? null,
                'keterangan_cuaca' => $cuacaTerbaru->keterangan ?? null,

                'kategori_suhu' => $analisisSuhu['kategori'],
                'rekomendasi_suhu' => $analisisSuhu['rekomendasi'],

                'status_monitoring' => $this->statusMonitoring(
                    $panenTerbaru,
                    $cuacaTerbaru,
                    $analisisSuhu['kategori']
                ),
            ];
        });

        return response()->json([
            'message' => 'Data monitoring berhasil diambil',
            'data' => $data
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

    private function statusMonitoring($panen, $cuaca, $kategoriSuhu)
    {
        if (!$panen && !$cuaca) {
            return 'Data panen dan cuaca belum lengkap';
        }

        if (!$panen) {
            return 'Data panen belum tersedia';
        }

        if (!$cuaca) {
            return 'Data cuaca belum tersedia';
        }

        if ($kategoriSuhu === 'Risiko Suhu Tinggi' || $kategoriSuhu === 'Risiko Suhu Rendah') {
            return 'Perlu perhatian';
        }

        return 'Normal';
    }
}