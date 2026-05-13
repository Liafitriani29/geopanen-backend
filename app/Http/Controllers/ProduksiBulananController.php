<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProduksiBulananController extends Controller
{
    public function index()
    {
        $data = DB::table('produksi_bulanan')
            ->orderBy('periode', 'asc')
            ->get();

        return response()->json([
            'message' => 'Data produksi bulanan berhasil diambil',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kabupaten' => 'nullable|string|max:100',
            'tahun' => 'required|integer|min:2021|max:2024',
            'bulan' => 'required|integer|min:1|max:12',
            'produksi' => 'required|numeric|min:0',
        ], [
            'tahun.min' => 'Tahun data historis TES minimal 2021.',
            'tahun.max' => 'Data Produksi Bulanan hanya digunakan sebagai data historis TES sampai tahun 2024. Data aktual tahun 2025 harus dimasukkan melalui menu Evaluasi Aktual TES.',
            'bulan.min' => 'Bulan harus berada antara 1 sampai 12.',
            'bulan.max' => 'Bulan harus berada antara 1 sampai 12.',
            'produksi.required' => 'Produksi bulanan wajib diisi.',
            'produksi.numeric' => 'Produksi bulanan harus berupa angka.',
            'produksi.min' => 'Produksi bulanan tidak boleh kurang dari 0.',
        ]);

        $kabupaten = $validated['kabupaten'] ?? 'Sukoharjo';
        $tahun = (int) $validated['tahun'];
        $bulan = (int) $validated['bulan'];
        $produksi = (float) $validated['produksi'];

        $periode = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-01';

        DB::table('produksi_bulanan')->updateOrInsert(
            [
                'kabupaten' => $kabupaten,
                'periode' => $periode,
            ],
            [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'produksi' => $produksi,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Data produksi bulanan berhasil disimpan',
            'data' => [
                'kabupaten' => $kabupaten,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'periode' => $periode,
                'produksi' => $produksi,
            ]
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'kabupaten' => 'nullable|string|max:100',
            'tahun' => 'required|integer|min:2021|max:2024',
            'bulan' => 'required|integer|min:1|max:12',
            'produksi' => 'required|numeric|min:0',
        ], [
            'tahun.min' => 'Tahun data historis TES minimal 2021.',
            'tahun.max' => 'Data Produksi Bulanan hanya digunakan sebagai data historis TES sampai tahun 2024. Data aktual tahun 2025 harus dimasukkan melalui menu Evaluasi Aktual TES.',
            'bulan.min' => 'Bulan harus berada antara 1 sampai 12.',
            'bulan.max' => 'Bulan harus berada antara 1 sampai 12.',
            'produksi.required' => 'Produksi bulanan wajib diisi.',
            'produksi.numeric' => 'Produksi bulanan harus berupa angka.',
            'produksi.min' => 'Produksi bulanan tidak boleh kurang dari 0.',
        ]);

        $data = DB::table('produksi_bulanan')->where('id', $id)->first();

        if (!$data) {
            return response()->json([
                'message' => 'Data produksi bulanan tidak ditemukan'
            ], 404);
        }

        $kabupaten = $validated['kabupaten'] ?? 'Sukoharjo';
        $tahun = (int) $validated['tahun'];
        $bulan = (int) $validated['bulan'];
        $produksi = (float) $validated['produksi'];

        $periode = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-01';

        DB::table('produksi_bulanan')
            ->where('id', $id)
            ->update([
                'kabupaten' => $kabupaten,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'periode' => $periode,
                'produksi' => $produksi,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Data produksi bulanan berhasil diupdate',
            'data' => [
                'id' => $id,
                'kabupaten' => $kabupaten,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'periode' => $periode,
                'produksi' => $produksi,
            ]
        ]);
    }

    public function destroy($id)
    {
        $data = DB::table('produksi_bulanan')->where('id', $id)->first();

        if (!$data) {
            return response()->json([
                'message' => 'Data produksi bulanan tidak ditemukan'
            ], 404);
        }

        DB::table('produksi_bulanan')->where('id', $id)->delete();

        return response()->json([
            'message' => 'Data produksi bulanan berhasil dihapus'
        ]);
    }
}