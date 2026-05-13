<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => 'Link reset password berhasil dikirim. Silakan cek email.'
                ], 200);
            }

            return response()->json([
                'message' => __($status),
                'status' => $status
            ], 422);

        } catch (\Exception $e) {
            Log::error('RESET PASSWORD EMAIL ERROR: ' . $e->getMessage());

            return response()->json([
                'message' => 'Gagal mengirim email reset password.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}