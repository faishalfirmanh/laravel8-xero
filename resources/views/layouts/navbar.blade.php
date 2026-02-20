<div class="bg-dark border-right text-white" id="sidebar-wrapper" style="width: 250px; min-height: 100vh;">
    <div class="sidebar-heading p-3 text-center"><h5>An namiroh</h5></div>
    <div class="list-group list-group-flush">

        <a href="#transaksiSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">
            Transaksi
        </a>
        <div class="collapse" id="transaksiSub">
            <a href="{{ route('admin-list-pembelian-hotel') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Penjualan Hotel</a>
            <a href="{{ route('admin-list-invoice') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Pengeluaran Paket</a>
            <a href="{{ route('xero-list-transaksi') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">List Transaksi Xero</a>

            <a href="#salesSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small dropdown-toggle">
                Sales
            </a>

            <div class="collapse" id="salesSub">
                <a href="{{route('admin-list-inv-xero-web')}}" class="list-group-item list-group-item-action text-white pl-5 small" style="background-color: #5a6268;">
                    Invoices
                </a>
                <a href="#" class="list-group-item list-group-item-action text-white pl-5 small" style="background-color: #5a6268;">
                    Quotes
                </a>
                <a href="#" class="list-group-item list-group-item-action text-white pl-5 small" style="background-color: #5a6268;">
                    Customers
                </a>
            </div>
            </div>
        <a href="#report_trans" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Report</a>
        <div class="collapse" id="report_trans">
            <a href="{{ route('admin-list-pembelian-hotel') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Penjualan Hotel</a>
            <a href="{{ route('admin-list-invoice') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Pengeluaran Paket</a>
            <a href="{{ route('web-log-history-list') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Log History</a>
        </div>

        <a href="#masterSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Master Data</a>
        <div class="collapse" id="masterSub">
            <a href="{{ route('admin-master-hotel') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Data Hotel</a>
            <a href="{{ route('admin-master-jamaah') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Data Jamaah / Mitra</a>
            <a href="{{ route('admin-list-pengeluaran') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Master Pengeluaran</a>
            <a href="{{ route('maskapai.index') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Master Maskapai</a>
            <a href="{{ route('role-user.index') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Master Role User</a>
        </div>

        <a href="#config_global" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Setting</a>
        <div class="collapse" id="config_global">
            <a href="{{ route('config-currency-web') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Setting Curency</a>
        </div>

        <a href="#accountSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Account</a>
        <div class="collapse" id="accountSub">
            <a data-toggle="modal" data-target="#loginModal" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Login</a>
            <a href="#" id="logout_btn" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Logout</a>
        </div>

    </div>
</div>
