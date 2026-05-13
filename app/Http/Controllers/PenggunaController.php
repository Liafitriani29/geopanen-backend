<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PenggunaController extends Controller
{
    // GET ALL DATA PENGGUNA
    public function index()
    {
        $data = User::select('id', 'name', 'email', 'role', 'created_at')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Data pengguna berhasil diambil',
            'data' => $data
        ]);
    }

    // TAMBAH DATA PENGGUNA
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',

            // Admin tidak boleh dibuat dari halaman Data Pengguna
            'role' => 'required|in:petani,penyuluh',
        ]);

        $pengguna = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Data pengguna berhasil ditambahkan',
            'data' => [
                'id' => $pengguna->id,
                'name' => $pengguna->name,
                'email' => $pengguna->email,
                'role' => $pengguna->role,
            ]
        ], 201);
    }

    // DETAIL DATA PENGGUNA
    public function show($id)
    {
        $pengguna = User::select('id', 'name', 'email', 'role', 'created_at')
            ->findOrFail($id);

        return response()->json([
            'message' => 'Detail pengguna berhasil diambil',
            'data' => $pengguna
        ]);
    }

    // UPDATE DATA PENGGUNA
    public function update(Request $request, $id)
    {
        $pengguna = User::findOrFail($id);

        // Admin utama tidak boleh diedit dari halaman Data Pengguna
        if ($pengguna->role === 'admin') {
            return response()->json([
                'message' => 'Admin utama tidak dapat diedit dari halaman Data Pengguna.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',

            // Role hanya boleh Petani atau Penyuluh
            'role' => 'required|in:petani,penyuluh',
        ]);

        $pengguna->name = $validated['name'];
        $pengguna->email = $validated['email'];
        $pengguna->role = $validated['role'];

        // Password hanya diubah jika admin mengisi password baru
        if (!empty($validated['password'])) {
            $pengguna->password = Hash::make($validated['password']);
        }

        $pengguna->save();

        return response()->json([
            'message' => 'Data pengguna berhasil diupdate',
            'data' => [
                'id' => $pengguna->id,
                'name' => $pengguna->name,
                'email' => $pengguna->email,
                'role' => $pengguna->role,
            ]
        ]);
    }

    // HAPUS DATA PENGGUNA
    public function destroy($id)
    {
        $pengguna = User::findOrFail($id);

        // Admin utama tidak boleh dihapus
        if ($pengguna->role === 'admin') {
            return response()->json([
                'message' => 'Admin utama tidak dapat dihapus.'
            ], 403);
        }

        $pengguna->delete();

        return response()->json([
            'message' => 'Data pengguna berhasil dihapus'
        ]);
    }
}