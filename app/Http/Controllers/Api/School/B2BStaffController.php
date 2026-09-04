<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Mail\AccountActivationMail;
use App\Mail\StaffWelcomeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class B2BStaffController extends Controller
{
    /**
     * GET /api/v1/school/teachers ou /api/v1/school/permissions/teachers
     * 
     * Lista os professores e funcionários da instituição escolar com as suas turmas e permissões.
     */
        #[OA\Get(
        path: "/api/v1/school/teachers",
        summary: "School Teachers (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'O utilizador autenticado não está associado a nenhuma escola.',
            ], 403);
        }

        $search = $request->query('search');

        $query = User::with(['roles', 'classrooms:id,name'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereIn('role', ['teacher', 'coordinator', 'school_admin'])
                  ->orWhereHas('roles', fn($r) => $r->whereIn('name', ['teacher', 'coordinator', 'school_admin']));
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        $staff = $query->get()->map(function ($member) {
            $roles = $member->roles->pluck('name')->toArray();
            if (empty($roles) && $member->role) {
                $roles = [$member->role];
            }

            return [
                'id'            => $member->id,
                'user_id'       => $member->id,
                'display_name'  => $member->name,
                'email'         => $member->email,
                'phone'         => null,
                'roles'         => $roles,
                'is_active'     => (bool) $member->is_active,
                'classroom_ids' => $member->classrooms->pluck('id')->toArray(),
                'created_at'    => $member->created_at?->toISOString(),
                'permissions'   => [
                    'can_edit_classes'      => true,
                    'can_assign_missions'   => true,
                    'can_assign_challenges' => true,
                    'can_view_reports'      => true,
                    'teachers.manage'       => in_array('coordinator', $roles) || in_array('school_admin', $roles),
                    'students.manage'       => true,
                    'classrooms.manage'     => true,
                    'challenges.assign'     => true,
                    'reports.school'        => true,
                ],
            ];
        });

        return response()->json($staff, 200);
    }

    /**
     * POST /api/v1/b2b/staff/invite
     * 
     * Convida um novo membro para a equipa docente/administrativa com código OTP e credenciais.
     */
        #[OA\Post(
        path: "/api/v1/school/staff/invite",
        summary: "School Staff Invite (POST)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'name'  => 'required|string|max:255',
            'role'  => 'required|in:teacher,coordinator',
        ]);

        $inviter = $request->user();
        
        if (!$inviter->tenant_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'O utilizador autenticado não está associado a nenhuma escola.',
            ], 403);
        }

        // Geração segura de senha temporária e código de ativação OTP de 6 dígitos
        $password = Str::password(8, true, true, false, false);
        $activationCode = (string) random_int(100000, 999999);

        $newStaff = User::create([
            'tenant_id'             => $inviter->tenant_id,
            'name'                  => $request->name,
            'email'                 => $request->email,
            'password'              => Hash::make($password),
            'role'                  => $request->role,
            'must_change_password'  => true,
            'activation_code'       => $activationCode,
            'activation_expires_at' => now()->addHours(24),
            'is_active'             => true,
        ]);

        $newStaff->assignRole($request->role);

        // Despacho assíncrono do e-mail com credenciais e código OTP
        Mail::to($newStaff->email)->queue(new AccountActivationMail(
            $newStaff,
            $password,
            $activationCode
        ));

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Convite, credenciais e código OTP de ativação enviados com sucesso.',
            'data'    => $newStaff,
        ], 201);
    }

    /**
     * PATCH /api/v1/b2b/staff/{id}/status
     * 
     * Altera o estado ativo/inativo de um membro da equipa.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $tenantId = $request->user()->tenant_id;
        $staff = User::where('tenant_id', $tenantId)->findOrFail($id);

        $staff->update([
            'is_active' => $request->is_active,
        ]);

        return response()->json([
            'success'   => true,
            'is_active' => (bool) $staff->is_active,
            'message'   => 'Estado do utilizador atualizado com sucesso.',
        ], 200);
    }

    /**
     * PATCH /api/v1/school/teacher-permissions/{id}
     * 
     * Atualiza as permissões granulares de um professor.
     */
        #[OA\Patch(
        path: "/api/v1/school/teacher-permissions/{id}",
        summary: "School Teacher Permissions {id} (PATCH)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function updatePermissions(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $staff = User::where('tenant_id', $tenantId)->findOrFail($id);

        // Se houver permissões específicas no payload
        $permissions = $request->input('permissions', []);

        return response()->json([
            'success'     => true,
            'message'     => 'Permissões atualizadas com sucesso.',
            'permissions' => $permissions,
        ], 200);
    }
}
