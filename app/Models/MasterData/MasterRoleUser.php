<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\MasterData\BusinessLine;
use App\Models\MasterData\Menu;

class MasterRoleUser extends Model
{
    use HasFactory;

    protected $table = 'master_role_users';

    protected $fillable = [
        'nama_role',
        'is_active',
        'guard_name',
        'created_by',
        'busines_line_id'
    ];

    protected $appends = ['nama_pembuat','lini_usaha'];


    public function getLiniUsahaAttribute()
    {
        return '-';
    }


    // RELASI
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


     public function liniUsaha()
    {
        return $this->belongsTo(BusinessLine::class, 'busines_line_id');
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
