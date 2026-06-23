<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InfoController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\PipelineStageController;
use App\Http\Controllers\Api\SavedViewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SegmentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Webhook\EmeliaWebhookController;
use App\Http\Controllers\Webhook\EmeliaIntentWebhookController;
use Illuminate\Support\Facades\Route;

// ─── Webhooks (sans JWT, sans CSRF) ──────────────────────────────────────────
Route::post('/webhooks/emelia', [EmeliaWebhookController::class, 'handle'])
    ->name('webhooks.emelia');
Route::post('/webhooks/emelia-intent', [EmeliaIntentWebhookController::class, 'handle'])
    ->name('webhooks.emelia-intent');

Route::prefix('v1')->name('api.')->group(function (): void {
    Route::get('/', [InfoController::class, 'index']);

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('jwt')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/search', [SearchController::class, 'index']);

        Route::prefix('ai')->group(function (): void {
            Route::post('/summarize/deal/{id}', [AiController::class, 'summarizeDeal']);
            Route::post('/summarize/contact/{id}', [AiController::class, 'summarizeContact']);
            Route::post('/next-action/deal/{id}', [AiController::class, 'nextActionDeal']);
            Route::post('/score/deal/{id}', [AiController::class, 'scoreDeal']);
        });

        Route::apiResource('companies', CompanyController::class);
        Route::post('/companies/{company}/contacts', [CompanyController::class, 'attachContact']);
        Route::delete('/companies/{company}/contacts/{contact}', [CompanyController::class, 'detachContact']);
        Route::patch('/companies/{company}/contacts/{contact}', [CompanyController::class, 'updateContactAssoc']);

        // Route statique déclarée AVANT la route paramétrique {contact} : sinon
        // `GET /contacts/stats` est capturé par show(int $id) → TypeError 500 (bug prod).
        Route::get('/contacts/stats', [ContactController::class, 'stats']);
        // whereNumber : garde-fou supplémentaire, le param {contact} n'accepte que des entiers.
        Route::apiResource('contacts', ContactController::class)->whereNumber('contact');
        Route::post('/contacts/{contact}/companies', [ContactController::class, 'attachCompany']);
        Route::delete('/contacts/{contact}/companies/{company}', [ContactController::class, 'detachCompany']);
        Route::patch('/contacts/{contact}/companies/{company}', [ContactController::class, 'updateCompanyAssoc']);

        Route::get('/deals/board', [DealController::class, 'board']);
        Route::apiResource('deals', DealController::class);
        Route::post('/deals/{deal}/move', [DealController::class, 'move']);
        Route::post('/deals/{deal}/contacts', [DealController::class, 'attachContact']);
        Route::delete('/deals/{deal}/contacts/{contact}', [DealController::class, 'detachContact']);
        Route::patch('/deals/{deal}/contacts/{contact}', [DealController::class, 'updateContactAssoc']);
        Route::post('/deals/{deal}/companies', [DealController::class, 'attachCompany']);
        Route::delete('/deals/{deal}/companies/{company}', [DealController::class, 'detachCompany']);
        Route::patch('/deals/{deal}/companies/{company}', [DealController::class, 'updateCompanyAssoc']);
        Route::get('/activities/due', [ActivityController::class, 'due']);
        Route::apiResource('activities', ActivityController::class);
        Route::apiResource('tasks', TaskController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('saved-views', SavedViewController::class);

        // Segments — lecture pour tous les authentifiés
        Route::get('/segments/fields/{entityType}', [SegmentController::class, 'fields']);
        Route::get('/segments/{id}/members', [SegmentController::class, 'members']);
        Route::get('/segments', [SegmentController::class, 'index']);
        Route::get('/segments/{id}', [SegmentController::class, 'show']);

        Route::middleware('role:admin,manager')->group(function (): void {
            // Segments — écriture réservée admin/manager
            Route::post('/segments', [SegmentController::class, 'store']);
            Route::put('/segments/{id}', [SegmentController::class, 'update']);
            Route::patch('/segments/{id}', [SegmentController::class, 'update']);
            Route::delete('/segments/{id}', [SegmentController::class, 'destroy']);
            Route::post('/segments/{id}/refresh', [SegmentController::class, 'refreshCount']);
            Route::post('/segments/preview', [SegmentController::class, 'preview']);

            Route::apiResource('pipelines', PipelineController::class);
            Route::apiResource('pipeline-stages', PipelineStageController::class);
            Route::apiResource('custom-fields', CustomFieldController::class);
            Route::post('/imports/preview', [ImportController::class, 'preview']);
            Route::apiResource('imports', ImportController::class)->only(['index', 'store', 'show']);
            Route::apiResource('exports', ExportController::class)->only(['index', 'store', 'show']);
            Route::get('/exports/{export}/download', [ExportController::class, 'download']);
        });
    });
});
