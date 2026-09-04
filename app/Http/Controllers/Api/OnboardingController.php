<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
        #[OA\Get(
        path: "/api/v1/onboarding/progress",
        summary: "Obter Progresso do Onboarding",
        tags: ["Onboarding"],
        responses: [
            new OA\Response(response: 200, description: "Progresso retornado com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function getProgress(Request $request)
    {
        $user = $request->user();
        
        $progress = $user->onboardingProgress()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_step' => 0,
                'step_index' => 0,
                'completed' => false,
                'skipped' => false,
            ]
        );

        return response()->json($progress);
    }

        #[OA\Put(
        path: "/api/v1/onboarding/progress",
        summary: "Atualizar Progresso do Onboarding",
        tags: ["Onboarding"],
        responses: [
            new OA\Response(response: 200, description: "Progresso atualizado."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function updateProgress(Request $request)
    {
        $user = $request->user();
        $progress = $user->onboardingProgress()->first();
        
        if (!$progress) {
             $progress = $user->onboardingProgress()->create([
                 'current_step' => 0,
                 'step_index' => 0,
                 'completed' => false,
                 'skipped' => false,
             ]);
        }

        $validated = $request->validate([
            'current_step' => 'nullable|integer',
            'step_index' => 'nullable|integer',
            'completed' => 'nullable|boolean',
            'skipped' => 'nullable|boolean',
            'completed_at' => 'nullable|date',
        ]);

        if (isset($validated['step_index']) && !isset($validated['current_step'])) {
            $validated['current_step'] = $validated['step_index'];
        }

        $progress->update($validated);

        return response()->json($progress);
    }

        #[OA\Post(
        path: "/api/v1/onboarding/analytics",
        summary: "Registar Analytics do Onboarding",
        tags: ["Onboarding"],
        responses: [
            new OA\Response(response: 200, description: "Evento registado."),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function trackAnalytics(Request $request)
    {
        $validated = $request->validate([
            'event_type' => 'required|string',
            'step_index' => 'required|integer',
            'role' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        \Log::info('Onboarding Analytics', [
            'user_id' => $request->user()->id,
            'event' => $validated
        ]);

        return response()->json(['status' => 'success']);
    }
}
