<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cedula',
        'telefono',
        'cargo_id',
        'sede_id',
        'firma',
        'estado',
    ];

    protected $appends = ['firma_url', 'correo'];

    public function findUsuario(): ?Usuario
    {
        // 1. Por cédula exacta en el campo usuario
        if (!empty($this->cedula)) {
            $u = Usuario::where('usuario', trim($this->cedula))->first();
            if ($u) {
                return $u;
            }
        }

        // 2. Por teléfono exacto si existe
        if (!empty($this->telefono)) {
            $u = Usuario::where('telefono', trim($this->telefono))->first();
            if ($u) {
                return $u;
            }
        }

        // 3. Por nombre completo exacto
        if (!empty($this->nombre)) {
            $u = Usuario::whereRaw('LOWER(TRIM(nombre_completo)) = LOWER(TRIM(?))', [trim($this->nombre)])->first();
            if ($u) {
                return $u;
            }

            // 4. Coincidencia flexible de palabras (ej. "ASHLY NICOLE PEREZ LOPEZ" vs "PEREZ LOPEZ ASHLY NICOLE")
            $cleanWords = function ($str) {
                $str = mb_strtolower(trim((string)$str), 'UTF-8');
                $str = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $str);
                $words = preg_split('/\s+/', $str);
                $words = array_filter($words, fn($w) => strlen($w) >= 2);
                sort($words);
                return array_values($words);
            };

            $pWords = $cleanWords($this->nombre);
            if (count($pWords) >= 2) {
                $usuarios = Usuario::all();
                foreach ($usuarios as $usr) {
                    $uWords = $cleanWords($usr->nombre_completo);
                    $diff1 = array_diff($pWords, $uWords);
                    $diff2 = array_diff($uWords, $pWords);
                    if (empty($diff1) || empty($diff2)) {
                        return $usr;
                    }
                }

                $bestUser = null;
                $maxMatches = 0;
                foreach ($usuarios as $usr) {
                    $uWords = $cleanWords($usr->nombre_completo);
                    $common = array_intersect($pWords, $uWords);
                    $count = count($common);
                    if ($count >= 2 && $count > $maxMatches) {
                        $maxMatches = $count;
                        $bestUser = $usr;
                    }
                }
                if ($bestUser) {
                    return $bestUser;
                }
            }
        }

        return null;
    }

    public function getCorreoAttribute()
    {
        // 1. Prioridad: Correo del usuario del sistema
        $usr = $this->findUsuario();
        if ($usr && !empty($usr->correo) && filter_var($usr->correo, FILTER_VALIDATE_EMAIL)) {
            return trim($usr->correo);
        }

        // 2. Correo propio guardado en la tabla personal
        if (!empty($this->attributes['correo']) && filter_var($this->attributes['correo'], FILTER_VALIDATE_EMAIL)) {
            return trim($this->attributes['correo']);
        }

        return null;
    }

    public function setSedeIdAttribute($value)
    {
        $this->attributes['sede_id'] = !empty($value) ? $value : null;
    }

    public function getFirmaUrlAttribute()
    {
        // Prioridad 1: Firma digital del usuario del sistema en la tabla usuarios
        $usr = $this->findUsuario();
        if ($usr) {
            $rawFirma = $usr->getRawOriginal('firma_digital') ?? $usr->attributes['firma_digital'] ?? $usr->firma_digital;
            if (!empty($rawFirma)) {
                $path = $this->limpiarRutaFirma($rawFirma);
                if ($path && (file_exists(storage_path('app/public/' . $path)) || file_exists(public_path('storage/' . $path)))) {
                    return url('storage/' . $path);
                }
            }
        }

        // Prioridad 2: Firma propia guardada en la tabla personal
        if (!empty($this->firma)) {
            $raw = $this->firma;
            if (str_starts_with($raw, 'data:image')) {
                return $raw;
            }

            $path = $this->limpiarRutaFirma($raw);
            if ($path && (file_exists(storage_path('app/public/' . $path)) || file_exists(public_path('storage/' . $path)))) {
                return url('storage/' . $path);
            }
        }

        return null;
    }

    private function limpiarRutaFirma(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $parsed = parse_url($raw, PHP_URL_PATH);
            return ltrim(preg_replace('#^/?storage/#', '', $parsed), '/');
        }
        return ltrim(str_replace(['public/', 'api/', 'storage/'], '', $raw), '/');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function cargo()
    {
        return $this->belongsTo(PCargo::class, 'cargo_id');
    }

    public function pcEntregas()
    {
        return $this->hasMany(PcEntrega::class, 'funcionario_id');
    }

    public function entregasActivos()
    {
        return $this->hasMany(CpEntregaActivosFijos::class, 'personal_id');
    }
}

