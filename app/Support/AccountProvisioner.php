<?php

namespace App\Support;

use App\Mail\AccountActivationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Cria contas com palavra-passe temporária + código OTP e envia o email de ativação.
 * A palavra-passe nunca é devolvida pela API: segue apenas por email.
 */
class AccountProvisioner
{
    /** @return array{user: User, email_sent: bool} */
    public static function create(array $attributes, ?string $password = null): array
    {
        $password = $password ?: Str::password(10, true, true, false, false);
        $code = (string) random_int(100000, 999999);

        $user = User::create(array_merge([
            'must_change_password' => true,
            'is_active' => true,
        ], $attributes, [
            'password' => $password,
            'activation_code' => $code,
            'activation_expires_at' => now()->addHours(24),
        ]));

        self::syncRole($user, $user->role);

        return ['user' => $user, 'email_sent' => self::sendActivation($user, $password, $code)];
    }

    /** Gera novas credenciais para um utilizador existente e reenvia o email. */
    public static function reissue(User $user): bool
    {
        $password = Str::password(10, true, true, false, false);
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'password' => $password,
            'activation_code' => $code,
            'activation_expires_at' => now()->addHours(24),
            'must_change_password' => true,
        ])->save();

        return self::sendActivation($user, $password, $code);
    }

    public static function syncRole(User $user, ?string $role): void
    {
        self::syncRoles($user, $role ? [$role] : []);
    }

    /** Sincroniza apenas papeis Spatie que existam, para nao falhar em ambientes sem seed. */
    public static function syncRoles(User $user, array $roles): void
    {
        $existing = Role::whereIn('name', $roles)->where('guard_name', 'web')->pluck('name')->all();
        if ($existing !== []) {
            $user->syncRoles($existing);
        }
    }

    private static function sendActivation(User $user, string $password, string $code): bool
    {
        try {
            Mail::to($user->email)->send(new AccountActivationMail($user, $password, $code));
            return true;
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }
}
