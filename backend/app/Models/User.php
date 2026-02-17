<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The guard name for Spatie permissions
     */
    protected $guard_name = 'sanctum';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'account_type',
        'workstation_id',
        'force_password_change',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'force_password_change' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the lines assigned to this user.
     */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'line_user');
    }

    /**
     * Get the workstation for workstation-type accounts.
     */
    public function workstation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    /**
     * Check if this is a workstation account.
     */
    public function isWorkstationAccount(): bool
    {
        return $this->account_type === 'workstation';
    }

    /**
     * Check if this is a user account.
     */
    public function isUserAccount(): bool
    {
        return $this->account_type === 'user';
    }
}
