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
            return null;
        }
        $path = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $value), '/');
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
}
