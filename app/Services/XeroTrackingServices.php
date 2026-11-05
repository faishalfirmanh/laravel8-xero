<?php

namespace App\Services\Xero;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class XeroTrackingServices
{
    const ENDPOINT = 'https://api.xero.com/api.xro/2.0/TrackingCategories';

    /**
     * Ambil semua tracking category (beserta options) dari Xero.
     *
     * includeArchived = true supaya option lama yang sudah di-archive
     * (mungkin masih dipakai invoice lama) tetap ikut terambil.
     *
     * @return array isi "TrackingCategories"
     */
    public function fetchTrackingCategories($includeArchived = true)
    {
        $credential = $this->getCredential();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credential['access_token'],
            'xero-tenant-id' => $credential['tenant_id'],
            'Accept' => 'application/json',
        ])
            ->timeout(30)
            ->get(self::ENDPOINT, [
                'includeArchived' => $includeArchived ? 'true' : 'false',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Xero TrackingCategories gagal (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $data = $response->json('TrackingCategories');

        return is_array($data) ? $data : [];
    }

    /**
     * GANTI bagian ini dengan cara project kamu mengambil access token + tenant id Xero
     * (mis. dari tabel token / service OAuth yang sudah dipakai sync invoice & webhook).
     */
    private function getCredential()
    {
        return [
            'access_token' => config('services.xero.access_token'),
            'tenant_id' => config('services.xero.tenant_id'),
        ];
    }
}