<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, ?Tenant $tenant = null): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $email, 'password' => 'secret-pass-123',
            'role' => $role, 'is_active' => true, 'tenant_id' => $tenant?->id,
        ]);
    }

    public function test_classroom_crud_is_isolated_by_tenant_and_role_aliases_work(): void
    {
        $a = Tenant::create(['name' => 'Escola A', 'tenant_type' => 'school']);
        $b = Tenant::create(['name' => 'Escola B', 'tenant_type' => 'school']);
        $coordA = $this->user('coordinator', 'ca@teste.ao', $a);
        $coordB = $this->user('coordinator', 'cb@teste.ao', $b);

        Sanctum::actingAs($coordA, ['access-api']);

        // rota declarada com `coordenador` aceita a conta `coordinator`
        $id = $this->postJson('/api/v1/b2b/classrooms', ['name' => 'Turma 3A', 'grade_level' => '8-10'])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('classrooms', ['id' => $id, 'tenant_id' => $a->id, 'name' => 'Turma 3A']);

        $this->getJson('/api/v1/school/classrooms')->assertOk()->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/school/classrooms/{$id}", ['name' => 'Turma 3B', 'grade' => '11-13'])
            ->assertOk()->assertJsonPath('data.name', 'Turma 3B')->assertJsonPath('data.grade_level', '11-13');
        $this->assertDatabaseHas('classrooms', ['id' => $id, 'name' => 'Turma 3B']);

        $stats = $this->getJson("/api/v1/classrooms/{$id}/stats")->assertOk()->json('data');
        $this->assertSame(0, $stats['students_count']);
        $this->assertSame(0, $stats['lessons_assigned']);
        $this->assertEquals(0, $stats['completion_rate']);

        $this->getJson('/api/v1/school/stats')->assertOk()
            ->assertJsonPath('data.classrooms_count', 1)->assertJsonPath('data.teachers_count', 1);

        // outra escola nao ve nem altera
        Sanctum::actingAs($coordB, ['access-api']);
        $this->getJson('/api/v1/school/classrooms')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/classrooms/{$id}")->assertStatus(404)->assertJsonPath('status', 'error');
        $this->patchJson("/api/v1/classrooms/{$id}", ['name' => 'Hack'])->assertStatus(404);
        $this->deleteJson("/api/v1/classrooms/{$id}")->assertStatus(404);
        $this->assertDatabaseHas('classrooms', ['id' => $id, 'name' => 'Turma 3B']);

        // professor nao elimina turmas
        $teacher = $this->user('teacher', 't@teste.ao', $a);
        Sanctum::actingAs($teacher, ['access-api']);
        $this->deleteJson("/api/v1/classrooms/{$id}")->assertStatus(403);

        Sanctum::actingAs($coordA, ['access-api']);
        $this->deleteJson("/api/v1/school/classrooms/{$id}")->assertOk();
        $this->assertDatabaseMissing('classrooms', ['id' => $id]);
    }

    public function test_evaluation_routes_exist_and_return_clean_404(): void
    {
        $tenant = Tenant::create(['name' => 'Escola T', 'tenant_type' => 'school']);
        Sanctum::actingAs($this->user('teacher', 'prof@teste.ao', $tenant), ['access-api']);

        $this->postJson('/api/v1/b2b/evaluations/' . \Illuminate\Support\Str::uuid() . '/approve')
            ->assertStatus(404)->assertJsonPath('status', 'error');
        $this->postJson('/api/v1/b2b/evaluations/' . \Illuminate\Support\Str::uuid() . '/reject', ['teacher_feedback' => 'Rever'])
            ->assertStatus(404);
    }

    public function test_trail_creation_sets_phase_and_defaults(): void
    {
        Sanctum::actingAs($this->user('admin', 'admin@teste.ao'), ['access-api']);

        $id = $this->postJson('/api/v1/trails', ['title' => 'Poupar', 'kivara_phase_id' => 2, 'grade_level' => '8-10'])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('trails', ['id' => $id, 'kivara_phase_id' => 2, 'grade_level' => '8-10', 'is_published' => 0]);

        $id2 = $this->postJson('/api/v1/trails', ['title' => 'Gastar'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('trails', ['id' => $id2, 'kivara_phase_id' => 1]);

        $this->getJson('/api/v1/trails')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_notifications_lifecycle(): void
    {
        $admin = $this->user('admin', 'admin@teste.ao');
        $parent = $this->user('parent', 'pai@teste.ao');

        Sanctum::actingAs($admin, ['access-api']);
        $this->postJson('/api/v1/admin/notifications/send', ['title' => 'Aviso', 'message' => 'Manutencao amanha', 'target_audience' => 'parents'])
            ->assertStatus(202)->assertJsonPath('data.recipients', 1);

        Sanctum::actingAs($parent, ['access-api']);
        $list = $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.title', 'Aviso')->assertJsonPath('data.0.read', false);
        $id = $list->json('data.0.id');

        $this->getJson('/api/v1/notifications?unread=1')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/v1/notifications?unread=1')->assertOk()->assertJsonCount(0, 'data');

        // notificacao de outro utilizador nao e acessivel
        Sanctum::actingAs($admin, ['access-api']);
        $this->patchJson("/api/v1/notifications/{$id}/read")->assertStatus(404);
        $this->deleteJson("/api/v1/notifications/{$id}")->assertStatus(404);

        Sanctum::actingAs($parent, ['access-api']);
        $this->postJson('/api/v1/admin/notifications/send', [])->assertStatus(403);
        $this->postJson('/api/v1/notifications/mark-all-read')->assertOk();

        $this->getJson('/api/v1/notifications/settings')->assertOk()->assertJsonPath('data.push_enabled', true);
        $this->putJson('/api/v1/notifications/settings', ['push_enabled' => false, 'email_enabled' => 'nao'])->assertStatus(422);
        $this->putJson('/api/v1/notifications/settings', ['push_enabled' => false])->assertOk()->assertJsonPath('data.push_enabled', false);
        $this->getJson('/api/v1/notifications/settings')->assertJsonPath('data.push_enabled', false)->assertJsonPath('data.email_enabled', true);

        $this->deleteJson("/api/v1/notifications/{$id}")->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
    }
}
