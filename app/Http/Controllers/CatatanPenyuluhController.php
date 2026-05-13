<?php

namespace App\Http\Controllers;

use App\Models\CatatanPenyuluh;
use Illuminate\Http\Request;

class CatatanPenyuluhController extends Controller
{
    public function index()
    {
        $data = CatatanPenyuluh::orderByDesc('created_at')->get();

        return response()->json([
            'message' => 'Data catatan penyuluh berhasil diambil',
            'data' => $data
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'nama_penyuluh' => 'nullable|string|max:255',
            'kabupaten' => 'nullable|string|max:100',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|min:1|max:12',
            'catatan' => 'required|string',
        ]);

        $tahun = $validated['tahun'];
        $bulan = $validated['bulan'];

        $periode = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-01';

        $catatan = CatatanPenyuluh::create([
            'user_id' => $validated['user_id'] ?? null,
            'nama_penyuluh' => $validated['nama_penyuluh'] ?? 'Penyuluh',
            'kabupaten' => $validated['kabupaten'] ?? 'Sukoharjo',
            'tahun' => $tahun,
            'bulan' => $bulan,
            'periode' => $periode,
            'catatan' => $validated['catatan'],
        ]);

        return response()->json([
            'message' => 'Catatan penyuluh berhasil disimpan',
            'data' => $catatan
        ], 201);
    }

    public function destroy($id)
    {
        $catatan = CatatanPenyuluh::find($id);

        if (!$catatan) {
            return response()->json([
                'message' => 'Catatan penyuluh tidak ditemukan'
            ], 404);
        }

        $catatan->delete();

        return response()->json([
            'message' => 'Catatan penyuluh berhasil dihapus'
        ], 200);
    }
}