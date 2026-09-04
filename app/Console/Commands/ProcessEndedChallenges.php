<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessEndedChallenges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'challenges:process-ended';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica desafios de parceiros que já terminaram e processa os vencedores.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $endedChallenges = \App\Models\PartnerChallenge::where('ends_at', '<=', now())
            ->where('status', 'active')
            ->get();

        foreach ($endedChallenges as $challenge) {
            $challenge->update(['status' => 'processing']);
            \App\Jobs\ProcessChallengeWinnersJob::dispatch($challenge);
        }

        $this->info("Despachados " . $endedChallenges->count() . " desafios para processamento.");
    }
}
