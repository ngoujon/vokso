<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryPageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LlmsController;
use App\Http\Controllers\N8nController;
use App\Http\Controllers\PodcastPageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Toutes les routes sont servies sous /api (Alias Apache vers public/).
 * Les chemins historiques (auth-login, user-podcasts...) sont conservés : le
 * front et la vitrine les appellent tels quels.
 */

// --- Public -----------------------------------------------------------------
Route::get('/', [HomeController::class, 'index']);
Route::get('home', [HomeController::class, 'index']);
Route::post('ping', [HomeController::class, 'ping']);

Route::get('listing', [CatalogController::class, 'latest']);
Route::get('search', [CatalogController::class, 'search']);
Route::get('categories', [CatalogController::class, 'categories']);
// Génération ouverte sans compte (plafonds anti-abus : GenerationLimits).
Route::post('generation', [GenerationController::class, 'generateText']);
Route::post('generation-audio', [GenerationController::class, 'generateFromAudio']);
Route::get('generation-status', [GenerationController::class, 'status']);

Route::get('contact-challenge', [ContactController::class, 'challenge']);
Route::post('contact', [ContactController::class, 'send']);
Route::post('newsletter', [ContactController::class, 'subscribeNewsletter']);

Route::post('auth-login', [AuthController::class, 'login']);
Route::post('auth-logout', [AuthController::class, 'logout']);

// Workflow n8n (même serveur) : IP autorisée + jeton secret, voir EnsureN8nCaller.
Route::middleware('n8n')->group(function () {
    Route::post('n8n/podcasts', [N8nController::class, 'store']);
    Route::get('n8n/podcasts/{jobId}', [N8nController::class, 'show'])->where('jobId', 'job_[a-f0-9]+');
});

// Pages rendues côté serveur (SEO), réécrites par site/.htaccess.
Route::get('podcast/{path}', [PodcastPageController::class, 'show'])->where('path', '.*');
Route::get('discotheque/{slug}', [CategoryPageController::class, 'show'])->where('slug', '[a-z0-9-]+');
Route::get('llms-full', [LlmsController::class, 'full']);
Route::get('llms-full.txt', [LlmsController::class, 'full']);
Route::get('sitemap', [SitemapController::class, 'show']);
// Réécritures [PT] de site/.htaccess : Laravel voit alors le chemin d'origine.
Route::get('sitemap.xml', [SitemapController::class, 'show']);

// --- Connecté (y compris avec un changement de mot de passe en attente) -----
Route::middleware('auth.token')->group(function () {
    Route::get('auth-me', [AuthController::class, 'me']);
    Route::post('auth-change-password', [AuthController::class, 'changePassword']);
    Route::get('gdpr-export', [GdprController::class, 'export']);
    Route::post('gdpr-delete-account', [GdprController::class, 'deleteAccount']);

    // --- Connecté, mot de passe à jour ----------------------------------------
    Route::middleware('password.changed')->group(function () {
        Route::get('user-podcasts', [UserController::class, 'podcasts']);

        // --- Administration ---------------------------------------------------
        Route::middleware('admin')->group(function () {
            Route::post('auth-2fa-setup', [AuthController::class, 'twoFactorSetup']);
            Route::post('auth-2fa-enable', [AuthController::class, 'twoFactorEnable']);
            Route::post('auth-2fa-disable', [AuthController::class, 'twoFactorDisable']);

            Route::get('admin-kpis', [AdminController::class, 'kpis']);
            Route::get('admin-users', [AdminController::class, 'users']);
            Route::post('admin-users', [AdminController::class, 'createUser']);
            Route::patch('admin-users', [AdminController::class, 'updateUser']);
            Route::get('admin-podcasts', [AdminController::class, 'podcasts']);
        });
    });
});
