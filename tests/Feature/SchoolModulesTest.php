<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\LearningObject;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\Trail;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolModulesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, ?Tenant $tenant): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $email, 'password' => 'secret-pass-123',
            'role' => $role, 'is_active' => true, 'tenant_id' => $tenant?->id,
        ]);
    }

    public function test_reports_and_dashboard_use_real_numbers_not_placeholders(): void
    {
        $tenant = Tenant::create(['name' => 'Escola R', 'tenant_type' => 'school']);
        $coord = $this->user('coordinator', 'coord@teste.ao', $tenant);
        $teacher = $this->user('teacher', 'prof@teste.ao', $tenant);
        $teen = $this->user('teen', 'aluno@teste.ao', $tenant);
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'Turma 9A', 'grade_level' => '9ª', 'year' => '2026/2027', 'teacher_id' => $teacher->id]);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'user_id' => $teacher->id, 'role' => 'teacher']);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'user_id' => $teen->id, 'role' => 'student']);

        Sanctum::actingAs($coord, ['access-api']);

        // sem actividade: tudo a zero (antes devolvia 85%, 82.4, 1420 missoes, XP aleatorio)
        $this->getJson('/api/v1/school/dashboard')->assertOk()
            ->assertJsonPath('summary.school_engagement_rate', 0)->assertJsonPath('progress_by_year.0.progress', 0)
            ->assertJsonPath('engagement_curve.6.xp', 0);
        $this->getJson('/api/v1/school/reports')->assertOk()
            ->assertJsonPath('summary.completion_rate', 0)->assertJsonPath('summary.total_missions_completed', 0)
            ->assertJsonPath('summary.collective_savings_aoa', 0)->assertJsonPath('class_progress.0.progress', 0);

        $lo = LearningObject::create(['tenant_id' => $tenant->id, 'title' => 'Orcamento', 'type' => 'quiz', 'topic' => 'budgeting', 'reward_xp' => 100, 'reward_coins' => 0]);
        UserProgress::create(['user_id' => $teen->id, 'learning_object_id' => $lo->id, 'completed' => true, 'total_xp_earned' => 80, 'best_score' => 90]);

        $this->getJson('/api/v1/school/reports')->assertOk()
            ->assertJsonPath('summary.completion_rate', 100)->assertJsonPath('summary.total_missions_completed', 1)
            ->assertJsonPath('summary.avg_financial_score', 90)->assertJsonPath('class_progress.0.progress', 100)
            ->assertJsonPath('class_progress.0.xp', 80)->assertJsonPath('class_progress.0.avg_kiva_points', 80)
            ->assertJsonCount(6, 'growth_curve')->assertJsonPath('growth_curve.5.xp', 80);
        $this->getJson('/api/v1/school/dashboard')->assertOk()->assertJsonPath('progress_by_year.0.progress', 100);
    }

    public function test_teacher_permissions_are_persisted_and_enforced(): void
    {
        $tenant = Tenant::create(['name' => 'Escola P', 'tenant_type' => 'school']);
        $coord = $this->user('coordinator', 'coord@teste.ao', $tenant);
        $teacher = $this->user('teacher', 'prof@teste.ao', $tenant);
        $other = Tenant::create(['name' => 'Outra', 'tenant_type' => 'school']);
        $stranger = $this->user('teacher', 'estranho@teste.ao', $other);
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'Turma 9A', 'grade_level' => '9ª', 'year' => '2026/2027', 'teacher_id' => $teacher->id]);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'user_id' => $teacher->id, 'role' => 'teacher']);
        $lo = LearningObject::create(['tenant_id' => $tenant->id, 'title' => 'Orcamento', 'type' => 'quiz', 'topic' => 'budgeting', 'reward_xp' => 100, 'reward_coins' => 0]);

        Sanctum::actingAs($coord, ['access-api']);
        $this->getJson('/api/v1/school/teacher-permissions')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.permissions.can_assign_missions', true);
        $this->patchJson("/api/v1/school/teacher-permissions/{$teacher->id}", ['permissions' => ['can_assign_missions' => false]])
            ->assertOk()->assertJsonPath('permissions.can_assign_missions', false)->assertJsonPath('permissions.can_view_reports', true);
        $this->patchJson("/api/v1/school/teacher-permissions/{$stranger->id}", ['permissions' => ['can_assign_missions' => false]])->assertStatus(404);

        Sanctum::actingAs($teacher->fresh(), ['access-api']);
        $this->getJson('/api/v1/school/teacher-permissions')->assertOk()->assertJsonPath('data.0.permissions.can_assign_missions', false);
        $this->postJson("/api/v1/b2b/teacher/classrooms/{$classroom->id}/missions", ['learning_object_id' => $lo->id])->assertStatus(403);
        $this->patchJson("/api/v1/school/teacher-permissions/{$teacher->id}", ['permissions' => ['can_assign_missions' => true]])->assertStatus(403);

        Sanctum::actingAs($coord, ['access-api']);
        $this->patchJson("/api/v1/school/teacher-permissions/{$teacher->id}", ['permissions' => ['can_assign_missions' => true]])->assertOk();
        Sanctum::actingAs($teacher->fresh(), ['access-api']);
        $this->postJson("/api/v1/b2b/teacher/classrooms/{$classroom->id}/missions", ['learning_object_id' => $lo->id])->assertOk();
    }

    public function test_lesson_catalog_lists_published_lessons_and_assigns_to_class(): void
    {
        $tenant = Tenant::create(['name' => 'Escola L', 'tenant_type' => 'school']);
        $coord = $this->user('coordinator', 'coord@teste.ao', $tenant);
        $teacher = $this->user('teacher', 'prof@teste.ao', $tenant);
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'Turma 5A', 'grade_level' => '5ª', 'year' => '2026/2027', 'teacher_id' => $teacher->id]);
        $trail = Trail::create(['title' => 'Poupar', 'kivara_phase_id' => 1, 'status' => 'published', 'order' => 1]);
        $pub = Lesson::create(['trail_id' => $trail->id, 'title' => 'O cofre', 'content_type' => 'text', 'content_body' => '<p>Guardar dinheiro</p>', 'xp_reward' => 30, 'status' => 'published', 'order' => 1]);
        Lesson::create(['trail_id' => $trail->id, 'title' => 'Rascunho', 'content_type' => 'text', 'content_body' => 'x', 'xp_reward' => 10, 'status' => 'draft', 'order' => 2]);

        \App\Models\MissionTemplate::create(['lesson_id' => $pub->id, 'title' => 'Missão do cofre', 'description' => 'Poupar', 'task_type' => 'quiz', 'base_coin_reward' => 10, 'base_xp_reward' => 10, 'status' => 'published']);

        Sanctum::actingAs($coord, ['access-api']);
        $this->getJson('/api/v1/lessons')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'O cofre')->assertJsonPath('data.0.kiva_points_reward', 30)->assertJsonPath('data.0.trail_name', 'Poupar');
        $this->getJson('/api/v1/lessons?include_inactive=1')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/trails/{$trail->id}/lessons")->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/classrooms/{$classroom->id}/lessons", ['lesson_id' => $pub->id])->assertSuccessful();
        $this->getJson("/api/v1/classrooms/{$classroom->id}/lessons")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_teacher_dashboard_student_detail_missions_and_messages(): void
    {
        $tenant = Tenant::create(['name' => 'Escola T', 'tenant_type' => 'school']);
        $teacher = $this->user('teacher', 'prof@teste.ao', $tenant);
        $outsider = $this->user('teacher', 'fora@teste.ao', $tenant);
        $parent = $this->user('parent', 'pai@teste.ao', null);
        $family = \App\Models\Family::create(['tenant_id' => $tenant->id, 'name' => 'Familia', 'currency' => 'AOA']);
        $child = \App\Models\ChildProfile::create(['parent_id' => $parent->id, 'family_id' => $family->id, 'tenant_id' => $tenant->id, 'name' => 'Aluno Um', 'username' => 'aluno1', 'auth_method' => 'pin', 'total_xp' => 250]);
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'Turma 4A', 'grade_level' => '4ª', 'year' => '2026/2027', 'teacher_id' => $teacher->id]);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'user_id' => $teacher->id, 'role' => 'teacher']);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'child_profile_id' => $child->id, 'role' => 'student']);
        $lo = LearningObject::create(['tenant_id' => $tenant->id, 'title' => 'Mealheiro', 'type' => 'quiz', 'topic' => 'saving', 'reward_xp' => 40, 'reward_coins' => 20]);
        $pending = UserProgress::create(['child_profile_id' => $child->id, 'learning_object_id' => $lo->id, 'status' => 'pending_review', 'submitted_at' => now()->subHour(), 'submission_url' => 'http://x/y.jpg']);

        Sanctum::actingAs($teacher->fresh(), ['access-api']);
        $this->getJson('/api/v1/teacher/dashboard')->assertOk()
            ->assertJsonPath('data.classes.0.name', 'Turma 4A')->assertJsonPath('data.classes.0.student_count', 1)
            ->assertJsonPath('data.queue.0.student_name', 'Aluno Um')->assertJsonPath('data.queue.0.type', 'photo')->assertJsonPath('data.queue.0.reward', 20)
            ->assertJsonPath('data.total_students', 1);
        $this->getJson('/api/v1/teacher/student-progress')->assertOk()->assertJsonPath('data.0.profile_id', $child->id)->assertJsonPath('data.0.level', 3);
        $this->getJson("/api/v1/teacher/students/{$child->id}")->assertOk()
            ->assertJsonPath('data.profile.display_name', 'Aluno Um')->assertJsonPath('data.pedagogical.level', 3)->assertJsonCount(1, 'data.tasks');

        // missoes do professor para alunos da sua turma
        $this->postJson('/api/v1/missions', ['title' => 'Poupar', 'reward' => 30, 'child_profile_id' => $child->id])->assertCreated();
        $this->getJson('/api/v1/missions?child_profile_id=' . $child->id)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/notifications', ['classroom_id' => $classroom->id, 'message' => 'Boa semana!'])->assertCreated()->assertJsonPath('recipients', 2);
        $this->postJson('/api/v1/notifications', ['recipient_id' => $parent->id, 'message' => 'Olá'])->assertCreated()->assertJsonPath('recipients', 1);
        $this->assertSame(2, $parent->notifications()->count());
        $this->getJson("/api/v1/classrooms/{$classroom->id}")->assertOk()->assertJsonPath('data.students.0.guardian.display_name', 'Parent');

        // aprovar pela fila do painel
        $this->postJson("/api/v1/teacher/evaluations/{$pending->id}", ['status' => 'approved'])->assertOk();
        $this->getJson('/api/v1/teacher/dashboard')->assertOk()->assertJsonCount(0, 'data.queue');

        // professor de outra turma nao ve o aluno nem envia mensagens a ele
        Sanctum::actingAs($outsider->fresh(), ['access-api']);
        $this->getJson("/api/v1/teacher/students/{$child->id}")->assertStatus(404);
        $this->postJson('/api/v1/missions', ['title' => 'x', 'reward' => 1, 'child_profile_id' => $child->id])->assertStatus(404);
        $this->postJson('/api/v1/notifications', ['profile_id' => $child->id, 'message' => 'x'])->assertStatus(404);
        $this->postJson('/api/v1/notifications', ['recipient_id' => $parent->id, 'message' => 'x'])->assertStatus(404);
    }

    public function test_assistant_role_can_read_school_but_not_manage_permissions(): void
    {
        $tenant = Tenant::create(['name' => 'Escola A', 'tenant_type' => 'school']);
        $coord = $this->user('coordinator', 'coord@teste.ao', $tenant);
        $teacher = $this->user('teacher', 'prof@teste.ao', $tenant);

        Sanctum::actingAs($coord, ['access-api']);
        $this->postJson('/api/v1/school/staff/invite', ['email' => 'assist@teste.ao', 'name' => 'Ana Assistente', 'role' => 'assistant'])->assertSuccessful();
        $assistant = User::where('email', 'assist@teste.ao')->firstOrFail();
        $this->assertSame('assistant', $assistant->role);

        Sanctum::actingAs($assistant, ['access-api']);
        $this->getJson('/api/v1/school/teachers')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/v1/school/students')->assertOk();
        $this->getJson('/api/v1/school/classrooms')->assertOk();
        $this->getJson('/api/v1/school/reports')->assertOk();
        // sem permissoes de coordenador
        $this->patchJson("/api/v1/school/teacher-permissions/{$teacher->id}", ['permissions' => ['can_view_reports' => false]])->assertStatus(403);
        $this->getJson('/api/v1/school/subscription')->assertStatus(403);
    }
}
