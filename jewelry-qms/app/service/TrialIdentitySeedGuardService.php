<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class TrialIdentitySeedGuardService
{
    public static function configuredCompanyId(): string
    {
        $companyId = trim((string)Config::get('qms.company_id', ''));
        $company = $companyId !== ''
            ? Db::name('companies')->where('id', $companyId)->find()
            : null;
        if (
            !$company
            || (int)$company['publish'] !== 1
            || (int)$company['soft_delete'] !== 0
        ) {
            throw new RuntimeException('配置的试运行机构不存在或当前不可用，已停止建立账号。');
        }

        return $companyId;
    }

    public static function findReusablePosition(string $companyId, string $code): ?array
    {
        $position = Db::name('qms_positions')->where('code', $code)->find();
        if (
            $position
            && (
                (string)$position['company_id'] !== $companyId
                || (int)$position['soft_delete'] !== 0
            )
        ) {
            throw new RuntimeException("岗位代码 {$code} 已被其他机构或停用数据占用，拒绝覆盖。");
        }

        return $position ?: null;
    }

    public static function findReusableEmployee(
        string $companyId,
        string $employeeNumber
    ): ?array {
        $employee = Db::name('employees')
            ->where('employee_number', $employeeNumber)
            ->find();
        if (
            $employee
            && (
                (string)$employee['company_id'] !== $companyId
                || (int)$employee['soft_delete'] !== 0
            )
        ) {
            throw new RuntimeException("员工号 {$employeeNumber} 已被其他机构或停用数据占用，拒绝覆盖。");
        }

        return $employee ?: null;
    }

    public static function findReusableUser(
        string $companyId,
        string $username,
        string $employeeId
    ): ?array {
        $user = Db::name('users')->where('username', $username)->find();
        if (
            $user
            && (
                (string)$user['company_id'] !== $companyId
                || (int)$user['soft_delete'] !== 0
                || (string)$user['employee_id'] !== $employeeId
            )
        ) {
            throw new RuntimeException("用户名 {$username} 已绑定其他机构、停用账号或其他员工，拒绝覆盖。");
        }

        return $user ?: null;
    }

    public static function findReusableAppointment(
        string $companyId,
        string $appointmentKey,
        string $employeeId,
        string $positionId
    ): ?array {
        $appointment = Db::name('employee_appointments')
            ->where('company_id', $companyId)
            ->where('appointment_key', $appointmentKey)
            ->find();
        if (
            $appointment
            && (
                (int)$appointment['soft_delete'] !== 0
                || (string)$appointment['employee_id'] !== $employeeId
                || (string)$appointment['position_id'] !== $positionId
            )
        ) {
            throw new RuntimeException(
                "任命键 {$appointmentKey} 已绑定停用数据、其他员工或其他岗位，拒绝覆盖。"
            );
        }

        return $appointment ?: null;
    }
}
