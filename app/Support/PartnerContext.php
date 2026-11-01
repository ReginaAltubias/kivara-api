<?php

namespace App\Support;

use App\Models\Partner;
use Illuminate\Http\Request;

/**
 * Resolve o parceiro em nome do qual o pedido atua.
 *
 * - Utilizador `partner`: o seu proprio parceiro (`users.partner_id`). Contas antigas sem
 *   parceiro recebem um parceiro proprio e aprovado (nunca o de outra entidade).
 * - Admin: tem de indicar `partner_id` (query ou corpo).
 */
class PartnerContext
{
    public static function resolve(Request $request): Partner
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $id = $request->input('partner_id', $request->query('partner_id'));

            if (!$id) {
                abort(422, 'Indique o parceiro (partner_id).');
            }

            return Partner::findOrFail($id);
        }

        if ($user->partner_id) {
            return Partner::findOrFail($user->partner_id);
        }

        $partner = Partner::create([
            'name' => $user->name,
            'contact_email' => $user->email,
            'is_active' => true,
            'status' => 'approved',
        ]);
        $user->forceFill(['partner_id' => $partner->id])->save();

        return $partner;
    }
}
