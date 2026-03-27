<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\MasterRoleUser;


class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
       'nama_menu',
       'slug',
       'route_menu',
       'module',
       'parent_id',
       'order',
       'is_active'
    ];


public function parent()
{
    return $this->belongsTo(Menu::class, 'parent_id');
}

public function children()
{
    return $this->hasMany(Menu::class, 'parent_id');
}

public function roles()
{
    return $this->belongsToMany(MasterRoleUser::class, 'role_menus', 'menu_id', 'role_id');
}


}
