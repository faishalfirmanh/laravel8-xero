<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Config\TravelUser;
use App\Models\MasterData\MasterRoleUser;
use App\Models\Config\RoleUsers;
use App\Models\MasterData\Menu;
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'list_travel',
        'list_role'
    ];

    public function getListTravelAttribute()
    {
        if ($this->relationLoaded('travelUsers')) {
            return $this->travelUsers
                        ->pluck('travel.name')
                        ->toArray();
        }
        return $this->travelUsers()
                    ->leftJoin('travel_names', 'travel_users.travel_id', '=', 'travel_names.id')
                    ->pluck('travel_names.name')
                    ->toArray();

    }





    public function getListRoleAttribute()
    {
       return $this->userRoles()
            ->join('master_role_users', 'role_users.role_id', '=', 'master_role_users.id')
            ->leftJoin('business_lines', 'master_role_users.busines_line_id', '=', 'business_lines.id')
            ->select([
                'master_role_users.nama_role',
                'business_lines.name_business'
            ])
            ->get()
            ->map(function ($item) {
                // Gabungan role dengan lini usaha
                if ($item->name_business) {
                    return $item->nama_role . ' - ' . $item->name_business;
                }
                return $item->nama_role;
            })
            ->toArray();
    }

    public function travelUsers()
    {
      return $this->hasMany(TravelUser::class, 'user_id', 'id');
    }


     public function userRoles()
    {
      return $this->hasMany(RoleUsers::class, 'user_id', 'id');
    }


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


   public function roles()
{
    return $this->belongsToMany(
        \App\Models\MasterData\MasterRoleUser::class,
        'role_users',
        'user_id',
        'role_id'
    )->withTimestamps();
}

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'role_menuses', 'role_id', 'menu_id')
                    ->where('menus.is_active', true)
                    ->orderBy('menus.urutan');
    }
}
