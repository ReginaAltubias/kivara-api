<?php

use App\Http\Controllers\Api\Admin\AdminAuditController;
use App\Http\Controllers\Api\Admin\AdminBannerController;
use App\Http\Controllers\Api\Admin\AdminComplianceController;
use App\Http\Controllers\Api\Admin\AdminLessonController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\AdminOnboardingController;
use App\Http\Controllers\Api\Admin\AdminRiskController;
use App\Http\Controllers\Api\Admin\AdminSubscriptionController;
use App\Http\Controllers\Api\Auth\ActivationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Family\FamilyPairingController;
use App\Http\Controllers\Api\Finance\TransactionController;
use App\Http\Controllers\Api\Finance\AllowanceController;
use App\Http\Controllers\Api\Finance\VaultController;
use App\Http\Controllers\Api\Finance\WalletController;
use App\Http\Controllers\Api\Gamification\LessonController;
use App\Http\Controllers\Api\Gamification\StreakController;
use App\Http\Controllers\Api\Gamification\BadgeController;
use App\Http\Controllers\Api\ParentGate\LevelUpRequestController;
use App\Http\Controllers\Api\School\SchoolApplicationController;
use App\Http\Controllers\Api\School\ClassroomController;
use App\Http\Controllers\Api\School\TeacherClassroomController;
use App\Http\Controllers\Api\School\ChallengeController;
use App\Http\Controllers\Api\School\MissionEvaluationController;
use App\Http\Controllers\Api\School\ReportController;
use App\Http\Controllers\Api\School\SchoolRewardController;
use App\Http\Controllers\Api\School\B2BSubscriptionController;
use App\Http\Controllers\Api\School\PaymentWebhookController;
use App\Http\Controllers\Api\School\SchoolDashboardController;
use App\Http\Controllers\Api\School\SchoolStudentController;
use App\Http\Controllers\Api\School\SchoolProfileController;
use App\Http\Controllers\Api\School\B2BStaffController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerWalletController;
use App\Http\Controllers\Api\Partner\PartnerProgramController;
use App\Http\Controllers\Api\Partner\PartnerChallengeController;
use App\Http\Controllers\Api\Partner\PartnerReportController;
use App\Http\Controllers\Api\Admin\AdminRoleController;
use App\Http\Controllers\Api\User\ChildProfileController;
use App\Http\Controllers\Api\ParentGate\TaskController;
use App\Http\Controllers\Api\ParentGate\MissionController;
use App\Http\Controllers\Api\ParentGate\RewardController;
use App\Http\Controllers\Api\ParentGate\ConsentController;
use App\Http\Controllers\Api\ParentGate\InsightController;
use App\Http\Controllers\Api\ParentGate\SubscriptionController;
use App\Http\Controllers\Api\Admin\TrailController;
use App\Http\Controllers\Api\MediaUploadController;
use App\Http\Controllers\Api\School\SchoolEvaluationController;
use App\Http\Controllers\Api\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ========================================================================
    // AUTENTICAÇÃO (Public Routes)
    // ========================================================================
    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'store'])->name('auth.login');
        Route::post('verify-activation', [ActivationController::class, 'verify'])->name('auth.verify-activation');
        Route::post('resend-activation', [ActivationController::class, 'resend'])->name('auth.resend-activation');
    });

    // ========================================================================
    // Protected Routes (Require Authentication)
    // ========================================================================
    Route::middleware('auth:sanctum')->group(function () {
        // ====================================================================
        // ONBOARDING
        // ====================================================================
        Route::prefix('onboarding')->group(function () {
            Route::get('/progress', [OnboardingController::class, 'getProgress']);
            Route::put('/progress', [OnboardingController::class, 'updateProgress']);
            Route::post('/analytics', [OnboardingController::class, 'trackAnalytics']);
        });
        
        // ====================================================================
        // AUTENTICAÇÃO - Authenticated
        // ====================================================================
        Route::prefix('auth')->group(function () {
            Route::post('logout', [LogoutController::class, 'destroy'])->name('auth.logout');
            Route::get('me', [ProfileController::class, 'show'])->name('auth.profile');
            Route::post('change-password', [ProfileController::class, 'changePassword'])->name('auth.change-password');
            Route::post('refresh', function (\Illuminate\Http\Request $request) {
                $user = $request->user();
                
                // Garantir que o token enviado é um Refresh Token válido
                if (!$user->currentAccessToken()->can('issue-access-token')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Token fornecido não tem permissão para renovar a sessão.',
                    ], 403);
                }

                // Recalcular a expiração baseada no Role
                $expiresIn = match ($user->role) {
                    'admin', 'coordinator' => 7200, // 2 horas
                    'teacher' => 43200, // 12 horas
                    'parent' => 86400, // 24 horas
                    default => 86400,
                };

                // Emitir novo Access Token (Curta Duração)
                $accessToken = $user->createToken('api-access', ['access-api'], now()->addSeconds($expiresIn))->plainTextToken;
                
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'access_token' => $accessToken,
                        'token_type' => 'Bearer',
                        'expires_in' => $expiresIn,
                    ]
                ], 200);
            })->name('auth.refresh');
            Route::post('security-check', [AdminAuditController::class, 'securityCheck'])
                ->middleware('ability:role:admin')
                ->name('auth.security-check');
        });

        // ====================================================================
        // MOTOR FINANCEIRO
        // ====================================================================
        Route::prefix('finance')->group(function () {
            // Wallet (Saldo, Histórico, Top-up)
            Route::prefix('wallet')->group(function () {
                Route::get('balance', [WalletController::class, 'balance'])->name('wallet.balance');
                Route::get('history', [WalletController::class, 'history'])->name('wallet.history');
                Route::post('top-up', [WalletController::class, 'initiateTopUp'])->name('wallet.top-up');
            });

            // Transactions
            Route::prefix('transactions')->group(function () {
                Route::get('/', [TransactionController::class, 'index'])->name('transactions.index');
                Route::post('/', [TransactionController::class, 'store'])->name('transactions.store');
            });

            // Allowances (Mesadas)
            Route::prefix('allowances')->group(function () {
                Route::get('/', [AllowanceController::class, 'index'])->name('allowances.index');
                Route::post('/', [AllowanceController::class, 'store'])->name('allowances.store');
                Route::get('{id}', [AllowanceController::class, 'show'])->name('allowances.show');
                Route::match(['put', 'patch'], '{id}', [AllowanceController::class, 'update'])->name('allowances.update');
                Route::delete('{id}', [AllowanceController::class, 'destroy'])->name('allowances.destroy');
                Route::post('{id}/process', [AllowanceController::class, 'process'])->name('allowances.process');
            });

            // Vaults (Cofres de Poupança)
            Route::prefix('vaults')->group(function () {
                Route::get('/', [VaultController::class, 'index'])->name('vaults.index');
                Route::post('/', [VaultController::class, 'store'])->name('vaults.store');
                Route::get('{id}', [VaultController::class, 'show'])->name('vaults.show');
                Route::match(['put', 'patch'], '{id}', [VaultController::class, 'update'])->name('vaults.update');
                Route::delete('{id}', [VaultController::class, 'destroy'])->name('vaults.destroy');
                Route::post('{id}/deposit', [VaultController::class, 'deposit'])->name('vaults.deposit');
                Route::post('{id}/withdraw', [VaultController::class, 'withdraw'])->name('vaults.withdraw');
                Route::post('{id}/break', [VaultController::class, 'withdraw'])->name('vaults.break');
            });
        });

        // ====================================================================
        // GAMIFICAÇÃO B2C
        // ====================================================================
        Route::prefix('gamification')->group(function () {
            // Learning Objects (Lições)
            Route::prefix('lessons')->group(function () {
                Route::get('/', [LessonController::class, 'index'])->name('lessons.index');
                Route::get('{id}', [LessonController::class, 'show'])->name('lessons.show');
                Route::post('{id}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
            });

            // Streaks (Atividade Diária)
            Route::prefix('streaks')->group(function () {
                Route::post('record', [StreakController::class, 'record'])->name('streaks.record');
                Route::get('me', [StreakController::class, 'show'])->name('streaks.show');
            });

            // Badges (Conquistas)
            Route::prefix('badges')->group(function () {
                Route::get('/', [BadgeController::class, 'index'])->name('badges.index');
                Route::get('{id}', [BadgeController::class, 'show'])->name('badges.show');
            });
        });

        // ====================================================================
        // PORTÃO PARENTAL
        // ====================================================================
        Route::prefix('parents')->group(function () {
            Route::prefix('level-up-requests')->group(function () {
                Route::get('/', [LevelUpRequestController::class, 'index'])->name('level-up-requests.index');
                Route::get('{id}', [LevelUpRequestController::class, 'show'])->name('level-up-requests.show');
                Route::post('{id}/approve', [LevelUpRequestController::class, 'approve'])->name('level-up-requests.approve');
                Route::post('{id}/reject', [LevelUpRequestController::class, 'reject'])->name('level-up-requests.reject');
            });

            Route::prefix('child-profiles')->group(function () {
                Route::post('{id}/join-classroom', [\App\Http\Controllers\Api\ParentGate\ChildClassroomController::class, 'joinClassroom'])->name('parents.child-profiles.join-classroom');
            });
        });

        // ====================================================================
        // B2B - ESCOLAS (Admin Routes)
        // ====================================================================
        Route::prefix('b2b')->group(function () {
            // School Applications
            Route::prefix('school-applications')->group(function () {
                Route::get('/', [SchoolApplicationController::class, 'index'])->name('school-applications.index')->middleware('role:admin');
                Route::get('{id}', [SchoolApplicationController::class, 'show'])->name('school-applications.show')->middleware('role:admin');
                Route::post('{id}/approve', [SchoolApplicationController::class, 'approve'])->name('school-applications.approve')->middleware('role:admin');
                Route::post('{id}/reject', [SchoolApplicationController::class, 'reject'])->name('school-applications.reject')->middleware('role:admin');
            });

            // Staff Management (Coordinators only)
            Route::prefix('staff')->middleware('role:coordinator')->group(function () {
                Route::post('invite', [\App\Http\Controllers\Api\School\B2BStaffController::class, 'invite'])->name('b2b.staff.invite');
            });

            // Classrooms
            Route::prefix('classrooms')->group(function () {
                Route::get('/', [ClassroomController::class, 'index'])->name('classrooms.index')->middleware('role:coordenador|teacher');
                Route::post('/', [ClassroomController::class, 'store'])->name('classrooms.store')->middleware('role:coordenador');
                Route::get('{id}', [ClassroomController::class, 'show'])->name('classrooms.show')->middleware('role:coordenador|teacher');
                
                // Assign teacher
                Route::post('{id}/assign-teacher', [ClassroomController::class, 'assignTeacher'])->name('classrooms.assign-teacher')->middleware('role:coordenador');

                Route::get('{id}/students', [ClassroomController::class, 'students'])->name('classrooms.students')->middleware('role:coordenador|teacher');
                Route::post('{id}/students', [ClassroomController::class, 'addStudents'])->name('classrooms.add-students')->middleware('role:coordenador|teacher');
                Route::delete('{id}/students/{studentId}', [ClassroomController::class, 'removeStudent'])->name('classrooms.remove-student')->middleware('role:coordenador|teacher');
            });

            // Teacher View
            Route::prefix('teacher/classrooms')->middleware('role:teacher')->group(function () {
                Route::get('/', [TeacherClassroomController::class, 'myClassrooms'])->name('teacher.classrooms');
                Route::get('{id}/progress', [TeacherClassroomController::class, 'studentsProgress'])->name('teacher.classrooms.progress');
                Route::get('{id}/evaluations', [TeacherClassroomController::class, 'evaluationQueue'])->name('teacher.classrooms.evaluations');
                Route::post('{id}/missions', [TeacherClassroomController::class, 'assignMission'])->name('teacher.classrooms.missions');
                Route::post('{id}/invite-student', [TeacherClassroomController::class, 'inviteStudent'])->name('teacher.classrooms.invite-student');
            });

            // Mission Evaluation (Professor aprova/rejeita)
            Route::prefix('evaluations')->middleware('role:teacher')->group(function () {
                Route::post('{progressId}/approve', [MissionEvaluationController::class, 'approve'])->name('evaluations.approve');
                Route::post('{progressId}/reject', [MissionEvaluationController::class, 'reject'])->name('evaluations.reject');
            });

            // Challenges (Inscrição protegida por Subscrição Ativa)
            Route::prefix('challenges')->middleware('role:coordenador|teacher')->group(function () {
                Route::get('/', [ChallengeController::class, 'index'])->name('challenges.index');
                Route::post('enroll', [ChallengeController::class, 'enroll'])->name('challenges.enroll')->middleware('subscribed.tenant');
                Route::get('{id}/leaderboard', [ChallengeController::class, 'leaderboard'])->name('challenges.leaderboard');
            });

            // Relatórios para Coordenador (Protegidos por Subscrição Ativa)
            Route::prefix('reports')->middleware(['role:coordinator|coordenador|admin', 'subscribed.tenant'])->group(function () {
                Route::get('teacher-activity', [ReportController::class, 'teacherActivity'])->name('b2b.reports.teacher-activity');
                Route::get('milestones', [ReportController::class, 'milestones'])->name('b2b.reports.milestones');
            });
        });

        // ====================================================================
        // PARTNER (SPONSOR) DASHBOARD, FINANCIAMENTO B2B, PROGRAMAS & DESAFIOS
        // ====================================================================
        Route::prefix('partner')->middleware('role:partner|admin')->group(function () {
            Route::get('dashboard', [PartnerDashboardController::class, 'index'])->name('partner.dashboard');
            Route::get('wallet', [PartnerWalletController::class, 'show'])->name('partner.wallet.show');
            Route::post('wallet/top-up', [PartnerWalletController::class, 'topUp'])->name('partner.wallet.top-up');

            // Programas (Campanhas Guarda-chuva com orçamento cativo)
            Route::prefix('programs')->group(function () {
                Route::get('/', [PartnerProgramController::class, 'index'])->name('partner.programs.index');
                Route::post('/', [PartnerProgramController::class, 'store'])->name('partner.programs.store');
                Route::get('{id}', [PartnerProgramController::class, 'show'])->name('partner.programs.show');
                Route::patch('{id}', [PartnerProgramController::class, 'update'])->name('partner.programs.update');
            });

            // Desafios Institucionais criados pelo Parceiro
            Route::prefix('challenges')->group(function () {
                Route::get('/', [PartnerChallengeController::class, 'index'])->name('partner.challenges.index');
                Route::post('/', [PartnerChallengeController::class, 'store'])->name('partner.challenges.store');
                Route::get('{id}', [PartnerChallengeController::class, 'show'])->name('partner.challenges.show');
                Route::patch('{id}', [PartnerChallengeController::class, 'update'])->name('partner.challenges.update');
                Route::delete('{id}', [PartnerChallengeController::class, 'destroy'])->name('partner.challenges.destroy');
            });

            // Relatórios Analíticos & Exportação de Impacto (Zero PII)
            Route::prefix('reports')->group(function () {
                Route::get('metrics', [PartnerReportController::class, 'metrics'])->name('partner.reports.metrics');
                Route::get('chart-data', [PartnerReportController::class, 'chartData'])->name('partner.reports.chart-data');
                Route::get('export', [PartnerReportController::class, 'export'])->name('partner.reports.export');
            });
        });

        // ====================================================================
        // SCHOOL — Gestão e Relatórios Escolares (Coordenador & Professor)
        // ====================================================================
        Route::prefix('school')->group(function () {
            // Dashboard Analítico da Escola
            Route::get('dashboard', [SchoolDashboardController::class, 'index'])->name('school.dashboard');

            // Gestão de Alunos da Escola
            Route::prefix('students')->middleware('role:coordinator|coordenador|teacher|admin')->group(function () {
                Route::get('/', [SchoolStudentController::class, 'index'])->name('school.students.index');
                Route::delete('{id}', [SchoolStudentController::class, 'destroy'])->name('school.students.destroy');
            });

            // Gestão de Professores & Permissões
            Route::get('teachers', [B2BStaffController::class, 'index'])->name('school.teachers.index');
            Route::post('staff/invite', [B2BStaffController::class, 'invite'])->name('school.staff.invite');
            Route::get('permissions/teachers', [B2BStaffController::class, 'index'])->name('school.permissions.teachers');
            Route::patch('teacher-permissions/{id}', [B2BStaffController::class, 'updatePermissions'])->name('school.teacher-permissions.update');

            // Turmas & Estatísticas da Escola
            Route::get('classrooms', [ClassroomController::class, 'index'])->name('school.classrooms.index');
            Route::post('classrooms', [ClassroomController::class, 'store'])->name('school.classrooms.store');
            Route::get('stats', [ClassroomController::class, 'stats'])->name('school.stats');

            // Relatórios e ROI Pedagógico
            Route::get('reports', [ReportController::class, 'reports'])->name('school.reports.index');

            // Desafios Coletivos da Escola
            Route::prefix('challenges')->middleware('role:coordinator|coordenador|teacher|admin')->group(function () {
                Route::get('/', [ChallengeController::class, 'index'])->name('school.challenges.index');
                Route::post('/', [ChallengeController::class, 'store'])->name('school.challenges.store')->middleware('subscribed.tenant');
                Route::get('{id}/leaderboard', [ChallengeController::class, 'leaderboard'])->name('school.challenges.leaderboard');
                Route::patch('{id}', [ChallengeController::class, 'update'])->name('school.challenges.update');
                Route::delete('{id}', [ChallengeController::class, 'destroy'])->name('school.challenges.destroy');
            });

            // Perfil Institucional da Escola
            Route::get('profile', [SchoolProfileController::class, 'show'])->name('school.profile.show');
            Route::match(['put', 'patch'], 'profile', [SchoolProfileController::class, 'update'])->name('school.profile.update');

            // Gestão de Subscrição da Escola (Coordenador)
            Route::prefix('subscription')->middleware('role:coordinator|coordenador|admin')->group(function () {
                Route::get('/', [B2BSubscriptionController::class, 'index'])->name('school.subscription.index');
                Route::post('checkout', [B2BSubscriptionController::class, 'checkout'])->name('school.subscription.checkout');
            });

            // Relatórios Avançados para Coordenador (Protegidos por Subscrição Ativa)
            Route::middleware(['role:coordinator|coordenador|admin', 'subscribed.tenant'])->group(function () {
                Route::get('teacher-activity', [ReportController::class, 'teacherActivity'])->name('school.teacher-activity');
                Route::get('milestones', [ReportController::class, 'milestones'])->name('school.milestones');
            });

            // Fila de Avaliações e Decisão do Professor
            Route::middleware('role:teacher|coordinator|coordenador|admin')->group(function () {
                Route::get('classrooms/{id}/pending-evaluations', [MissionEvaluationController::class, 'pendingEvaluations'])->name('school.classrooms.pending-evaluations');
                Route::post('evaluations/{progressId}', [SchoolEvaluationController::class, 'evaluate'])->name('school.evaluations.evaluate');
            });

            // Catálogo de Recompensas Institucionais da Escola
            Route::prefix('rewards')->group(function () {
                Route::get('/', [SchoolRewardController::class, 'index'])->name('school.rewards.index');
                Route::post('/', [SchoolRewardController::class, 'store'])->name('school.rewards.store')->middleware('role:coordinator|coordenador|admin');
                Route::get('{id}', [SchoolRewardController::class, 'show'])->name('school.rewards.show');
                Route::match(['put', 'patch'], '{id}', [SchoolRewardController::class, 'update'])->name('school.rewards.update')->middleware('role:coordinator|coordenador|admin');
                Route::delete('{id}', [SchoolRewardController::class, 'destroy'])->name('school.rewards.destroy')->middleware('role:coordinator|coordenador|admin');
            });

            // Submissão de provas de missões pedagógicas (Alunos)
            Route::prefix('evaluations')->middleware('role:teen|yang')->group(function () {
                Route::post('{progressId}/submit', [MissionEvaluationController::class, 'submit'])->name('school.evaluations.submit');
            });
        });

        // ====================================================================
        // TEACHER — Portal e Gestão Pedagógica do Professor
        // ====================================================================
        Route::prefix('teacher')->middleware('role:teacher|coordinator|coordenador|admin')->group(function () {
            Route::get('classrooms/{id}/lessons', [ClassroomController::class, 'lessons'])->name('teacher.classrooms.lessons');
            Route::post('classrooms/{id}/lessons', [ClassroomController::class, 'assignLesson'])->name('teacher.classrooms.assign-lesson');
            Route::get('classrooms/{id}/pending-evaluations', [MissionEvaluationController::class, 'pendingEvaluations'])->name('teacher.classrooms.pending-evaluations');
            Route::post('evaluations/{progressId}', [SchoolEvaluationController::class, 'evaluate'])->name('teacher.evaluations.evaluate');
        });

        // ====================================================================
        // PARENT — Gestão Familiar e Parent Gate
        // ====================================================================
        Route::prefix('parent')->group(function () {
            Route::get('children/{id}/summary', [ChildProfileController::class, 'summary'])->name('parent.children.summary');
            Route::get('tasks', [TaskController::class, 'index'])->name('parent.tasks.index');
            Route::post('tasks', [TaskController::class, 'store'])->name('parent.tasks.store');
            Route::post('tasks/{id}/approve', [TaskController::class, 'approve'])->name('parent.tasks.approve');
            Route::post('allowances/{id}/send-now', [AllowanceController::class, 'sendNow'])->name('parent.allowances.send-now');
        });

        // ====================================================================
        // GESTÃO DE TRILHAS & LIÇÕES (API Contract-First)
        // ====================================================================
        Route::prefix('trails')->group(function () {
            Route::get('/', [TrailController::class, 'index'])->name('trails.index');
            Route::post('/', [TrailController::class, 'store'])->name('trails.store');
            Route::patch('{id}', [TrailController::class, 'update'])->name('trails.update');
            Route::post('{id}/publish', [TrailController::class, 'publish'])->name('trails.publish');
            Route::post('{id}/version', [TrailController::class, 'version'])->name('trails.version');
        });

        Route::prefix('lessons')->group(function () {
            Route::post('{id}/submit-review', [TrailController::class, 'submitLessonReview'])->name('lessons.submit-review');
            Route::post('{id}/approve', [TrailController::class, 'approveLesson'])->name('lessons.approve');
            Route::delete('progress/{studentProfileId}/{lessonId}', [ClassroomController::class, 'resetProgress'])->name('lessons.progress.reset');
        });

        // Uploads & Mídias
        Route::post('media', [MediaUploadController::class, 'store'])->name('media.upload');
        Route::post('profiles/avatar', [MediaUploadController::class, 'avatar'])->name('profiles.avatar');

        // ====================================================================
        // GESTÃO DE TURMAS (Root Alias para Frontend)
        // ====================================================================
        Route::prefix('classrooms')->middleware('role:coordinator|coordenador|teacher|admin')->group(function () {
            Route::get('/', [ClassroomController::class, 'index'])->name('root.classrooms.index');
            Route::post('/', [ClassroomController::class, 'store'])->name('root.classrooms.store');
            Route::get('{id}', [ClassroomController::class, 'show'])->name('root.classrooms.show');
            Route::match(['put', 'patch'], '{id}', [ClassroomController::class, 'update'])->name('root.classrooms.update');
            Route::delete('{id}', [ClassroomController::class, 'destroy'])->name('root.classrooms.destroy');
            Route::get('{id}/lessons', [ClassroomController::class, 'lessons'])->name('root.classrooms.lessons');
            Route::post('{id}/lessons', [ClassroomController::class, 'assignLesson'])->name('root.classrooms.assign-lesson');
            Route::delete('{id}/lessons/{lessonId}', [ClassroomController::class, 'removeLesson'])->name('root.classrooms.remove-lesson');
            Route::get('{id}/stats', [ClassroomController::class, 'stats'])->name('root.classrooms.stats');
            Route::post('{classroomId}/students/{profileId}', [SchoolStudentController::class, 'assignToClassroom'])->name('root.classrooms.assign-student');
            Route::delete('{classroomId}/students/{profileId}', [SchoolStudentController::class, 'removeFromClassroom'])->name('root.classrooms.remove-student');
        });

        // ====================================================================
        // GESTÃO INSTITUCIONAL DE TENANTS
        // ====================================================================
        Route::prefix('tenants')->group(function () {
            Route::get('{id}', [SchoolProfileController::class, 'show'])->name('tenants.show');
            Route::match(['put', 'patch'], '{id}', [SchoolProfileController::class, 'update'])->name('tenants.update');
            Route::post('{id}/logo', [SchoolProfileController::class, 'uploadLogo'])->name('tenants.upload-logo');
        });

        // ====================================================================
        // GESTÃO DE CRIANÇAS E PARENT GATE (KIVARA Parent App)
        // ====================================================================
        Route::prefix('children')->group(function () {
            Route::get('/', [ChildProfileController::class, 'index'])->name('children.index');
            Route::post('/', [ChildProfileController::class, 'store'])->name('children.store');
            Route::get('{id}', [ChildProfileController::class, 'show'])->name('children.show');
            Route::get('{id}/summary', [ChildProfileController::class, 'summary'])->name('children.summary');
            Route::put('{id}', [ChildProfileController::class, 'update'])->name('children.update');
            Route::patch('{id}', [ChildProfileController::class, 'update'])->name('children.update_patch');
            Route::delete('{id}', [ChildProfileController::class, 'destroy'])->name('children.destroy');
            Route::post('{id}/upgrade-auth', [ChildProfileController::class, 'upgradeAuth'])->name('children.upgrade-auth');
        });

        Route::prefix('allowances')->group(function () {
            Route::post('{id}/send-now', [AllowanceController::class, 'sendNow'])->name('allowances.send-now');
        });

        Route::prefix('tasks')->group(function () {
            Route::get('household', [TaskController::class, 'index'])->name('tasks.household');
            Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
            Route::post('suggest', [TaskController::class, 'suggest'])->name('tasks.suggest');
            Route::put('{id}', [TaskController::class, 'update'])->name('tasks.update');
            Route::patch('{id}', [TaskController::class, 'update'])->name('tasks.update_patch');
            Route::delete('{id}', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::post('{id}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
            Route::post('{id}/reject', [TaskController::class, 'reject'])->name('tasks.reject');
        });

        Route::prefix('missions')->group(function () {
            Route::get('/', [MissionController::class, 'index'])->name('missions.index');
            Route::post('/', [MissionController::class, 'store'])->name('missions.store');
        });

        Route::prefix('rewards')->group(function () {
            Route::get('/', [RewardController::class, 'index'])->name('rewards.index');
            Route::post('/', [RewardController::class, 'store'])->name('rewards.store');
            Route::post('suggest', [RewardController::class, 'suggest'])->name('rewards.suggest');
            Route::get('{id}', [RewardController::class, 'show'])->name('rewards.show');
            Route::match(['put', 'patch'], '{id}', [RewardController::class, 'update'])->name('rewards.update');
            Route::delete('{id}', [RewardController::class, 'destroy'])->name('rewards.destroy');
            Route::post('{id}/claim', [RewardController::class, 'claim'])->name('rewards.claim');
        });

        // Root aliases para Vaults e Allowance Configs
        Route::prefix('vaults')->group(function () {
            Route::get('/', [VaultController::class, 'index'])->name('root.vaults.index');
            Route::post('/', [VaultController::class, 'store'])->name('root.vaults.store');
            Route::get('{id}', [VaultController::class, 'show'])->name('root.vaults.show');
            Route::match(['put', 'patch'], '{id}', [VaultController::class, 'update'])->name('root.vaults.update');
            Route::delete('{id}', [VaultController::class, 'destroy'])->name('root.vaults.destroy');
            Route::post('{id}/deposit', [VaultController::class, 'deposit'])->name('root.vaults.deposit');
            Route::post('{id}/withdraw', [VaultController::class, 'withdraw'])->name('root.vaults.withdraw');
            Route::post('{id}/break', [VaultController::class, 'withdraw'])->name('root.vaults.break');
        });

        Route::prefix('allowance-configs')->group(function () {
            Route::get('/', [AllowanceController::class, 'index'])->name('root.allowance-configs.index');
            Route::post('/', [AllowanceController::class, 'store'])->name('root.allowance-configs.store');
            Route::get('{id}', [AllowanceController::class, 'show'])->name('root.allowance-configs.show');
            Route::match(['put', 'patch'], '{id}', [AllowanceController::class, 'update'])->name('root.allowance-configs.update');
            Route::delete('{id}', [AllowanceController::class, 'destroy'])->name('root.allowance-configs.destroy');
            Route::post('{id}/process', [AllowanceController::class, 'process'])->name('root.allowance-configs.process');
        });

        Route::prefix('insights')->group(function () {
            Route::post('generate', [InsightController::class, 'generate'])->name('insights.generate');
        });

        Route::prefix('subscription')->group(function () {
            Route::get('tiers', [SubscriptionController::class, 'tiers'])->name('subscription.tiers');
            Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
            Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
            Route::get('invoices', [SubscriptionController::class, 'invoices'])->name('subscription.invoices');
        });

        Route::prefix('consents')->group(function () {
            Route::get('{childProfileId}', [ConsentController::class, 'index'])->name('consents.index');
            Route::post('{childProfileId}/update', [ConsentController::class, 'update'])->name('consents.update');
        });

        // ====================================================================
        // FAMILY PAIRING (Teen/Yang ↔ Parent)
        // ====================================================================
        Route::prefix('family')->group(function () {
            Route::post('generate-code', [FamilyPairingController::class, 'generateCode'])
                ->middleware('ability:role:teen,role:yang')
                ->name('family.generate-code');
            Route::post('pair', [FamilyPairingController::class, 'pairWithTeen'])
                ->middleware('ability:role:parent')
                ->name('family.pair');
        });

        // ====================================================================
        // ADMIN - Gestão Global
        // ====================================================================
        Route::middleware(['ability:role:admin'])->prefix('admin')->group(function () {
            Route::apiResource('subscription-plans', AdminSubscriptionController::class);

            // Admin Roles
            Route::prefix('roles')->group(function () {
                Route::get('/', [AdminRoleController::class, 'index'])->name('admin.roles.index');
                Route::post('{id}/sync', [AdminRoleController::class, 'sync'])->name('admin.roles.sync');
            });


            // reorder ANTES do apiResource para não colidir com lessons/{lesson}
            Route::post('lessons/reorder', [AdminLessonController::class, 'reorder'])
                ->name('admin.lessons.reorder');
            Route::apiResource('lessons', AdminLessonController::class);

            Route::get('audit-logs', [AdminAuditController::class, 'index'])
                ->name('admin.audit-logs.index');

            Route::get('risk-flags', [AdminRiskController::class, 'index'])
                ->name('admin.risk-flags.index');
            Route::patch('risk-flags/{riskFlag}', [AdminRiskController::class, 'update'])
                ->name('admin.risk-flags.update');

            Route::get('compliance/stats', [AdminComplianceController::class, 'stats'])
                ->name('admin.compliance.stats');

            Route::post('login-banners/reorder', [AdminBannerController::class, 'reorder'])
                ->name('admin.login-banners.reorder');
            Route::apiResource('login-banners', AdminBannerController::class);

            Route::post('notifications/send', [AdminNotificationController::class, 'send'])
                ->name('admin.notifications.send');

            Route::get('onboarding-analytics', [AdminOnboardingController::class, 'stats'])
                ->name('admin.onboarding-analytics');
        });
    });

    // ========================================================================
    // PUBLIC ENDPOINTS (No Authentication Required)
    // ========================================================================
    
    // Currencies
    Route::get('currencies', [\App\Http\Controllers\Api\CurrencyController::class, 'index'])->name('currencies.index');
    
    // Tenants (Public list for school registration dropdowns, etc)
    Route::get('tenants', function (\Illuminate\Http\Request $request) {
        // Mock tenants or return from DB if Tenant model exists
        // Since we don't know the exact schema, returning a basic mock to satisfy the frontend
        return response()->json([
            'status' => 'success',
            'data' => [
                ['id' => '1', 'name' => 'Colégio de Teste', 'type' => 'school']
            ]
        ]);
    })->name('tenants.public');

    // B2B Public Endpoints
    Route::prefix('b2b')->group(function () {
        Route::prefix('school-applications')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\School\SchoolApplicationController::class, 'store'])->name('school-applications.store');
        });
    });
    
    // Alias for school-applications without b2b prefix (as used by frontend)
    Route::post('school-applications', [\App\Http\Controllers\Api\School\SchoolApplicationController::class, 'store'])->name('school-applications.store.alias');

    // Webhook Multicaixa Express (liquidação de top-up)
    Route::post('finance/wallet/webhooks/multicaixa', [WalletController::class, 'settleTopUp'])
        ->name('wallet.webhook.multicaixa');

    // Webhook Multicaixa / Gateway (liquidação de subscrições B2B)
    Route::post('school/subscription/webhook', [PaymentWebhookController::class, 'handle'])
        ->name('school.subscription.webhook');

    // Webhook Multicaixa / Gateway (liquidação de carregamentos B2B de Parceiros)
    Route::post('partner/wallet/webhook', [PartnerWalletController::class, 'webhook'])
        ->name('partner.wallet.webhook');
});
