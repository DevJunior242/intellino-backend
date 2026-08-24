<?php

use Illuminate\Http\Request;
use App\Models\KataController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\JuryController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\KataNotationController;
use App\Http\Controllers\KataTeamController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\MedalController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\CombatController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MandatController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NbJugeController;
use App\Http\Controllers\PodiumController;
use App\Http\Controllers\SaisonController;
use App\Http\Controllers\SeanceController;
use App\Http\Controllers\VuePubController;
use App\Http\Controllers\ArbitreController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\LicenceController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CandidatController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminClubController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EquipementController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\FederationController;
use App\Http\Controllers\SuperAdminOrganizationController;
use App\Http\Controllers\GovernanceController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ModeSaisieController;
use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\LicenceTypeController;
use App\Http\Controllers\PricingPlanController;
use App\Http\Controllers\ArbitreStatsController;
use App\Http\Controllers\Auth\SettingController;
use App\Http\Controllers\CombatActionController;
use App\Http\Controllers\EnchainementController;
use App\Http\Controllers\ExamenLeagueController;
use App\Http\Controllers\JudgeSessionController;
use App\Http\Controllers\KumiteFormatController;
use App\Http\Controllers\LeagueMemberController;
use App\Http\Controllers\FederationMemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrdrePassageController;
use App\Http\Controllers\StudentGradeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ActivationKeyController;
use App\Http\Controllers\AddManuelClubController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\LicenceleagueController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PlatformPaymentMethodController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SubDisciplineController;
use App\Http\Controllers\ConfigNotationController;
use App\Http\Controllers\StudentPaymentController;
use App\Http\Controllers\DashBoardLeagueController;
use App\Http\Controllers\PaymentCategoryController;
use App\Http\Controllers\RotationArbitreController;
use App\Http\Controllers\DisciplineConfigController;
use App\Http\Controllers\StageRegistrationController;
use App\Http\Controllers\Auth\AdminRegisterController;
use App\Http\Controllers\NiveauxCompetitionController;
use App\Http\Controllers\FederationDashboardController;


// Endpoints d'authentification : brute-force / spam d'inscription / bombardement d'emails.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/forgot-password', [ResetPasswordController::class, 'forgotPassword']);
    Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword']);
});

Route::get('/reset-password/{token}', [ResetPasswordController::class, 'sentToken'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('password.reset');

// Étape 2 du login quand la 2FA est active (voir LoginController::login) :
// l'utilisateur n'est pas encore authentifié, juste porteur du jeton
// temporaire reçu à l'étape 1, donc route publique elle aussi.
Route::middleware('throttle:5,1')->post('/2fa/challenge', [TwoFactorAuthController::class, 'challenge']);

// Lien de confirmation d'email envoyé à l'inscription (URL signée, pas besoin d'auth).
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('verification.verify');

// Vues publiques de compétition (rafraîchies via WebSocket, pas de polling) : limite large anti-flood.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('configs/{config}/vue-publique', [SeanceController::class, 'vuePublique']);
    Route::get('configs/{config}/next-athlete', [SeanceController::class, 'nextAthlete']);
    //public
    Route::get('/public/configs/{config}/combat-en-cours', [VuePubController::class, 'combatEnCours']);
    Route::get('/public/configs/{config}/next-combat', [VuePubController::class, 'nextCombat']);
    //podium
    Route::get('/podium/{config}', [PodiumController::class, 'obtenirPodium']);
    Route::get('/configs/{config}/bracket', [CombatController::class, 'bracket']);
});

// Page publique "Tarifs" : consultable sans être connecté.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/public/plans', [PlanController::class, 'getPlans']);
    Route::get('/public/annual-discount', [PlanController::class, 'getAnnualDiscount']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', [LoginController::class, 'me']);

    // Contrôle total du super admin sur les organisations (club/ligue/fédération)
    Route::middleware('superadmin')->group(function () {
        Route::get('/super-admin/organizations', [SuperAdminOrganizationController::class, 'overview']);
        Route::patch('/super-admin/organizations/{type}/{id}/toggle-status', [SuperAdminOrganizationController::class, 'toggleStatus'])
            ->middleware('throttle:20,1');

        // Clés d'activation : n'importe quel utilisateur authentifié pouvait générer/lister
        // des clés avant ce middleware — seule la route frontend était protégée.
        Route::apiResource('/activation-keys', ActivationKeyController::class);
    });

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    Route::post('/2fa/enable', [TwoFactorAuthController::class, 'enable']);
    Route::post('/2fa/confirm', [TwoFactorAuthController::class, 'confirm']);
    Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable']);
    Route::post('/2fa/recovery-codes/regenerate', [TwoFactorAuthController::class, 'regenerateRecoveryCodes']);

    //countries
    Route::apiResource('/countries', CountryController::class);

    //discipline 
    Route::apiResource('/disciplines', DisciplineController::class);
    //roles 

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
    //federations
    Route::apiResource('/federations', FederationController::class);



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




    Route::get('/arbitre/stats', [ArbitreStatsController::class, 'statsCarriere']);

    //league students 
    Route::prefix('league/students')->group(function () {
        Route::get('/', [LeagueController::class, 'getLeagueStudents']);
    });
    //examen leagues
    Route::prefix('examen-leagues')->group(function () {
        Route::apiResource('/examen-leagues', ExamenLeagueController::class);
    });




    //combats
    Route::post('/combats/{combat}/start-chrono', [CombatController::class, 'startChrono']);
    Route::post('/combats/{combat}/stop-chrono', [CombatController::class, 'stopChrono']);
    Route::post('/configs/{config}/lancer-kumite', [CombatController::class, 'lancerSeanceKumite']);
    Route::get('/configs/{config}/combat-en-cours', [CombatController::class, 'combatEnCours']);
    Route::get('/configs/{config}/next-combat', [CombatController::class, 'nextCombat']);
    Route::post('/configs/{config}/combat-suivant', [CombatController::class, 'combatSuivant']);
    // Actions
    Route::post('combat-actions', [CombatActionController::class, 'store']);
    Route::post('/combat-penalite', [CombatActionController::class, 'penalite']);
    Route::post('/config/{config}/get-judge-number', [JudgeSessionController::class, 'getJudgeNumber']);
    Route::get('/config/{config}/judges', [JudgeSessionController::class, 'getJudges']);

    Route::delete('/reset-judge/{config}/{juge_numero}', [JudgeSessionController::class, 'resetSpecificJudge']);
    Route::delete('/reset-all-judges/{config}', [JudgeSessionController::class, 'resetAllJudges']);

    //arbitre dispo
    Route::get('/arbitres/disponibles/{evenementId}', [ArbitreController::class, 'getDisponibles']);
    //rotation arbitres
    Route::post('/rotation-arbitres/assigner-poste', [RotationArbitreController::class, 'update']);
    Route::patch('rotation-arbitres/{config}/superviseur',         [RotationArbitreController::class, 'designerSuperviseur']);
    Route::patch('rotation-arbitres/{config}/superviseur/retirer', [RotationArbitreController::class, 'retirerSuperviseur']);

    Route::apiResource('/rotation-arbitres', RotationArbitreController::class);
    //katas
    Route::prefix('katas')->group(function () {
        Route::apiResource('/katas', KataController::class);
    });



    //student grade 
    Route::get('grade', [GradeController::class, 'index']);
    Route::apiResource('/users', UserController::class);
    //notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::prefix('leagues')->group(function () {
        Route::apiResource('/leagues', LeagueController::class);
    });
    Route::get('leagues/getLeaguesForAdmin', [LeagueController::class, 'getLeaguesForAdmin']);
    ///api/leagues/public-info/{leagueId}
    Route::get('leagues/public-info/{leagueId}', [LeagueController::class, 'getLeaguePublicInfo']);
    Route::post('/leagues/takeover', [MandatController::class, 'takeoverLeague']);

    Route::middleware('permission:instructeur,secretaire,admin,parent,karateka,super_admin,arbitre')->group(function () {

        //organisation active (club/ligue/fédération) : auto-édition par son admin
        Route::prefix('organisation')->group(function () {
            Route::get('/', [OrganisationController::class, 'show']);
            Route::post('/', [OrganisationController::class, 'update']);
            Route::post('/activate', [OrganisationController::class, 'activate']);
        });

        Route::get('/federation/dashboard-stats', [FederationDashboardController::class, 'getStats']);
        Route::get('/federation/clubs-par-ligue', [FederationDashboardController::class, 'clubsParLigue']);
        Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
        Route::patch('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
        Route::patch('/payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggleActive']);
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
        // --- Consultation publique au moment de payer un stage/une licence ---
        // Exemple d'appel : GET /api/payment-methods/Ligue/019ed526-af33-73ec-99c0-6791cb0ccee4
        Route::get('/payment-methods/{type}/{id}', [PaymentMethodController::class, 'pourOrganisateur']);

        // --- Transactions : unifie licences/affiliations/stages/examens ---
        // (anciennement LicencePaymentController/AffiliationPaymentController/
        // StagePaymentController/ExamenPaymentController, 4 systèmes séparés
        // fusionnés en un seul pour avoir une vraie vue "Comptabilité").
        Route::prefix('transactions')->group(function () {
            Route::get('/mes-lots', [TransactionController::class, 'mesLots']);
            Route::get('/mon-statut-affiliation', [TransactionController::class, 'monStatutAffiliation']);
            Route::get('/a-verifier', [TransactionController::class, 'paiementsAVerifier']);
            Route::get('/statistiques', [TransactionController::class, 'statistiques']);
            Route::get('/statistiques-mensuelles', [TransactionController::class, 'statistiquesMensuelles']);
            Route::get('/', [TransactionController::class, 'index']);
            Route::middleware(['throttle:15,1', 'verified'])->group(function () {
                Route::post('/{transaction}/declarer', [TransactionController::class, 'declarer']);
                Route::patch('/{transaction}/confirmer', [TransactionController::class, 'confirmer']);
                Route::patch('/{transaction}/rejeter', [TransactionController::class, 'rejeter']);
            });
        });
        //LicenceleagueController
        Route::get('/licences', [LicenceleagueController::class, 'index']);

        Route::get('/federation/ligues-clubs', [FederationController::class, 'mesLiguesEtClubs']);
        Route::get('/roles', [RoleController::class, 'getRoles']);
        //invitation
        Route::post('clubs/rejoindre-ligue', [InvitationController::class, 'rejoindreLigue']);
        Route::post('league/rejoindre-federation', [InvitationController::class, 'rejoindreFederation']);


        //seance config
        Route::prefix('seances')->group(function () {

            Route::post('configs/{config}/valider',          [SeanceController::class, 'valider']);
            Route::post('configs/{config}/lancer',            [SeanceController::class, 'lancerSeance']);
            Route::post('configs/{config}/athlete-suivant',  [SeanceController::class, 'athleteSuivant']);
            Route::post('configs/{config}/kiken',             [SeanceController::class, 'declarerKiken']);
            Route::post('configs/{config}/hantei',            [KataNotationController::class, 'forcerHantei']);
            Route::post('configs/{config}/disqualification-bunkai', [KataNotationController::class, 'declarerDisqualificationBunkai']);
            Route::get('configs/{config}/etat',              [SeanceController::class, 'etat']);
            Route::get('configs/{config}/pret', [SeanceController::class, 'estPret']);
            Route::get('competition/{config}/en-cours', [SeanceController::class, 'enCours']);
            Route::post('configs/{config}/connecter-tablette', [SeanceController::class, 'connecterTablette']);
            Route::get('configs/{config}/arbitres-rotation',    [SeanceController::class, 'arbitresRotation']);
        });
        // Athlètes d'un tatami

        Route::patch(
            'inscriptions/{inscription}/assigner-tatami',
            [InscriptionController::class, 'assignerTatami']
        );

        // Athlètes non assignés d'une compétition
        Route::get(
            'competitions/{competition}/inscriptions-non-assignees',
            [InscriptionController::class, 'nonAssignees']
        );

        Route::get(
            'configs/{config}/inscriptions',
            [InscriptionController::class, 'parTatami']
        );
        Route::prefix('kumite')->group(function () {
            Route::apiResource('/kumite-formats', KumiteFormatController::class);
        });

        //ordre passage
        Route::get('ordre-passages/{competitionId}/non-assign', [OrdrePassageController::class, 'getNonAssignees']);
        Route::post('ordre-passages/assigner', [OrdrePassageController::class, 'assigner']);
        Route::delete('ordre-passages/inscription/{inscriptionId}', [OrdrePassageController::class, 'retirer']);
        Route::get('ordre-passages/{configId}', [OrdrePassageController::class, 'index']);
        //notes
        Route::post('notes', [KataNotationController::class, 'store']);
        Route::post('notes/centralise', [KataNotationController::class, 'storeCentralise']);
        Route::get('inscriptions/{ordrePassage}/notes', [KataNotationController::class, 'notesInscription']);

        // Kata par équipe (Art. 3.5 WKF)
        Route::post('kata-teams', [KataTeamController::class, 'store'])
            ->middleware(['throttle:20,1', 'verified']);


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



        Route::apiResource('/saisons', SaisonController::class);

        // Paiements des stages : voir le groupe transactions?payable_type=stage
        // plus haut (mes-lots / a-verifier / declarer / confirmer / rejeter /
        // statistiques). GET /stages/{stage}/paiements équivaut désormais à
        // GET /transactions?payable_type=stage&payable_id={stage}.
        //stages
        Route::get('/stages/ma-ligue', [StageController::class, 'stagesDeMaLigue']);
        // routes/api.php
        Route::get('/stages/{stage}/mes-inscrits', [StageRegistrationController::class, 'mesInscriptionsDetail']);
        Route::delete('/inscriptions/{registration}', [StageRegistrationController::class, 'destroy']);
        Route::get('/mes-pratiquants', [StageRegistrationController::class, 'mesPratiquants']);
        Route::post('/stages/{stage}/inscriptions', [StageRegistrationController::class, 'store']);
        // Côté Ligue
        Route::get('/stages/{stage}/inscriptions', [StageRegistrationController::class, 'parStage']);
        Route::patch('/inscriptions/{registration}/presence', [StageRegistrationController::class, 'togglePresence']);
        Route::apiResource('/stages', StageController::class);

        //mandat 
        Route::prefix('mandat')->group(function () {
            Route::post('transfer-mandate', [MandatController::class, 'transferMandate']);
        });
        //setup
        Route::prefix('setup')->group(function () {
            Route::apiResource('/setup', SubDisciplineController::class);
        });
        //ajout membre league
        Route::prefix('membres')->group(function () {
            Route::apiResource('league', LeagueMemberController::class);
            //arbitres
            Route::get('arbitres', [LeagueMemberController::class, 'arbitres']);

            //ajout membre federation
            Route::apiResource('federation', FederationMemberController::class);
            Route::get('federation-arbitres', [FederationMemberController::class, 'arbitres']);
        });
        //AddManuelClubController
        Route::post('leagues/addClubManuel', [AddManuelClubController::class, 'store']);

        //ligues

        Route::get('leagues/myClubs', [LeagueController::class, 'myClubs']);

        Route::post('leagues/addClub/{clubId}', [LeagueController::class, 'addClub']);

        //categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/getCategories', [CategoryController::class, 'getCategories']);

        Route::apiResource('/disciplineLeague', DisciplineConfigController::class);

        //affiliations : tarif fixé par la fédération pour la saison
        Route::prefix('affiliations')->group(function () {
            Route::apiResource('/affiliations', AffiliationController::class);
        });

        // Suivi, club par club, du paiement de la cotisation d'affiliation :
        // voir le groupe transactions?payable_type=affiliation plus haut
        // (mon-statut-affiliation / index / declarer / confirmer / rejeter).

        //inscription 

        Route::post('inscriptions',                               [InscriptionController::class, 'store'])
            ->middleware(['throttle:20,1', 'verified']);
        // NB: "inscriptions/{inscription}" collides with the StageRegistrationController
        // delete route above, which is registered first and would otherwise swallow this one.
        Route::delete('inscriptions/desinscrire/{inscription}',   [InscriptionController::class, 'destroy']);
        //adin inscription index
        Route::get('/admin/inscriptions', [InscriptionController::class, 'index']);
        Route::prefix('inscriptions')->group(function () {
            Route::apiResource('/inscriptions', InscriptionController::class);
        });
        Route::post('inscriptions/annuler/{inscription}',         [InscriptionController::class, 'annuler'])
            ->middleware(['throttle:20,1', 'verified']);
        Route::post('inscriptions/valider/{inscription}',         [InscriptionController::class, 'valider'])
            ->middleware(['throttle:20,1', 'verified']);

        Route::get('/inscriptions/competition/{competitionId}', [InscriptionController::class, 'getByCompetition']);
        Route::get('evenements/ouverts',                          [InscriptionController::class, 'getEvenementsOuverts']);
        Route::get('inscriptions/epreuve/{competition}',          [InscriptionController::class, 'parEpreuve']);
        Route::get('inscriptions/epreuve/{competition}/disponibles', [InscriptionController::class, 'studentsDisponibles']);
        //evenements
        // throttle:20,1 : actions de création/changement d'état sujettes au double-clic (créer, ouvrir, clôturer, arbitrer)
        Route::prefix('evenements')->middleware(['throttle:20,1', 'verified'])->group(function () {
            //ouvrir et cloturer evenement
            Route::post('/ouvrir/{evenement}', [EvenementController::class, 'ouvrir']);
            Route::post('/cloturer/{evenement}', [EvenementController::class, 'cloturer']);
            //arbitrer
            Route::post('/arbitrer/{evenement}', [EvenementController::class, 'arbitrer']);
            Route::apiResource('/evenements', EvenementController::class);
            Route::get('/getEventActive', [EvenementController::class, 'getEventActive']);
        });
        //competitions

        Route::prefix('competitions')->middleware(['throttle:20,1', 'verified'])->group(function () {
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
        Route::patch('/plan/annual-discount', [PlanController::class, 'updateAnnualDiscount']);
        Route::patch('/plan/{plan}', [PlanController::class, 'updatePlan']);
        Route::delete('/plan/{plan}', [PlanController::class, 'destroyPlan']);

        Route::get('/club-exams', [ExamenLeagueController::class, 'getClubExams']);

        //subscriptions (abonnements Intellino : Club/Ligue/Fédération)
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index']);
            Route::post('/', [SubscriptionController::class, 'store'])
                ->middleware(['throttle:15,1', 'verified']);
            Route::post('/{subscription}/declarer', [SubscriptionController::class, 'declarer'])
                ->middleware(['throttle:15,1', 'verified']);
            Route::get('/paiements-a-verifier', [SubscriptionController::class, 'paiementsAVerifier']);
            Route::patch('/paiements/{subscriptionPayment}/confirmer', [SubscriptionController::class, 'confirmer']);
            Route::patch('/paiements/{subscriptionPayment}/rejeter', [SubscriptionController::class, 'rejeter']);
            Route::get('/statistiques', [SubscriptionController::class, 'statistiques']);
        });

        //moyens de paiement d'Intellino (pour recevoir les abonnements)
        Route::prefix('platform-payment-methods')->group(function () {
            Route::get('/', [PlatformPaymentMethodController::class, 'index']);
            Route::post('/', [PlatformPaymentMethodController::class, 'store']);
            Route::patch('/{platformPaymentMethod}', [PlatformPaymentMethodController::class, 'update']);
            Route::patch('/{platformPaymentMethod}/toggle', [PlatformPaymentMethodController::class, 'toggleActive']);
            Route::delete('/{platformPaymentMethod}', [PlatformPaymentMethodController::class, 'destroy']);
        });
        //course
        Route::post('/course/full-store', [CourseController::class, 'storeFullCourse']);
        Route::post('/course/storeSession', [CourseController::class, 'storeSession']);
        Route::get('/course/eligible-instructors', [CourseController::class, 'eligibleInstructorsList']);


        Route::get('/medals', [MedalController::class, 'getMedals']);
        Route::post('/medal/store', [MedalController::class, 'storeMedal']);


        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/activity', [DashboardController::class, 'getDashboardActivity']);
        Route::get('/attendances/chart', [AdminClubController::class, 'presence']);
        Route::get('/dashboard/club/alert', [AdminClubController::class, 'alerts']);


        //dashboard league
        Route::get('/dashboard/league/stats', [DashBoardLeagueController::class, 'stats']);
        Route::get('/dashboard/league/alert', [DashBoardLeagueController::class, 'Alert']);

        // --- Côté Fédération : gestion des types de licence ---
        Route::get('/licence-types', [LicenceTypeController::class, 'index']);
        Route::post('/licence-types', [LicenceTypeController::class, 'store']);
        Route::patch('/licence-types/{licenceType}', [LicenceTypeController::class, 'update']);
        Route::delete('/licence-types/{licenceType}', [LicenceTypeController::class, 'destroy']);

        // --- Côté Club : achat et consultation ---
        Route::get('/licence-types/{licenceType}/licencies', [LicenceTypeController::class, 'licencies']);
        Route::get('/licences/types-disponibles', [LicenceController::class, 'typesDisponibles']);
        Route::post('/licences', [LicenceController::class, 'store']);
        Route::get('/licences/mes-licences', [LicenceController::class, 'mesLicences']);
        Route::get('/licences/mes-etudiants', [LicenceController::class, 'mesEtudiants']);
        Route::get('/licences/mes-licences-etudiant', [LicenceController::class, 'mesLicencesEtudiant']);
        Route::delete('/licences/{licence}', [LicenceController::class, 'destroy']);

        Route::post('/grade/store', [GradeController::class, 'store']);

        Route::post('/student-grades/store', [StudentGradeController::class, 'store']);
        Route::get('/student-grades', [StudentGradeController::class, 'index']);
        Route::get('/student-grades/{studentGrade}', [StudentGradeController::class, 'show']);
        Route::get('/student-grades/{student}/history', [StudentGradeController::class, 'studentHistory']);
        //session 
        Route::get('/session', [SessionController::class, 'index']);


        //student 

        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/search', [StudentController::class, 'search']);
        Route::get('/students/mon-club', [StudentController::class, 'monClub']);
        Route::get('/students/mes-parents', [StudentController::class, 'mesParents']);
        Route::get('/students/without-grade', [StudentController::class, 'studentsWithoutGrade']);
        Route::get('/students/latest', [StudentController::class, 'latestStudent']);
        Route::get('/parents-users', [StudentController::class, 'getParent']);
        Route::post('/parent-eleven/store-multiple', [StudentController::class, 'store']);
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
            Route::get('/vitality-stats', [ExamenController::class, 'exmenstates']);
            Route::get('/mes-examens', [ExamenController::class, 'mesExamens']);
            Route::get('/disponibles-club', [ExamenController::class, 'examensDisponiblesClub']);

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
            Route::post('/batch/{examen}', [CandidatController::class, 'storeBatch'])
                ->middleware(['throttle:20,1', 'verified']);
            Route::get('/eligibles/{examen}', [CandidatController::class, 'candidatsEligibles']);
            Route::get('/{id}', [CandidatController::class, 'show']);
            Route::put('/{id}', [CandidatController::class, 'update']);
            Route::delete('/remove/{examen}/{examenCandidat}', [CandidatController::class, 'destroy']);
        });

        Route::prefix('evaluation')->group(function () {
            Route::get('/{examen}', [EvaluationController::class, 'index']);
            Route::post('/examen/{examen}/candidat/{candidat}', [EvaluationController::class, 'store']);
            Route::put('/examen/{examen}/candidat/{candidat}', [EvaluationController::class, 'update']);
        });

        //payment categories
        Route::prefix('payment-categories')->group(function () {
            Route::get('/', [PaymentCategoryController::class, 'index']);
            Route::post('/', [PaymentCategoryController::class, 'store']);
            Route::delete('/{id}', [PaymentCategoryController::class, 'destroy']);
        });

        //pricing plans
        Route::prefix('pricing-plans')->group(function () {
            Route::get('/', [PricingPlanController::class, 'index']);
            Route::post('/', [PricingPlanController::class, 'store']);
            Route::patch('/{id}/toggle', [PricingPlanController::class, 'toggle']);
            Route::delete('/{id}', [PricingPlanController::class, 'destroy']);
        });
        //paiments
        Route::prefix('payments')->group(function () {
            Route::get('/', [StudentPaymentController::class, 'index']);
            Route::get('/stats', [StudentPaymentController::class, 'revenueStats']);
            Route::post('/', [StudentPaymentController::class, 'store'])
                ->middleware(['throttle:15,1', 'verified']);
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
        Route::get('/programmes/activites', [ProgrammeController::class, 'activites']);
    });

    //pdf 
    Route::get('/examens/{examen}/notes/pdf', [PdfController::class, 'resultsPdf']);
});
