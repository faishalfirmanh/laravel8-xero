<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Xero\InvoicesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExecutiveFinancePivotApiController extends Controller
{
    /**
     * Helper to retrieve all active invoices from cache
     */
    private function getXeroInvoices()
    {
        return Cache::remember("namiroh_exec_invoices_cache_v3", 300, function () {
            try {
                $ctrl = app(InvoicesController::class);
                $resp = $ctrl->listAllInvoices();
                $data = $resp->getData(true);
                return $data["data"] ?? [];
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Helper to get 11 Saudi Cost Components strictly in SAR (SR)
     */
    private function getSaudiExpenses($startDate = null, $endDate = null)
    {
        $saudiCategories = [
            "BIAYA BAKSIS"              => "Biaya Baksis",
            "BIAYA BAKSIS HOTEL"        => "Biaya Baksis Hotel",
            "BIAYA BUS TAMBAHAN"        => "Biaya Bus Tambahan",
            "BIAYA COKLAT & AIR ZAM ZAM"=> "Biaya Coklat & Air Zam Zam",
            "BIAYA GUIDE"               => "Biaya Guide (Muthawwif)",
            "BIAYA KERETA CEPAT"        => "Biaya Kereta Cepat",
            "BIAYA NASI BOX SAUDI"      => "Biaya Nasi Box Saudi",
            "BIAYA BUS SAUDI"           => "Biaya Bus Saudi",
            "BIAYA VISA"                => "Biaya Visa",
            "HANDLING SAUDI"            => "Handling Saudi",
            "BIAYA HOTEL"               => "Biaya Hotel Saudi"
        ];

        $results = [];
        foreach ($saudiCategories as $key => $label) {
            $results[$key] = [
                "name"             => $key,
                "label"            => $label,
                "currency"         => "SR",
                "amount_sr"        => 0.0,
                "amount_formatted" => "0 SR",
                "count"            => 0,
                "items"            => []
            ];
        }

        $matchers = [
            "BIAYA BAKSIS HOTEL"        => "/baksis.*hotel|tips.*hotel/i",
            "BIAYA BAKSIS"              => "/baksis|tips/i",
            "BIAYA BUS TAMBAHAN"        => "/bus.*tambahan|bis.*tambahan/i",
            "BIAYA COKLAT & AIR ZAM ZAM"=> "/coklat|zam.*zam|zamzam/i",
            "BIAYA GUIDE"               => "/guide|muthawwif|mutowif/i",
            "BIAYA KERETA CEPAT"        => "/kereta.*cepat|kereta.*haramain|kereta/i",
            "BIAYA NASI BOX SAUDI"      => "/nasi.*box|catering|konsumsi.*saudi/i",
            "BIAYA BUS SAUDI"           => "/bus.*saudi|bis.*saudi|bus.*makkah|bus.*madinah|transport.*saudi/i",
            "BIAYA VISA"                => "/visa/i",
            "HANDLING SAUDI"            => "/handling.*saudi|handling/i",
            "BIAYA HOTEL"               => "/hotel.*saudi|hotel.*makkah|hotel.*madinah|hotel/i"
        ];

        try {
            // 1. Query from d_package_expenses_xeros (nominal_sar)
            if (DB::getSchemaBuilder()->hasTable("d_package_expenses_xeros") && DB::getSchemaBuilder()->hasTable("master_pengeluaran_pakets")) {
                $qPkg = DB::table("d_package_expenses_xeros as d")
                    ->join("master_pengeluaran_pakets as m", "m.id", "=", "d.pengeluaran_id")
                    ->select("m.nama_pengeluaran", "d.nominal_sar", "d.created_at");

                $pkgRows = $qPkg->get();
                foreach ($pkgRows as $pr) {
                    $pName = trim($pr->nama_pengeluaran ?? "");
                    foreach ($matchers as $mKey => $pattern) {
                        if (preg_match($pattern, $pName)) {
                            $sar = (float)($pr->nominal_sar ?? 0);
                            $results[$mKey]["amount_sr"] += $sar;
                            $results[$mKey]["count"] += 1;
                            $results[$mKey]["items"][] = [
                                "desc"       => $pName,
                                "date"       => substr($pr->created_at ?? date("Y-m-d"), 0, 10),
                                "nominal_sr" => $sar,
                                "formatted"  => number_format($sar, 0, ",", ".") . " SR"
                            ];
                            break;
                        }
                    }
                }
            }

            // 2. Query from d_bills
            if (DB::getSchemaBuilder()->hasTable("d_bills")) {
                $q = DB::table("d_bills as d")
                    ->leftJoin("p_bills as p", "p.id", "=", "d.bills_parent_id")
                    ->select("d.id", "d.desc", "d.unit_price", "d.qty", "p.date_req", "p.reference", "p.total");

                if (!empty($startDate) && !empty($endDate) && DB::getSchemaBuilder()->hasColumn("p_bills", "date_req")) {
                    $q->where("p.date_req", ">=", $startDate)->where("p.date_req", "<=", $endDate);
                }

                $rows = $q->get();
                foreach ($rows as $r) {
                    $descStr = trim($r->desc ?? "");
                    foreach ($matchers as $mKey => $pattern) {
                        if (preg_match($pattern, $descStr)) {
                            $nominalSr = (float)($r->unit_price ?? $r->total ?? 0);
                            $results[$mKey]["amount_sr"] += $nominalSr;
                            $results[$mKey]["count"] += 1;
                            $results[$mKey]["items"][] = [
                                "desc"       => $r->desc,
                                "date"       => $r->date_req ?? date("Y-m-d"),
                                "nominal_sr" => $nominalSr,
                                "formatted"  => number_format($nominalSr, 0, ",", ".") . " SR"
                            ];
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {}

        // Format all amounts strictly in SR
        foreach ($results as $k => &$item) {
            $item["amount_formatted"] = number_format($item["amount_sr"], 0, ",", ".") . " SR";
        }

        return $results;
    }

    /**
     * Date Range Report API (Machine Text JSON)
     */
    public function rangeReport(Request $request)
    {
        $startDate = $request->input("start_date");
        $endDate = $request->input("end_date") ?? date("Y-m-d");
        $segment = $request->input("segment", "all");
        $reportType = $request->input("report_type", "all");
        $limit = (int) $request->input("limit", 50);

        $useDate = (!empty($startDate) && !empty($endDate));

        $totalKredit = 0.0;
        $totalDebit = 0.0;
        $totalDue = 0.0;
        $totalJamaah = 0;
        $items = [];
        $packagesMap = [];
        $agentsMap = [];

        try {
            $invoices = $this->getXeroInvoices();

            foreach ($invoices as $inv) {
                $status = strtoupper($inv["Status"] ?? "");
                if (in_array($status, ["DELETED", "VOIDED"])) {
                    continue;
                }

                $type = strtoupper($inv["Type"] ?? "");
                $paid = (float)($inv["AmountPaid"] ?? 0);
                $due = (float)($inv["AmountDue"] ?? 0);
                $total = (float)($inv["Total"] ?? 0);
                $invDate = substr($inv["DateString"] ?? "", 0, 10);

                if ($useDate) {
                    if (!empty($invDate) && ($invDate < $startDate || $invDate > $endDate)) {
                        continue;
                    }
                }

                $contactName = $inv["Contact"]["Name"] ?? "Jamaah Namiroh";
                $noInv = !empty($inv["InvoiceNumber"]) ? $inv["InvoiceNumber"] : ("INV-" . substr($inv["InvoiceID"] ?? "", 0, 6));
                $pkgName = !empty($inv["Reference"]) ? trim($inv["Reference"]) : "Lain-lain / Tanpa Paket";

                // Format date dd-mm-yyyy
                $dateDmy = $invDate;
                if (!empty($invDate) && strpos($invDate, "-") !== false) {
                    $dp = explode("-", $invDate);
                    if (count($dp) === 3) {
                        $dateDmy = "{$dp[2]}-{$dp[1]}-{$dp[0]}";
                    }
                }

                if ($type === "ACCREC") {
                    $totalKredit += $paid;
                    $totalDue += $due;
                    $totalJamaah++;

                    // Package grouping
                    if (!isset($packagesMap[$pkgName])) {
                        $packagesMap[$pkgName] = [
                            "package_name"   => $pkgName,
                            "income"         => 0.0,
                            "due"            => 0.0,
                            "total"          => 0.0,
                            "jamaah_count"   => 0,
                            "invoices"       => []
                        ];
                    }
                    $packagesMap[$pkgName]["income"] += $paid;
                    $packagesMap[$pkgName]["due"] += $due;
                    $packagesMap[$pkgName]["total"] += $total;
                    $packagesMap[$pkgName]["jamaah_count"] += 1;
                    $packagesMap[$pkgName]["invoices"][] = [
                        "no_invoice"   => $noInv,
                        "nama_jamaah"  => $contactName,
                        "tanggal"      => $dateDmy,
                        "nominal"      => $total,
                        "amount_paid"  => $paid,
                        "amount_due"   => $due,
                        "status"       => $status
                    ];

                    // Agent / Contact grouping
                    if (!isset($agentsMap[$contactName])) {
                        $agentsMap[$contactName] = [
                            "agent_name"   => $contactName,
                            "income"       => 0.0,
                            "due"          => 0.0,
                            "jamaah_count" => 0,
                            "invoices"     => []
                        ];
                    }
                    $agentsMap[$contactName]["income"] += $paid;
                    $agentsMap[$contactName]["due"] += $due;
                    $agentsMap[$contactName]["jamaah_count"] += 1;
                    $agentsMap[$contactName]["invoices"][] = [
                        "no_invoice"   => $noInv,
                        "paket"        => $pkgName,
                        "tanggal"      => $dateDmy,
                        "amount_paid"  => $paid,
                        "amount_due"   => $due,
                        "status"       => $status
                    ];
                } elseif ($type === "ACCPAY") {
                    $totalDebit += $paid;
                }

                $items[] = [
                    "id"          => $inv["InvoiceID"] ?? "",
                    "no_invoice"  => $noInv,
                    "keterangan"  => $contactName . ($pkgName ? " ({$pkgName})" : ""),
                    "nama_jamaah" => $contactName,
                    "agent_name"  => $contactName,
                    "created_at"  => $dateDmy,
                    "nominal"     => $total,
                    "amount_paid" => $paid,
                    "amount_due"  => $due,
                    "tanda"       => ($type === "ACCREC") ? "+" : "-",
                    "kredit"      => ($type === "ACCREC") ? $paid : 0,
                    "debit"       => ($type === "ACCPAY") ? $paid : 0,
                    "metode"      => !empty($inv["Reference"]) ? $inv["Reference"] : "Transfer Bank",
                    "status"      => $status,
                    "package_name"=> $pkgName,
                    "segment"     => "namiroh"
                ];
            }
        } catch (\Exception $e) {
            return response()->json(["status" => "error", "code" => 500, "message" => "Query error: " . $e->getMessage()], 500);
        }

        $netBalance = $totalKredit - $totalDebit;
        $totalFound = count($items);

        // Sort items by created_at DESC
        usort($items, function ($a, $b) {
            return strcmp($b["created_at"], $a["created_at"]);
        });

        if ($limit > 0 && count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        // Format and sort packages list
        $packagesList = array_values($packagesMap);
        usort($packagesList, function ($a, $b) {
            return $b["income"] <=> $a["income"];
        });

        foreach ($packagesList as &$pkg) {
            $pkg["income_formatted"] = "Rp " . number_format($pkg["income"], 0, ",", ".");
            $pkg["due_formatted"]    = "Rp " . number_format($pkg["due"], 0, ",", ".");
            $pkg["total_formatted"]  = "Rp " . number_format($pkg["total"], 0, ",", ".");
        }

        // Get 11 Saudi Cost Components strictly in SR
        $saudiExpenses = $this->getSaudiExpenses($startDate, $endDate);

        return response()->json([
            "status"     => "success",
            "code"       => 200,
            "start_date" => $startDate,
            "end_date"   => $endDate,
            "segment"    => $segment,
            "report_type"=> $reportType,
            "summary"    => [
                "total_kredit"          => $totalKredit,
                "total_debit"           => $totalDebit,
                "total_due"             => $totalDue,
                "net_balance"           => $netBalance,
                "total_jamaah"          => $totalJamaah,
                "total_packages"        => count($packagesList),
                "total_agents"          => count($agentsMap),
                "total_kredit_formatted"=> "Rp " . number_format($totalKredit, 0, ",", "."),
                "total_debit_formatted" => "Rp " . number_format($totalDebit, 0, ",", "."),
                "total_due_formatted"   => "Rp " . number_format($totalDue, 0, ",", "."),
                "net_balance_formatted" => "Rp " . number_format($netBalance, 0, ",", "."),
                "transaction_count"     => $totalFound
            ],
            "packages"       => $packagesList,
            "agents"         => array_values($agentsMap),
            "saudi_expenses" => array_values($saudiExpenses),
            "data"           => $items
        ]);
    }

    /**
     * Multi-Dimensional Pivot Matrix API
     */
    public function pivot(Request $request)
    {
        $dimension1 = $request->input("dimension_1", "month");
        $dimension2 = $request->input("dimension_2");
        $measure = $request->input("measure", "net_balance");
        $segment = $request->input("segment", "all");
        $startDate = $request->input("start_date");
        $endDate = $request->input("end_date") ?? date("Y-m-d");

        $matrix = [];
        $grandTotal = 0.0;
        $useDate = (!empty($startDate) && !empty($endDate));

        try {
            $invoices = $this->getXeroInvoices();
            $grouped = [];

            foreach ($invoices as $inv) {
                $status = strtoupper($inv["Status"] ?? "");
                if (in_array($status, ["DELETED", "VOIDED"])) continue;

                $invDate = substr($inv["DateString"] ?? "", 0, 10);
                if ($useDate && ($invDate < $startDate || $invDate > $endDate)) continue;

                $type = strtoupper($inv["Type"] ?? "");
                $paid = (float)($inv["AmountPaid"] ?? 0);
                $val = ($type === "ACCREC") ? $paid : -$paid;

                $key = "Lainnya";
                if ($dimension1 === "month") {
                    $key = !empty($invDate) ? substr($invDate, 0, 7) : "Unknown";
                } elseif ($dimension1 === "payment_method") {
                    $key = $inv["Reference"] ?? "Bank";
                } else {
                    $key = $type;
                }

                if (!isset($grouped[$key])) $grouped[$key] = 0.0;
                $grouped[$key] += $val;
                $grandTotal += $val;
            }

            foreach ($grouped as $dim => $v) {
                $matrix[] = [
                    "dimension" => $dim,
                    "value"     => (float) $v,
                    "formatted" => "Rp " . number_format((float) $v, 0, ",", ".")
                ];
            }
        } catch (\Exception $e) {
            return response()->json(["status" => "error", "code" => 500, "message" => "Pivot error: " . $e->getMessage()], 500);
        }

        return response()->json([
            "status"       => "success",
            "code"         => 200,
            "dimension_1"  => $dimension1,
            "dimension_2"  => $dimension2,
            "measure"      => $measure,
            "grand_total"  => $grandTotal,
            "grand_total_formatted" => "Rp " . number_format($grandTotal, 0, ",", "."),
            "matrix"       => $matrix
        ]);
    }

    /**
     * Overall Financial Summary API
     */
    public function summaryAll(Request $request)
    {
        $repReq = new Request(["segment" => "all", "report_type" => "all"]);
        $repData = $this->rangeReport($repReq)->getData(true);

        return response()->json([
            "status"     => "success",
            "code"       => 200,
            "server_time"=> date("Y-m-d H:i:s"),
            "segmen_1_kolektif" => $repData["summary"] ?? [],
            "segmen_2_kantor"   => $repData["summary"] ?? []
        ]);
    }
    /**
     * List COA Summaries for 25 Kas Categories
     */
    public function listCoas(Request $request)
    {
        $startDate = $request->input("start_date");
        $endDate = $request->input("end_date");
        $useDate = (!empty($startDate) && !empty($endDate));

        $transSummary = [];

        try {
            if (DB::getSchemaBuilder()->hasTable("coas")) {
                // 1. Ambil dari transaction_all_coas
                if (DB::getSchemaBuilder()->hasTable("transaction_all_coas")) {
                    $q = DB::table("transaction_all_coas as t")
                        ->join("coas as c", function($j) {
                            $j->on("c.id", "=", "t.uuid_coa")
                              ->orOn("c.code", "=", "t.uuid_coa")
                              ->orOn("c.account_uuid", "=", "t.uuid_coa");
                        })
                        ->select("c.code", "c.name", DB::raw("SUM(t.nominal) as total_nominal"))
                        ->groupBy("c.code", "c.name");

                    if ($useDate && DB::getSchemaBuilder()->hasColumn("transaction_all_coas", "date_transaction")) {
                        $q->where("t.date_transaction", ">=", $startDate)->where("t.date_transaction", "<=", $endDate);
                    }

                    $rows = $q->get();
                    foreach ($rows as $r) {
                        $code = strval($r->code);
                        if (!isset($transSummary[$code])) {
                            $transSummary[$code] = ["code" => $code, "name" => $r->name, "total_nominal" => 0.0];
                        }
                        $transSummary[$code]["total_nominal"] += (float)($r->total_nominal ?? 0);
                    }
                }

                // 2. Ambil dari d_bills (account_id_coa)
                if (DB::getSchemaBuilder()->hasTable("d_bills")) {
                    $qBills = DB::table("d_bills as d")
                        ->join("coas as c", function($j) {
                            $j->on("c.id", "=", "d.account_id_coa")
                              ->orOn("c.code", "=", "d.account_id_coa");
                        })
                        ->select("c.code", "c.name", DB::raw("SUM(d.unit_price * IFNULL(d.qty, 1)) as total_nominal"))
                        ->groupBy("c.code", "c.name")
                        ->get();

                    foreach ($qBills as $qb) {
                        $code = strval($qb->code);
                        if (!isset($transSummary[$code])) {
                            $transSummary[$code] = ["code" => $code, "name" => $qb->name, "total_nominal" => 0.0];
                        }
                        $transSummary[$code]["total_nominal"] += (float)($qb->total_nominal ?? 0);
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json(["status" => "error", "code" => 500, "message" => $e->getMessage()], 500);
        }

        return response()->json([
            "status" => "success",
            "code"   => 200,
            "trans_coa_summary" => array_values($transSummary)
        ]);
    }

    /**
     * Endpoint API Matriks Profit & Loss Per Paket Umroh (100% Pure Live Database Xero)
     * Mendukung query 2 arah: baris difilter kolom, kolom difilter baris, atau kombinasi keduanya,
     * serta laporan pos data yang belum tersedia di database Xero.
     */
    public function packageProfitLossMatrix(\Illuminate\Http\Request $request)
    {
        try {
            $startDate = $request->input('date_start', $request->input('start_date', '2026-09-01'));
            $endDate = $request->input('date_end', $request->input('end_date', '2026-09-30'));
            $packageName = trim((string)($request->input('package_name', '') ?: $request->input('package_date', '')));
            $accountFilter = trim((string)($request->input('account_name', '') ?: $request->input('cost_element', '')));

            $dateStart = \Illuminate\Support\Carbon::parse($startDate)->startOfDay();
            $dateEndExclusive = \Illuminate\Support\Carbon::parse($endDate)->addDay()->startOfDay();

            // 1. Ambil daftar paket umroh dari tabel items_paket_all_from_xeros
            $pkgQuery = DB::table('items_paket_all_from_xeros as ip');
            if (!empty($packageName)) {
                $pkgQuery->where('ip.nama_paket', 'like', "%{$packageName}%");
            }
            $packagesList = $pkgQuery->orderBy('ip.nama_paket', 'asc')->pluck('ip.nama_paket')->toArray();

            // 2. Query Invoice Penjualan Paket (Trading Income & Pax)
            $invQuery = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->join('item_detail_invoices as d', 'd.uuid_detail_inv', '=', 't.uuid_detail')
                ->join('items_paket_all_from_xeros as ip', 'ip.id', '=', 'd.item_id')
                ->where('t.date_transaction', '>=', $dateStart)
                ->where('t.date_transaction', '<', $dateEndExclusive)
                ->select(
                    'ip.nama_paket',
                    'c.code as coa_code',
                    'c.name as coa_name',
                    'c.account_type',
                    't.is_speend',
                    't.base_nominal',
                    'd.qty'
                );
            if (!empty($packageName)) {
                $invQuery->where('ip.nama_paket', 'like', "%{$packageName}%");
            }
            $invData = $invQuery->get();

            // 3. Query Tagihan Vendor & HPP (Cost of Sales)
            $billQuery = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->join('d_bills as d', 'd.uuid_detail', '=', 't.uuid_detail')
                ->join('items_paket_all_from_xeros as ip', function($j) {
                    $j->on('ip.code', '=', 'd.item_code')
                      ->orOn('ip.id', '=', 'd.item_id_prod_serv');
                })
                ->where('t.date_transaction', '>=', $dateStart)
                ->where('t.date_transaction', '<', $dateEndExclusive)
                ->select(
                    'ip.nama_paket',
                    'c.code as coa_code',
                    'c.name as coa_name',
                    'c.account_type',
                    't.is_speend',
                    't.base_nominal'
                );
            if (!empty($packageName)) {
                $billQuery->where('ip.nama_paket', 'like', "%{$packageName}%");
            }
            $billData = $billQuery->get();

            // 4. Query Transaksi Bank Operasional
            $bankQuery = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->join('transaction_bank_trans_d_s as d', 'd.uuid_detail_trans_bank', '=', 't.uuid_detail')
                ->join('items_paket_all_from_xeros as ip', 'ip.id', '=', 'd.item_code')
                ->where('t.date_transaction', '>=', $dateStart)
                ->where('t.date_transaction', '<', $dateEndExclusive)
                ->select(
                    'ip.nama_paket',
                    'c.code as coa_code',
                    'c.name as coa_name',
                    'c.account_type',
                    't.is_speend',
                    't.base_nominal'
                );
            if (!empty($packageName)) {
                $bankQuery->where('ip.nama_paket', 'like', "%{$packageName}%");
            }
            $bankData = $bankQuery->get();

            // 5. Agregasi matriks dinamis
            $activePackages = [];
            $allAccounts = [];
            $matrix = [
                'JUMLAH PAX' => ['Total' => 0.0],
                'Total Trading Income' => ['Total' => 0.0],
                'Total Cost of Sales' => ['Total' => 0.0],
                'Gross Profit' => ['Total' => 0.0],
                'Total Operating Expenses' => ['Total' => 0.0],
                'Net Profit' => ['Total' => 0.0],
                'LABA PER PAX' => ['Total' => 0.0]
            ];

            $addMatrix = function($acct, $pkg, $amount) use (&$matrix, &$allAccounts, &$activePackages) {
                if (!isset($matrix[$acct])) {
                    $matrix[$acct] = ['Total' => 0.0];
                }
                if (!isset($matrix[$acct][$pkg])) {
                    $matrix[$acct][$pkg] = 0.0;
                }
                $matrix[$acct][$pkg] += (float)$amount;
                $matrix[$acct]['Total'] += (float)$amount;

                if (!in_array($acct, $allAccounts)) {
                    $allAccounts[] = $acct;
                }
                if (!in_array($pkg, $activePackages) && $pkg !== 'Total') {
                    $activePackages[] = $pkg;
                }
            };

            // Hitung Pax dan Trading Income
            foreach ($invData as $row) {
                $pkg = $row->nama_paket;
                $acct = strtoupper(trim($row->coa_name));
                $nom = (float)$row->base_nominal;
                $qty = (float)($row->qty ?? 0);

                if ($qty > 0) {
                    $addMatrix('JUMLAH PAX', $pkg, $qty);
                }

                $amount = ($row->is_speend == 0) ? $nom : -$nom;
                $addMatrix($acct, $pkg, $amount);
                $addMatrix('Total Trading Income', $pkg, $amount);
            }

            // Hitung Cost of Sales dari Bills
            foreach ($billData as $row) {
                $pkg = $row->nama_paket;
                $acct = strtoupper(trim($row->coa_name));
                $nom = (float)$row->base_nominal;
                $amount = ($row->is_speend == 1) ? $nom : -$nom;

                $addMatrix($acct, $pkg, $amount);
                $addMatrix('Total Cost of Sales', $pkg, $amount);
            }

            // Hitung Beban dari Bank
            foreach ($bankData as $row) {
                $pkg = $row->nama_paket;
                $acct = strtoupper(trim($row->coa_name));
                $nom = (float)$row->base_nominal;

                if ($row->account_type === 'DIRECTCOSTS') {
                    $amount = ($row->is_speend == 1) ? $nom : -$nom;
                    $addMatrix($acct, $pkg, $amount);
                    $addMatrix('Total Cost of Sales', $pkg, $amount);
                } else {
                    $amount = ($row->is_speend == 1) ? $nom : -$nom;
                    $addMatrix($acct, $pkg, $amount);
                    $addMatrix('Total Operating Expenses', $pkg, $amount);
                }
            }

            // Hitung Gross Profit, Net Profit, Laba per Pax
            $allActive = array_merge($activePackages, ['Total']);
            foreach ($allActive as $pkg) {
                $inc = (float)($matrix['Total Trading Income'][$pkg] ?? 0);
                $cos = (float)($matrix['Total Cost of Sales'][$pkg] ?? 0);
                $gp = $inc - $cos;
                $matrix['Gross Profit'][$pkg] = $gp;

                $opex = (float)($matrix['Total Operating Expenses'][$pkg] ?? 0);
                $np = $gp - $opex;
                $matrix['Net Profit'][$pkg] = $np;

                $pax = (float)($matrix['JUMLAH PAX'][$pkg] ?? 0);
                $matrix['LABA PER PAX'][$pkg] = ($pax > 0) ? ($np / $pax) : 0.0;
            }

            sort($activePackages);
            $finalPackages = array_merge($activePackages, ['Total']);

            // 6. Laporan Data yang Tidak Tersedia di Database Xero (Sesuai Permintaan Email #238)
            $unavailableInXero = [
                [
                    'name' => 'BELUM MASUK XERO (ACC Penyesuaian)',
                    'status' => 'Tidak tersedia di Xero',
                    'nominal' => 3900000,
                    'notes' => 'Catatan penyesuaian manual tim akuntansi, belum diinput ke jurnal Xero.'
                ],
                [
                    'name' => 'Pembayaran paket tambahan - thaif dll',
                    'status' => 'Belum ada akun COA',
                    'nominal' => 0,
                    'notes' => 'Akun COA tersendiri belum dibuat di sistem akuntansi Xero.'
                ],
                [
                    'name' => 'Pembelian barang selain perlengkapan',
                    'status' => 'Belum ada akun COA',
                    'nominal' => 0,
                    'notes' => 'Akun COA tersendiri belum dibuat di sistem akuntansi Xero.'
                ],
                [
                    'name' => 'Pembayaran air dan coklat',
                    'status' => 'Belum ada akun COA',
                    'nominal' => 0,
                    'notes' => 'Akun COA tersendiri belum dibuat di sistem akuntansi Xero.'
                ],
                [
                    'name' => 'Pembayaran biaya marketing',
                    'status' => 'Belum ada akun COA',
                    'nominal' => 0,
                    'notes' => 'Akun COA tersendiri belum dibuat di sistem akuntansi Xero.'
                ]
            ];

            // 7. Penanganan filter 2 arah jika accountFilter / cost_element diberikan
            if (!empty($accountFilter)) {
                $filteredAccounts = [];
                $filteredMatrix = [];
                foreach ($matrix as $acct => $cols) {
                    if (stripos($acct, $accountFilter) !== false || $acct === 'Total Trading Income' || $acct === 'Total Cost of Sales' || $acct === 'Net Profit' || $acct === 'JUMLAH PAX') {
                        $filteredAccounts[] = $acct;
                        $filteredMatrix[$acct] = $cols;
                    }
                }
                if (!empty($filteredAccounts)) {
                    $matrix = $filteredMatrix;
                    $allAccounts = $filteredAccounts;
                }
            }

            return response()->json([
                'status'              => 'success',
                'code'                => 200,
                'source'              => 'Live MySQL Database Xero (u2314635_laravel8_xero)',
                'title'               => "Profit and Loss PT An Namiroh Travelindo ({$startDate} s/d {$endDate})",
                'period'              => "{$startDate} s/d {$endDate}",
                'filter_applied'      => [
                    'package_filter' => $packageName ?: 'Semua Paket',
                    'account_filter' => $accountFilter ?: 'Semua Pos Biaya'
                ],
                'packages'            => $finalPackages,
                'accounts'            => $allAccounts,
                'matrix'              => $matrix,
                'unavailable_in_xero' => $unavailableInXero
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Gagal mengagregasi data Xero: ' . $e->getMessage()
            ], 500);
        }
    }

}
