<?php

namespace App\Services\Tools;

use App\Models\Doctor;

class FindDoctorsTool implements ToolInterface
{
    public function name(): string
    {
        return 'find_doctors';
    }

    public function description(): string
    {
        return 'Find doctors by specialization, department, name, or a day of the week they are available. All arguments are optional filters — omit any you do not need.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'specialization' => ['type' => 'string', 'description' => 'e.g. "Cardiology" or "Sports Medicine".'],
                'department' => ['type' => 'string', 'description' => 'Department name, e.g. "Orthopedics".'],
                'name' => ['type' => 'string', 'description' => "The doctor's name, or part of it."],
                'day' => ['type' => 'string', 'description' => 'A day of the week the doctor should be available on, e.g. "Monday".'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $args): array
    {
        $doctors = Doctor::query()
            ->with('department')
            ->when($args['specialization'] ?? null, fn ($q, $v) => $q->where('specialization', 'like', "%{$v}%"))
            ->when($args['name'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when(
                $args['department'] ?? null,
                fn ($q, $v) => $q->whereHas('department', fn ($q) => $q->where('name', 'like', "%{$v}%"))
            )
            ->when($args['day'] ?? null, fn ($q, $v) => $q->whereJsonContains('available_days', $v))
            ->get();

        return $doctors->map(fn (Doctor $doctor) => [
            'name' => $doctor->name,
            'qualification' => $doctor->qualification,
            'specialization' => $doctor->specialization,
            'department' => $doctor->department->name,
            'available_days' => $doctor->available_days,
            'consultation_hours' => "{$doctor->consultation_start} - {$doctor->consultation_end}",
            'room_number' => $doctor->room_number,
        ])->all();
    }
}
