<?php

declare(strict_types=1);

use think\facade\Route;

Route::rule('login', 'Login/index');
Route::get('logout', 'Login/logout');
Route::get('login/logout', 'Login/logout');
Route::rule('login/changePassword', 'Login/changePassword');

Route::group(function () {
    Route::get('/', 'Dashboard/index');
    Route::rule('dashboard/index', 'Dashboard/index');

    Route::rule('document/index', 'Document/index');
    Route::rule('document/add', 'Document/add');
    Route::rule('document/edit', 'Document/edit');
    Route::rule('document/view', 'Document/view');
    Route::rule('document/revise', 'Document/revise');
    Route::rule('document/submitReview', 'Document/submitReview');
    Route::get('document/download', 'Document/download');

    Route::post('approval/approve', 'Approval/approve');

    Route::rule('user/changePassword', 'User/changePassword');

    Route::rule('import/index', 'Import/index');
    Route::post('import/upload', 'Import/upload');

    Route::rule('notification/index', 'Notification/index');
    Route::get('notification/read', 'Notification/read');
    Route::get('notification/markAllRead', 'Notification/markAllRead');

    Route::get('capa/advance', 'Capa/advance');
    Route::rule('capa/advance', 'Capa/advance');
    Route::get('audit_plan/approve', 'AuditPlan/approve');
    Route::get('audit_finding/createCapa', 'AuditFinding/createCapa');
    Route::get('nonconformity/createCapa', 'Nonconformity/createCapa');
    Route::get('complaint/createCapa', 'Complaint/createCapa');
    Route::rule('complaint/advance', 'Complaint/advance');
    Route::get('management_review/complete', 'ManagementReview/complete');
    Route::rule('review_action/verify', 'ReviewAction/verify');
    Route::get('review_action/createCapa', 'ReviewAction/createCapa');
    Route::get('training/complete', 'Training/complete');
    Route::get('supplier/qualified', 'Supplier/qualified');

    $crudModules = [
        'user', 'department', 'employee',
        'doc_category' => 'DocCategory',
        'doc_template' => 'DocTemplate',
        'audit_plan' => 'AuditPlan',
        'audit_schedule' => 'AuditSchedule',
        'audit_checklist' => 'AuditChecklist',
        'audit_finding' => 'AuditFinding',
        'management_review' => 'ManagementReview',
        'review_action' => 'ReviewAction',
        'capa',
        'equipment',
        'equipment_maintenance' => 'EquipmentMaintenance',
        'calibration',
        'training',
        'training_record' => 'TrainingRecord',
        'competency_record' => 'CompetencyRecord',
        'supplier',
        'supplier_evaluation' => 'SupplierEvaluation',
        'complaint',
        'nonconformity',
    ];

    foreach ($crudModules as $key => $controller) {
        if (is_int($key)) {
            $path = $controller;
            $ctrl = ucfirst($controller);
        } else {
            $path = $key;
            $ctrl = $controller;
        }
        Route::rule("$path/index", "$ctrl/index");
        Route::rule("$path/add", "$ctrl/add");
        Route::rule("$path/edit", "$ctrl/edit");
        Route::rule("$path/view", "$ctrl/view");
        Route::rule("$path/delete", "$ctrl/delete");
    }
})->middleware([
    \app\middleware\Auth::class,
    \app\middleware\Rbac::class,
    \app\middleware\AuditLog::class,
]);
