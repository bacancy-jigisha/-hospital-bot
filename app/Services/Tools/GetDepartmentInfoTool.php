<?php

namespace App\Services\Tools;

use App\Models\Department;

class GetDepartmentInfoTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_department_info';
    }

    public function description(): string
    {
        return 'Get details about a hospital department: description, location (building/floor), phone number, and visiting hours.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'department_name' => [
                    'type' => 'string',
                    'description' => 'The department name, e.g. "Cardiology".',
                ],
            ],
            'required' => ['department_name'],
        ];
    }

    public function execute(array $args): array
    {
        $department = Department::query()
            ->where('name', 'like', "%{$args['department_name']}%")
            ->first();

        if (! $department) {
            return [];
        }

        return [
            'name' => $department->name,
            'description' => $department->description,
            'floor' => $department->floor,
            'building' => $department->building,
            'phone' => $department->phone,
            'visiting_hours' => $department->visiting_hours,
            'is_emergency_department' => $department->is_emergency,
        ];
    }
}
