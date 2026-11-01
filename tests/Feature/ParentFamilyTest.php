<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\Family;
use App\Models\Streak;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentFamilyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ChildProfile} */
    private function family(string $name): array
    {
        $tenant = Tenant::create(['name' => "Familia {$name}", 'tenant_type' => 'family']);
        $family = Family::create(['tenant_id' => $tenant->id, 'name' => $name, 'currency' => 'AOA']);
        $parent = User::create([
            'name' => "Pai {$name}", 'email' => strtolower($name) . '@teste.ao', 'password' => 'secret-pass-123',
            'role' => 'parent', 'is_active' => true, 'tenant_id' => $tenant->id, 'family_id' => $family->id,
        ]);
        $child = ChildProfile::create([
            'parent_id' => $parent->id, 'family_id' => $family->id, 'tenant_id' => $tenant->id,
            'name' => "Filho {$name}", 'username' => 'kid_' . strtolower($name), 'auth_method' => 'pin',
        ]);

        return [$parent, $child];
    }

    public function test_consent_lifecycle_and_isolation(): void
    {
        [$parentA, $childA] = $this->family('Ana');
        [$parentB] = $this->family('Bruno');

        Sanctum::actingAs($parentA, ['access-api']);
        $id = $this->postJson('/api/v1/parent/consent', [
            'child_id' => $childA->id, 'consent_type' => 'data_processing', 'document_version' => '2.0',
        ])->assertCreated()->assertJsonPath('data.status', 'granted')->json('data.id');

        $this->getJson('/api/v1/parent/consent')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/parent/consent/{$id}/revoke", ['reason' => 'Mudei de ideias'])
            ->assertOk()->assertJsonPath('data.status', 'revoked')->assertJsonPath('data.revocation_reason', 'Mudei de ideias');

        // reconceder reactiva o mesmo registo
        $this->postJson('/api/v1/parent/consent', ['child_id' => $childA->id, 'consent_type' => 'data_processing'])
            ->assertCreated()->assertJsonPath('data.status', 'granted');
        $this->assertDatabaseCount('consents', 1);

        Sanctum::actingAs($parentB, ['access-api']);
        $this->getJson('/api/v1/parent/consent')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/parent/consent/{$id}")->assertStatus(404);
        $this->postJson("/api/v1/parent/consent/{$id}/revoke")->assertStatus(404);
        $this->postJson('/api/v1/parent/consent', ['child_id' => $childA->id, 'consent_type' => 'x'])->assertStatus(404);
    }

    public function test_streaks_and_export_are_owner_only(): void
    {
        [$parentA, $childA] = $this->family('Ana');
        [$parentB] = $this->family('Bruno');
        Streak::create(['child_profile_id' => $childA->id, 'current_streak' => 4, 'best_streak' => 9, 'last_activity_at' => now()]);

        Sanctum::actingAs($parentA, ['access-api']);
        $this->getJson('/api/v1/parent/streaks')->assertOk()
            ->assertJsonPath('data.0.current_streak', 4)->assertJsonPath('data.0.longest_streak', 9)
            ->assertJsonPath('data.0.total_active_days', 0);
        $this->postJson("/api/v1/parent/children/{$childA->id}/export")->assertOk()
            ->assertJsonPath('data.profile.id', $childA->id);

        Sanctum::actingAs($parentB, ['access-api']);
        $this->getJson('/api/v1/parent/streaks')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.current_streak', 0);
        $this->postJson("/api/v1/parent/children/{$childA->id}/export")->assertStatus(404);
    }

    public function test_profile_update_changes_only_personal_fields(): void
    {
        [$parent] = $this->family('Ana');
        Sanctum::actingAs($parent, ['access-api']);

        $this->patchJson('/api/v1/auth/me', [
            'name' => 'Ana Silva', 'phone' => '+244 923 456 789', 'country' => 'Angola', 'language' => 'pt', 'currency' => 'AOA',
            'role' => 'admin', 'email' => 'outro@teste.ao',
        ])->assertOk()->assertJsonPath('data.name', 'Ana Silva')->assertJsonPath('data.phone', '+244 923 456 789');

        $parent->refresh();
        $this->assertSame('parent', $parent->role);
        $this->assertSame('ana@teste.ao', $parent->email);
        $this->patchJson('/api/v1/auth/me', ['language' => 'xx'])->assertStatus(422);
    }
}
