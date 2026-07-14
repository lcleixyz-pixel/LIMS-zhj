<?php
declare(strict_types=1);

namespace app\middleware;

use app\model\History;
use think\facade\Session;

class AuditLog
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        // Responsibility writes persist their business change and success audit in
        // one controller-owned transaction. Middleware logging would be both late
        // and duplicative, so this controller is deliberately excluded here.
        if (strtolower((string)$request->controller()) === 'planningresponsibility') {
            return $response;
        }

        if (Session::has('user.id') && $request->isPost()) {
            $controller = $request->controller();
            $action = $request->action();
            $isRegulatoryAction = strtolower((string)$controller) === 'planningregulatorymonitor';
            $regulatoryAudit = $isRegulatoryAction
                ? (array)$request->middleware('qms_regulatory_audit', [])
                : [];
            // The controller marks this only after its transaction succeeds.
            // A rejected/rolled-back review must never become a generic success log.
            if ($isRegulatoryAction && $regulatoryAudit === []) {
                return $response;
            }
            if (
                strtolower((string)$controller) === 'planningresponsibility'
                && strtolower((string)$action) === 'saveassignment'
                && (string)$request->post('operation', '') === 'remove'
            ) {
                $action = 'removeAssignment';
            }
            $method = $request->method();
            $isResponsibilityAction = strtolower((string)$controller) === 'planningresponsibility';
            $responsibilityAudit = $isResponsibilityAction
                ? (array)$request->middleware('qms_responsibility_audit', [])
                : [];
            $logActions = [
                'add',
                'approve',
                'changepassword',
                'create',
                'delete',
                'edit',
                'exportpdf',
                'restore',
                'revise',
                'seedbatch',
                'seedsamples',
                'submitreview',
                'transition',
                'updatereview',
                'uploadattachment',
                'save',
                'savechangerequest',
                'updatechangerequest',
                'test',
                'purge',
                'send',
                'createinitialdraft',
                'saveassignment',
                'removeassignment',
                'validateversion',
                'submitversion',
                'registergeneralmanager',
                'requestlabdirector',
                'review',
                'run',
            ];
            if (in_array(strtolower($action), $logActions, true)) {
                try {
                    $activeAudit = $isRegulatoryAction ? $regulatoryAudit : $responsibilityAudit;
                    $outcome = in_array((string)($activeAudit['outcome'] ?? ''), ['success', 'failed'], true)
                        ? (string)$activeAudit['outcome']
                        : (($isResponsibilityAction || $isRegulatoryAction) ? 'failed' : 'success');
                    $subjectType = trim((string)($activeAudit['subject_type'] ?? 'route_record'));
                    $subjectKey = trim((string)($activeAudit['subject_key'] ?? ''));
                    $auditMeta = $isRegulatoryAction ? $regulatoryAudit : $responsibilityAudit;
                    $regulatoryRunStatus = in_array(
                        (string)($regulatoryAudit['run_status'] ?? ''),
                        ['completed', 'partial_failed', 'failed'],
                        true
                    ) ? (string)$regulatoryAudit['run_status'] : '';
                    $recordId = $this->resolveRecordId(
                        $request,
                        $response,
                        $auditMeta,
                        $isResponsibilityAction || $isRegulatoryAction
                    );
                    $details = $isResponsibilityAction
                        ? implode(' ', array_filter([
                            'outcome=' . $outcome,
                            'subject_type=' . $this->detailValue($subjectType),
                            'subject_key=' . $this->detailValue($subjectKey !== '' ? $subjectKey : $recordId),
                            ($responsibilityAudit['failure_kind'] ?? '') !== ''
                                ? 'failure_kind=' . $this->detailValue((string)$responsibilityAudit['failure_kind'])
                                : '',
                            $method,
                            $controller . '/' . $action,
                        ]))
                        : ($isRegulatoryAction
                            ? implode(' ', array_filter([
                                'outcome=' . $outcome,
                                $regulatoryRunStatus !== ''
                                    ? 'run_status=' . $this->detailValue($regulatoryRunStatus)
                                    : '',
                                $method,
                                $controller . '/' . $action,
                            ]))
                            : $method . ' ' . $controller . '/' . $action);
                    History::create([
                        'id' => qms_uuid(),
                        'model_name' => $controller,
                        'controller_name' => $controller,
                        'action' => $action,
                        'record_id' => $recordId,
                        'user_id' => Session::get('user.id'),
                        'details' => $details,
                        'created' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Throwable $e) {
                }
            }
        }

        return $response;
    }

    private function resolveRecordId($request, $response, array $responsibilityAudit = [], bool $required = false): string
    {
        $auditRecordId = trim((string)($responsibilityAudit['record_id'] ?? ''));
        if ($auditRecordId !== '') {
            return $this->compactRecordId($auditRecordId, (string)($responsibilityAudit['subject_type'] ?? 'audit'));
        }

        $recordId = trim((string)$request->param('id', ''));
        if ($recordId !== '') {
            return $this->compactRecordId($recordId, 'id');
        }

        if ($required) {
            foreach (['version_id', 'assignment_id', 'approval_id', 'responsibility_id', 'batch_key'] as $field) {
                $postId = trim((string)$request->post($field, ''));
                if ($postId !== '') {
                    return $this->compactRecordId($postId, $field);
                }
            }
        }

        if (!method_exists($response, 'getHeader')) {
            return $required ? $this->requestAttemptId($request) : '';
        }

        $location = (string)($response->getHeader('Location') ?? '');
        if ($location === '') {
            return $required ? $this->requestAttemptId($request) : '';
        }

        $query = (string)(parse_url($location, PHP_URL_QUERY) ?? '');
        if ($query === '') {
            return $required ? $this->requestAttemptId($request) : '';
        }

        parse_str($query, $params);

        $redirectId = trim((string)($params['id'] ?? $params['version_id'] ?? ''));

        return $redirectId !== ''
            ? $this->compactRecordId($redirectId, isset($params['version_id']) ? 'version_id' : 'id')
            : ($required ? $this->requestAttemptId($request) : '');
    }

    private function compactRecordId(string $value, string $subjectType): string
    {
        if (strlen($value) <= 36) {
            return $value;
        }

        $prefix = substr(preg_replace('/[^a-z0-9_]+/i', '', $subjectType) ?: 'subject', 0, 7);

        return $prefix . ':' . substr(hash('sha256', $value), 0, 35 - strlen($prefix));
    }

    private function requestAttemptId($request): string
    {
        return 'request:' . substr(hash('sha256', implode('|', [
            (string)Session::get('user.id', ''),
            (string)$request->controller(),
            (string)$request->action(),
            (string)Session::get('user.session_id', ''),
        ])), 0, 28);
    }

    private function detailValue(string $value): string
    {
        return preg_replace('/\s+/', '_', trim($value)) ?: '-';
    }
}
