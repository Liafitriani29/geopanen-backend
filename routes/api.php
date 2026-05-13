<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LahanController;
use App\Http\Controllers\PanenController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\CuacaController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PetaniController;
use App\Http\Controllers\PenyuluhController;
use App\Http\Controllers\TesController;
use App\Http\Controllers\ProduksiBulananController;
use App\Http\Controllers\MonitoringPrediksiController;
use App\Http\Controllers\RekomendasiPrediksiController;
use App\Http\Controllers\CatatanPenyuluhController;
use App\Http\Controllers\AktualProduksiBulananController;

// RESET PASSWORD
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

// AUTH
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// RESET PASSWORD
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// LAHAN
Route::get('/lahan', [LahanController::class, 'index']);
Route::post('/lahan', [LahanController::class, 'store']);
Route::get('/lahan/{id}', [LahanController::class, 'show']);
Route::put('/lahan/{id}', [LahanController::class, 'update']);
Route::delete('/lahan/{id}', [LahanController::class, 'destroy']);

// PANEN
Route::get('/panen', [PanenController::class, 'index']);
Route::get('/panen/grafik', [PanenController::class, 'grafik']);
Route::post('/panen', [PanenController::class, 'store']);
Route::get('/panen/{id}', [PanenController::class, 'show']);
Route::put('/panen/{id}', [PanenController::class, 'update']);
Route::delete('/panen/{id}', [PanenController::class, 'destroy']);

// PENGGUNA
Route::get('/pengguna', [PenggunaController::class, 'index']);
Route::post('/pengguna', [PenggunaController::class, 'store']);
Route::get('/pengguna/{id}', [PenggunaController::class, 'show']);
Route::put('/pengguna/{id}', [PenggunaController::class, 'update']);
Route::delete('/pengguna/{id}', [PenggunaController::class, 'destroy']);

// CUACA
Route::get('/cuaca', [CuacaController::class, 'index']);
Route::post('/cuaca', [CuacaController::class, 'store']);

// CUACA REALTIME
Route::get('/cuaca/realtime', [CuacaController::class, 'realtime']);

// DETAIL, UPDATE, DELETE CUACA
Route::get('/cuaca/{id}', [CuacaController::class, 'show']);
Route::put('/cuaca/{id}', [CuacaController::class, 'update']);
Route::delete('/cuaca/{id}', [CuacaController::class, 'destroy']);

// MONITORING DAN LAPORAN
Route::get('/monitoring', [MonitoringController::class, 'index']);
Route::get('/laporan', [LaporanController::class, 'index']);

// PETANI
Route::get('/petani/dashboard', [PetaniController::class, 'dashboard']);
Route::get('/petani/riwayat-produksi', [PetaniController::class, 'riwayatProduksi']);
Route::get('/petani/kondisi-lingkungan', [PetaniController::class, 'kondisiLingkungan']);
Route::get('/petani/rekomendasi', [PetaniController::class, 'rekomendasi']);

// PENYULUH
Route::get('/penyuluh/dashboard', [PenyuluhController::class, 'dashboard']);
Route::get('/penyuluh/monitoring-wilayah', [PenyuluhController::class, 'monitoringWilayah']);
Route::get('/penyuluh/rekomendasi', [PenyuluhController::class, 'rekomendasi']);
Route::get('/penyuluh/laporan', [PenyuluhController::class, 'laporan']);

// TES
Route::get('/tes/prediksi', [TesController::class, 'prediksi']);

// PRODUKSI BULANAN
Route::get('/produksi-bulanan', [ProduksiBulananController::class, 'index']);
Route::post('/produksi-bulanan', [ProduksiBulananController::class, 'store']);
Route::put('/produksi-bulanan/{id}', [ProduksiBulananController::class, 'update']);
Route::delete('/produksi-bulanan/{id}', [ProduksiBulananController::class, 'destroy']);

// MONITORING PREDIKSI DAN REKOMENDASI
Route::get('/monitoring-prediksi', [MonitoringPrediksiController::class, 'index']);
Route::get('/rekomendasi-prediksi', [RekomendasiPrediksiController::class, 'index']);

// CATATAN PENYULUH
Route::get('/catatan-penyuluh', [CatatanPenyuluhController::class, 'index']);
Route::post('/catatan-penyuluh', [CatatanPenyuluhController::class, 'store']);
Route::delete('/catatan-penyuluh/{id}', [CatatanPenyuluhController::class, 'destroy']);

Route::get('/aktual-produksi-bulanan', [AktualProduksiBulananController::class, 'index']);
Route::post('/aktual-produksi-bulanan', [AktualProduksiBulananController::class, 'store']);
Route::put('/aktual-produksi-bulanan/{id}', [AktualProduksiBulananController::class, 'update']);
Route::delete('/aktual-produksi-bulanan/{id}', [AktualProduksiBulananController::class, 'destroy']);

Route::get('/tes/evaluasi-aktual', [TesController::class, 'evaluasiAktual']);