<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\GovernedChangePolicyService;
use app\service\GovernedChangeService;
use app\service\NotificationService;
use InvalidArgumentException;
use think\facade\Db;
use think\facade\Session;

final class GovernedChange extends BaseController
{
    public function request()
    {
        $subjectType = GovernedChangePolicyService::normalizeSubjectType(
            (string)$this->request->post('subject_type', '')
        );
        $subjectId = trim((string)$this->request->post('subject_id', ''));
        $returnUrl = $this->returnUrl($subjectType, $subjectId);
        $record = GovernedChangePolicyService::findSubject($subjectType, $subjectId);
        if (!$record) {
            Session::flash('error', '要更正的记录不存在或已被移除。');

            return redirect($returnUrl);
        }

        try {
            $request = GovernedChangeService::createRequest($subjectType, $record, [
                'field_name' => $this->request->post('field_name', ''),
                'change_kind' => $this->request->post('change_kind', 'correction'),
                'proposed_value' => $this->request->post('proposed_value', ''),
                'reason' => $this->request->post('reason', ''),
            ]);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect($returnUrl);
        } catch (\Throwable $exception) {
            Session::flash('error', '更正申请未能提交，请稍后重试；如仍失败，请联系质量负责人。');

            return redirect($returnUrl);
        }

        $recipientIds = $this->decisionUserIds();
        if ($recipientIds !== []) {
            NotificationService::notifyUsers(
                '业务记录更正申请',
                sprintf(
                    '%s申请更正字段“%s”；原值：%s；拟更正值：%s；原因：%s；申请编号：%s',
                    (string)$request->subject_label,
                    (string)$request->field_label,
                    (string)$request->original_value,
                    (string)$request->proposed_value,
                    (string)$request->reason,
                    (string)$request->id
                ),
                'general',
                $recipientIds,
                qms_controller_url($subjectType),
                'view',
                $subjectId,
                null,
                'governed_change_request:' . $request->id
            );
        }

        Session::flash('success', '更正申请已提交。原记录保持冻结，批准后系统只会追加字段差异和审批痕迹。');

        return redirect($returnUrl);
    }

    public function decide()
    {
        $requestId = trim((string)$this->request->post('request_id', ''));
        $decision = trim((string)$this->request->post('decision', ''));
        $comment = trim((string)$this->request->post('comment', ''));
        $fallbackUrl = $this->safePostedReturnUrl('/dashboard/index');

        try {
            $request = GovernedChangeService::decide($requestId, $decision, $comment);
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect($fallbackUrl);
        } catch (\Throwable $exception) {
            Session::flash('error', '更正申请未能处理，请刷新后重试；如仍失败，请联系系统管理员。');

            return redirect($fallbackUrl);
        }

        $resultLabel = $decision === 'approved' ? '已批准并追加到更正记录链' : '已驳回';
        $requesterId = trim((string)$request->requested_by);
        if ($requesterId !== '') {
            NotificationService::notifyUsers(
                '业务记录更正申请处理结果',
                sprintf(
                    '%s｜字段“%s”的更正申请%s。处理意见：%s',
                    (string)$request->subject_label,
                    (string)$request->field_label,
                    $resultLabel,
                    $comment !== '' ? $comment : '同意更正'
                ),
                'general',
                [$requesterId],
                qms_controller_url((string)$request->subject_type),
                'view',
                (string)$request->subject_id,
                null,
                'governed_change_decision:' . $request->id
            );
        }

        Session::flash(
            'success',
            $decision === 'approved'
                ? '更正申请已批准。原值未被覆盖，当前有效值和完整修订记录已同步更新。'
                : '更正申请已驳回，处理意见已留存。'
        );

        return redirect($this->returnUrl((string)$request->subject_type, (string)$request->subject_id));
    }

    /**
     * @return list<string>
     */
    private function decisionUserIds(): array
    {
        try {
            return array_values(array_unique(array_map('strval', Db::name('users')
                ->where('company_id', (string)\think\facade\Config::get('qms.company_id'))
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->where(function ($query) {
                    $query->where('is_approver', 1)
                        ->whereOr('role', 'quality_manager');
                })
                ->column('id'))));
        } catch (\Throwable) {
            return [];
        }
    }

    private function returnUrl(string $subjectType, string $subjectId): string
    {
        $posted = $this->safePostedReturnUrl('');
        if ($posted !== '') {
            return $posted;
        }
        if ($subjectType !== '' && $subjectId !== '') {
            return '/' . qms_controller_url($subjectType) . '/view?id=' . rawurlencode($subjectId);
        }

        return '/dashboard/index';
    }

    private function safePostedReturnUrl(string $fallback): string
    {
        $returnUrl = trim((string)$this->request->post('return_url', ''));
        if ($returnUrl === ''
            || !str_starts_with($returnUrl, '/')
            || str_starts_with($returnUrl, '//')
            || str_contains($returnUrl, "\r")
            || str_contains($returnUrl, "\n")) {
            return $fallback;
        }

        return $returnUrl;
    }
}
