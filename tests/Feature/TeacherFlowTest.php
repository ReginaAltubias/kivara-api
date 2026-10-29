<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\LearningObject;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, ?Tenant $tenant): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $email, 'password' => 'secret-pass-123',
            'role' => $role, 'is_active' => true, 'tenant_id' => $tenant?->id,
        ]);
    }

    public function test_teacher_sees_progress_queue_and_approves_submission(): void
    {
        $tenant = Tenant::create(['name' => 'Escola P', 'tenant_type' => 'school']);
        $teacher = $this->user('teacher', 'prof@teste.ao', $tenant);
        $teen = $this->user('teen', 'aluno@teste.ao', $tenant);

        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'Turma 9A', 'grade_level' => '11-13', 'year' => '2026/2027', 'teacher_id' => $teacher->id]);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'user_id' => $teacher->id, 'role' => 'teacher']);
        ClassroomUser::create(['classroom_id' => $classroom->id, 'user_id' => $teen->id, 'role' => 'student']);

        $lo = LearningObject::create([
            'tenant_id' => $tenant->id, 'title' => 'Orcamento', 'type' => 'quiz', 'topic' => 'budgeting',
            'reward_xp' => 100, 'reward_coins' => 0,
        ]);

        Sanctum::actingAs($teacher, ['access-api']);

        $this->getJson('/api/v1/b2b/teacher/classrooms')->assertOk()->assertJsonPath('data.0.student_count', 1)->assertJsonPath('data.0.students.0.name', 'Teen');

        $this->postJson("/api/v1/b2b/teacher/classrooms/{$classroom->id}/missions", ['learning_object_id' => $lo->id])
            ->assertOk()->assertJsonPath('status', 'success');
        $progress = UserProgress::where('user_id', $teen->id)->where('learning_object_id', $lo->id)->first();
        $this->assertNotNull($progress);

        $this->getJson("/api/v1/b2b/teacher/classrooms/{$classroom->id}/progress")->assertOk()
            ->assertJsonPath('data.0.name', 'Teen')->assertJsonPath('data.0.type', 'teen')->assertJsonPath('data.0.pending_reviews', 0);

        // aluno submete; aparece na fila de avaliacao
        $progress->update(['status' => 'pending_review', 'submitted_at' => now(), 'submission_note' => 'Feito']);

        $this->getJson("/api/v1/b2b/teacher/classrooms/{$classroom->id}/evaluations")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_name', 'Teen');
        $this->getJson("/api/v1/teacher/classrooms/{$classroom->id}/pending-evaluations")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.student.name', 'Teen');

        $this->postJson("/api/v1/b2b/evaluations/{$progress->id}/approve", ['teacher_feedback' => 'Muito bem'])
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.xp_awarded', 100);
        $this->assertDatabaseHas('user_progress', ['id' => $progress->id, 'status' => 'approved', 'total_xp_earned' => 100]);

        // ja avaliada
        $this->postJson("/api/v1/b2b/evaluations/{$progress->id}/reject")->assertStatus(422);

        $this->getJson("/api/v1/b2b/teacher/classrooms/{$classroom->id}/progress")->assertOk()->assertJsonPath('data.0.total_xp', 100);
    }

    public function test_school_challenges_are_isolated_by_tenant(): void
    {
        $a = Tenant::create(['name' => 'Escola A', 'tenant_type' => 'school']);
        $b = Tenant::create(['name' => 'Escola B', 'tenant_type' => 'school']);
        $plan = \App\Models\Plan::create(['name' => 'Escola', 'price' => 10, 'billing_cycle' => 'monthly']);
        foreach ([$a, $b] as $t) {
            \App\Models\Subscription::create(['tenant_id' => $t->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        }
        $coordA = $this->user('coordinator', 'ca@teste.ao', $a);
        $coordB = $this->user('coordinator', 'cb@teste.ao', $b);
        $classA = Classroom::create(['tenant_id' => $a->id, 'name' => 'A1', 'grade_level' => '8-10', 'year' => '2026/2027', 'teacher_id' => $coordA->id]);
        $classB = Classroom::create(['tenant_id' => $b->id, 'name' => 'B1', 'grade_level' => '8-10', 'year' => '2026/2027', 'teacher_id' => $coordB->id]);

        Sanctum::actingAs($coordA, ['access-api']);

        // nao pode associar turmas de outra escola
        $this->postJson('/api/v1/school/challenges', ['title' => 'Hack', 'classroom_ids' => [$classB->id]])->assertStatus(422);

        $id = $this->postJson('/api/v1/school/challenges', ['title' => 'Poupar mais', 'type' => 'saving', 'classroom_ids' => [$classA->id]])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('partner_challenges', ['id' => $id, 'tenant_id' => $a->id, 'goal_type' => 'total_xp']);
        $this->getJson('/api/v1/school/challenges')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($coordB, ['access-api']);
        $this->getJson('/api/v1/school/challenges')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/school/challenges/{$id}", ['title' => 'Roubado'])->assertStatus(404);
        $this->deleteJson("/api/v1/school/challenges/{$id}")->assertStatus(404);
        $this->assertDatabaseHas('partner_challenges', ['id' => $id, 'title' => 'Poupar mais']);

        Sanctum::actingAs($coordA, ['access-api']);
        $this->patchJson("/api/v1/school/challenges/{$id}", ['title' => 'Poupar ainda mais'])->assertOk();
        $this->deleteJson("/api/v1/school/challenges/{$id}")->assertOk();
        $this->assertDatabaseMissing('partner_challenges', ['id' => $id]);
    }

    public function test_school_profile_and_staff_management_are_scoped(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $a = Tenant::create(['name' => 'Escola A', 'tenant_type' => 'school']);
        $b = Tenant::create(['name' => 'Escola B', 'tenant_type' => 'school']);
        $coordA = $this->user('coordinator', 'ca@teste.ao', $a);
        $teacherA = $this->user('teacher', 'ta@teste.ao', $a);
        $teacherB = $this->user('teacher', 'tb@teste.ao', $b);

        Sanctum::actingAs($coordA, ['access-api']);
        $this->getJson("/api/v1/tenants/{$a->id}")->assertOk()->assertJsonPath('name', 'Escola A');
        $this->getJson("/api/v1/tenants/{$b->id}")->assertStatus(404);
        $this->patchJson("/api/v1/tenants/{$b->id}", ['name' => 'Roubada'])->assertStatus(404);
        $this->patchJson("/api/v1/tenants/{$a->id}", ['name' => 'Escola A Nova', 'city' => 'Luanda'])->assertOk();
        $this->assertDatabaseHas('tenants', ['id' => $a->id, 'name' => 'Escola A Nova', 'city' => 'Luanda']);

        $this->postJson("/api/v1/tenants/{$a->id}/logo", [])->assertStatus(422);
        $this->postJson("/api/v1/tenants/{$a->id}/logo", ['file' => \Illuminate\Http\UploadedFile::fake()->image('logo.png')])->assertOk();
        $this->assertNotNull($a->fresh()->logo_url);

        // professor nao altera o perfil
        Sanctum::actingAs($teacherA, ['access-api']);
        $this->patchJson("/api/v1/tenants/{$a->id}", ['name' => 'X'])->assertStatus(403);

        // gestao de professores
        Sanctum::actingAs($coordA, ['access-api']);
        $this->patchJson("/api/v1/school/teachers/{$teacherA->id}/status", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('users', ['id' => $teacherA->id, 'is_active' => 0]);
        $this->patchJson("/api/v1/school/teachers/{$teacherB->id}/status", ['is_active' => false])->assertStatus(404);
        $this->patchJson("/api/v1/school/teachers/{$coordA->id}/status", ['is_active' => false])->assertStatus(404);

        $this->deleteJson("/api/v1/school/teachers/{$teacherA->id}")->assertOk();
        $this->assertDatabaseHas('users', ['id' => $teacherA->id, 'tenant_id' => null, 'is_active' => 0]);
        $this->deleteJson("/api/v1/school/teachers/{$teacherB->id}")->assertStatus(404);
    }

    public function test_coordinator_assigns_teacher_to_own_classrooms_only(): void
    {
        $a = Tenant::create(['name' => 'Escola A', 'tenant_type' => 'school']);
        $b = Tenant::create(['name' => 'Escola B', 'tenant_type' => 'school']);
        $coord = $this->user('coordinator', 'c@teste.ao', $a);
        $teacher = $this->user('teacher', 't@teste.ao', $a);
        $c1 = Classroom::create(['tenant_id' => $a->id, 'name' => '1A', 'grade_level' => '5-7', 'year' => '2026/2027', 'teacher_id' => $coord->id]);
        $c2 = Classroom::create(['tenant_id' => $a->id, 'name' => '1B', 'grade_level' => '5-7', 'year' => '2026/2027', 'teacher_id' => $coord->id]);
        $other = Classroom::create(['tenant_id' => $b->id, 'name' => 'X', 'grade_level' => '5-7', 'year' => '2026/2027', 'teacher_id' => $coord->id]);

        Sanctum::actingAs($coord, ['access-api']);
        $this->patchJson("/api/v1/school/teachers/{$teacher->id}/classrooms", ['classroom_ids' => [$c1->id, $c2->id]])->assertOk();
        $this->assertDatabaseCount('classroom_user', 2);

        $this->patchJson("/api/v1/school/teachers/{$teacher->id}/classrooms", ['classroom_ids' => [$c1->id]])->assertOk();
        $this->assertDatabaseCount('classroom_user', 1);
        $this->assertDatabaseHas('classroom_user', ['classroom_id' => $c1->id, 'user_id' => $teacher->id, 'role' => 'teacher']);

        $this->patchJson("/api/v1/school/teachers/{$teacher->id}/classrooms", ['classroom_ids' => [$other->id]])->assertStatus(422);
        $this->assertDatabaseCount('classroom_user', 1);

        Sanctum::actingAs($teacher, ['access-api']);
        $this->getJson('/api/v1/b2b/teacher/classrooms')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', '1A');
    }

    public function test_teacher_cannot_touch_other_school_or_foreign_classroom(): void
    {
        $a = Tenant::create(['name' => 'Escola A', 'tenant_type' => 'school']);
        $b = Tenant::create(['name' => 'Escola B', 'tenant_type' => 'school']);
        $teacherA = $this->user('teacher', 'pa@teste.ao', $a);
        $teacherB = $this->user('teacher', 'pb@teste.ao', $b);
        $teenB = $this->user('teen', 'ab@teste.ao', $b);

        $classB = Classroom::create(['tenant_id' => $b->id, 'name' => 'Turma B', 'grade_level' => '8-10', 'year' => '2026/2027', 'teacher_id' => $teacherB->id]);
        ClassroomUser::create(['classroom_id' => $classB->id, 'user_id' => $teacherB->id, 'role' => 'teacher']);
        ClassroomUser::create(['classroom_id' => $classB->id, 'user_id' => $teenB->id, 'role' => 'student']);

        $lo = LearningObject::create(['title' => 'X', 'type' => 'quiz', 'topic' => 'saving', 'reward_xp' => 10]);
        $progress = UserProgress::create(['user_id' => $teenB->id, 'learning_object_id' => $lo->id, 'status' => 'pending_review', 'submitted_at' => now()]);

        Sanctum::actingAs($teacherA, ['access-api']);

        $this->getJson("/api/v1/b2b/teacher/classrooms/{$classB->id}/progress")->assertStatus(404);
        $this->getJson("/api/v1/b2b/teacher/classrooms/{$classB->id}/evaluations")->assertStatus(404);
        $this->getJson("/api/v1/teacher/classrooms/{$classB->id}/pending-evaluations")->assertStatus(404);
        $this->postJson("/api/v1/b2b/evaluations/{$progress->id}/approve")->assertStatus(403);
        $this->assertDatabaseHas('user_progress', ['id' => $progress->id, 'status' => 'pending_review']);
    }
}
