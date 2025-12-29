<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ApiResponse
{
    /**
     * Core Response format agar konsisten JSON-nya
     */
    protected function coreResponse(string $message, $data = null, int $statusCode, bool $isSuccess = true): JsonResponse
    {
        // Format standar JSON API
        $response = [
            'status'  => $isSuccess, // true/false lebih mudah diparse frontend daripada string "ok"/"error"
            'message' => $message,
        ];

        // Jika error, taruh di 'errors', jika sukses taruh di 'data'
        if ($isSuccess) {
            $response['data'] = $data;
        } else {
            $response['errors'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Panggil ini saat Sukses (200, 201)
     */
    public function success($data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $this->coreResponse($message, $data, $code, true);
    }

    /**
     * Panggil ini saat Error (400, 404, 500)
     */
    public function error(string $message, int $code = 400, $data = null): JsonResponse
    {
        return $this->coreResponse($message, $data, $code, false);
    }

    /**
     * [OPTIMASI UTAMA]
     * Fungsi Smart Response pengganti 'responseTrait' Anda.
     * Menggunakan instanceof (lebih aman) daripada explode string.
     */
    public function autoResponse($data, string $messageSuccess = 'Data retrieved successfully')
    {
        // 1. Cek jika data adalah Error Validasi (MessageBag)
        if ($data instanceof MessageBag) {
            return $this->error('Validation Error', 422, $data->messages());
        }

        // 2. Cek jika data kosong (opsional, tergantung kebutuhan)
        if (!$data) {
             return $this->error('Data not found', 404);
        }

        // 3. Default: Return Sukses
        return $this->success($data, $messageSuccess);
    }
}
