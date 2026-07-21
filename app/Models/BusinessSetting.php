<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'business_hours' => 'array',
    ];

    public const DEFAULT_HOURS = [
        ['key' => 'monday', 'label' => 'Lunes', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'tuesday', 'label' => 'Martes', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'wednesday', 'label' => 'Miércoles', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'thursday', 'label' => 'Jueves', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'friday', 'label' => 'Viernes', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'saturday', 'label' => 'Sábado', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'sunday', 'label' => 'Domingo', 'enabled' => false, 'opens' => '09:00', 'closes' => '18:00'],
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'business_name' => config('app.name', 'Calle Sabor'),
            'platform_name' => config('app.name', 'Calle Sabor'),
            'business_hours' => self::DEFAULT_HOURS,
        ]);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->postal_code])
            ->filter()->implode(', ');
    }
}
