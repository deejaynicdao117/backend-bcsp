<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CommunityController as AdminCommunityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarangayController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\PortalNotificationController;
use App\Http\Controllers\Residents\CommunityController as ResidentCommunityController;
use App\Http\Controllers\Residents\DocumentRequestController as ResidentDocumentRequestController;
use App\Http\Controllers\Residents\EmailVerificationController;
use App\Http\Controllers\Staff\AnnouncementController as StaffAnnouncementController;
use App\Http\Controllers\Staff\CommunityController as StaffCommunityController;
use App\Http\Controllers\Staff\DocumentRequestController as StaffDocumentRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/locations/bulacan/barangays', [BarangayController::class, 'locations']);

Route::prefix('auth')->middleware('force.json')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/register/verify', 'verifyRegistrationCode')->middleware('throttle:6,1');
        Route::post('/register/resend-code', 'resendRegistrationCode')->middleware('throttle:3,1');
        Route::post('/login', 'login');
        Route::post('/logout', 'logout')->middleware('auth:sanctum');
        Route::get('/me', 'me')->middleware('auth:sanctum');
    });

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
});

Route::middleware(['force.json', 'auth:sanctum'])->group(function () {
    Route::get('/user/account', [AuthController::class, 'me']);
    Route::get('/notifications', [PortalNotificationController::class, 'index']);

    Route::controller(DocumentTypeController::class)
        ->prefix('document-types')
        ->group(function () {
            Route::get('/', 'index');
        });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::post('/document-types', [DocumentTypeController::class, 'store']);
        Route::put('/document-types/{documentType}', [DocumentTypeController::class, 'update']);
        Route::delete('/document-types/{documentType}', [DocumentTypeController::class, 'destroy']);
    });

    Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    Route::prefix('community')->controller(ResidentCommunityController::class)->group(function () {
        Route::get('/announcements', 'announcements');
        Route::get('/emergency-contacts', 'emergencyContacts');
        Route::get('/evacuation-centers', 'evacuationCenters');
    });

    Route::prefix('resident')->middleware('role:resident')->group(function () {
        Route::controller(ResidentCommunityController::class)->group(function () {
            Route::get('/profile', 'profile');
            Route::put('/profile', 'updateProfile');
            Route::get('/complaints', 'complaints');
            Route::post('/complaints', 'submitComplaint');
            Route::get('/disaster-assistance', 'assistanceRequests');
            Route::post('/disaster-assistance', 'submitAssistanceRequest');
        });

        Route::controller(ResidentDocumentRequestController::class)->group(function () {
            Route::post('/document-requests', 'store');
            Route::get('/document-requests', 'index');
            Route::get('/document-requests/{documentRequest}/preview', 'previewDocument');
        });
    });

    Route::prefix('staff')->middleware('role:staff')->group(function () {
        Route::controller(StaffAnnouncementController::class)->prefix('announcements')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{announcement}', 'update');
            Route::delete('/{announcement}', 'destroy');
        });

        Route::controller(StaffDocumentRequestController::class)->group(function () {
            Route::get('/document-requests', 'index');
            Route::put('/document-requests/{documentRequest}', 'updateStatus');
            Route::get('/document-requests/{documentRequest}/document', 'downloadDocument');
        });

        Route::controller(StaffCommunityController::class)->group(function () {
            Route::get('/households', 'households');
            Route::put('/households/{household}/verification', 'verifyHousehold');
            Route::get('/residents', 'residents');
            Route::put('/residents/{user}/verification', 'verifyResident');
            Route::get('/complaints', 'complaints');
            Route::put('/complaints/{complaint}', 'updateComplaint');
            Route::get('/disaster-assistance', 'assistanceRequests');
            Route::put('/disaster-assistance/{assistanceRequest}', 'updateAssistance');
            Route::get('/analytics/community', 'analytics');
        });
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::post('/document-types', [DocumentTypeController::class, 'store']);

        Route::controller(AdminController::class)->group(function () {
            Route::get('/users', 'users');
            Route::get('/dashboard', 'dashboard');
            Route::get('/document-requests', 'documentRequests');
            Route::get('/reports', 'reports');
            Route::get('/notifications', 'notifications');
        });

        Route::controller(AdminUserController::class)->group(function () {
            Route::post('/users', 'store');
            Route::put('/users/{user}', 'update');
            Route::delete('/users/{user}', 'destroy');
        });

        Route::controller(AdminCommunityController::class)->group(function () {
            Route::get('/households', 'households');
            Route::get('/announcements', 'announcements');
            Route::post('/announcements', 'saveAnnouncement');
            Route::put('/announcements/{announcement}', 'saveAnnouncement');
            Route::delete('/announcements/{announcement}', 'deleteAnnouncement');
            Route::get('/emergency-contacts', 'emergencyContacts');
            Route::post('/emergency-contacts', 'saveEmergencyContact');
            Route::put('/emergency-contacts/{contact}', 'saveEmergencyContact');
            Route::delete('/emergency-contacts/{contact}', 'deleteEmergencyContact');
            Route::get('/evacuation-centers', 'evacuationCenters');
            Route::post('/evacuation-centers', 'saveEvacuationCenter');
            Route::put('/evacuation-centers/{center}', 'saveEvacuationCenter');
            Route::delete('/evacuation-centers/{center}', 'deleteEvacuationCenter');
            Route::get('/activity-logs', 'activityLogs');
            Route::get('/complaints', 'complaints');
            Route::get('/disaster-assistance', 'assistanceRequests');
            Route::get('/analytics/community', 'analytics');
        });
    });
});

Route::middleware(['force.json', 'auth:sanctum', 'role:platform_admin'])->prefix('platform')->group(function () {
    Route::get('/barangays', [BarangayController::class, 'index']);
    Route::post('/barangays', [BarangayController::class, 'store']);
    Route::post('/barangays/{psgcCode}', [BarangayController::class, 'save']);
    Route::put('/barangays/{barangay}/onboarding-review', [BarangayController::class, 'reviewOnboarding']);
    Route::post('/barangays/{barangay}/users', [BarangayController::class, 'createAdministrator']);
    Route::post('/barangays/{barangay}/import-default-workspace', [BarangayController::class, 'importDefaultWorkspace']);
});

