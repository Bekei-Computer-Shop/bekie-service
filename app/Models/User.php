<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\ApiToken;
use App\Models\ContentItem;
use App\Models\Order;
use App\Models\CustomerGroup;
use App\Models\TeamActivityLog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'role',
        'password',
        'is_active',
        'is_banned',
        'is_admin',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function setNameAttribute(string $value): void
    {
        [$firstName, $lastName] = array_pad(explode(' ', $value, 2), 2, '');

        $this->attributes['first_name'] = $firstName;
        $this->attributes['last_name'] = $lastName;
    }

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function contentItems()
    {
        return $this->hasMany(ContentItem::class, 'author_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(TeamActivityLog::class, 'actor_id');
    }

    public function customerGroups()
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_user');
    }
}
