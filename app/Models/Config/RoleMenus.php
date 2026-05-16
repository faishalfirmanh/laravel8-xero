<?php

namespace App\Models\Config;

use App\Models\MasterData\Menu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMenus extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',//master_role_users
        'menu_id'
    ];


    protected $appends = [
        'nama_menu'
    ];

    public function getNamaMenuAttribute()
    {
        if ($this->getMenu) {

            if ($this->getMenu->nama_menu) {
                return $this->getMenu->nama_menu;
            } else {
                return '-';
            }
        } else {
            return '-';
        }
    }

    public function getMenu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function getRole()
    {
        return $this->belongsTo(RoleUsers::class, 'role_id');
    }
}
