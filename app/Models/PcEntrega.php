<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcEntrega extends Model
{
    protected $table = 'pc_entregas';
    public $timestamps = false; // No standard timestamps

    protected $fillable = [
        'equipo_id',
        'funcionario_id',
        'fecha_entrega',
        'firma_entrega',
        'firma_recibe',
        'devuelto',
        'estado'
    ];

    protected $casts = [
        'fecha_entrega' => 'date',
        'devuelto' => 'date',
        'equipo_id' => 'integer',
        'funcionario_id' => 'integer',
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
        if ($raw) {
            return $this->formatFirmaUrl($raw);
        }
        return $this->funcionario ? $this->funcionario->firma_url : null;
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

    public function equipo()
    {
        return $this->belongsTo(PcEquipo::class, 'equipo_id');
    }

    public function funcionario()
    {
        return $this->belongsTo(Personal::class, 'funcionario_id');
    }

    public function perifericos()
    {
        return $this->hasMany(PcPerifericoEntregado::class, 'entrega_id');
    }
}
