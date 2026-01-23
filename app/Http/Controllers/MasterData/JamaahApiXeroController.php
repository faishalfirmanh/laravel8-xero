<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\JamaahXeroRepository;

class JamaahApiXeroController extends Controller
{
    //

    protected $repo;
    use ApiResponse;
    public function __construct(JamaahXeroRepository $repo)
    {
        $this->repo = $repo;
    }



}
