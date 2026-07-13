<?php

use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\AiController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DealController;
use App\Http\Controllers\Web\EmailTemplateController;
use App\Http\Controllers\Web\EmeliaController;
use App\Http\Controllers\Web\FleetController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PipelineController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SegmentController;
use App\Http\Controllers\Web\Settings\ConsoleController;
use App\Http\Controllers\Web\Settings\CustomFieldController;
use App\Http\Controllers\Web\Settings\StageController;
use App\Http\Controllers\Web\TrashController;
use App\Http\Controllers\Tracking\TrackingController;
use Illuminate\Support\Facades\Route;

// ─── Tracking d'ouverture cold email (pixel public : sans auth, GET donc exempt CSRF) ──
// URL courte volontaire (/o/…) pour rester discret côté anti-spam. Le token est signé HMAC.
Route::get('/o/{token}', [TrackingController::class, 'open'])->name('tracking.open');

// ─── Auth (public) ───────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ─── App (protected) ─────────────────────────────────────────────────────────
Route::middleware('web.auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    Route::get('/deals/{deal}/edit', [DealController::class, 'edit']);
    Route::put('/deals/{deal}', [DealController::class, 'update']);
    Route::post('/deals/{deal}/won', [DealController::class, 'markWon'])->name('deals.won');
    Route::post('/deals/{deal}/lost', [DealController::class, 'markLost'])->name('deals.lost');
    Route::post('/deals/{deal}/contacts', [DealController::class, 'attachContact'])->name('deals.contacts.attach');
    Route::delete('/deals/{deal}/contacts/{contact}', [DealController::class, 'detachContact'])->name('deals.contacts.detach');
    Route::post('/deals/{deal}/companies', [DealController::class, 'attachCompany'])->name('deals.companies.attach');
    Route::delete('/deals/{deal}/companies/{company}', [DealController::class, 'detachCompany'])->name('deals.companies.detach');

    Route::get('/pipeline', [PipelineController::class, 'index'])->name('pipeline.index');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::get('/contacts/create', [ContactController::class, 'create']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit']);
    Route::put('/contacts/{contact}', [ContactController::class, 'update']);

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/export', [CompanyController::class, 'export'])->name('companies.export');
    Route::get('/companies/create', [CompanyController::class, 'create']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit']);
    Route::put('/companies/{company}', [CompanyController::class, 'update']);

    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::post('/activities/{activity}/toggle-done', [ActivityController::class, 'toggleDone']);
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/web/ai/deal/{id}/{action}', [AiController::class, 'dealInsight']);
        Route::post('/web/ai/contact/{id}/summarize', [AiController::class, 'contactInsight']);
        Route::post('/web/ai/company/{id}/summarize', [AiController::class, 'companyInsight']);
        Route::post('/web/ai/dashboard/suggestions', [AiController::class, 'dashboardSuggestions']);
        Route::post('/web/ai/draft-email', [AiController::class, 'draftEmail']);
        Route::post('/web/ai/report-insights', [AiController::class, 'reportInsights'])->middleware('role:admin,manager');
        Route::get('/web/ai/proactive-alerts', [AiController::class, 'proactiveAlerts']);
    });

    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/quick', [SearchController::class, 'quick'])->name('search.quick');

    // ─── Modèles d'email (tous rôles : chacun gère les siens) ──────────────────
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/email-templates/options', [EmailTemplateController::class, 'options'])->name('email-templates.options');
    Route::post('/email-templates', [EmailTemplateController::class, 'store'])->name('email-templates.store');
    Route::post('/email-templates/{template}/render', [EmailTemplateController::class, 'render'])->name('email-templates.render');
    Route::put('/email-templates/{template}', [EmailTemplateController::class, 'update']);
    Route::delete('/email-templates/{template}', [EmailTemplateController::class, 'destroy']);

    Route::get('/segments', [SegmentController::class, 'index']);
    Route::get('/segments/create', [SegmentController::class, 'create']);
    Route::post('/segments', [SegmentController::class, 'store']);
    Route::post('/segments/preview', [SegmentController::class, 'preview']);
    Route::get('/segments/{segment}', [SegmentController::class, 'show']);
    Route::get('/segments/{segment}/edit', [SegmentController::class, 'edit']);
    Route::put('/segments/{segment}', [SegmentController::class, 'update']);
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy']);
    Route::get('/segments/{segment}/export', [SegmentController::class, 'export'])->name('segments.export');

    // ─── Emelia ───────────────────────────────────────────────────────────────
    Route::get('/emelia/campaigns', [EmeliaController::class, 'campaigns'])->name('emelia.campaigns');
    Route::get('/contacts/{contact}/emelia/status', [EmeliaController::class, 'status'])->name('contacts.emelia.status');
    Route::post('/contacts/{contact}/emelia/sync', [EmeliaController::class, 'syncContact'])->name('contacts.emelia.sync');

    // ─── Admin + Manager uniquement ──────────────────────────────────────────
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
        Route::get('/fleet/data', [FleetController::class, 'data'])->name('fleet.data');
        Route::get('/fleet/agent/{dept}/tasks', [FleetController::class, 'agentTasks'])->name('fleet.agent.tasks');
        Route::post('/fleet/trigger', [FleetController::class, 'triggerAction'])->name('fleet.trigger');
        Route::post('/fleet/approve/{taskId}', [FleetController::class, 'approveTask'])->name('fleet.approve');
        Route::post('/fleet/reject/{taskId}', [FleetController::class, 'rejectTask'])->name('fleet.reject');
        Route::post('/fleet/retry/{taskId}', [FleetController::class, 'retryTask'])->name('fleet.retry');
        Route::post('/fleet/purge', [FleetController::class, 'purgeTasks'])->name('fleet.purge');

        Route::post('/contacts/{contact}/emelia', [EmeliaController::class, 'addContact'])->name('contacts.emelia.add');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
        Route::post('/contacts/bulk-destroy', [ContactController::class, 'bulkDestroy']);
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
        Route::post('/companies/bulk-destroy', [CompanyController::class, 'bulkDestroy']);
        Route::delete('/deals/{deal}', [DealController::class, 'destroy']);
        Route::post('/deals/bulk-destroy', [DealController::class, 'bulkDestroy']);

        Route::get('/imports/{entityType}/create', [ImportController::class, 'create']);
        Route::post('/imports/preview', [ImportController::class, 'preview']);
        Route::post('/imports', [ImportController::class, 'store']);
        Route::get('/imports/{id}/status', [ImportController::class, 'status']);
        Route::post('/imports/quick-field', [ImportController::class, 'quickField']);

        Route::get('/settings/stages', [StageController::class, 'index'])->name('stages.index');
        Route::post('/settings/stages', [StageController::class, 'store']);
        Route::post('/settings/stages/reorder', [StageController::class, 'reorder'])->name('stages.reorder');
        Route::patch('/settings/stages/{stage}', [StageController::class, 'update']);

        Route::get('/settings/fields', [CustomFieldController::class, 'index'])->name('fields.index');
        Route::post('/settings/fields', [CustomFieldController::class, 'store']);
        Route::patch('/settings/fields/{field}', [CustomFieldController::class, 'update']);
        Route::delete('/settings/fields/{field}', [CustomFieldController::class, 'destroy']);

        Route::post('/settings/emelia/sync', [EmeliaController::class, 'syncNow'])->name('emelia.sync-now');

        // Console admin — admin uniquement
        Route::middleware('role:admin')->group(function () {
            Route::get('/settings/console', [ConsoleController::class, 'index'])->name('console.index');
            Route::post('/settings/console/run', [ConsoleController::class, 'run'])->name('console.run');
            Route::get('/settings/console/run/{run}', [ConsoleController::class, 'status'])->name('console.status');
        });
        Route::post('/notifications/emelia-replies/seen', [NotificationController::class, 'markEmeliaRepliesSeen'])->name('notifications.emelia-replies.seen');

        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/contacts/{id}/restore', [TrashController::class, 'restoreContact'])->name('contacts.restore');
        Route::post('/companies/{id}/restore', [TrashController::class, 'restoreCompany'])->name('companies.restore');
        Route::post('/deals/{id}/restore', [TrashController::class, 'restoreDeal'])->name('deals.restore');
    });
});
