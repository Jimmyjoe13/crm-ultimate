<?php

use App\Http\Controllers\Web\AiController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DealController;
use App\Http\Controllers\Web\PipelineController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\SegmentController;
use App\Http\Controllers\Web\TrashController;
use App\Http\Controllers\Web\Settings\StageController;
use App\Http\Controllers\Web\Settings\CustomFieldController;
use Illuminate\Support\Facades\Route;

// ─── Auth (public) ───────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ─── App (protected) ─────────────────────────────────────────────────────────
Route::middleware('web.auth')->group(function () {

    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/deals',              [DealController::class, 'index'])->name('deals.index');
    Route::post('/deals',             [DealController::class, 'store'])->name('deals.store');
    Route::get('/deals/{deal}',       [DealController::class, 'show'])->name('deals.show');
    Route::get('/deals/{deal}/edit',  [DealController::class, 'edit']);
    Route::put('/deals/{deal}',       [DealController::class, 'update']);
    Route::post('/deals/{deal}/won',  [DealController::class, 'markWon'])->name('deals.won');
    Route::post('/deals/{deal}/lost', [DealController::class, 'markLost'])->name('deals.lost');

    Route::get('/pipeline',  [PipelineController::class, 'index'])->name('pipeline.index');

    Route::get('/contacts',               [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/create',        [ContactController::class, 'create']);
    Route::post('/contacts',              [ContactController::class, 'store']);
    Route::get('/contacts/{contact}',     [ContactController::class, 'show'])->name('contacts.show');
    Route::get('/contacts/{contact}/edit',[ContactController::class, 'edit']);
    Route::put('/contacts/{contact}',     [ContactController::class, 'update']);

    Route::get('/companies',               [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create',        [CompanyController::class, 'create']);
    Route::post('/companies',              [CompanyController::class, 'store']);
    Route::get('/companies/{company}',     [CompanyController::class, 'show'])->name('companies.show');
    Route::get('/companies/{company}/edit',[CompanyController::class, 'edit']);
    Route::put('/companies/{company}',     [CompanyController::class, 'update']);

    Route::get('/activities',                            [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities',                           [ActivityController::class, 'store']);
    Route::post('/activities/{activity}/toggle-done',    [ActivityController::class, 'toggleDone']);

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/web/ai/deal/{id}/{action}',       [AiController::class, 'dealInsight']);
        Route::post('/web/ai/contact/{id}/summarize',   [AiController::class, 'contactInsight']);
        Route::post('/web/ai/company/{id}/summarize',   [AiController::class, 'companyInsight']);
        Route::post('/web/ai/dashboard/suggestions',    [AiController::class, 'dashboardSuggestions']);
    });

    Route::get('/search',    [SearchController::class, 'index'])->name('search');

    Route::get('/segments',                  [SegmentController::class, 'index']);
    Route::get('/segments/create',           [SegmentController::class, 'create']);
    Route::post('/segments',                 [SegmentController::class, 'store']);
    Route::post('/segments/preview',         [SegmentController::class, 'preview']);
    Route::get('/segments/{segment}',        [SegmentController::class, 'show']);
    Route::get('/segments/{segment}/edit',   [SegmentController::class, 'edit']);
    Route::put('/segments/{segment}',        [SegmentController::class, 'update']);
    Route::delete('/segments/{segment}',     [SegmentController::class, 'destroy']);
    Route::get('/segments/{segment}/export', [SegmentController::class, 'export'])->name('segments.export');

    // ─── Admin + Manager uniquement ──────────────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::delete('/contacts/{contact}',  [ContactController::class, 'destroy']);
        Route::post('/contacts/bulk-destroy', [ContactController::class, 'bulkDestroy']);
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
        Route::post('/companies/bulk-destroy',[CompanyController::class, 'bulkDestroy']);
        Route::delete('/deals/{deal}',        [DealController::class, 'destroy']);
        Route::post('/deals/bulk-destroy',    [DealController::class, 'bulkDestroy']);

        Route::get('/imports/{entityType}/create', [ImportController::class, 'create']);
        Route::post('/imports/preview',            [ImportController::class, 'preview']);
        Route::post('/imports',                    [ImportController::class, 'store']);
        Route::get('/imports/{id}/status',         [ImportController::class, 'status']);
        Route::post('/imports/quick-field',        [ImportController::class, 'quickField']);

        Route::get('/settings/stages',           [StageController::class, 'index'])->name('stages.index');
        Route::post('/settings/stages',          [StageController::class, 'store']);
        Route::post('/settings/stages/reorder',  [StageController::class, 'reorder'])->name('stages.reorder');
        Route::patch('/settings/stages/{stage}', [StageController::class, 'update']);

        Route::get('/settings/fields',            [CustomFieldController::class, 'index'])->name('fields.index');
        Route::post('/settings/fields',           [CustomFieldController::class, 'store']);
        Route::patch('/settings/fields/{field}',  [CustomFieldController::class, 'update']);
        Route::delete('/settings/fields/{field}', [CustomFieldController::class, 'destroy']);

        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/contacts/{id}/restore',  [TrashController::class, 'restoreContact'])->name('contacts.restore');
        Route::post('/companies/{id}/restore', [TrashController::class, 'restoreCompany'])->name('companies.restore');
        Route::post('/deals/{id}/restore',     [TrashController::class, 'restoreDeal'])->name('deals.restore');
    });
});
