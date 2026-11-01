<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloqueia a API de parceiros enquanto a conta nao for aprovada (ou se estiver suspensa). */
class EnsurePartnerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'partner' && $user->partner_id) {
            $partner = Partner::find($user->partner_id);

            if ($partner && $partner->status !== 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => $partner->status === 'rejected'
                        ? 'A candidatura de parceiro foi rejeitada.'
                        : 'A conta de parceiro aguarda aprovação do administrador.',
                    'error_code' => $partner->status === 'rejected' ? 'partner_rejected' : 'partner_pending_approval',
                ], 403);
            }

            if ($partner && !$partner->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A conta de parceiro está suspensa.',
                    'error_code' => 'partner_suspended',
                ], 403);
            }
        }

        return $next($request);
    }
}
