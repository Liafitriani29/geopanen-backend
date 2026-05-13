<?php

namespace App\Http\Controllers;

use App\Models\Cuaca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CuacaController extends Controller
{
    public function index()
    {
        $data = Cuaca::orderByDesc('updated_at')
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'message' => 'Data cuaca berhasil diambil',
            'data' => $data
        ], 200);
    }

    public function realtime(Request $request)
    {
        $request->validate([
            'kecamatan' => 'required|string',
            'kabupaten' => 'required|string',
            'provinsi' => 'required|string',
            'desa' => 'nullable|string',
        ]);

        $apiKey = trim(env('OPENWEATHER_API_KEY'));

        if (!$apiKey) {
            return response()->json([
                'message' => 'API key OpenWeather belum diatur di file .env'
            ], 500);
        }

        $desa = $request->query('desa');
        $kecamatan = $request->query('kecamatan');
        $kabupaten = $request->query('kabupaten');
        $provinsi = $request->query('provinsi');

        $desaUntukSimpan = $desa ?: '-';

        $lokasiLengkap = implode(', ', array_filter([
            $desa,
            $kecamatan,
            $kabupaten,
            $provinsi,
            'Indonesia'
        ]));

        $queryList = [
            $lokasiLengkap,
            implode(', ', array_filter([$kecamatan, $kabupaten, $provinsi, 'Indonesia'])),
            implode(', ', array_filter([$kabupaten, $provinsi, 'Indonesia'])),
            implode(', ', array_filter([$provinsi, 'Indonesia'])),
        ];

        $geo = null;
        $queryBerhasil = null;
        $lastGeoError = null;

        foreach ($queryList as $index => $query) {
            if (!$query) {
                continue;
            }

            if ($index > 0) {
                sleep(1);
            }

            $geoResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'GeoPanen/1.0 (contact: admin@geopanen.local)',
            ])->timeout(15)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'id',
                'addressdetails' => 1,
            ]);

            if (!$geoResponse->successful()) {
                $lastGeoError = [
                    'status' => $geoResponse->status(),
                    'body' => $geoResponse->json(),
                ];
                continue;
            }

            $geoData = $geoResponse->json();

            if (!empty($geoData)) {
                $geo = $geoData[0];
                $queryBerhasil = $query;
                break;
            }
        }

        if (!$geo) {
            return response()->json([
                'message' => 'Koordinat lokasi tidak ditemukan. Coba gunakan nama kecamatan/kabupaten/provinsi yang lebih umum.',
                'lokasi_dicari' => $lokasiLengkap,
                'error_terakhir' => $lastGeoError,
            ], 404);
        }

        $lat = $geo['lat'];
        $lon = $geo['lon'];

        $weatherResponse = Http::timeout(15)->get('https://api.openweathermap.org/data/2.5/weather', [
            'lat' => $lat,
            'lon' => $lon,
            'units' => 'metric',
            'lang' => 'id',
            'appid' => $apiKey,
        ]);

        if (!$weatherResponse->successful()) {
            return response()->json([
                'message' => 'Gagal mengambil data cuaca realtime dari OpenWeather',
                'status' => $weatherResponse->status(),
                'error' => $weatherResponse->json()
            ], 500);
        }

        $weather = $weatherResponse->json();

        $suhu = $weather['main']['temp'] ?? null;
        $kelembaban = $weather['main']['humidity'] ?? null;
        $kondisi = $weather['weather'][0]['description'] ?? '-';
        $tekanan = $weather['main']['pressure'] ?? null;
        $angin = $weather['wind']['speed'] ?? null;

        $kategori = 'Normal';
        $rekomendasi = 'Kondisi cuaca cukup baik. Lakukan pemantauan lahan secara berkala.';

        if ($suhu !== null) {
            if ($suhu >= 35) {
                $kategori = 'Panas Tinggi';
                $rekomendasi = 'Suhu cukup tinggi. Perhatikan kebutuhan air tanaman dan pastikan irigasi berjalan baik.';
            } elseif ($suhu <= 22) {
                $kategori = 'Suhu Rendah';
                $rekomendasi = 'Suhu relatif rendah. Pantau kelembapan lahan dan pertumbuhan tanaman.';
            }
        }

        if ($kelembaban !== null) {
            if ($kelembaban >= 85) {
                $kategori = 'Kelembapan Tinggi';
                $rekomendasi = 'Kelembapan tinggi. Perhatikan potensi penyakit tanaman dan lakukan pemantauan rutin.';
            } elseif ($kelembaban <= 50) {
                $kategori = 'Kelembapan Rendah';
                $rekomendasi = 'Kelembapan rendah. Perhatikan ketersediaan air dan kondisi tanah.';
            }
        }

        /*
         * Simpan data realtime ke database.
         * Jika pada tanggal, kecamatan, dan desa yang sama sudah ada,
         * maka data akan diperbarui.
         */
        $cuaca = Cuaca::updateOrCreate(
            [
                'tanggal' => now()->toDateString(),
                'kecamatan' => $kecamatan,
                'desa' => $desaUntukSimpan,
            ],
            [
                'suhu' => $suhu,
                'kelembaban' => $kelembaban,
                'kondisi' => $kondisi,
                'kategori' => $kategori,
                'rekomendasi' => $rekomendasi,
            ]
        );

        return response()->json([
            'message' => 'Data cuaca realtime berhasil diambil dan disimpan',
            'data' => [
                'id' => $cuaca->id,
                'tanggal' => $cuaca->tanggal,
                'waktu_pengambilan' => now()->format('Y-m-d H:i:s'),

                'lokasi_dicari' => $lokasiLengkap,
                'lokasi_digunakan' => $queryBerhasil,
                'nama_geocoding' => $geo['display_name'] ?? null,
                'nama_lokasi_api' => $weather['name'] ?? null,

                'latitude' => (float) $lat,
                'longitude' => (float) $lon,

                'desa' => $cuaca->desa,
                'kecamatan' => $cuaca->kecamatan,
                'kabupaten' => $kabupaten,
                'provinsi' => $provinsi,

                'suhu' => $cuaca->suhu,
                'kelembaban' => $cuaca->kelembaban,
                'kondisi' => $cuaca->kondisi,
                'tekanan' => $tekanan,
                'kecepatan_angin' => $angin,

                'kategori' => $cuaca->kategori,
                'rekomendasi' => $cuaca->rekomendasi,
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'kecamatan' => 'required|string|max:255',
            'desa' => 'nullable|string|max:255',
            'suhu' => 'required|numeric',
            'kelembaban' => 'nullable|numeric',
            'kondisi' => 'nullable|string|max:255',
            'kategori' => 'nullable|string|max:255',
            'rekomendasi' => 'nullable|string',
        ]);

        $validated['desa'] = $validated['desa'] ?? '-';

        $cuaca = Cuaca::create($validated);

        return response()->json([
            'message' => 'Data cuaca berhasil ditambahkan',
            'data' => $cuaca
        ], 201);
    }

    public function show($id)
    {
        $cuaca = Cuaca::findOrFail($id);

        return response()->json([
            'message' => 'Detail data cuaca berhasil diambil',
            'data' => $cuaca
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $cuaca = Cuaca::findOrFail($id);

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'kecamatan' => 'required|string|max:255',
            'desa' => 'nullable|string|max:255',
            'suhu' => 'required|numeric',
            'kelembaban' => 'nullable|numeric',
            'kondisi' => 'nullable|string|max:255',
            'kategori' => 'nullable|string|max:255',
            'rekomendasi' => 'nullable|string',
        ]);

        $validated['desa'] = $validated['desa'] ?? '-';

        $cuaca->update($validated);

        return response()->json([
            'message' => 'Data cuaca berhasil diupdate',
            'data' => $cuaca
        ], 200);
    }

    public function destroy($id)
    {
        $cuaca = Cuaca::findOrFail($id);
        $cuaca->delete();

        return response()->json([
            'message' => 'Data cuaca berhasil dihapus'
        ], 200);
    }
}