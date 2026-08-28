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
        if ($raw) {
            $formatted = $this->formatFirmaUrl($raw);
            if ($formatted) {
                return $formatted;
            }
        }

        // Fallback al usuario administrador/sistemas con firma digital registrada
        try {
            $admin = Usuario::whereNotNull('firma_digital')->where('firma_digital', '!=', '')->first();
            if ($admin && $admin->firma_digital) {
                return $admin->firma_digital;
            }
        } catch (\Throwable $e) {
            // Ignorar en caso de que no haya conexión a base de datos en algún contexto
        }

        return null;
    }

    public function getFirmaRecibeUrlAttribute(): ?string
    {
        $raw = $this->getRawOriginal('firma_recibe') ?? $this->attributes['firma_recibe'] ?? null;
        if ($raw) {
            $formatted = $this->formatFirmaUrl($raw);
            if ($formatted) {
                return $formatted;
            }
        }

        // Fallback a la firma registrada en el perfil del funcionario
        if ($this->relationLoaded('funcionario') && $this->funcionario) {
            return $this->funcionario->firma_url ?? $this->funcionario->firma;
        }

        return null;
    }

    private function formatFirmaUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'data:image') || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        $path = ltrim(str_replace(['public/', 'api/', 'storage/'], '', $value), '/');
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
