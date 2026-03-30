<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\MasterData\Menu;

class MasterRoleUser extends Model
{
    use HasFactory;

    protected $table = 'master_role_users';

    protected $fillable = [
        'nama_role',
        'is_active',
        'guard_name',
        'created_by'
    ];

    protected $appends = ['nama_pembuat'];

    // RELASI
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

   public function users()
    {
        return $this->belongsToMany(
            User::class,
            'role_users',      // nama pivot table
            'role_id',         // foreign key di pivot
            'user_id'          // foreign key di pivot
        )->withTimestamps();
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'role_menuses', 'role_id', 'menu_id');
    }



    public function getNamaPembuatAttribute()
    {
        if($this->creator){

            if($this->creator->name){
                return $this->creator->name;
            }
            else{
                return 'nama kosong';
            }
        }
        else{
            return '-';}
        }
    }
