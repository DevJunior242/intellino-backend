<?php

use Illuminate\Http\Request;
use App\Models\KataController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\JuryController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\MedalController;
use App\Http\Controllers\CombatController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NbJugeController;
use App\Http\Controllers\SaisonController;
use App\Http\Controllers\SeanceController;
use App\Http\Controllers\ArbitreController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\LicenceController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CandidatController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminClubController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EquipementController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\FederationController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\ModeSaisieController;
use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\LeagueSetupController;
use App\Http\Controllers\PricingPlanController;
use App\Http\Controllers\AffiliationControllerr;
use App\Http\Controllers\ArbitreStatsController;
use App\Http\Controllers\Auth\SettingController;
use App\Http\Controllers\EnchainementController;
use App\Http\Controllers\ExamenLeagueController;
use App\Http\Controllers\KumiteFormatController;
use App\Http\Controllers\LeagueMemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrdrePassageController;
use App\Http\Controllers\StudentGradeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ConfigNotationController;
use App\Http\Controllers\StudentPaymentController;
use App\Http\Controllers\DashBoardLeagueController;
use App\Http\Controllers\PaymentCategoryController;
use App\Http\Controllers\RotationArbitreController;
use App\Http\Controllers\DisciplineConfigController;
use App\Http\Controllers\Auth\AdminRegisterController;
use App\Http\Controllers\NiveauxCompetitionController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::post('/forgot-password', [ResetPasswordController::class, 'forgotPassword']);
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'sentToken'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword']);
Route::get('configs/{config}/vue-publique', [SeanceController::class, 'vuePublique']);
Route::get('configs/{config}/next-athlete', [SeanceController::class, 'nextAthlete']);


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    //roles  leagues 
    Route::get('leagues/getRoles', [LeagueMemberController::class, 'getRoles']);

    //countries
    Route::apiResource('/countries', CountryController::class);

    //discipline 
    Route::apiResource('/disciplines', DisciplineController::class);
    //roles 
    Route::get('/roles', [RoleController::class, 'roles']);
    //register
    Route::post('/Admin/register', [AdminRegisterController::class, 'register']);
    //logout 
    Route::post('/logout', [LoginController::class, 'logout']);
    //user
    Route::get('/plans', [PlanController::class, 'getPlans']);
    //profile
    Route::prefix('setting')->group(function () {
        Route::post('/upload-photo', [SettingController::class, 'uploadPhoto']);
        Route::put('/profile', [SettingController::class, 'updateProfile']);
        Route::put('/password', [SettingController::class, 'updatePassword']);
        Route::delete('/account', [SettingController::class, 'deleteAccount']);
    });



    //clubs
    Route::prefix('clubs')->group(function () {
        Route::apiResource('/clubs', ClubController::class);
        Route::get('/getClubs', [ClubController::class, 'getClubs']);
        Route::get('/getClubsAvailable', [ClubController::class, 'getClubsAvailable']);
        Route::get('/count', [ClubController::class, 'count']);
    });

    //likes
    Route::post('/likes/{club}/store', [LikeController::class, 'store']);



    //contact
    Route::prefix('contact')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::post('/', [ContactController::class, 'store']);
        Route::get('/{id}', [ContactController::class, 'show']);
        Route::put('/{id}', [ContactController::class, 'update']);
        Route::delete('/{id}', [ContactController::class, 'destroy']);
    });

    //payment categories
    Route::prefix('payment-categories')->group(function () {
        Route::get('/', [PaymentCategoryController::class, 'index']);
    });



    Route::get('/arbitre/stats', [ArbitreStatsController::class, 'statsCarriere']);

    //league students 
    Route::prefix('league/students')->group(function () {
        Route::get('/', [LeagueController::class, 'getLeagueStudents']);
    });
    //examen leagues
    Route::prefix('examen-leagues')->group(function () {
        Route::apiResource('/examen-leagues', ExamenLeagueController::class);
    });


    //niveaux competitions
    Route::prefix('niveaux-competitions')->group(function () {
        Route::apiResource('/niveaux-competitions', NiveauxCompetitionController::class);
    });
    //modes saisie
    Route::prefix('modes-saisie')->group(function () {
        Route::apiResource('/modes-saisie', ModeSaisieController::class);
    });
    //nb juges
    Route::prefix('nb-juges')->group(function () {
        Route::apiResource('/nb-juges', NbJugeController::class);
    });
    //config notation
    Route::get('evenements/{evenementId}/plateaux', [ConfigNotationController::class, 'getPlateauxByEvenement']);


    Route::prefix('seances')->group(function () {

        Route::post('configs/{config}/valider',          [SeanceController::class, 'valider']);
        Route::post('configs/{config}/lancer',            [SeanceController::class, 'lancerSeance']);
        Route::post('configs/{config}/athlete-suivant',  [SeanceController::class, 'athleteSuivant']);
        Route::get('configs/{config}/etat',              [SeanceController::class, 'etat']);
        Route::get('configs/{config}/pret', [SeanceController::class, 'estPret']);
        Route::get('competition/{config}/en-cours', [SeanceController::class, 'enCours']);
        Route::post('configs/{config}/connecter-tablette', [SeanceController::class, 'connecterTablette']);
        Route::get('configs/{config}/arbitres-rotation',    [SeanceController::class, 'arbitresRotation']);
    });


    //arbitre dispo
    Route::get('/arbitres/disponibles/{evenementId}/{configId}', [ArbitreController::class, 'getDisponibles']);
    //rotation arbitres
    Route::post('/rotation-arbitres/assigner-poste', [RotationArbitreController::class, 'update']);
    Route::patch('rotation-arbitres/{config}/superviseur',         [RotationArbitreController::class, 'designerSuperviseur']);

    Route::apiResource('/rotation-arbitres', RotationArbitreController::class);
    //katas
    Route::prefix('katas')->group(function () {
        Route::apiResource('/katas', KataController::class);
    });
    //ordre passage
    Route::get('ordre-passages/{competitionId}/non-assign', [OrdrePassageController::class, 'getNonAssignees']);
    Route::post('ordre-passages/assigner', [OrdrePassageController::class, 'assigner']);
    Route::delete('ordre-passages/inscription/{inscriptionId}', [OrdrePassageController::class, 'retirer']);
    Route::get('ordre-passages/{configId}', [OrdrePassageController::class, 'index']);
    //notes
    Route::post('notes', [NoteController::class, 'store']);
    Route::post('notes/centralise', [NoteController::class, 'storeCentralise']);
    Route::get('inscriptions/{ordrePassage}/notes', [NoteController::class, 'notesInscription']);

    Route::patch(
        'inscriptions/{inscription}/assigner-tatami',
        [InscriptionController::class, 'assignerTatami']
    );

    // Athlètes non assignés d'une compétition
    Route::get(
        'competitions/{competition}/inscriptions-non-assignees',
        [InscriptionController::class, 'nonAssignees']
    );

    // Athlètes d'un tatami
    Route::get(
        'configs/{config}/inscriptions',
        [InscriptionController::class, 'parTatami']
    );
    Route::prefix('kumite')->group(function () {
        Route::apiResource('/kumite-formats', KumiteFormatController::class);
    });
    Route::get('configs/{config}/bracket', [CombatController::class, 'bracket']);


    //student grade 
    Route::get('grade', [GradeController::class, 'index']);
    Route::apiResource('/users', UserController::class);
    //notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::prefix('leagues')->group(function () {
        Route::apiResource('/leagues', LeagueController::class);
    });
    Route::middleware('permission:instructeur,secretaire,admin_club,parent,karateka,super_admin,admin_league,arbitre_league')->group(function () {
        Route::apiResource('/saisons', SaisonController::class);
        //setup
        Route::prefix('setup')->group(function () {
            Route::apiResource('/setup', LeagueSetupController::class);
        });
        //ajout membre league
        Route::prefix('membres')->group(function () {
            Route::apiResource('league', LeagueMemberController::class);
            //arbitres
            Route::get('arbitres', [LeagueMemberController::class, 'arbitres']);
        });

        //ligues
        Route::get('leagues/myClubs', [LeagueController::class, 'myClubs']);

        Route::post('leagues/addClub/{clubId}', [LeagueController::class, 'addClub']);
        Route::post('leagues/addClubManuel', [LeagueController::class, 'addClubManuel']);
        //licences
        Route::prefix('licences')->group(function () {
            Route::apiResource('/licences', LicenceController::class);
        });
        //categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/getCategories', [CategoryController::class, 'getCategories']);
        Route::post('/categories', [CategoryController::class, 'store']);

        Route::apiResource('/disciplineLeague', DisciplineConfigController::class);

        //affiliations
        Route::prefix('affiliations')->group(function () {
            Route::apiResource('/affiliations', AffiliationController::class);
        });

        //inscription 

        Route::post('inscriptions',                               [InscriptionController::class, 'store']);
        Route::delete('inscriptions/{inscription}',               [InscriptionController::class, 'destroy']);
        //adin inscription index
        Route::get('/admin/inscriptions', [InscriptionController::class, 'index']);
        Route::prefix('inscriptions')->group(function () {
            Route::apiResource('/inscriptions', InscriptionController::class);
        });
        Route::post('inscriptions/annuler/{inscription}',         [InscriptionController::class, 'annuler']);
        Route::post('inscriptions/valider/{inscription}',         [InscriptionController::class, 'valider']);

        Route::get('/inscriptions/competition/{competitionId}', [InscriptionController::class, 'getByCompetition']);
        Route::get('evenements/ouverts',                          [InscriptionController::class, 'getEvenementsOuverts']);
        Route::get('inscriptions/epreuve/{competition}',          [InscriptionController::class, 'parEpreuve']);
        //evenements
        Route::prefix('evenements')->group(function () {
            //ouvrir et cloturer evenement
            Route::post('/ouvrir/{evenement}', [EvenementController::class, 'ouvrir']);
            Route::post('/cloturer/{evenement}', [EvenementController::class, 'cloturer']);
            //arbitrer
            Route::post('/arbitrer/{evenement}', [EvenementController::class, 'arbitrer']);
            Route::apiResource('/evenements', EvenementController::class);
            Route::get('/getEventActive', [EvenementController::class, 'getEventActive']);
        });
        //competitions

        Route::prefix('competitions')->group(function () {
            Route::get('/getActiveCompetition', [CompetitionController::class, 'getActiveCompetition']);
            Route::get('/getCompetitions', [CompetitionController::class, 'getCompetitions']);
            Route::post('/ouvrir/{competition}', [CompetitionController::class, 'ouvrir']);
            Route::post('/cloturer/{competition}', [CompetitionController::class, 'cloturer']);
            Route::post('/arbitrer/{competition}', [CompetitionController::class, 'arbitrer']);
            Route::apiResource('/competitions', CompetitionController::class);
        });

        Route::prefix('config-notation')->group(function () {
            Route::apiResource('/config-notation', ConfigNotationController::class);
        });
        //plan controller
        Route::post('/plan/store', [PlanController::class, 'storePlan']);

        Route::get('/club-exams', [ExamenLeagueController::class, 'getClubExams']);

        //subscriptions 
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::get('/show', [SubscriptionController::class, 'show']);
            Route::put('/{id}', [SubscriptionController::class, 'update']);
            Route::delete('/{id}', [SubscriptionController::class, 'destroy']);
        });
        //course
        Route::post('/course/full-store', [CourseController::class, 'storeFullCourse']);
        Route::post('/course/storeSession', [CourseController::class, 'storeSession']);


        Route::get('/medals', [MedalController::class, 'getMedals']);
        Route::post('/medal/store', [MedalController::class, 'storeMedal']);


        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/activity', [DashboardController::class, 'getDashboardActivity']);
        Route::get('/attendances/chart', [\App\Http\Controllers\AdminClubController::class, 'presence']);
        //dashboard league
        Route::get('/dashboard/league/stats', [DashBoardLeagueController::class, 'stats']);
        Route::get('/dashboard/league/alert', [DashBoardLeagueController::class, 'Alert']);
        Route::get('/dashboard/league/exmenstates', [DashBoardLeagueController::class, 'exmenstates']);


        Route::post('/grade/store', [GradeController::class, 'store']);

        Route::post('/student-grades/store', [StudentGradeController::class, 'store']);
        Route::get('/student-grades', [StudentGradeController::class, 'index']);
        Route::get('/student-grades/{studentGrade}', [StudentGradeController::class, 'show']);
        Route::get('/student-grades/{student}/history', [StudentGradeController::class, 'studentHistory']);
        //session 
        Route::get('/session', [SessionController::class, 'index']);


        //student 

        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/without-grade', [StudentController::class, 'studentsWithoutGrade']);
        Route::get('/students/latest', [StudentController::class, 'latestStudent']);
        Route::get('/parents-users', [StudentController::class, 'getParent']);
        Route::post('/parent-eleven/store-multiple ', [StudentController::class, 'store']);
        Route::post('/student/parent', [StudentController::class, 'studentParent']);
        Route::post('/student/{student}', [StudentController::class, 'updateStudent']);
        Route::delete('/student/{student}', [StudentController::class, 'destroy']);
        //stats
        Route::get('/student-stats', [StudentController::class, 'StudentStatsDashboard']);
        //instructor/
        Route::get('/instructors', [InstructorController::class, 'getInstructor']);
        Route::post('/instructor/store', [InstructorController::class, 'storeInstructor']);

        //ajout membre club
        Route::prefix('members')->group(function () {
            Route::get('/', [MemberController::class, 'index']);
            Route::post('/', [MemberController::class, 'store']);
            Route::get('/{id}', [MemberController::class, 'show']);
            Route::post('/{user}', [MemberController::class, 'updateRole']);
            Route::delete('/{user}', [MemberController::class, 'deleteUser']);
        });
        Route::get('session/{sessionId}/students', [SessionController::class, 'students']);
        Route::post('session/{session}/cancel', [SessionController::class, 'cancel']);
        Route::post('session/{session}/reschedule', [SessionController::class, 'reschedule']);
        Route::post('session/{session}/start', [SessionController::class, 'startSession']);
        Route::post('session/{session}/stop', [SessionController::class, 'stopSession']);
        //course 
        Route::prefix('sessions')->group(function () {
            Route::get('/', [CourseController::class, 'index']);
            Route::post('/', [CourseController::class, 'store']);
            Route::get('/{session}/show', [CourseController::class, 'show']);
            Route::put('/{id}', [CourseController::class, 'update']);
            Route::delete('/{id}', [CourseController::class, 'destroy']);
            Route::get('session-stats', [SessionController::class, '__invoke']);
            Route::put('/edit/{session}', [SessionController::class, 'editSession']);
            Route::delete('remove/{session}', [SessionController::class, 'removeSession']);
        });


        Route::prefix('attendances')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::get('/students', [AttendanceController::class, 'getStudentAttendance']);
            Route::post('/bulk', [AttendanceController::class, 'bulkStore']);
            Route::get('/{id}', [AttendanceController::class, 'show']);
            Route::put('/{id}', [AttendanceController::class, 'update']);
            Route::delete('/{id}', [AttendanceController::class, 'destroy']);
        });

        //examen
        Route::prefix('examens')->group(function () {
            Route::get('/', [ExamenController::class, 'index']);
            Route::post('/', [ExamenController::class, 'store']);
            Route::get('/stats', [ExamenController::class, 'stats']);

            Route::get('/{examen}', [ExamenController::class, 'getExamenCandidats']);
            Route::get('/{examen}/show', [ExamenController::class, 'show']);

            Route::put('/{examen}', [ExamenController::class, 'update']);
            Route::delete('/{examen}', [ExamenController::class, 'destroy']);
            Route::post('/{examen}/cancel', [ExamenController::class, 'cancel']);
            Route::post('/{examen}/reschedule', [ExamenController::class, 'reschedule']);
            Route::post('/{examen}/start', [ExamenController::class, 'startExamen']);
            Route::post('/{examen}/stop', [ExamenController::class, 'stopExamen']);
        });

        //enchainement
        Route::prefix('enchainements')->group(function () {
            Route::get('/', [EnchainementController::class, 'index']);
            Route::post('/{examen}', [EnchainementController::class, 'store']);
            Route::get('/{examenId}', [EnchainementController::class, 'show']);
            Route::put('/{id}', [EnchainementController::class, 'update']);
            Route::delete('/{examen}/{id}', [EnchainementController::class, 'destroy']);
        });

        //candidat
        Route::prefix('candidats')->group(function () {
            Route::get('/', [CandidatController::class, 'index']);
            Route::post('/', [CandidatController::class, 'store']);
            Route::post('/add/{examen}/{student}', [CandidatController::class, 'addCandidate']);
            Route::get('/{id}', [CandidatController::class, 'show']);
            Route::put('/{id}', [CandidatController::class, 'update']);
            Route::delete('/remove/{examen}/{examenCandidat}', [CandidatController::class, 'destroy']);
        });
        Route::prefix('evaluation')->group(function () {
            Route::get('/{examen}', [EvaluationController::class, 'index']);
            Route::post('/examen/{examen}/candidat/{candidat}', [EvaluationController::class, 'store']);
            Route::put('/examen/{examen}/candidat/{candidat}', [EvaluationController::class, 'update']);
        });

        //pricing plans
        Route::prefix('pricing-plans')->group(function () {
            Route::get('/', [PricingPlanController::class, 'index']);
            Route::post('/', [PricingPlanController::class, 'store']);
            Route::delete('/{id}', [PricingPlanController::class, 'destroy']);
        });
        //paiments
        Route::prefix('payments')->group(function () {
            Route::get('/', [StudentPaymentController::class, 'index']);
            Route::get('/stats', [StudentPaymentController::class, 'revenueStats']);
            Route::post('/', [StudentPaymentController::class, 'store']);
            Route::delete('/{id}', [StudentPaymentController::class, 'destroy']);
            Route::get('/{id}/downloadInvoice', [StudentPaymentController::class, 'downloadInvoice']);
            Route::get('/statistiques', [StudentPaymentController::class, 'stats']);
            Route::get('/students/{student}/debt/{pricingPlan}', [StudentPaymentController::class, 'getRemainingDebt']);
            Route::get('/finance/debts', [StudentPaymentController::class, 'getDebtors']);
            Route::get('/students/debts', [StudentPaymentController::class, 'getParentDebts']);
        });
        //catalogue
        Route::prefix('inventory')->group(function () {

            // --- CATÉGORIES ---

            Route::get('/categories', [EquipementController::class, 'getCategories']);
            Route::post('/categories', [EquipementController::class, 'storeCategory']);

            // --- MATERIELS   pretes---
            Route::get('/prets', [EquipementController::class, 'getPret']);

            Route::get('/equipments', [EquipementController::class, 'index']);
            Route::post('/equipments', [EquipementController::class, 'store']);
            Route::delete('/equipments/{id}', [EquipementController::class, 'destroy']);


            Route::post('/transactions', [EquipementController::class, 'storeTransaction']);
            Route::get('/transactions', [EquipementController::class, 'transactionHistory']);


            Route::post('/loans', [EquipementController::class, 'loanEquipment']);
            Route::post('/loans/{equipmentLoan}/return', [EquipementController::class, 'returnEquipment']);
        });
        Route::get('/programmes', [ProgrammeController::class, 'index']);
    });

    //pdf 
    Route::get('/examens/{examen}/notes/pdf', [PdfController::class, 'resultsPdf']);
});
