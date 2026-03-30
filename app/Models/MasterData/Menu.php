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
       'slug',//untuk url web
       'route_name',//prefix
       'module',
       'parent_id',
       'order',
       'is_active'
    ];

    // protected $appends = [
    //     'nama_parent'
    // ];

    // public function getNamaParentAttribute()
    // {
    //    if ($this->parent) {
    //         return $this->parent->nama_menu;
    //     }
    //    return '-';
    // }


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
        return $this->belongsToMany(
            MasterRoleUser::class,
            'route_name',     // nama pivot table
            'menu_id',        // foreign key di pivot
            'role_id'         // foreign key di pivot
        );
    }


}
