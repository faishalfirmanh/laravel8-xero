<?php

namespace Database\Seeders;

use App\Models\Config\TravelUser;

use Illuminate\Database\Seeder;
use App\Models\MasterData\TravelName;
use App\Models\User;

class TravelUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $list_tra = TravelName::get();

        $user = User::where('email','isal@gmail.com')->first();
        if($user){
           $travelIds = TravelName::pluck('id');

    foreach ($travelIds as $travelId) {
        TravelUser::firstOrCreate([
            'user_id'   => $user->id,
            'travel_id' => $travelId,
        ]);
    }

        }
    }
}
