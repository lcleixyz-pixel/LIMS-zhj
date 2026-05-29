<?php

declare(strict_types=1);

use think\facade\Route;

Route::rule('login', 'Login/index');
Route::get('logout', 'Login/logout');
Route::get('login/logout', 'Login/logout');
Route::rule('login/changePassword', 'Login/changePassword');
Route::get('api/v1/employees', 'Api/employees');
Route::get('api/v1/equipments', 'Api/equipments');
Route::get('api/v1/customers', 'Api/customers');

Route::group(function () {
    Route::get('/', 'Dashboard/index');
    Route::rule('dashboard/index', 'Dashboard/index');

    Route::rule('document/index', 'Document/index');
    Route::rule('document/add', 'Document/add');
    Route::rule('document/edit', 'Document/edit');
    Route::rule('document/view', 'Document/view');
    Route::rule('document/revise', 'Document/revise');
    Route::rule('document/submitReview', 'Document/submitReview');
    Route::get('document/onlyoffice', 'Document/onlyoffice');
    Route::get('document/controlledPrint', 'Document/controlledPrint');
    Route::get('document/download', 'Document/download');

    Route::rule('record_form_template/index', 'RecordFormTemplate/index');
    Route::rule('record_form_template/add', 'RecordFormTemplate/add');
    Route::rule('record_form_template/edit', 'RecordFormTemplate/edit');
    Route::post('record_form_template/reviewSchemaDraftFields', 'RecordFormTemplate/reviewSchemaDraftFields');
    Route::rule('record_form_template/view', 'RecordFormTemplate/view');
    Route::rule('record_form_template/delete', 'RecordFormTemplate/delete');
    Route::get('record_form_template/review', 'RecordFormTemplate/review');
    Route::post('record_form_template/updateReview', 'RecordFormTemplate/updateReview');
    Route::get('record_form_template/source', 'RecordFormTemplate/source');
    Route::get('record_form_template/sourcePreview', 'RecordFormTemplate/sourcePreview');
    Route::get('record_form_template/preview', 'RecordFormTemplate/preview');
    Route::post('record_form_template/seedSamples', 'RecordFormTemplate/seedSamples');
    Route::post('record_form_template/seedBatch', 'RecordFormTemplate/seedBatch');

    Route::rule('record_form_instance/index', 'RecordFormInstance/index');
    Route::rule('record_form_instance/create', 'RecordFormInstance/create');
    Route::rule('record_form_instance/edit', 'RecordFormInstance/edit');
    Route::get('record_form_instance/view', 'RecordFormInstance/view');
    Route::get('record_form_instance/print', 'RecordFormInstance/print');
    Route::post('record_form_instance/exportPdf', 'RecordFormInstance/exportPdf');
    Route::get('record_form_instance/downloadPdf', 'RecordFormInstance/downloadPdf');

    Route::rule('planning/index', 'PlanningDashboard/index');
    Route::post('planning/suggestions/review', 'PlanningDashboard/reviewSuggestion');
    Route::rule('planning/elements/view', 'PlanningElement/view');
    Route::rule('planning/elements/edit', 'PlanningElement/edit');
    Route::post('planning/elements/modules/map', 'PlanningElement/mapBusinessModule');
    Route::post('planning/elements/seed', 'PlanningElement/seed');
    Route::rule('planning/elements', 'PlanningElement/index');
    Route::post('planning/sources/upload', 'PlanningSource/upload');
    Route::post('planning/sources/seed', 'PlanningSource/seed');
    Route::post('planning/sources/freshness', 'PlanningSource/freshness');
    Route::post('planning/sources/extract-clauses', 'PlanningSource/extractClauses');
    Route::post('planning/sources/obsolete', 'PlanningSource/obsolete');
    Route::rule('planning/sources', 'PlanningSource/index');
    Route::post('planning/clauses/map', 'PlanningClause/map');
    Route::post('planning/clauses/local-element', 'PlanningClause/localElement');
    Route::rule('planning/clauses/view', 'PlanningClause/view');
    Route::rule('planning/clauses', 'PlanningClause/index');
    Route::rule('planning/structures/view', 'PlanningStructure/view');
    Route::get('planning/structures/blocks/edit', 'PlanningStructure/editBlock');
    Route::post('planning/structures/blocks/update', 'PlanningStructure/updateBlock');
    Route::get('planning/structures/links/review', 'PlanningStructure/reviewLinks');
    Route::post('planning/structures/links/save', 'PlanningStructure/saveLink');
    Route::post('planning/structures/links/delete', 'PlanningStructure/deleteLink');
    Route::post('planning/structures/reference-match/save', 'PlanningStructure/saveReferenceMatch');
    Route::post('planning/structures/publish', 'PlanningStructure/publishDocument');
    Route::post('planning/structures/refresh-source', 'PlanningStructure/refreshSource');
    Route::get('planning/structures/package', 'PlanningStructure/package');
    Route::post('planning/structures/render-package', 'PlanningStructure/renderPackage');
    Route::post('planning/structures/seed', 'PlanningStructure/seed');
    Route::rule('planning/structures', 'PlanningStructure/index');
    Route::rule('planning/traceability', 'PlanningTraceability/index');
    Route::post('planning/objectives/create-policy', 'PlanningObjective/createPolicy');
    Route::post('planning/objectives/create-objective', 'PlanningObjective/createObjective');
    Route::rule('planning/objectives', 'PlanningObjective/index');

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
