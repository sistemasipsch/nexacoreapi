<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = [
        'nombre_completo',
        'usuario',
        'contrasena',
        'rol_id',
        'correo',
        'telefono',
        'estado',
        'sede_id',
        'firma_digital',
        'codigo_verificacion',
        'codigo_verificacion_expira_at',
        'foto_usuario',
        'last_activity',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
    ];

    protected $appends = ['is_online', 'activity_status'];

    public function getIsOnlineAttribute()
    {
        return $this->last_activity && $this->last_activity->gt(now()->subMinutes(5));
    }

    public function getActivityStatusAttribute()
    {
        if (!$this->last_activity) {
            return 'inactive';
        }

        if ($this->last_activity->gt(now()->subMinutes(5))) {
            return 'active';
        }

        return 'away';
    }

    public function getFotoUsuarioAttribute($value)
    {
        if (!$value) {
            return null;
        }
        $path = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $value), '/');
        return url('storage/' . $path);
    }

    public function getFirmaDigitalAttribute($value)
    {
        if (!$value) {
            // Fallback a tabla personal si existe registro coincidente por nombre
            try {
                $nombre = trim($this->nombre_completo ?? '');
                if ($nombre) {
                    $personal = \App\Models\Personal::where('nombre', 'LIKE', "%$nombre%")->first();
                    if ($personal && !empty($personal->firma)) {
                        $value = $personal->firma;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore fallback error
            }

            if (!$value) {
                return null;
            }
        }

        $raw = $value;
        if (str_starts_with($raw, 'data:image')) {
            return $raw;
        }

        $path = $raw;
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $parsed = parse_url($raw, PHP_URL_PATH);
            $path = ltrim(preg_replace('#^/?storage/#', '', $parsed), '/');
        } else {
            $path = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $raw), '/');
        }

        $exists = false;
        if (file_exists(storage_path('app/public/' . $path)) || file_exists(public_path('storage/' . $path))) {
            $exists = true;
        }

        if (!$exists) {
            $fallback = 'signatures/nG7eo0GkSldiEnYs3xcxHAofCTyW1RW8jtBZAQZc.png';
            if (file_exists(storage_path('app/public/' . $fallback))) {
                return url('storage/' . $fallback);
            }
        }

        return url('storage/' . $path);
    }

    protected $hidden = [
        'contrasena',
    ];

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->contrasena;
    }


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    // Relationships
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Determina si el usuario tiene rol de coordinador o rol superior con facultades equivalentes.
     */
    public function isCoordinador(): bool
    {
        if (!$this->relationLoaded('rol')) {
            $this->load('rol');
        }
        $rolNombre = mb_strtolower($this->rol?->nombre ?? '');
        return str_contains($rolNombre, 'coordinad')
            || str_contains($rolNombre, 'coordinac')
            || str_contains($rolNombre, 'administrador')
            || str_contains($rolNombre, 'gerente');
    }
}
