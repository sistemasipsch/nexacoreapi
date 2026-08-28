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
        $raw = $this->getRawOriginal('firma_recibe') ?? $this->attributes['firma_recibe'] ?? null;
        if ($raw) {
            $formatted = $this->formatFirmaUrl($raw);
            if ($formatted) {
                return $formatted;
            }
        }

        // Fallback a la firma registrada en el perfil del funcionario
        try {
            if ($this->relationLoaded('funcionario') && $this->funcionario) {
                return $this->funcionario->firma_url ?? $this->funcionario->firma;
            }
            if ($this->funcionario_id) {
                $func = Personal::find($this->funcionario_id);
                if ($func) {
                    return $func->firma_url ?? $func->firma;
                }
            }
        } catch (\Throwable $e) {
            // Ignorar en caso de error
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

        // Validar que el archivo exista en disco para no retornar imágenes rotas
        $existe = false;
        try {
            if (Storage::disk('public')->exists($path)) {
                $existe = true;
            } elseif (file_exists(public_path('storage/' . $path))) {
                $existe = true;
            } elseif (file_exists(storage_path('app/public/' . $path))) {
                $existe = true;
            }
        } catch (\Throwable $e) {
            $existe = true; // Si hay error verificando, permitir
        }

        if (!$existe) {
            return null; // Retornar null para que el accessor active el fallback automático a la firma de admin
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
