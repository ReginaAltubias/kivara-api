<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name'          => 'School Starter',
                'price'         => 0.00,
                'billing_cycle' => 'monthly',
                'features'      => [
                    'Até 2 turmas',
                    'Acesso a missões básicas',
                    'Suporte standard por email',
                ],
                'is_active'     => true,
            ],
            [
                'name'          => 'School Premium Mensal',
                'price'         => 45000.00,
                'billing_cycle' => 'monthly',
                'features'      => [
                    'Turmas ilimitadas',
                    'Relatórios pedagógicos e analíticos em tempo real',
                    'Inscrição em Desafios de Parceiros (Prémios Institucionais)',
                    'Catálogo de Recompensas e Loja Escolar',
                    'Suporte prioritário',
                ],
                'is_active'     => true,
            ],
            [
                'name'          => 'School Premium Anual',
                'price'         => 450000.00,
                'billing_cycle' => 'yearly',
                'features'      => [
                    'Turmas ilimitadas',
                    'Relatórios pedagógicos e analíticos em tempo real',
                    'Inscrição em Desafios de Parceiros (Prémios Institucionais)',
                    'Catálogo de Recompensas e Loja Escolar',
                    '2 Meses de isenção incluídos',
                    'Formação e onboarding de professores incluído',
                ],
                'is_active'     => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::firstOrCreate(
                ['name' => $planData['name']],
                $planData
            );
        }
    }
}
