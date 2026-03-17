<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;
use App\Models\Transaction\SpendMoneyXero;

class SpendMoneyRepo extends BaseRepository
{
    public function __construct(SpendMoneyXero $model)
    {
        $this->model = $model;
    }

    // Tambahan jika perlu custom logic (misalnya hitung total otomatis)
    public function createFromXeroForm(array $data)
    {
        // lines sudah array dari request, Laravel akan simpan sebagai JSON otomatis
        $id = $data['id'] ?? null;
       unset($data['id']);
        return $this->CreateOrUpdate($data, $id);
    }
}
