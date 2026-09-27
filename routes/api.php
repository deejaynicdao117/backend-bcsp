<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CommunityController as AdminCommunityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\Residents\CommunityController as ResidentCommunityController;
use App\Http\Controllers\Residents\EmailVerificationController;
use App\Http\Controllers\Residents\DocumentRequestController as ResidentDocumentRequestController;
use App\Http\Controllers\Staff\CommunityController as StaffCommunityController;
use App\Http\Controllers\Staff\DocumentRequestController as StaffDocumentRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('force.json')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
});

Route::middleware(['force.json', 'auth:sanctum'])->group(function () {
    Route::get('/user/account', [AuthController::class, 'me']);

    Route::apiResource('document-types', DocumentTypeController::class)
        ->only(['index', 'store']);

    Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    Route::prefix('community')->group(function () {
        Route::get('/announcements', [ResidentCommunityController::class, 'announcements']);
        Route::get('/emergency-contacts', [ResidentCommunityController::class, 'emergencyContacts']);
        Route::get('/evacuation-centers', [ResidentCommunityController::class, 'evacuationCenters']);
    });

    Route::prefix('resident')->middleware('role:resident')->group(function () {
        Route::get('/profile', [ResidentCommunityController::class, 'profile']);
        Route::put('/profile', [ResidentCommunityController::class, 'updateProfile']);
        Route::post('/document-requests', [ResidentDocumentRequestController::class, 'store']);
        Route::get('/document-requests', [ResidentDocumentRequestController::class, 'index']);
        Route::get('/complaints', [ResidentCommunityController::class, 'complaints']);
        Route::post('/complaints', [ResidentCommunityController::class, 'submitComplaint']);
        Route::get('/disaster-assistance', [ResidentCommunityController::class, 'assistanceRequests']);
        Route::post('/disaster-assistance', [ResidentCommunityController::class, 'submitAssistanceRequest']);
    });

    Route::prefix('staff')->middleware('role:staff')->group(function () {
        Route::get('/document-requests', [StaffDocumentRequestController::class, 'index']);
        Route::put('/document-requests/{documentRequest}', [StaffDocumentRequestController::class, 'updateStatus']);
        Route::get('/households', [StaffCommunityController::class, 'households']);
        Route::put('/households/{household}/verification', [StaffCommunityController::class, 'verifyHousehold']);
        Route::get('/residents', [StaffCommunityController::class, 'residents']);
        Route::put('/residents/{user}/verification', [StaffCommunityController::class, 'verifyResident']);
        Route::get('/complaints', [StaffCommunityController::class, 'complaints']);
        Route::put('/complaints/{complaint}', [StaffCommunityController::class, 'updateComplaint']);
        Route::get('/disaster-assistance', [StaffCommunityController::class, 'assistanceRequests']);
        Route::put('/disaster-assistance/{assistanceRequest}', [StaffCommunityController::class, 'updateAssistance']);
        Route::get('/analytics/community', [StaffCommunityController::class, 'analytics']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/document-requests', [AdminController::class, 'documentRequests']);
        Route::get('/reports', [AdminController::class, 'reports']);
        Route::get('/notifications', [AdminController::class, 'notifications']);
        Route::get('/households', [AdminCommunityController::class, 'households']);
        Route::get('/announcements', [AdminCommunityController::class, 'announcements']);
        Route::post('/announcements', [AdminCommunityController::class, 'saveAnnouncement']);
        Route::put('/announcements/{announcement}', [AdminCommunityController::class, 'saveAnnouncement']);
        Route::delete('/announcements/{announcement}', [AdminCommunityController::class, 'deleteAnnouncement']);
        Route::get('/emergency-contacts', [AdminCommunityController::class, 'emergencyContacts']);
        Route::post('/emergency-contacts', [AdminCommunityController::class, 'saveEmergencyContact']);
        Route::put('/emergency-contacts/{contact}', [AdminCommunityController::class, 'saveEmergencyContact']);
        Route::delete('/emergency-contacts/{contact}', [AdminCommunityController::class, 'deleteEmergencyContact']);
        Route::get('/evacuation-centers', [AdminCommunityController::class, 'evacuationCenters']);
        Route::post('/evacuation-centers', [AdminCommunityController::class, 'saveEvacuationCenter']);
        Route::put('/evacuation-centers/{center}', [AdminCommunityController::class, 'saveEvacuationCenter']);
        Route::delete('/evacuation-centers/{center}', [AdminCommunityController::class, 'deleteEvacuationCenter']);
        Route::get('/settings', [AdminCommunityController::class, 'settings']);
        Route::put('/settings', [AdminCommunityController::class, 'saveSetting']);
        Route::get('/activity-logs', [AdminCommunityController::class, 'activityLogs']);
        Route::get('/complaints', [AdminCommunityController::class, 'complaints']);
        Route::get('/disaster-assistance', [AdminCommunityController::class, 'assistanceRequests']);
        Route::get('/analytics/community', [AdminCommunityController::class, 'analytics']);
    });
});
