<?php

namespace App\Http\Controllers;

use App\Models\Panen;
use Illuminate\Http\Request;

class PanenController extends Controller
{
    // GET ALL DATA PANEN
    public function index()
    {
        $data = Panen::with(['lahan.user'])
            ->latest()
            ->get();

        return response()->json($data);
    }

    // GET DATA UNTUK GRAFIK DATA PANEN
    public function grafik()
    {
        $data = Panen::with('lahan')
            ->orderBy('tanggal')
            ->get();

        return response()->json(
            $data->map(function ($item, $index) {
                $hasil = floatval(str_replace(',', '.', $item->hasil));

                return [
                    "name" => $item->tanggal ?? "Data " . ($index + 1),
                    "lahan" => $item->lahan->nama ?? "-",
                    "aktual" => $hasil,
                ];
            })
        );
    }

    // TAMBAH DATA PANEN
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lahan_id' => 'required|exists:lahan,id',
            'tanggal' => 'required|date',
            'hasil' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        $data = Panen::create($validated);

        return response()->json([
            'message' => 'Data panen berhasil ditambahkan',
            'data' => $data
        ], 201);
    }

    // DETAIL DATA PANEN
    public function show($id)
    {
        $data = Panen::with(['lahan.user'])->findOrFail($id);

        return response()->json($data);
    }

    // UPDATE DATA PANEN
    public function update(Request $request, $id)
    {
        $panen = Panen::findOrFail($id);

        $validated = $request->validate([
            'lahan_id' => 'required|exists:lahan,id',
            'tanggal' => 'required|date',
            'hasil' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        $panen->update($validated);

        return response()->json([
            'message' => 'Data panen berhasil diupdate',
            'data' => $panen
        ]);
    }

    // HAPUS DATA PANEN
    public function destroy($id)
    {
        $panen = Panen::findOrFail($id);
        $panen->delete();

        return response()->json([
            'message' => 'Data panen berhasil dihapus'
        ]);
    }
}