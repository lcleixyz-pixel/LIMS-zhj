<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Config;
use think\facade\Db;

class Api extends BaseController
{
    public function employees()
    {
        if ($response = $this->authorize()) {
            return $response;
        }

        return $this->payload(Db::table('employees')
            ->where('soft_delete', 0)
            ->field($this->availableFields('employees', [
                'id',
                'employee_number',
                'name',
                'department_id',
                'designation_id',
                'email',
                'phone',
                'primary_site_id',
                'publish',
                'created',
                'modified',
            ]))
            ->order('employee_number', 'asc')
            ->select()
            ->toArray());
    }

    public function equipments()
    {
        if ($response = $this->authorize()) {
            return $response;
        }

        return $this->payload(Db::table('equipments')
            ->where('soft_delete', 0)
            ->field($this->availableFields('equipments', [
                'id',
                'equipment_number',
                'name',
                'model',
                'manufacturer',
                'serial_number',
                'department_id',
                'site_id',
                'location',
                'calibration_required',
                'last_calibration_date',
                'next_calibration_date',
                'status',
                'publish',
                'created',
                'modified',
            ]))
            ->order('equipment_number', 'asc')
            ->select()
            ->toArray());
    }

    public function customers()
    {
        if ($response = $this->authorize()) {
            return $response;
        }

        $fields = $this->availableFields('customer_complaints', ['customer_name', 'contact']);
        $rows = Db::table('customer_complaints')
            ->where('soft_delete', 0)
            ->where('customer_name', '<>', '')
            ->field($fields)
            ->order('customer_name', 'asc')
            ->select()
            ->toArray();

        $customers = [];
        foreach ($rows as $row) {
            $name = (string)($row['customer_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $customers[$name] ??= [
                'name' => $name,
                'contact' => (string)($row['contact'] ?? ''),
            ];
            if ($customers[$name]['contact'] === '' && !empty($row['contact'])) {
                $customers[$name]['contact'] = (string)$row['contact'];
            }
        }

        return $this->payload(array_values($customers));
    }

    private function authorize()
    {
        $expectedToken = (string)Config::get('qms.integration.api_token', '');
        $providedToken = $this->request->header('X-QMS-API-Token', '');
        $authorization = (string)$this->request->header('Authorization', '');
        if ($providedToken === '' && str_starts_with($authorization, 'Bearer ')) {
            $providedToken = substr($authorization, 7);
        }
        if ($providedToken === '') {
            $providedToken = (string)$this->request->param('token', '');
        }

        if ($expectedToken !== '') {
            if (!hash_equals($expectedToken, (string)$providedToken)) {
                return json(['code' => 403, 'message' => 'Invalid integration token'], 403);
            }

            return null;
        }

        $ip = $this->request->ip();
        if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return json(['code' => 403, 'message' => 'Integration token is not configured'], 403);
        }

        return null;
    }

    private function payload(array $rows)
    {
        return json([
            'code' => 0,
            'count' => count($rows),
            'generated_at' => date('Y-m-d H:i:s'),
            'data' => $rows,
        ]);
    }

    private function availableFields(string $table, array $fields): array
    {
        try {
            $existing = array_keys(Db::table($table)->getFields());
        } catch (\Throwable) {
            return $fields;
        }

        return array_values(array_intersect($fields, $existing));
    }
}
