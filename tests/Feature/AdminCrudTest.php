<?php

namespace Tests\Feature;

use App\Mail\AccountActivationMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'secret-pass-123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = $this->makeUser('admin', 'admin@teste.ao');
        Sanctum::actingAs($admin, ['role:admin']);

        return $admin;
    }

    public function test_tenant_crud_and_school_admin_email(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $res = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Colegio Horizonte',
            'tenant_type' => 'school',
            'currency' => 'AOA',
            'nif' => '5401928374',
            'city' => 'Luanda',
            'contact_email' => 'diretor@horizonte.ao',
            'director_name' => 'Maria Diretora',
        ])->assertCreated()->assertJsonPath('email_sent', true)->assertJsonPath('data.contact_email', 'diretor@horizonte.ao');

        $id = $res->json('data.id');
        Mail::assertQueued(AccountActivationMail::class, fn ($m) => $m->hasTo('diretor@horizonte.ao'));
        $this->assertDatabaseHas('users', ['email' => 'diretor@horizonte.ao', 'tenant_id' => $id, 'role' => 'coordinator']);

        $this->postJson('/api/v1/admin/tenants', ['name' => 'Colegio Horizonte'])->assertStatus(422);

        $this->getJson('/api/v1/admin/tenants?type=school&search=horiz')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/tenants/{$id}")->assertOk()->assertJsonPath('data.user_count', 1);

        $this->patchJson("/api/v1/admin/tenants/{$id}", ['city' => 'Benguela'])->assertOk()->assertJsonPath('data.city', 'Benguela');

        $this->postJson("/api/v1/admin/tenants/{$id}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/v1/admin/tenants/{$id}/activate")->assertOk()->assertJsonPath('data.is_active', true);

        Mail::fake();
        $this->postJson("/api/v1/admin/tenants/{$id}/send-credentials")->assertOk()->assertJsonPath('email_sent', true);
        Mail::assertQueued(AccountActivationMail::class, 1);

        $this->postJson("/api/v1/admin/tenants/{$id}/agreement", ['start' => '2026-01-01'])->assertOk();
        $this->postJson("/api/v1/admin/tenants/{$id}/reassign-admin", ['email' => 'novo@horizonte.ao'])->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'novo@horizonte.ao', 'tenant_id' => $id]);

        $this->getJson("/api/v1/admin/tenants/{$id}/members")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/admin/tenants/{$id}/members?role=teacher")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/tenants/{$id}/classrooms")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/tenants/{$id}/students")->assertOk()->assertJsonCount(0, 'data');

        $this->deleteJson("/api/v1/admin/tenants/{$id}")->assertStatus(409);

        $empty = Tenant::create(['name' => 'Vazio', 'tenant_type' => 'family']);
        $this->deleteJson("/api/v1/admin/tenants/{$empty->id}")->assertOk();
        $this->assertDatabaseMissing('tenants', ['id' => $empty->id]);
    }

    public function test_user_crud_block_and_resend(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();

        $res = $this->postJson('/api/v1/admin/users', [
            'name' => 'Pedro Pai',
            'email' => 'pedro@exemplo.ao',
            'role' => 'parent',
        ])->assertCreated()->assertJsonPath('email_sent', true);
        $id = $res->json('data.id');
        Mail::assertQueued(AccountActivationMail::class, fn ($m) => $m->hasTo('pedro@exemplo.ao'));

        $this->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'pedro@exemplo.ao', 'role' => 'parent'])->assertStatus(422);
        $this->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'x@exemplo.ao', 'role' => 'bogus'])->assertStatus(422);

        $this->getJson('/api/v1/admin/users?role=parent&search=pedro')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/users/{$id}")->assertOk()->assertJsonPath('data.email', 'pedro@exemplo.ao');

        $this->patchJson("/api/v1/admin/users/{$id}", ['name' => 'Pedro Novo', 'roles' => ['teacher']])
            ->assertOk()->assertJsonPath('data.name', 'Pedro Novo')->assertJsonPath('data.role', 'teacher');

        $this->postJson("/api/v1/admin/users/{$id}/block")->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/v1/admin/users/{$id}/unblock")->assertOk()->assertJsonPath('data.is_active', true);

        Mail::fake();
        $this->postJson("/api/v1/admin/users/{$id}/resend-activation")->assertOk()->assertJsonPath('email_sent', true);
        Mail::assertQueued(AccountActivationMail::class, 1);

        $this->postJson("/api/v1/admin/users/{$admin->id}/block")->assertStatus(422);
        $this->deleteJson("/api/v1/admin/users/{$admin->id}")->assertStatus(422);

        $this->deleteJson("/api/v1/admin/users/{$id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_application_approval_creates_school_and_sends_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/school-applications', [
            'name' => 'Escola Nova', 'nif' => '123456789', 'school_type' => 'private',
            'education_level' => 'primary', 'contact_email' => 'geral@nova.ao',
            'director_name' => 'Diretor Novo', 'director_email' => 'diretor@nova.ao',
            'status' => 'approved',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->actingAsAdmin();
        $appId = $this->getJson('/api/v1/b2b/school-applications?status=pending')->assertOk()->json('data.0.id');

        $this->postJson("/api/v1/b2b/school-applications/{$appId}/approve")
            ->assertOk()->assertJsonPath('email_sent', true)->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('tenants', ['name' => 'Escola Nova', 'tenant_type' => 'school']);
        $this->assertDatabaseHas('users', ['email' => 'geral@nova.ao', 'role' => 'coordinator']);
        Mail::assertQueued(AccountActivationMail::class, fn ($m) => $m->hasTo('geral@nova.ao'));

        $this->postJson("/api/v1/b2b/school-applications/{$appId}/approve")->assertOk();
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_stats_and_public_tenants_and_forbidden_for_non_admin(): void
    {
        Tenant::create(['name' => 'Escola Publica', 'tenant_type' => 'school']);
        $this->getJson('/api/v1/tenants')->assertOk()->assertJsonPath('data.0.name', 'Escola Publica');

        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/stats')->assertOk()->assertJsonPath('data.totalTenants', 1)
            ->assertJsonCount(7, 'data.weeklySparkline');

        $parent = $this->makeUser('parent', 'pai@teste.ao');
        Sanctum::actingAs($parent, ['role:parent']);
        $this->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->getJson('/api/v1/admin/tenants')->assertStatus(403);
    }
}
