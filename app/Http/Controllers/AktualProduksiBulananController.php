<?php

namespace App\Http\Controllers;

use App\Models\AktualProduksiBulanan;
use Illuminate\Http\Request;

class AktualProduksiBulananController extends Controller
{
    public function index()
    {
        $data = AktualProduksiBulanan::orderBy('periode', 'asc')->get();

        return response()->json([
            'message' => 'Data aktual produksi bulanan berhasil diambil',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kabupaten' => 'required|string|max:100',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|min:1|max:12',
            'periode' => 'required|date',
            'produksi_aktual' => 'required|numeric|min:0',
        ]);

        $data = AktualProduksiBulanan::updateOrCreate(
            [
                'kabupaten' => $validated['kabupaten'],
                'periode' => $validated['periode'],
            ],
            $validated
        );

        return response()->json([
            'message' => 'Data aktual produksi bulanan berhasil disimpan',
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = AktualProduksiBulanan::findOrFail($id);

        $validated = $request->validate([
            'kabupaten' => 'required|string|max:100',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|min:1|max:12',
            'periode' => 'required|date',
            'produksi_aktual' => 'required|numeric|min:0',
        ]);

        $data->update($validated);

        return response()->json([
            'message' => 'Data aktual produksi bulanan berhasil diperbarui',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $data = AktualProduksiBulanan::findOrFail($id);
        $data->delete();

        return response()->json([
            'message' => 'Data aktual produksi bulanan berhasil dihapus'
        ]);
    }
}