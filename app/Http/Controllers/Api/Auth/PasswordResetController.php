<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class PasswordResetController extends Controller
{
    private const TTL_MINUTES = 60;

    /**
     * POST /api/v1/auth/forgot-password
     * Responde sempre 200 para não revelar se o email existe.
     */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->is_active) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            try {
                Mail::to($user->email)->send(new PasswordResetMail($user, $token));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Se o email existir, enviámos as instruções de recuperação.',
        ]);
    }

    /** POST /api/v1/auth/reset-password */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        $valid = $row
            && Hash::check($data['token'], $row->token)
            && now()->diffInMinutes($row->created_at, true) <= self::TTL_MINUTES;

        $user = $valid ? User::where('email', $data['email'])->first() : null;

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'O link de recuperação é inválido ou expirou.',
                'error_code' => 'invalid_reset_token',
            ], 422);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return response()->json(['status' => 'success', 'message' => 'Palavra-passe alterada com sucesso.']);
    }
}
