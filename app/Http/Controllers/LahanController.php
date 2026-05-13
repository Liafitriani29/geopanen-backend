<?php

namespace App\Http\Controllers;

use App\Models\Lahan;
use Illuminate\Http\Request;

class LahanController extends Controller
{
    // GET ALL DATA LAHAN
    public function index()
    {
        $data = Lahan::with('user')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Data lahan berhasil diambil',
            'data' => $data
        ]);
    }

    // TAMBAH DATA LAHAN
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'nama' => 'required|string|max:255',
            'luas' => 'required|numeric',
            'kecamatan' => 'required|string|max:255',
            'desa' => 'required|string|max:255',
        ]);

        $lahan = Lahan::create($validated);

        $lahan->load('user');

        return response()->json([
            'message' => 'Lahan berhasil ditambahkan',
            'data' => $lahan
        ], 201);
    }

    // DETAIL DATA LAHAN
    public function show($id)
    {
        $lahan = Lahan::with('user')->findOrFail($id);

        return response()->json([
            'message' => 'Detail lahan berhasil diambil',
            'data' => $lahan
        ]);
    }

    // UPDATE DATA LAHAN
    public function update(Request $request, $id)
    {
        $lahan = Lahan::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'nama' => 'required|string|max:255',
            'luas' => 'required|numeric',
            'kecamatan' => 'required|string|max:255',
            'desa' => 'required|string|max:255',
        ]);

        $lahan->update($validated);

        $lahan->load('user');

        return response()->json([
            'message' => 'Lahan berhasil diupdate',
            'data' => $lahan
        ]);
    }

    // HAPUS DATA LAHAN
    public function destroy($id)
    {
        $lahan = Lahan::findOrFail($id);
        $lahan->delete();

        return response()->json([
            'message' => 'Lahan berhasil dihapus'
        ]);
    }
}