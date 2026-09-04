<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Support\Facades\DB;
use App\Models\PartnerChallenge;
use App\Models\User;
use Illuminate\Support\Str;
use App\Mail\SchoolChallengeVictoryMail;
use Illuminate\Support\Facades\Mail;

class ProcessChallengeWinnersJob implements ShouldQueue
{
    use Queueable;

    public $challenge;

    /**
     * Create a new job instance.
     */
    public function __construct(PartnerChallenge $challenge)
    {
        $this->challenge = $challenge;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Find the winner classroom (Top 1)
            $winnerPivot = DB::table('classroom_challenge')
                ->where('partner_challenge_id', $this->challenge->id)
                ->orderBy('current_progress', 'desc')
                ->first();

            if (!$winnerPivot) {
                $this->challenge->update(['status' => 'concluded']);
                return;
            }

            $winnerClassroomId = $winnerPivot->classroom_id;

            // Get students for this classroom
            $children = DB::table('classroom_user')
                ->where('classroom_id', $winnerClassroomId)
                ->where('role', 'student')
                ->whereNotNull('child_profile_id')
                ->pluck('child_profile_id');
                
            $teens = DB::table('classroom_user')
                ->where('classroom_id', $winnerClassroomId)
                ->where('role', 'student')
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $totalStudents = $children->count() + $teens->count();

            if ($totalStudents > 0) {
                $rewardPerStudent = $this->challenge->reward_pool / $totalStudents;

                // Process children wallets
                foreach ($children as $childId) {
                    $wallet = \App\Models\Wallet::where('walletable_type', 'App\Models\ChildProfile')
                        ->where('walletable_id', $childId)
                        ->first();
                    
                    if ($wallet) {
                        \App\Models\LedgerEntry::create([
                            'wallet_id' => $wallet->id,
                            'idempotency_key' => Str::uuid()->toString(),
                            'direction' => 'credit',
                            'entry_type' => 'challenge_reward',
                            'amount' => $rewardPerStudent,
                            'description' => "Prémio: 1º Lugar no Desafio {$this->challenge->title}",
                            'created_at' => now(),
                        ]);
                        $wallet->increment('balance', $rewardPerStudent);
                    }
                }

                // Process teens wallets
                foreach ($teens as $teenId) {
                    $wallet = \App\Models\Wallet::where('walletable_type', 'App\Models\User')
                        ->where('walletable_id', $teenId)
                        ->first();
                    
                    if ($wallet) {
                        \App\Models\LedgerEntry::create([
                            'wallet_id' => $wallet->id,
                            'idempotency_key' => Str::uuid()->toString(),
                            'direction' => 'credit',
                            'entry_type' => 'challenge_reward',
                            'amount' => $rewardPerStudent,
                            'description' => "Prémio: 1º Lugar no Desafio {$this->challenge->title}",
                            'created_at' => now(),
                        ]);
                        $wallet->increment('balance', $rewardPerStudent);
                    }
                }
            }

            // Update challenge status
            $this->challenge->update([
                'status' => 'concluded',
                'winner_classroom_id' => $winnerClassroomId,
            ]);

            // Check institutional reward
            if ($this->challenge->school_reward_type) {
                $classroom = \App\Models\Classroom::find($winnerClassroomId);
                if ($classroom && $classroom->tenant_id) {
                    $tenant = $classroom->tenant;
                    
                    // Find Main Coordinator
                    $coordinators = User::where('tenant_id', $tenant->id)->role('coordinator')->get();
                    // Find Super Admin
                    $superAdmins = User::role('admin')->get();
                    
                    $recipients = $coordinators->concat($superAdmins)->pluck('email')->filter()->unique();
                    
                    if ($recipients->count() > 0) {
                        Mail::to($recipients)->send(new SchoolChallengeVictoryMail(
                            $tenant->name,
                            $this->challenge->title,
                            $this->challenge->school_reward_description
                        ));
                    }
                }
            }
        });
    }
}
