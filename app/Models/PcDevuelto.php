<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcDevuelto extends Model
{
    protected $table = 'pc_devuelto';
    public $timestamps = false; 

    protected $fillable = [
        'entrega_id',
        'fecha_devolucion',
        'firma_entrega',
        'firma_recibe',
        'observaciones'
    ];

    protected $casts = [
        'fecha_devolucion' => 'datetime',
        'entrega_id' => 'integer',
    ];

    protected $appends = ['firma_entrega_url', 'firma_recibe_url'];

    public function getFirmaEntregaUrlAttribute(): ?string
    {
        $raw = $this->getRawOriginal('firma_entrega') ?? $this->attributes['firma_entrega'] ?? null;
        return $this->formatFirmaUrl($raw);
    }

    public function getFirmaRecibeUrlAttribute(): ?string
    {
        $raw = $this->getRawOriginal('firma_recibe') ?? $this->attributes['firma_recibe'] ?? null;
        return $this->formatFirmaUrl($raw);
    }

    private function formatFirmaUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image')) {
            return $value;
        }
        $path = ltrim(str_replace(['storage/', 'public/'], '', $value), '/');
        return url('storage/' . $path);
    }

    public function entrega()
    {
        return $this->belongsTo(PcEntrega::class, 'entrega_id');
    }
}
