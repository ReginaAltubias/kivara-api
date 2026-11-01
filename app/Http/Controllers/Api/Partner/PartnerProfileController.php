<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Support\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Perfil institucional do parceiro (dados da empresa e do representante). */
class PartnerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->present(PartnerContext::resolve($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);

        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:255',
            'nif' => 'sometimes|nullable|string|max:30',
            'sector' => 'sometimes|nullable|string|max:100',
            'website' => 'sometimes|nullable|string|max:255',
            'contact_email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:40',
            'country' => 'sometimes|nullable|string|max:100',
            'province' => 'sometimes|nullable|string|max:100',
            'city' => 'sometimes|nullable|string|max:100',
            'address' => 'sometimes|nullable|string|max:255',
            'director_name' => 'sometimes|nullable|string|max:255',
            'director_role' => 'sometimes|nullable|string|max:150',
            'director_email' => 'sometimes|nullable|email|max:255',
            'director_phone' => 'sometimes|nullable|string|max:40',
        ]);

        $partner->update($data);

        return response()->json(['status' => 'success', 'data' => $this->present($partner->fresh())]);
    }

    public function logo(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $request->validate(['logo' => 'required|image|max:2048']);

        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }
        $partner->update(['logo_path' => $request->file('logo')->store("partners/{$partner->id}", 'public')]);

        return response()->json(['status' => 'success', 'data' => $this->present($partner->fresh())]);
    }

    private function present(Partner $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'nif' => $p->nif,
            'sector' => $p->sector,
            'website' => $p->website,
            'contact_email' => $p->contact_email,
            'phone' => $p->phone,
            'country' => $p->country,
            'province' => $p->province,
            'city' => $p->city,
            'address' => $p->address,
            'director_name' => $p->director_name,
            'director_role' => $p->director_role,
            'director_email' => $p->director_email,
            'director_phone' => $p->director_phone,
            'logo_url' => $p->logo_path ? Storage::disk('public')->url($p->logo_path) : null,
            'status' => $p->status,
        ];
    }
}
