<?php

namespace App\Services\Tools;

use App\Models\Department;

class GetVisitingHoursTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_visiting_hours';
    }

    public function description(): string
    {
        return 'Get visiting hours for a specific hospital department, or for every department if none is specified.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'department_name' => ['type' => 'string', 'description' => 'Optional. A specific department name.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $args): array
    {
        $departments = Department::query()
            ->when(
                $args['department_name'] ?? null,
                fn ($q, $v) => $q->where('name', 'like', "%{$v}%")
            )
            ->get(['name', 'visiting_hours', 'is_emergency']);

        return $departments->map(fn (Department $department) => [
            'department' => $department->name,
            'visiting_hours' => $department->visiting_hours,
            'is_emergency_department' => $department->is_emergency,
        ])->all();
    }
}
