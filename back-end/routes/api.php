<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TypeController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\ComposantController;
use App\Http\Controllers\Api\DemandeController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques (sans authentification)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Routes protégées (avec authentification Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // === AUTHENTIFICATION ===
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/refresh', [AuthController::class, 'refreshToken']);
    });

    // === DASHBOARD ===
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/statistiques-rapides', [DashboardController::class, 'statistiquesRapides']);
        Route::get('/alertes-importantes', [DashboardController::class, 'alertesImportantes']);
        Route::get('/activites', [DashboardController::class, 'getActivitesRecentes']);
        Route::get('/evolution', [DashboardController::class, 'getGraphiqueEvolution']);
        Route::get('/resume', [DashboardController::class, 'getResume']);
    });

    // === TYPES ===
    Route::prefix('types')->group(function () {
        Route::get('/', [TypeController::class, 'index']);
        Route::get('/actifs', [TypeController::class, 'getActifs']);
        Route::get('/statistiques', [TypeController::class, 'statistiques']);
        Route::get('/{id}', [TypeController::class, 'show']);
        
        // Routes admin uniquement
        Route::middleware('role:admin')->group(function () {
            Route::post('/', [TypeController::class, 'store']);
            Route::put('/{id}', [TypeController::class, 'update']);
            Route::delete('/{id}', [TypeController::class, 'destroy']);
            Route::patch('/{id}/toggle-actif', [TypeController::class, 'toggleActif']);
        });
    });

    // === MACHINES ===
    Route::prefix('machines')->group(function () {
        Route::get('/', [MachineController::class, 'index']);
        Route::get('/actives', [MachineController::class, 'getActives']);
        Route::get('/statistiques', [MachineController::class, 'statistiques']);
        Route::get('/{id}', [MachineController::class, 'show']);
        Route::get('/{id}/composants', [MachineController::class, 'getComposants']);
        Route::get('/{id}/demandes', [MachineController::class, 'getDemandes']);
        
        // Routes admin uniquement
        Route::middleware('role:admin')->group(function () {
            Route::post('/', [MachineController::class, 'store']);
            Route::put('/{id}', [MachineController::class, 'update']);
            Route::delete('/{id}', [MachineController::class, 'destroy']);
            Route::patch('/{id}/statut', [MachineController::class, 'updateStatut']);
            Route::patch('/{id}/maintenance', [MachineController::class, 'updateMaintenance']);
        });
    });

    // === COMPOSANTS ===
    Route::prefix('composants')->group(function () {
        Route::get('/', [ComposantController::class, 'index']);
        Route::get('/statistiques', [ComposantController::class, 'statistiques']);
        Route::get('/defaillants', [ComposantController::class, 'getDefaillants']);
        Route::get('/a-inspecter', [ComposantController::class, 'getAInspecter']);
        Route::get('/{id}', [ComposantController::class, 'show']);
        
        // Routes admin uniquement
        Route::middleware('role:admin')->group(function () {
            Route::post('/', [ComposantController::class, 'store']);
            Route::put('/{id}', [ComposantController::class, 'update']);
            Route::delete('/{id}', [ComposantController::class, 'destroy']);
            Route::patch('/{id}/statut', [ComposantController::class, 'updateStatut']);
            Route::patch('/{id}/inspection', [ComposantController::class, 'updateInspection']);
        });
    });

    // === DEMANDES ===
    Route::prefix('demandes')->group(function () {
        Route::get('/', [DemandeController::class, 'index']);
        Route::post('/', [DemandeController::class, 'store']);
        Route::get('/statistiques', [DemandeController::class, 'statistiques']);
        Route::get('/mes-demandes', [DemandeController::class, 'getMesDemandes']);
        Route::get('/{id}', [DemandeController::class, 'show']);
        Route::put('/{id}', [DemandeController::class, 'update']);
        Route::delete('/{id}', [DemandeController::class, 'destroy']);
        
        // Routes admin uniquement
        Route::middleware('role:admin')->group(function () {
            Route::get('/en-attente', [DemandeController::class, 'getEnAttente']);
            Route::get('/urgentes', [DemandeController::class, 'getUrgentes']);
            Route::patch('/{id}/accepter', [DemandeController::class, 'accepter']);
            Route::patch('/{id}/refuser', [DemandeController::class, 'refuser']);
            Route::patch('/{id}/statut', [DemandeController::class, 'updateStatut']);
        });
    });

    // === NOTIFICATIONS ===
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/non-lues', [NotificationController::class, 'getNonLues']);
        Route::get('/recentes', [NotificationController::class, 'getRecentes']);
        Route::get('/count', [NotificationController::class, 'getCount']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::patch('/{id}/lue', [NotificationController::class, 'marquerCommeLue']);
        Route::patch('/{id}/non-lue', [NotificationController::class, 'marquerCommeNonLue']);
        Route::patch('/marquer-toutes-lues', [NotificationController::class, 'marquerToutesCommeLues']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/lues/supprimer', [NotificationController::class, 'supprimerLues']);
        
        // Routes admin uniquement
        Route::middleware('role:admin')->group(function () {
            Route::post('/', [NotificationController::class, 'creer']);
            Route::post('/diffuser', [NotificationController::class, 'diffuser']);
        });
    });
});

// Route de test pour vérifier l'API
Route::get('/test', function () {
    return response()->json([
        'message' => 'API TELSOSPLICE TS3 - Fonctionnelle',
        'version' => '1.0.0',
        'timestamp' => now()
    ]);
});