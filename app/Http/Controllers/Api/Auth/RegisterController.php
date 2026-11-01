<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\Family;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class RegisterController extends Controller
{
    /**
     * POST /api/v1/auth/register
     *
     * Cria uma conta inativa e envia um OTP de 6 dígitos por email. Não devolve token:
     * a conta só fica ativa após POST /auth/verify-activation.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'sometimes|in:parent,partner',
            'institution_name' => 'nullable|string|max:255',
            'nif' => 'nullable|string|max:30',
            'sector' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'director_name' => 'nullable|string|max:255',
        ]);

        $role = $data['role'] ?? 'parent';
        $code = (string) random_int(100000, 999999);

        $user = DB::transaction(function () use ($data, $role, $code) {
            $tenant = Tenant::create([
                'name' => ($role === 'partner' ? 'Parceiro ' : 'Família ') . $data['name'] . ' ' . Str::lower(Str::random(5)),
                'tenant_type' => $role === 'partner' ? 'partner' : 'family',
                'currency' => 'AOA',
                'email' => $data['email'],
            ]);

            $family = $role === 'parent'
                ? Family::create(['tenant_id' => $tenant->id, 'name' => 'Família ' . $data['name'], 'currency' => 'AOA'])
                : null;

            // Parceiros ficam `pending` ate o administrador aprovar a candidatura.
            $partner = $role === 'partner'
                ? Partner::create([
                    'name' => $data['institution_name'] ?? $data['name'],
                    'contact_email' => $data['email'],
                    'is_active' => true,
                    'status' => 'pending',
                    'nif' => $data['nif'] ?? null,
                    'sector' => $data['sector'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'country' => $data['country'] ?? null,
                    'city' => $data['city'] ?? null,
                    'address' => $data['address'] ?? null,
                    'director_name' => $data['director_name'] ?? $data['name'],
                ])
                : null;

            return User::create([
                'tenant_id' => $tenant->id,
                'partner_id' => $partner?->id,
                'family_id' => $family?->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'is_active' => false,
                'must_change_password' => false,
                'activation_code' => $code,
                'activation_expires_at' => now()->addHours(24),
            ]);
        });

        $emailSent = true;
        try {
            Mail::to($user->email)->send(new RegistrationOtpMail($user, $code));
        } catch (Throwable $e) {
            report($e);
            $emailSent = false;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Conta criada. Enviámos um código de ativação para o seu email.',
            'data' => ['requires_activation' => true, 'email' => $user->email, 'email_sent' => $emailSent],
        ], 201);
    }
}
