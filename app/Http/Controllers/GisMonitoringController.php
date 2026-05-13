<?php

namespace App\Http\Controllers;

use App\Services\TesService;
use Illuminate\Support\Facades\DB;

class GisMonitoringController extends Controller
{
    public function index(TesService $tesService)
    {
        /*
         * GIS Monitoring Geopanen
         *
         * Konsep:
         * 1. Angka prediksi panen tetap dihitung oleh TES.
         * 2. Cuaca tidak masuk ke rumus TES.
         * 3. Cuaca dipakai untuk membaca risiko wilayah.
         * 4. Rekomendasi dibuat dari gabungan:
         *    - Status TES / MAPE
         *    - Suhu
         *    - Kelembaban
         *    - Kondisi cuaca
         *    - Risiko wilayah
         */

        $dataTes = $this->getDataTesTerbaru($tesService);
        $daftarKecamatan = $this->getDaftarKecamatan();

        $statusTes = $this->tentukanStatusMape($dataTes['mape']);
        $alasanPrediksi = $this->buatAlasanPrediksi($dataTes, $statusTes);
        $analisisTes = $this->buatAnalisisTes($dataTes, $statusTes);

        $hasil = collect($daftarKecamatan)->map(function ($kecamatan) use (
            $dataTes,
            $statusTes,
            $alasanPrediksi,
            $analisisTes
        ) {
            $risiko = $this->tentukanRisiko(
                $kecamatan['suhu'],
                $kecamatan['kelembaban'],
                $kecamatan['kondisi']
            );

            $alasanRisiko = $this->buatAlasanRisiko(
                $kecamatan['suhu'],
                $kecamatan['kelembaban'],
                $kecamatan['kondisi'],
                $risiko
            );

            $faktorCuaca = $this->buatFaktorCuaca(
                $kecamatan['nama'],
                $kecamatan['suhu'],
                $kecamatan['kelembaban'],
                $kecamatan['kondisi'],
                $risiko
            );

            $analisisCuaca = $this->buatAnalisisCuaca(
                $kecamatan['nama'],
                $kecamatan['suhu'],
                $kecamatan['kelembaban'],
                $kecamatan['kondisi'],
                $risiko,
                $alasanRisiko
            );

            $analisisKolaborasi = $this->buatAnalisisKolaborasi(
                $dataTes,
                $statusTes,
                $kecamatan['nama'],
                $risiko,
                $alasanRisiko
            );

            $rekomendasi = $this->buatRekomendasi(
                $dataTes['mape'],
                $kecamatan['suhu'],
                $kecamatan['kelembaban'],
                $kecamatan['kondisi'],
                $dataTes['sudah_dievaluasi'],
                $risiko
            );

            $kesimpulanMonitoring = $this->buatKesimpulanMonitoring(
                $dataTes,
                $statusTes,
                $kecamatan['nama'],
                $risiko,
                $rekomendasi
            );

            return [
                'nama' => $kecamatan['nama'],
                'lat' => $kecamatan['lat'],
                'lng' => $kecamatan['lng'],

                /*
                 * Data TES level kabupaten.
                 * Nilainya sama untuk semua kecamatan karena dataset produksi
                 * yang dipakai masih level Kabupaten Sukoharjo.
                 */
                'periode' => $dataTes['periode'],
                'periode_label' => $this->formatPeriodeLabel($dataTes['periode']),
                'tahun' => $this->ambilTahun($dataTes['periode']),
                'bulan' => $this->ambilBulan($dataTes['periode']),
                'status' => $statusTes,
                'prediksi' => number_format($dataTes['prediksi'], 0, ',', '.') . ' ton',
                'aktual' => $dataTes['aktual'] !== null
                    ? number_format($dataTes['aktual'], 0, ',', '.') . ' ton'
                    : '-',
                'mape' => $dataTes['mape'] !== null
                    ? $dataTes['mape'] . '%'
                    : '-',

                /*
                 * Data cuaca per kecamatan.
                 * Bagian ini yang membuat risiko wilayah bisa berbeda-beda.
                 */
                'suhu' => $kecamatan['suhu'] . '°C',
                'kelembaban' => $kecamatan['kelembaban'] . '%',
                'kondisi' => $kecamatan['kondisi'],
                'risiko' => $risiko,

                /*
                 * Narasi kolaborasi TES + Cuaca.
                 * Ini yang menjawab pertanyaan:
                 * "Prediksi panen sekian, lalu karena apa perlu dipantau?"
                 */
                'alasan_prediksi' => $alasanPrediksi,
                'analisis_tes' => $analisisTes,
                'faktor_cuaca' => $faktorCuaca,
                'analisis_cuaca' => $analisisCuaca,
                'alasan_risiko' => $alasanRisiko,
                'analisis_kolaborasi' => $analisisKolaborasi,
                'kesimpulan_monitoring' => $kesimpulanMonitoring,

                /*
                 * Rekomendasi akhir dari rule based system.
                 */
                'rekomendasi' => $rekomendasi,

                /*
                 * Keterangan konsep supaya tidak salah saat dijelaskan ke dosen.
                 */
                'keterangan' => 'Prediksi TES dihitung dari data historis produksi bulanan Kabupaten Sukoharjo. Cuaca tidak menjadi input langsung rumus TES, tetapi digunakan sebagai faktor pendukung untuk menentukan risiko wilayah dan rekomendasi monitoring.',
            ];
        })->values();

        return response()->json([
            'message' => 'Data GIS monitoring berhasil diambil',

            'summary' => [
                'total_wilayah' => $hasil->count(),

                'normal' => $hasil->where('status', 'Normal')->count(),
                'cukup' => $hasil->where('status', 'Cukup')->count(),
                'perlu_evaluasi' => $hasil->where('status', 'Perlu Evaluasi')->count(),
                'belum_dievaluasi' => $hasil->where('status', 'Belum Dievaluasi')->count(),

                'risiko_rendah' => $hasil->where('risiko', 'Rendah')->count(),
                'risiko_sedang' => $hasil->where('risiko', 'Sedang')->count(),
                'risiko_tinggi' => $hasil->where('risiko', 'Tinggi')->count(),
            ],

            'data_tes' => [
                'level_data' => 'Kabupaten Sukoharjo',
                'periode' => $dataTes['periode'],
                'periode_label' => $this->formatPeriodeLabel($dataTes['periode']),
                'tahun' => $this->ambilTahun($dataTes['periode']),
                'bulan' => $this->ambilBulan($dataTes['periode']),
                'prediksi' => number_format($dataTes['prediksi'], 0, ',', '.') . ' ton',
                'aktual' => $dataTes['aktual'] !== null
                    ? number_format($dataTes['aktual'], 0, ',', '.') . ' ton'
                    : '-',
                'mape' => $dataTes['mape'] !== null
                    ? $dataTes['mape'] . '%'
                    : '-',
                'status' => $statusTes,
                'analisis' => $analisisTes,
                'alasan_prediksi' => $alasanPrediksi,
            ],

            'kesimpulan_umum' => $this->buatKesimpulanUmum($dataTes, $statusTes, $hasil),

            'data' => $hasil,
        ]);
    }

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

    private function getDataTesTerbaru(TesService $tesService): array
    {
        $dataHistoris = $this->getDataHistoris();

        if (count($dataHistoris) < 24) {
            return [
                'periode' => '-',
                'prediksi' => 0,
                'aktual' => null,
                'mape' => null,
                'sudah_dievaluasi' => false,
            ];
        }

        $hasilTes = $tesService->hitung($dataHistoris);
        $prediksiMendatang = $hasilTes['prediksiMendatang'] ?? [];

        $evaluasiTerbaru = null;

        /*
         * Jika admin sudah mengisi aktual bulan tertentu,
         * GIS akan mengikuti periode aktual terbaru yang cocok dengan prediksi.
         *
         * Contoh:
         * - Admin isi aktual Juli 2025
         * - Jika prediksi Juli 2025 ada
         * - Maka GIS menampilkan prediksi, aktual, MAPE, dan status Juli 2025
         */
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

            $ape = $nilaiAktual != 0
                ? abs($selisih / $nilaiAktual) * 100
                : 0;

            $evaluasiTerbaru = [
                'periode' => $prediksi['periode'],
                'prediksi' => round($nilaiPrediksi, 2),
                'aktual' => round($nilaiAktual, 2),
                'mape' => round($ape, 2),
                'sudah_dievaluasi' => true,
            ];
        }

        if ($evaluasiTerbaru) {
            return $evaluasiTerbaru;
        }

        $prediksiPertama = $prediksiMendatang[0] ?? null;

        if (!$prediksiPertama) {
            return [
                'periode' => '-',
                'prediksi' => 0,
                'aktual' => null,
                'mape' => null,
                'sudah_dievaluasi' => false,
            ];
        }

        return [
            'periode' => $prediksiPertama['periode'],
            'prediksi' => round((float) $prediksiPertama['prediksi'], 2),
            'aktual' => null,
            'mape' => null,
            'sudah_dievaluasi' => false,
        ];
    }

    private function getDaftarKecamatan(): array
    {
        /*
         * Koordinat kecamatan + data cuaca contoh.
         * Nanti suhu, kelembaban, dan kondisi bisa diganti dari API cuaca
         * atau tabel cuaca di database.
         */

        return [
            [
                'nama' => 'Baki',
                'lat' => -7.6110368,
                'lng' => 110.7836652,
                'suhu' => 28,
                'kelembaban' => 92,
                'kondisi' => 'Awan mendung',
            ],
            [
                'nama' => 'Nguter',
                'lat' => -7.7463,
                'lng' => 110.8834,
                'suhu' => 30,
                'kelembaban' => 86,
                'kondisi' => 'Berawan',
            ],
            [
                'nama' => 'Kartasura',
                'lat' => -7.5517,
                'lng' => 110.7378,
                'suhu' => 29,
                'kelembaban' => 80,
                'kondisi' => 'Cerah berawan',
            ],
            [
                'nama' => 'Grogol',
                'lat' => -7.6018,
                'lng' => 110.8186,
                'suhu' => 29,
                'kelembaban' => 84,
                'kondisi' => 'Berawan',
            ],
            [
                'nama' => 'Mojolaban',
                'lat' => -7.5759,
                'lng' => 110.8681,
                'suhu' => 28,
                'kelembaban' => 88,
                'kondisi' => 'Berawan',
            ],
            [
                'nama' => 'Sukoharjo',
                'lat' => -7.6809,
                'lng' => 110.832,
                'suhu' => 28,
                'kelembaban' => 82,
                'kondisi' => 'Berawan',
            ],
            [
                'nama' => 'Polokarto',
                'lat' => -7.6465,
                'lng' => 110.9117,
                'suhu' => 29,
                'kelembaban' => 85,
                'kondisi' => 'Cerah berawan',
            ],
            [
                'nama' => 'Tawangsari',
                'lat' => -7.7327,
                'lng' => 110.7884,
                'suhu' => 28,
                'kelembaban' => 83,
                'kondisi' => 'Berawan',
            ],
            [
                'nama' => 'Bendosari',
                'lat' => -7.7056,
                'lng' => 110.8589,
                'suhu' => 31,
                'kelembaban' => 89,
                'kondisi' => 'Mendung',
            ],
            [
                'nama' => 'Bulu',
                'lat' => -7.7778,
                'lng' => 110.7994,
                'suhu' => 29,
                'kelembaban' => 81,
                'kondisi' => 'Cerah berawan',
            ],
            [
                'nama' => 'Weru',
                'lat' => -7.7711,
                'lng' => 110.7406,
                'suhu' => 28,
                'kelembaban' => 79,
                'kondisi' => 'Berawan',
            ],
            [
                'nama' => 'Gatak',
                'lat' => -7.5902,
                'lng' => 110.7049,
                'suhu' => 28,
                'kelembaban' => 83,
                'kondisi' => 'Berawan',
            ],
        ];
    }

    private function tentukanStatusMape($mape): string
    {
        if ($mape === null) {
            return 'Belum Dievaluasi';
        }

        if ($mape <= 10) {
            return 'Normal';
        }

        if ($mape <= 20) {
            return 'Cukup';
        }

        return 'Perlu Evaluasi';
    }

    private function tentukanRisiko($suhu, $kelembaban, $kondisi): string
    {
        $kondisiLower = strtolower($kondisi);

        /*
         * Risiko wilayah ditentukan dari cuaca.
         * Jadi risiko bisa beda antar kecamatan walaupun prediksi TES sama.
         */
        if (
            $suhu >= 33 ||
            $kelembaban >= 95 ||
            (
                (
                    str_contains($kondisiLower, 'hujan') ||
                    str_contains($kondisiLower, 'mendung')
                ) &&
                $kelembaban >= 90
            )
        ) {
            return 'Tinggi';
        }

        if (
            $suhu >= 31 ||
            $kelembaban >= 85 ||
            str_contains($kondisiLower, 'hujan') ||
            str_contains($kondisiLower, 'mendung')
        ) {
            return 'Sedang';
        }

        return 'Rendah';
    }

    private function buatAlasanPrediksi(array $dataTes, string $statusTes): string
    {
        if ($dataTes['periode'] === '-') {
            return 'Prediksi TES belum tersedia karena data historis produksi bulanan belum cukup.';
        }

        return 'Prediksi produksi panen dihitung menggunakan metode Triple Exponential Smoothing berdasarkan pola data historis produksi bulanan Kabupaten Sukoharjo. Cuaca tidak digunakan sebagai input langsung pada rumus TES, tetapi digunakan setelah prediksi keluar untuk membaca risiko wilayah.';
    }

    private function buatAnalisisTes(array $dataTes, string $statusTes): string
    {
        if ($dataTes['periode'] === '-') {
            return 'Analisis TES belum dapat ditampilkan karena data historis belum mencukupi.';
        }

        $prediksi = number_format($dataTes['prediksi'], 0, ',', '.') . ' ton';
        $periode = $dataTes['periode'];

        if (!$dataTes['sudah_dievaluasi'] || $dataTes['mape'] === null) {
            return "Berdasarkan perhitungan Triple Exponential Smoothing, prediksi produksi panen padi Kabupaten Sukoharjo pada periode {$periode} adalah {$prediksi}. Data aktual untuk periode ini belum tersedia, sehingga MAPE belum dapat dihitung.";
        }

        $aktual = number_format($dataTes['aktual'], 0, ',', '.') . ' ton';
        $mape = $dataTes['mape'] . '%';

        return "Berdasarkan perhitungan Triple Exponential Smoothing, prediksi produksi panen padi Kabupaten Sukoharjo pada periode {$periode} adalah {$prediksi}. Data aktual tercatat sebesar {$aktual}, dengan nilai MAPE {$mape}. Status model dikategorikan {$statusTes}.";
    }

    private function buatAlasanRisiko($suhu, $kelembaban, $kondisi, string $risiko): string
    {
        $alasan = [];
        $kondisiLower = strtolower($kondisi);

        if ($risiko === 'Tinggi') {
            $alasan[] = 'Risiko tinggi karena kondisi cuaca wilayah perlu diprioritaskan.';
        } elseif ($risiko === 'Sedang') {
            $alasan[] = 'Risiko sedang karena terdapat indikator cuaca yang perlu dipantau.';
        } else {
            $alasan[] = 'Risiko rendah karena kondisi cuaca relatif normal.';
        }

        if ($kelembaban >= 95) {
            $alasan[] = 'Kelembaban sangat tinggi.';
        } elseif ($kelembaban >= 85) {
            $alasan[] = 'Kelembaban berada pada kategori tinggi.';
        }

        if ($suhu >= 33) {
            $alasan[] = 'Suhu sangat tinggi.';
        } elseif ($suhu >= 31) {
            $alasan[] = 'Suhu cukup tinggi.';
        }

        if (str_contains($kondisiLower, 'hujan')) {
            $alasan[] = 'Kondisi hujan dapat meningkatkan kelembaban dan risiko gangguan tanaman.';
        } elseif (str_contains($kondisiLower, 'mendung')) {
            $alasan[] = 'Kondisi mendung dapat berkaitan dengan kelembaban wilayah yang lebih tinggi.';
        }

        return implode(' ', $alasan);
    }

    private function buatFaktorCuaca($namaKecamatan, $suhu, $kelembaban, $kondisi, string $risiko): string
    {
        return "Pada Kecamatan {$namaKecamatan}, suhu tercatat {$suhu}°C, kelembaban {$kelembaban}%, dan kondisi cuaca {$kondisi}. Berdasarkan parameter cuaca tersebut, risiko wilayah dikategorikan {$risiko}.";
    }

    private function buatAnalisisCuaca($namaKecamatan, $suhu, $kelembaban, $kondisi, string $risiko, string $alasanRisiko): string
    {
        return "Pada Kecamatan {$namaKecamatan}, kondisi cuaca tercatat {$kondisi} dengan suhu {$suhu}°C dan kelembaban {$kelembaban}%. Berdasarkan parameter tersebut, risiko wilayah dikategorikan {$risiko}. {$alasanRisiko}";
    }

    private function buatAnalisisKolaborasi(array $dataTes, string $statusTes, string $namaKecamatan, string $risiko, string $alasanRisiko): string
    {
        if ($dataTes['periode'] === '-') {
            return 'Analisis kolaborasi TES dan cuaca belum dapat dibuat karena data prediksi belum tersedia.';
        }

        $prediksi = number_format($dataTes['prediksi'], 0, ',', '.') . ' ton';
        $periodeLabel = $this->formatPeriodeLabel($dataTes['periode']);

        return "Prediksi TES menunjukkan produksi panen padi Kabupaten Sukoharjo pada {$periodeLabel} sebesar {$prediksi} dengan status {$statusTes}. Selanjutnya, data cuaca Kecamatan {$namaKecamatan} digunakan untuk membaca risiko wilayah. Hasil analisis cuaca menunjukkan risiko {$risiko}. {$alasanRisiko} Dengan demikian, TES berfungsi sebagai prediksi utama, sedangkan cuaca berfungsi sebagai pendukung analisis risiko dan rekomendasi.";
    }

    private function buatKesimpulanMonitoring(array $dataTes, string $statusTes, string $namaKecamatan, string $risiko, string $rekomendasi): string
    {
        if ($dataTes['periode'] === '-') {
            return 'Kesimpulan monitoring belum tersedia karena prediksi TES belum dapat dihitung.';
        }

        $prediksi = number_format($dataTes['prediksi'], 0, ',', '.') . ' ton';
        $periodeLabel = $this->formatPeriodeLabel($dataTes['periode']);

        return "Sistem memprediksi produksi panen padi Kabupaten Sukoharjo pada {$periodeLabel} sebesar {$prediksi} dengan status TES {$statusTes}. Pada Kecamatan {$namaKecamatan}, kondisi cuaca menunjukkan risiko {$risiko}. Oleh karena itu, rekomendasi sistem adalah: {$rekomendasi}";
    }

    private function buatKesimpulanUmum(array $dataTes, string $statusTes, $hasil): string
    {
        if ($dataTes['periode'] === '-') {
            return 'Kesimpulan umum belum tersedia karena data historis produksi bulanan belum mencukupi untuk perhitungan TES.';
        }

        $prediksi = number_format($dataTes['prediksi'], 0, ',', '.') . ' ton';
        $periodeLabel = $this->formatPeriodeLabel($dataTes['periode']);

        $rendah = $hasil->where('risiko', 'Rendah')->count();
        $sedang = $hasil->where('risiko', 'Sedang')->count();
        $tinggi = $hasil->where('risiko', 'Tinggi')->count();

        return "Prediksi TES Kabupaten Sukoharjo pada {$periodeLabel} sebesar {$prediksi} dengan status model {$statusTes}. Berdasarkan kondisi cuaca kecamatan, terdapat {$rendah} wilayah risiko rendah, {$sedang} wilayah risiko sedang, dan {$tinggi} wilayah risiko tinggi. Rekomendasi sistem diberikan berdasarkan gabungan hasil prediksi TES dan risiko cuaca wilayah.";
    }

    private function buatRekomendasi($mape, $suhu, $kelembaban, $kondisi, $sudahDievaluasi = true, $risiko = 'Rendah'): string
    {
        $aturan = [];
        $kondisiLower = strtolower($kondisi);

        /*
         * Rule berdasarkan TES / MAPE.
         */
        if (!$sudahDievaluasi || $mape === null) {
            $aturan[] = 'Prediksi TES sudah tersedia, tetapi belum ada data aktual yang sesuai untuk evaluasi MAPE. Admin perlu menambahkan data aktual produksi bulanan.';
        } elseif ($mape <= 10) {
            $aturan[] = 'Hasil prediksi tergolong normal karena nilai MAPE rendah. Monitoring rutin tetap dilakukan.';
        } elseif ($mape <= 20) {
            $aturan[] = 'Status prediksi TES berada pada kategori cukup. Perlu pemantauan ulang data aktual dan kondisi wilayah.';
        } else {
            $aturan[] = 'Prediksi kurang akurat. Perlu evaluasi data historis, data aktual, dan kondisi lapangan.';
        }

        /*
         * Rule berdasarkan risiko cuaca.
         */
        if ($risiko === 'Tinggi') {
            $aturan[] = 'Risiko cuaca wilayah tinggi, penyuluh disarankan melakukan monitoring lapangan lebih prioritas.';
        } elseif ($risiko === 'Sedang') {
            $aturan[] = 'Risiko cuaca wilayah sedang, kondisi lingkungan perlu dipantau secara berkala.';
        } else {
            $aturan[] = 'Risiko cuaca wilayah rendah, monitoring rutin tetap dilakukan.';
        }

        /*
         * Rule detail kelembaban.
         */
        if ($kelembaban >= 90) {
            $aturan[] = 'Kelembaban sangat tinggi, sehingga perlu pemantauan hama, penyakit tanaman, dan kondisi lahan.';
        } elseif ($kelembaban >= 85) {
            $aturan[] = 'Kelembaban tinggi, sehingga perlu pemantauan risiko gangguan tanaman.';
        }

        /*
         * Rule detail suhu.
         */
        if ($suhu >= 33) {
            $aturan[] = 'Suhu sangat tinggi, sehingga perlu perhatian terhadap ketersediaan air dan kondisi tanaman.';
        } elseif ($suhu >= 31) {
            $aturan[] = 'Suhu cukup tinggi, sehingga perlu pemantauan pengairan dan kelembaban tanah.';
        }

        /*
         * Rule detail kondisi cuaca.
         */
        if (
            (
                str_contains($kondisiLower, 'hujan') ||
                str_contains($kondisiLower, 'mendung')
            ) &&
            $kelembaban >= 85
        ) {
            $aturan[] = 'Kondisi cuaca lembab atau basah, penyuluh disarankan melakukan monitoring lapangan secara berkala.';
        }

        return implode(' ', $aturan);
    }

    private function ambilTahun($periode)
    {
        if (!$periode || $periode === '-') {
            return null;
        }

        return (int) date('Y', strtotime($periode));
    }

    private function ambilBulan($periode)
    {
        if (!$periode || $periode === '-') {
            return null;
        }

        return (int) date('m', strtotime($periode));
    }

    private function formatPeriodeLabel($periode): string
    {
        if (!$periode || $periode === '-') {
            return '-';
        }

        $bulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $timestamp = strtotime($periode);
        $bulanAngka = (int) date('n', $timestamp);
        $tahun = date('Y', $timestamp);

        return ($bulan[$bulanAngka] ?? '-') . ' ' . $tahun;
    }
}