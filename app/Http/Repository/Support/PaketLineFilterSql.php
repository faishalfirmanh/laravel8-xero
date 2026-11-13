<?php
namespace App\Http\Repository\Support;
trait PaketLineFilterSql
{
    /**
     * Potongan SQL filter baris item_detail_invoices (alias $alias):
     * item_id IN (...) OR paket_tracking_uuid IN (...)   -> [sql, bindings]
     */
    protected function paketLineFilter(string $alias, array $itemIds, array $trackingUuids): array
    {
        $parts = [];
        $bind = [];

        if (!empty($itemIds)) {
            $parts[] = "{$alias}.item_id IN (" . implode(',', array_fill(0, count($itemIds), '?')) . ')';
            $bind = array_merge($bind, array_values($itemIds));
        }
        if (!empty($trackingUuids)) {
            $parts[] = "{$alias}.paket_tracking_uuid IN (" . implode(',', array_fill(0, count($trackingUuids), '?')) . ')';
            $bind = array_merge($bind, array_values($trackingUuids));
        }

        if (empty($parts)) {
            return ['1 = 0', []];
        }

        return ['(' . implode(' OR ', $parts) . ')', $bind];
    }
}