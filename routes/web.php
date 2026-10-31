<?php

use App\Http\Controllers\AccountabilityController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExternalRequisitionController;
use App\Http\Controllers\GuestMeetingController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// The login page is served straight from the main URL.
// Authenticated users are taken on to their dashboard.
// The login page is served straight from the main URL.
// Authenticated users are taken on to their dashboard.
Route::get('/', function (\Illuminate\Http\Request $request) {
    if ($request->user()) {
        return redirect()->route('dashboard');
    }

    return app(AuthenticatedSessionController::class)->create($request);
})->name('home');

// Public, tokenized fill-and-sign flow for external requesters (no account).
Route::prefix('share')->middleware(['throttle:30,1'])->group(function () {
    Route::get('/external-requisitions/{token}', [\App\Http\Controllers\ExternalShareController::class, 'show'])
        ->name('external-share.show');
    Route::post('/external-requisitions/{token}', [\App\Http\Controllers\ExternalShareController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->name('external-share.submit');
});

// Public meeting join (guests: display name only, unpredictable link ids).
Route::prefix('meet')->middleware(['throttle:60,1'])->group(function () {
    Route::get('/{publicId}', [GuestMeetingController::class, 'show'])->name('meetings.guest');
    Route::post('/{publicId}/join', [GuestMeetingController::class, 'join'])->middleware('throttle:10,1')->name('meetings.guest.join');
    Route::get('/{publicId}/room', [GuestMeetingController::class, 'room'])->name('meetings.guest.room');
    Route::get('/{publicId}/chat', [GuestMeetingController::class, 'chatHistory'])->name('meetings.guest.chat-history');
    Route::post('/{publicId}/chat', [GuestMeetingController::class, 'chat'])->middleware('throttle:60,1')->name('meetings.guest.chat');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:employees.view,employees.manage')->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('permission:employees.manage')->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:employees.view,employees.manage')->name('employees.show');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employees.manage')->name('employees.update');

    // Contracts
    Route::get('/contracts', [ContractController::class, 'index'])->middleware('permission:contracts.view,contracts.manage')->name('contracts.index');
    Route::post('/contracts', [ContractController::class, 'store'])->middleware('permission:contracts.manage')->name('contracts.store');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{contract}/versions', [ContractController::class, 'addVersion'])->middleware('permission:contracts.manage')->name('contracts.versions.store');
    Route::patch('/contracts/{contract}/status', [ContractController::class, 'updateStatus'])->middleware('permission:contracts.manage')->name('contracts.status');

    // Internal requisitions
    Route::get('/requisitions', [RequisitionController::class, 'index'])->middleware('permission:requisitions.create,requisitions.view_department,requisitions.view_all')->name('requisitions.index');
    Route::get('/requisitions/create', [RequisitionController::class, 'create'])->middleware('permission:requisitions.create')->name('requisitions.create');
    Route::post('/requisitions', [RequisitionController::class, 'store'])->middleware('permission:requisitions.create')->name('requisitions.store');
    Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])->name('requisitions.show');
    Route::put('/requisitions/{requisition}', [RequisitionController::class, 'update'])->name('requisitions.update');
    Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])->name('requisitions.submit');
    Route::post('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
        ->middleware('permission:requisitions.review_technical,requisitions.review_hr,requisitions.approve_md')
        ->name('requisitions.approve');
    Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
        ->middleware('permission:requisitions.review_technical,requisitions.review_hr,requisitions.approve_md')
        ->name('requisitions.reject');
    Route::post('/requisitions/{requisition}/return', [RequisitionController::class, 'returnForCorrection'])
        ->middleware('permission:requisitions.review_technical,requisitions.review_hr,requisitions.approve_md')
        ->name('requisitions.return');
    Route::delete('/requisitions/{requisition}', [RequisitionController::class, 'destroy'])->name('requisitions.destroy');

    // Approval center
    Route::get('/approvals', [ApprovalController::class, 'index'])
        ->middleware('permission:requisitions.review_technical,requisitions.review_hr,requisitions.approve_md')
        ->name('approvals.index');

    // Finance
    Route::get('/disbursements', [PaymentController::class, 'disbursementQueue'])
        ->middleware('permission:finance.view,finance.disburse')
        ->name('disbursements.index');
    Route::get('/finance', [PaymentController::class, 'index'])
        ->middleware('permission:finance.view,finance.disburse')
        ->name('finance.index');
    Route::post('/requisitions/{requisition}/payments', [PaymentController::class, 'store'])
        ->middleware('permission:finance.disburse')
        ->name('payments.store');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
        ->middleware('permission:finance.disburse')
        ->name('payments.reverse');

    // Receipts
    Route::get('/receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])->middleware('signed')->name('receipts.pdf');
    Route::post('/receipts/{receipt}/retry-delivery', [ReceiptController::class, 'retryDelivery'])->name('receipts.retry');

    // Accountability
    Route::get('/accountability', [AccountabilityController::class, 'index'])->name('accountability.index');
    Route::post('/requisitions/{requisition}/accountability', [AccountabilityController::class, 'store'])->name('accountability.store');
    Route::post('/accountability/{submission}/review', [AccountabilityController::class, 'review'])->middleware('permission:finance.accountability_review')->name('accountability.review');

    // External requisitions
    Route::get('/external-requisitions', [ExternalRequisitionController::class, 'index'])->middleware('permission:external_requisitions.manage,external_requisitions.view')->name('external-requisitions.index');
    Route::get('/external-requisitions/create', [ExternalRequisitionController::class, 'create'])->middleware('permission:external_requisitions.manage')->name('external-requisitions.create');
    Route::post('/external-requisitions', [ExternalRequisitionController::class, 'store'])->middleware('permission:external_requisitions.manage')->name('external-requisitions.store');
    Route::get('/external-requisitions/{external_requisition}', [ExternalRequisitionController::class, 'show'])->name('external-requisitions.show');
    Route::post('/external-requisitions/{external_requisition}/approve', [ExternalRequisitionController::class, 'approve'])->middleware('permission:external_requisitions.manage')->name('external-requisitions.approve');
    Route::post('/external-requisitions/{external_requisition}/reject', [ExternalRequisitionController::class, 'reject'])->middleware('permission:external_requisitions.manage')->name('external-requisitions.reject');
    Route::post('/external-requisitions/{external_requisition}/send', [ExternalRequisitionController::class, 'sendToExternal'])->middleware('permission:external_requisitions.manage')->name('external-requisitions.send');
    Route::post('/external-requisitions/{external_requisition}/share-link', [ExternalRequisitionController::class, 'shareLink'])->middleware('permission:external_requisitions.manage')->name('external-requisitions.shareLink');
    Route::get('/external-requisitions/{external_requisition}/pdf', [ExternalRequisitionController::class, 'pdf'])->middleware('signed')->name('external-requisitions.pdf');

    // Users & permissions
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:system.users')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:system.users')->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:system.users')->name('users.update');
Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:system.users')->name('users.destroy');
    Route::post('/users/{user}/invite', [UserController::class, 'invite'])->middleware('permission:system.users')->name('users.invite');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->middleware('permission:system.users')->name('users.suspend');
    Route::post('/users/{user}/renew', [UserController::class, 'renew'])->middleware('permission:system.users')->name('users.renew');
    Route::post('/users/{user}/reset-link', [UserController::class, 'sendResetLink'])->middleware('permission:system.users')->name('users.resetLink');
    Route::get('/users/permissions/matrix', [UserController::class, 'permissionsMatrix'])->middleware('permission:system.roles')->name('users.permissions');
    Route::put('/roles/{role}/permissions', [UserController::class, 'syncRolePermissions'])->middleware('permission:system.roles')->name('roles.permissions');

    // Payroll
    Route::get('/payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->middleware('permission:payroll.view')->name('payroll.index');
    Route::post('/payroll/periods', [\App\Http\Controllers\PayrollController::class, 'storePeriod'])->middleware('permission:payroll.run')->name('payroll.periods.store');
    Route::post('/payroll/periods/{period}/compute', [\App\Http\Controllers\PayrollController::class, 'compute'])->middleware('permission:payroll.run')->name('payroll.compute');
    Route::get('/payroll/runs/{run}', [\App\Http\Controllers\PayrollController::class, 'show'])->middleware('permission:payroll.view')->name('payroll.runs.show');
    Route::post('/payroll/runs/{run}/approve', [\App\Http\Controllers\PayrollController::class, 'approve'])->middleware('permission:payroll.approve')->name('payroll.runs.approve');
    Route::post('/payroll/runs/{run}/pay', [\App\Http\Controllers\PayrollController::class, 'pay'])->middleware('permission:payroll.pay')->name('payroll.runs.pay');
    Route::post('/payroll/runs/{run}/close', [\App\Http\Controllers\PayrollController::class, 'close'])->middleware('permission:payroll.approve')->name('payroll.runs.close');
    Route::post('/payroll/lines/{line}/retry-email', [\App\Http\Controllers\PayrollController::class, 'retryPayslip'])->middleware('permission:payroll.pay')->name('payroll.lines.retryEmail');
    Route::get('/my-payslips', [\App\Http\Controllers\PayrollController::class, 'myPayslips'])->name('payroll.myPayslips');
    Route::get('/my-payslips/{line}/pdf', [\App\Http\Controllers\PayrollController::class, 'payslipPdf'])->middleware('signed')->name('payroll.payslipPdf');

    // Client invoicing
    Route::get('/invoices', [\App\Http\Controllers\InvoiceController::class, 'index'])->middleware('permission:invoices.view')->name('invoices.index');
    Route::get('/invoices/create', [\App\Http\Controllers\InvoiceController::class, 'create'])->middleware('permission:invoices.manage')->name('invoices.create');
    Route::post('/invoices', [\App\Http\Controllers\InvoiceController::class, 'store'])->middleware('permission:invoices.manage')->name('invoices.store');
    Route::get('/invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'show'])->middleware('permission:invoices.view')->name('invoices.show');
    Route::put('/invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'update'])->middleware('permission:invoices.manage')->name('invoices.update');
    Route::post('/invoices/{invoice}/send', [\App\Http\Controllers\InvoiceController::class, 'markSent'])->middleware('permission:invoices.manage')->name('invoices.send');
    Route::post('/invoices/{invoice}/void', [\App\Http\Controllers\InvoiceController::class, 'void'])->middleware('permission:invoices.manage')->name('invoices.void');
    Route::post('/invoices/{invoice}/payments', [\App\Http\Controllers\InvoiceController::class, 'recordPayment'])->middleware('permission:invoices.payments')->name('invoices.payments.store');
    Route::post('/invoices/{invoice}/clear', [\App\Http\Controllers\InvoiceController::class, 'clear'])->middleware('permission:invoices.payments')->name('invoices.clear');
    Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\InvoiceController::class, 'pdf'])->middleware(['signed', 'permission:invoices.view'])->name('invoices.pdf');

    // Employee document submissions
    Route::get('/documents-submissions', [\App\Http\Controllers\DocumentSubmissionController::class, 'index'])
        ->middleware('permission:docs.submit,docs.review')
        ->name('document-submissions.index');
    Route::post('/documents-submissions', [\App\Http\Controllers\DocumentSubmissionController::class, 'store'])
        ->middleware('permission:docs.submit')
        ->name('document-submissions.store');
    Route::get('/documents-submissions/{document_submission}', [\App\Http\Controllers\DocumentSubmissionController::class, 'show'])
        ->middleware('permission:docs.submit,docs.review')
        ->name('document-submissions.show');
    Route::post('/documents-submissions/{document_submission}/review', [\App\Http\Controllers\DocumentSubmissionController::class, 'review'])
        ->middleware('auth')
        ->name('document-submissions.review');

    // Finance reports (cashbook, aging, summary)
    Route::get('/reports/finance', [\App\Http\Controllers\FinanceReportController::class, 'index'])
        ->middleware('permission:reports.financial,finance.view')
        ->name('reports.finance');

    // Operations hubs (HR, Technical, Projects, Procurement, Inventory, Attendance)
    Route::get('/hr', [\App\Http\Controllers\OperationsController::class, 'hr'])->name('hr.index');
    Route::get('/technical', [\App\Http\Controllers\OperationsController::class, 'technical'])->name('technical.index');
    Route::get('/projects', [\App\Http\Controllers\OperationsController::class, 'projects'])->name('projects.index');
    Route::get('/procurement', [\App\Http\Controllers\OperationsController::class, 'procurement'])->name('procurement.index');
    Route::get('/inventory', [\App\Http\Controllers\OperationsController::class, 'inventory'])->name('inventory.index');
    Route::get('/attendance', [\App\Http\Controllers\OperationsController::class, 'attendance'])->name('attendance.index');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.executive,reports.financial,reports.hr,reports.technical')
        ->name('reports.index');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Audit
    Route::get('/audit', [AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:system.settings')->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:system.settings')->name('settings.update');

    // Documents (downloads; the controller performs the authorisation check)
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');

    // Meetings (Biiteek Global video conferencing)
    Route::get('/meetings', [MeetingController::class, 'index'])->middleware('permission:meetings.view,meetings.create,meetings.manage')->name('meetings.index');
    Route::post('/meetings', [MeetingController::class, 'store'])->middleware('permission:meetings.create')->name('meetings.store');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->middleware('permission:meetings.view,meetings.create,meetings.manage')->name('meetings.show');
    Route::get('/meetings/{meeting}/room', [MeetingController::class, 'room'])->name('meetings.room');
    Route::post('/meetings/{meeting}/invite', [MeetingController::class, 'invite'])->name('meetings.invite');
    Route::post('/meetings/{meeting}/end', [MeetingController::class, 'end'])->name('meetings.end');
    Route::delete('/meetings/{meeting}/participants/{identity}', [MeetingController::class, 'removeParticipant'])->name('meetings.participants.remove');
    Route::get('/meetings/{meeting}/chat', [MeetingController::class, 'chatIndex'])->name('meetings.chat.index');
    Route::post('/meetings/{meeting}/chat', [MeetingController::class, 'chatStore'])->name('meetings.chat.store');
});

require __DIR__.'/auth.php';
