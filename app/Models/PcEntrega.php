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
            return $this->formatFirmaUrl($raw);
        }
        return null;
    }

    public function getFirmaRecibeUrlAttribute(): ?string
    {
        $raw = $this->getRawOriginal('firma_recibe') ?? $this->attributes['firma_recibe'] ?? null;
        
        // 1. Si tiene firma guardada en el acta, verificar si existe o si es base64/URL
        if ($raw) {
            $cleanPath = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $raw), '/');
            if (str_starts_with($raw, 'data:image') || str_starts_with($raw, 'http') || file_exists(storage_path('app/public/' . $cleanPath))) {
                return $this->formatFirmaUrl($raw);
            }
        }

        // 2. Fallback a la firma del funcionario en Personal
        if ($this->funcionario && $this->funcionario->firma) {
            $personalPath = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $this->funcionario->firma), '/');
            if (file_exists(storage_path('app/public/' . $personalPath))) {
                return $this->funcionario->firma_url;
            }
        }

        // 3. Fallback a la firma del usuario en la tabla Usuarios (si el funcionario tiene usuario del sistema)
        if ($this->funcionario) {
            $user = Usuario::where('nombre_completo', $this->funcionario->nombre)
                ->orWhere('telefono', $this->funcionario->telefono)
                ->whereNotNull('firma_digital')
                ->first();

            if ($user && $user->getRawOriginal('firma_digital')) {
                $userFirmaPath = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $user->getRawOriginal('firma_digital')), '/');
                if (file_exists(storage_path('app/public/' . $userFirmaPath))) {
                    return url('storage/' . $userFirmaPath);
                }
            }
        }

        // 4. Si nada de lo anterior coincidió con archivo en disco, retornar el valor del acta si existía
        return $raw ? $this->formatFirmaUrl($raw) : ($this->funcionario ? $this->funcionario->firma_url : null);
    }

    private function formatFirmaUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image')) {
            return $value;
        }
        $path = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $value), '/');
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
