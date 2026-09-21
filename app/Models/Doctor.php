<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id', 'name', 'qualification', 'specialization',
        'available_days', 'consultation_start', 'consultation_end', 'room_number',
    ];

    protected function casts(): array
    {
        return [
            'available_days' => 'array',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
