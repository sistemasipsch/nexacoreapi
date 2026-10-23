<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

        // Fallback automático al usuario administrador/sistemas con firma digital registrada
        try {
            $admin = Usuario::whereNotNull('firma_digital')
                ->where('firma_digital', '!=', '')
                ->where(function ($q) {
                    $q->whereHas('rol', function ($r) {
                        $r->where('nombre', 'LIKE', '%admin%')
                          ->orWhere('nombre', 'LIKE', '%sistema%')
                          ->orWhere('nombre', 'LIKE', '%super%');
                    })
                    ->orWhere('usuario', 'LIKE', '%admin%')
                    ->orWhere('rol_id', 1);
                })
                ->first();

            if (!$admin) {
                $admin = Usuario::whereNotNull('firma_digital')
                    ->where('firma_digital', '!=', '')
                    ->first();
            }

            if ($admin && $admin->firma_digital) {
                return $admin->firma_digital;
            }
        } catch (\Throwable $e) {
            // Ignorar en caso de error
        }

        return null;
    }

    public function getFirmaRecibeUrlAttribute(): ?string
    {
        // 1. Prioridad absoluta: Firma digital del usuario del funcionario en la tabla usuarios
        try {
            $func = $this->funcionario ?? ($this->funcionario_id ? Personal::find($this->funcionario_id) : null);
            if ($func && !empty($func->firma_url)) {
                return $func->firma_url;
            }
        } catch (\Throwable $e) {
            // Ignorar en caso de error
        }

        // 2. Firma registrada explícitamente en pc_entregas
        $raw = $this->getRawOriginal('firma_recibe') ?? $this->attributes['firma_recibe'] ?? null;
        if ($raw) {
            $formatted = $this->formatFirmaUrl($raw);
            if ($formatted) {
                return $formatted;
            }
        }

        return null;
    }

    private function formatFirmaUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'data:image')) {
            return $value;
        }

        $raw = $value;
        $path = $raw;
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $parsed = parse_url($raw, PHP_URL_PATH);
            $path = ltrim(preg_replace('#^/?storage/#', '', $parsed), '/');
        } else {
            $path = ltrim(str_replace(['public/', 'api/', 'storage/'], '', $raw), '/');
        }

        $existe = false;
        try {
            if (Storage::disk('public')->exists($path) || file_exists(storage_path('app/public/' . $path)) || file_exists(public_path('storage/' . $path))) {
                $existe = true;
            }
        } catch (\Throwable $e) {
            $existe = false;
        }

        if (!$existe) {
            return null;
        }

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
