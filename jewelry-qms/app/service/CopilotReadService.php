<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class CopilotReadService
{
    public static function currentRecordInstance(string $companyId, ?string $recordId): ?array
    {
        $recordId = trim((string)$recordId);
        if ($recordId === '') {
            return null;
        }

        $row = Db::name('record_form_instances')
            ->where('company_id', $companyId)
            ->where('id', $recordId)
            ->field('id,template_id,template_name,template_module,template_version,doc_number,record_title,field_values,status,created,modified')
            ->find();
        if (!is_array($row)) {
            return null;
        }

        $values = self::decodeJson((string)($row['field_values'] ?? ''));

        return [
            'id' => (string)$row['id'],
            'doc_number' => (string)$row['doc_number'],
            'record_title' => (string)$row['record_title'],
            'status' => (string)$row['status'],
            'template_id' => (string)$row['template_id'],
            'template_name' => (string)($row['template_name'] ?? ''),
            'template_module' => (string)($row['template_module'] ?? ''),
            'template_version' => (string)($row['template_version'] ?? ''),
            'year' => self::yearFromRecord($row, $values),
            'field_values' => self::compactValue($values),
        ];
    }

    public static function recordTemplates(string $companyId, array $docNumbers = [], int $limit = 8): array
    {
        $query = Db::name('record_form_templates')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->field('id,doc_number,name,module,version,status,review_status,field_schema,print_template_key,modified')
            ->order('doc_number', 'asc')
            ->limit(max(1, $limit));

        $docNumbers = self::cleanStrings($docNumbers);
        if ($docNumbers !== []) {
            $query->whereIn('doc_number', $docNumbers);
        } else {
            $query->where('status', 'published');
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (string)$row['id'],
                'doc_number' => (string)$row['doc_number'],
                'name' => (string)$row['name'],
                'module' => (string)($row['module'] ?? ''),
                'version' => (string)($row['version'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'review_status' => (string)($row['review_status'] ?? ''),
                'print_template_key' => (string)($row['print_template_key'] ?? ''),
                'fields' => self::templateFields((string)($row['field_schema'] ?? '')),
            ];
        }, $query->select()->toArray());
    }

    public static function recordInstancesByYearDoc(string $companyId, int $year, array $docNumbers, int $limit = 8): array
    {
        $docNumbers = self::cleanStrings($docNumbers);
        if ($year <= 0 || $docNumbers === []) {
            return [];
        }

        $rows = Db::name('record_form_instances')
            ->where('company_id', $companyId)
            ->whereIn('doc_number', $docNumbers)
            ->where('status', '<>', 'voided')
            ->where('record_title', 'like', $year . '运行记录-%')
            ->field('id,template_id,template_name,doc_number,record_title,field_values,status,modified')
            ->order('modified', 'desc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();

        return array_map(static function (array $row): array {
            return [
                'id' => (string)$row['id'],
                'doc_number' => (string)$row['doc_number'],
                'record_title' => (string)$row['record_title'],
                'status' => (string)$row['status'],
                'template_id' => (string)$row['template_id'],
                'template_name' => (string)($row['template_name'] ?? ''),
                'field_values' => self::compactValue(self::decodeJson((string)($row['field_values'] ?? ''))),
            ];
        }, $rows);
    }

    public static function employees(string $companyId, int $limit = 8): array
    {
        $rows = Db::name('employees')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->field('id,employee_number,name,department_id,designation_id,education,entry_date')
            ->order('name', 'asc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'employee_number' => (string)($row['employee_number'] ?? ''),
            'name' => (string)$row['name'],
            'department_id' => (string)($row['department_id'] ?? ''),
            'designation_id' => (string)($row['designation_id'] ?? ''),
            'education' => (string)($row['education'] ?? ''),
            'entry_date' => (string)($row['entry_date'] ?? ''),
        ], $rows);
    }

    public static function equipment(string $companyId, int $limit = 8): array
    {
        $rows = Db::name('equipments')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->field('id,equipment_number,name,model,manufacturer,traceability_due_date,next_calibration_date,status')
            ->order('equipment_number', 'asc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'equipment_number' => (string)$row['equipment_number'],
            'name' => (string)$row['name'],
            'model' => (string)($row['model'] ?? ''),
            'manufacturer' => (string)($row['manufacturer'] ?? ''),
            'traceability_due_date' => (string)($row['traceability_due_date'] ?? ''),
            'next_calibration_date' => (string)($row['next_calibration_date'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
        ], $rows);
    }

    public static function structuredDocuments(string $companyId, string $keyword = '', int $limit = 6): array
    {
        $query = Db::name('qms_structured_documents')
            ->where('company_id', $companyId)
            ->where('soft_delete', 0)
            ->field('id,document_role,doc_number,title,version,source_status,status,markdown_path,modified')
            ->order('doc_number', 'asc')
            ->limit(max(1, $limit));

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('doc_number', '%' . $keyword . '%')
                    ->whereOr('title', 'like', '%' . $keyword . '%');
            });
        }

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'document_role' => (string)$row['document_role'],
            'doc_number' => (string)$row['doc_number'],
            'title' => (string)$row['title'],
            'version' => (string)($row['version'] ?? ''),
            'source_status' => (string)($row['source_status'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'markdown_path' => (string)($row['markdown_path'] ?? ''),
        ], $query->select()->toArray());
    }

    public static function procedureRecordRequirements(string $companyId, array $docNumbers = [], int $limit = 6): array
    {
        $query = Db::name('qms_document_blocks')
            ->alias('b')
            ->join('qms_structured_documents sd', 'sd.id = b.structured_document_id')
            ->where('b.company_id', $companyId)
            ->where('b.soft_delete', 0)
            ->where('sd.soft_delete', 0)
            ->where('sd.document_role', 'procedure')
            ->whereIn('b.block_type', ['record_requirement', 'form_schema'])
            ->field('b.id,b.title,b.block_type,b.section_number,b.markdown,sd.doc_number,sd.title document_title')
            ->order('sd.doc_number', 'asc')
            ->order('b.sort_order', 'asc')
            ->limit(max(1, $limit));

        $docNumbers = self::cleanStrings($docNumbers);
        if ($docNumbers !== []) {
            $query->whereIn('sd.doc_number', $docNumbers);
        }

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'procedure_doc_number' => (string)$row['doc_number'],
            'procedure_title' => (string)$row['document_title'],
            'block_title' => (string)$row['title'],
            'block_type' => (string)$row['block_type'],
            'section_number' => (string)($row['section_number'] ?? ''),
            'excerpt' => self::textExcerpt((string)$row['markdown'], 360),
        ], $query->select()->toArray());
    }

    public static function applicationProfile(): array
    {
        $path = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'application-profile' . DIRECTORY_SEPARATOR . 'qms_application_profile.json';
        if (!is_file($path)) {
            return [];
        }

        $profile = self::decodeJson((string)file_get_contents($path));
        if ($profile === []) {
            return [];
        }

        return [
            'organization' => self::compactValue((array)($profile['organization'] ?? []), 12),
            'certificate_number' => (string)($profile['certificate_number'] ?? ''),
            'capability_keywords' => array_slice((array)($profile['capability_keywords'] ?? []), 0, 12),
            'equipment_keywords' => array_slice((array)($profile['equipment_keywords'] ?? []), 0, 12),
            'standards' => array_slice((array)($profile['standards'] ?? []), 0, 12),
            'people' => array_slice((array)($profile['people'] ?? []), 0, 8),
        ];
    }

    public static function docNumbersFromText(string $text): array
    {
        preg_match_all('/XZTC\\/[A-Z]+-\\d{2}(?:-\\d{2})?/u', $text, $matches);

        return self::cleanStrings($matches[0] ?? []);
    }

    public static function yearFromText(string $text): ?int
    {
        if (preg_match('/(20\\d{2})/u', $text, $m)) {
            return (int)$m[1];
        }

        return null;
    }

    private static function templateFields(string $schemaJson): array
    {
        $schema = RecordFormSchemaService::decode($schemaJson);

        return array_map(static fn (array $field): array => [
            'key' => (string)($field['key'] ?? ''),
            'label' => (string)($field['label'] ?? ''),
            'type' => (string)($field['type'] ?? 'text'),
            'required' => (bool)($field['required'] ?? false),
        ], array_slice($schema, 0, 16));
    }

    private static function yearFromRecord(array $row, array $values): ?int
    {
        foreach (['plan_year', 'record_year', 'year'] as $key) {
            if (isset($values[$key]) && preg_match('/20\\d{2}/', (string)$values[$key], $m)) {
                return (int)$m[0];
            }
        }
        if (preg_match('/(20\\d{2})运行记录/u', (string)($row['record_title'] ?? ''), $m)) {
            return (int)$m[1];
        }

        return null;
    }

    private static function cleanStrings(array $values): array
    {
        $cleaned = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $cleaned[$value] = $value;
            }
        }

        return array_values($cleaned);
    }

    private static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function compactValue(mixed $value, int $limit = 8): mixed
    {
        if (!is_array($value)) {
            return is_string($value) ? self::textExcerpt($value, 500) : $value;
        }

        $result = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if ($count >= $limit) {
                $result['_truncated'] = true;
                break;
            }
            $result[$key] = self::compactValue($item, $limit);
            $count++;
        }

        return $result;
    }

    private static function textExcerpt(string $text, int $maxLength): string
    {
        $text = trim(preg_replace('/\\s+/u', ' ', $text) ?: $text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '...';
    }
}
