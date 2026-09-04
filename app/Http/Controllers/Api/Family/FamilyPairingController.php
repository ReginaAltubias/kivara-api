<?php

namespace App\Http\Controllers\Api\Family;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyPairingController extends Controller
{
    /**
     * Gera um código de pairing único para teen/yang.
     */
        #[OA\Post(
        path: "/api/v1/family/generate-code",
        summary: "Family Generate Code (POST)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function generateCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['teen', 'yang'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Apenas adolescentes (teen/yang) podem gerar um código de pairing.',
            ], 403);
        }

        do {
            $code = 'FAM-' . Str::upper(Str::random(6));
        } while (User::where('pairing_code', $code)->exists());

        $user->pairing_code = $code;
        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'pairing_code' => $code,
            ],
        ]);
    }

    /**
     * Associa um pai a um adolescente via pairing_code.
     */
        #[OA\Post(
        path: "/api/v1/family/pair",
        summary: "Family Pair (POST)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function pairWithTeen(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pairing_code' => 'required|string|max:15',
            ]);

            $parent = $request->user();

            if ($parent->role !== 'parent') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Apenas pais podem associar-se a um adolescente.',
                ], 403);
            }

            $teen = User::query()
                ->where('pairing_code', $validated['pairing_code'])
                ->whereIn('role', ['teen', 'yang'])
                ->first();

            if (! $teen) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Código de pairing inválido ou expirado.',
                ], 404);
            }

            DB::transaction(function () use ($parent, $teen) {
                if (! $parent->family_id) {
                    $family = Family::create([
                        'tenant_id' => $parent->tenant_id,
                        'name' => $parent->name . ' Family',
                        'currency' => 'AOA',
                    ]);
                    $parent->family_id = $family->id;
                }

                $parent->save();

                $teen->family_id = $parent->family_id;
                $teen->pairing_code = null;
                $teen->save();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Pairing realizado com sucesso.',
                'data' => [
                    'family_id' => $parent->fresh()->family_id,
                    'teen' => [
                        'id' => $teen->id,
                        'name' => $teen->name,
                        'role' => $teen->role,
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível concluir o pairing.',
            ], 500);
        }
    }
}
