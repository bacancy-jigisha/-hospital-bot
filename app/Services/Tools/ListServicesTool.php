<?php

namespace App\Services\Tools;

use App\Models\Service;

class ListServicesTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_services';
    }

    public function description(): string
    {
        return 'List hospital services (e.g. lab tests, imaging, physiotherapy, ambulance), optionally filtered by a search term or department.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search term to match against the service name or description.'],
                'department' => ['type' => 'string', 'description' => 'Department name to filter by.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $args): array
    {
        $services = Service::query()
            ->with('department')
            ->when(
                $args['query'] ?? null,
                fn ($q, $v) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%")
                )
            )
            ->when(
                $args['department'] ?? null,
                fn ($q, $v) => $q->whereHas('department', fn ($q) => $q->where('name', 'like', "%{$v}%"))
            )
            ->get();

        return $services->map(fn (Service $service) => [
            'name' => $service->name,
            'description' => $service->description,
            'department' => $service->department?->name,
            'availability' => $service->availability,
            'requires_appointment' => $service->requires_appointment,
        ])->all();
    }
}
