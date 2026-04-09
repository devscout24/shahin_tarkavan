<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\Admin\AdminController;
use App\Http\Controllers\Backend\CoachController;
use App\Http\Controllers\Backend\AdminProfileController;
use App\Http\Controllers\Backend\CommissionController;
use App\Http\Controllers\Backend\CompetitionLevelController;
use App\Http\Controllers\Backend\DynamicPageController;
use App\Http\Controllers\Backend\OrganizationTypeController;
use App\Http\Controllers\Backend\PlayerPositionController;
use App\Http\Controllers\Backend\CoachPositionController;
use App\Http\Controllers\Backend\RolePermissionController;
use App\Http\Controllers\Backend\SettingsController;
use App\Http\Controllers\Backend\SubscriptionPlanController;
use App\Http\Controllers\Backend\UserController;


Route::controller(AdminController::class)->group(function () {
    Route::get('/admin/dashboard', 'adminDashboard')->name('admin.dashboard');
});


Route::middleware('auth')->group(function () {
    Route::get('/admin/profile', [AdminProfileController::class, 'index'])->name('admin.profile');
    Route::post('/admin/profile', [AdminProfileController::class, 'update'])->name('admin.profile.update');

    Route::prefix('admin/settings')->name('admin.settings.')->middleware('permission:manage settings')->group(function () {
        Route::get('/smtp', [SettingsController::class, 'smtp'])->name('smtp');
        Route::post('/smtp', [SettingsController::class, 'updateSmtp'])->name('smtp.update');

        Route::get('/website', [SettingsController::class, 'website'])->name('website');
        Route::post('/website', [SettingsController::class, 'updateWebsite'])->name('website.update');

        Route::get('/admin', [SettingsController::class, 'admin'])->name('admin');
        Route::post('/admin', [SettingsController::class, 'updateAdmin'])->name('admin.update');

        Route::get('/stripe', [SettingsController::class, 'stripe'])->name('stripe');
        Route::post('/stripe', [SettingsController::class, 'updateStripe'])->name('stripe.update');

        Route::get('/dynamic-page', [DynamicPageController::class, 'index'])->name('dynamic.page');
        Route::get('/dynamic-page/data', [DynamicPageController::class, 'data'])->name('dynamic.page.data');
        Route::post('/dynamic-page', [DynamicPageController::class, 'store'])->name('dynamic.page.store');
        Route::get('/dynamic-page/{dynamicPage}/edit', [DynamicPageController::class, 'edit'])->name('dynamic.page.edit');
        Route::put('/dynamic-page/{dynamicPage}', [DynamicPageController::class, 'update'])->name('dynamic.page.update');
        Route::delete('/dynamic-page/{dynamicPage}', [DynamicPageController::class, 'destroy'])->name('dynamic.page.destroy');

        Route::get('/organization-types', [OrganizationTypeController::class, 'index'])->name('organization.types');
        Route::get('/organization-types/data', [OrganizationTypeController::class, 'data'])->name('organization.types.data');
        Route::post('/organization-types', [OrganizationTypeController::class, 'store'])->name('organization.types.store');
        Route::get('/organization-types/{organizationType}/edit', [OrganizationTypeController::class, 'edit'])->name('organization.types.edit');
        Route::put('/organization-types/{organizationType}', [OrganizationTypeController::class, 'update'])->name('organization.types.update');
        Route::delete('/organization-types/{organizationType}', [OrganizationTypeController::class, 'destroy'])->name('organization.types.destroy');

        Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions');
        Route::get('/commissions/data', [CommissionController::class, 'data'])->name('commissions.data');
        Route::post('/commissions', [CommissionController::class, 'store'])->name('commissions.store');
        Route::get('/commissions/{commission}/edit', [CommissionController::class, 'edit'])->name('commissions.edit');
        Route::put('/commissions/{commission}', [CommissionController::class, 'update'])->name('commissions.update');
        Route::delete('/commissions/{commission}', [CommissionController::class, 'destroy'])->name('commissions.destroy');
    });

    Route::prefix('admin/settings')->name('admin.settings.')->middleware('permission:manage roles')->group(function () {
        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles.permissions');
        Route::post('/roles', [RolePermissionController::class, 'storeRole'])->name('roles.store');
        Route::post('/permissions', [RolePermissionController::class, 'storePermission'])->name('permissions.store');
        Route::post('/assign-role', [RolePermissionController::class, 'assignRole'])->name('assign-role');
        Route::post('/sync-role-permissions', [RolePermissionController::class, 'syncPermissionToRole'])->name('sync-role-permissions');
    });

    Route::prefix('admin/users')->name('admin.users.')->middleware('permission:manage users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/data', [UserController::class, 'data'])->name('data');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
    });

    Route::prefix('admin/coaches')->name('admin.coaches.')->middleware('permission:manage users')->group(function () {
        Route::get('/', [CoachController::class, 'index'])->name('index');
        Route::get('/data', [CoachController::class, 'data'])->name('data');
        Route::get('/{coach}', [CoachController::class, 'show'])->name('show');
        Route::post('/{coach}/approve', [CoachController::class, 'approve'])->name('approve');
    });

    Route::prefix('admin/subscriptions')->name('admin.subscriptions.')->middleware('permission:manage settings')->group(function () {
        Route::get('/', [SubscriptionPlanController::class, 'index'])->name('index');
        Route::get('/data', [SubscriptionPlanController::class, 'data'])->name('data');
        Route::post('/', [SubscriptionPlanController::class, 'store'])->name('store');
        Route::get('/{subscriptionPlan}/edit', [SubscriptionPlanController::class, 'edit'])->name('edit');
        Route::put('/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->name('update');
        Route::delete('/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/competition-levels')->name('admin.competition-levels.')->middleware('permission:manage settings')->group(function () {
        Route::get('/', [CompetitionLevelController::class, 'index'])->name('index');
        Route::get('/data', [CompetitionLevelController::class, 'data'])->name('data');
        Route::post('/', [CompetitionLevelController::class, 'store'])->name('store');
        Route::get('/{competitionLevel}/edit', [CompetitionLevelController::class, 'edit'])->name('edit');
        Route::put('/{competitionLevel}', [CompetitionLevelController::class, 'update'])->name('update');
        Route::delete('/{competitionLevel}', [CompetitionLevelController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/player-positions')->name('admin.player-positions.')->middleware('permission:manage users')->group(function () {
        Route::get('/', [PlayerPositionController::class, 'index'])->name('index');
        Route::get('/data', [PlayerPositionController::class, 'data'])->name('data');
        Route::post('/', [PlayerPositionController::class, 'store'])->name('store');
        Route::get('/{playerPosition}/edit', [PlayerPositionController::class, 'edit'])->name('edit');
        Route::put('/{playerPosition}', [PlayerPositionController::class, 'update'])->name('update');
        Route::delete('/{playerPosition}', [PlayerPositionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/coach-positions')->name('admin.coach-positions.')->middleware('permission:manage settings')->group(function () {
        Route::get('/', [CoachPositionController::class, 'index'])->name('index');
        Route::get('/data', [CoachPositionController::class, 'data'])->name('data');
        Route::post('/', [CoachPositionController::class, 'store'])->name('store');
        Route::get('/{coachPosition}/edit', [CoachPositionController::class, 'edit'])->name('edit');
        Route::put('/{coachPosition}', [CoachPositionController::class, 'update'])->name('update');
        Route::delete('/{coachPosition}', [CoachPositionController::class, 'destroy'])->name('destroy');
    });
});
