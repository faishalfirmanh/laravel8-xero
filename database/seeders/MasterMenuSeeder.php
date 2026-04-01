<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\Menu;
use App\Models\MasterData\TravelName;
use App\Models\MasterData\MasterRoleUser;
use App\Models\Config\RoleMenus;
use App\Models\Config\TravelUser;
use App\Models\Config\RoleUsers;
use App\Models\User;
use Illuminate\Support\Facades\DB; // Tambahkan ini
use Exception; // Tambahkan ini untuk menangkap error

class MasterMenuSeeder extends Seeder
{
    public function run()
    {
        //urutan hapus:
        //RoleMenus->RoleUsers->Menu->MasterRoleUser
        // Mulai Transaksi
        DB::beginTransaction();

        try {
            $list_parent_menu = [
                ['nama_menu' => 'master data', 'order' => 1, 'is_active' => 1],
                ['nama_menu' => 'transaksi', 'order' => 2, 'is_active' => 1],
                ['nama_menu' => 'pengaturan', 'order' => 3, 'is_active' => 1]
            ];

            // 1. Simpan Parent Menu
            foreach ($list_parent_menu as $value) {
                Menu::firstOrCreate(
                    ['nama_menu' => $value['nama_menu']],
                    [
                        'nama_menu'  => $value['nama_menu'],
                        'slug'       => NULL,
                        'route_name' => NULL,
                        'module'     => NULL,
                        'parent_id'  => NULL,
                        'urutan'      => $value['order'],
                        'is_active'  => $value['is_active']
                    ]
                );
            }

            // 2. Simpan Child Menu untuk 'master data'
            $masterDataParent = Menu::where('nama_menu', 'master data')->first();

            if ($masterDataParent) {
                $child_menus = ['hotel', 'jamaah/mitra', 'tracking-category', 'coa','bank-xero','role-user','business-line','travel'];
                $slug_web = [
                'travel/admin/master-data/hotel',
                'travel/admin/master-data/jamaah',
                'travel/admin/master-data/tracking-category',
                'travel/admin/master-data/coa',
                'travel/admin/master-data/bank-xero',
                'travel/admin/master-data/role-user',
                'travel/admin/master-data/business-line',
                'travel/admin/master-data/travel'];
                $i=0;
                foreach ($child_menus as $name) {
                    Menu::firstOrCreate(
                        [
                            'nama_menu' =>$name, //str_replace('/', '-', $name),
                            'route_name' => $name,
                        ],
                        [
                            'nama_menu'  => $name, //str_replace('/', '-', $name),
                            'slug'       =>$slug_web[$i],
                            'module'     => NULL,
                            'parent_id'  => $masterDataParent->id,
                            'is_active'  => 1
                        ]
                    );
                    $i++;
                    $this->command->info('Berhasil menyiapkan menu: ' . $name);
                }
            }


            $ConfigParent = Menu::where('nama_menu', 'pengaturan')->first();
            if($ConfigParent){
                $child_menu_config = ['config-currency', 'config-role-user'];
                $slug_web_config = [
                'travel/admin/config/currency',
                'travel/admin/config/role-user'];

                $pp = 0;
                foreach ($child_menu_config as $name) {
                    Menu::firstOrCreate(
                        [
                            'nama_menu' =>$name, //str_replace('/', '-', $name),
                            'route_name' => $name,
                        ],
                        [
                            'nama_menu'  => $name, //str_replace('/', '-', $name),
                            'slug'       =>$slug_web_config[$pp],
                            'module'     => NULL,
                            'parent_id'  => $ConfigParent->id,
                            'is_active'  => 1
                        ]
                    );
                    //$pp++;
                    $this->command->info('Berhasil simpan menu config : ' . $name."-".$pp);

                    $pp++;
                }

            }

            //jika it insert role
            $cek_role_it = MasterRoleUser::where('nama_role','it')->first();
            if($cek_role_it){
                $getAllMenu = Menu::get();
                foreach ($getAllMenu as $key => $value) {
                   RoleMenus::firstOrCreate(
                    [
                        'role_id'=>$cek_role_it->id,
                        'menu_id'=>$value->id
                    ],[
                         'role_id'=>$cek_role_it->id,
                        'menu_id'=>$value->id
                    ]);
                }

                //tambah akses
                $cek_akses_role = RoleUsers::where('role_id',$cek_role_it)->first();
                $cek_user = User::where('email','isal@gmail.com')->first();

                $cekAkses = RoleUsers::where(['role_id'=>$cek_role_it,'user_id'=>$cek_user->id])->first();
                if(empty($cekAkses)){
                    RoleUsers::create([
                        'role_id'=>$cek_role_it->id,
                        'user_id'=>$cek_user->id
                    ]);
                }
            }

            DB::commit();
            $this->command->info('--- SEMUA DATA BERHASIL DISIMPAN (COMMIT) ---');

        } catch (Exception $e) {
            // Jika ada satu saja yang gagal, batalkan semua perubahan
            DB::rollBack();
            $this->command->error('Terjadi kesalahan, database di-rollback: ' . $e->getMessage());
        }
    }
}
