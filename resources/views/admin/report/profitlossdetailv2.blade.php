@extends('layouts.app')

@section('content')
<style>
.detail-back { font-size: 13px; color: #0070c4; text-decoration: none; }
.detail-back:hover { text-decoration: underline; }
.detail-header { margin: 14px 0 20px; }
.detail-header h2 { font-size: 20px; margin: 0 0 4px; }
.detail-header .period { font-size: 12px; color: #666; }
.detail-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.detail-table th {
    text-align: left; background: #f5f5f5; padding: 8px 10px;
    border-bottom: 1px solid #d8d8d8; font-weight: 600;
}
.detail-table td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; }
.detail-table td.amt { text-align: right; font-variant-numeric: tabular-nums; }
.detail-table td.amt.negative { color: #c0392b; }
.detail-table td.amt.positive { color: #1a6abf; }
.detail-total td {
    font-weight: 600; border-top: 2px solid #333; background: #fafafa;
}
</style>

<a href="{{ url()->previous() }}" class="detail-back">&larr; Kembali ke Profit and Loss</a>

<div class="detail-header">
    <h2>{{ $coa->name }}</h2>
    <div class="period">
        {{ \Carbon\Carbon::parse($dateStart)->isoFormat('D MMM YYYY') }}
        &ndash;
        {{ \Carbon\Carbon::parse($dateEnd)->isoFormat('D MMM YYYY') }}
    </div>
</div>

<table class="detail-table">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th style="text-align:right;">Nominal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions as $t)
            <tr>
                <td>{{ \Carbon\Carbon::parse($t->date_transaction)->format('d M Y') }}</td>
                <td class="amt {{ $t->signed_nominal < 0 ? 'negative' : 'positive' }}">
                    {{ $t->signed_nominal < 0 ? '(' : '' }}{{ number_format(abs($t->signed_nominal), 2) }}{{ $t->signed_nominal < 0 ? ')' : '' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="2">Tidak ada transaksi pada periode ini.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="detail-total">
            <td>Total</td>
            <td class="amt">
                {{ $total < 0 ? '(' : '' }}{{ number_format(abs($total), 2) }}{{ $total < 0 ? ')' : '' }}
            </td>
        </tr>
    </tfoot>
</table>
@endsection