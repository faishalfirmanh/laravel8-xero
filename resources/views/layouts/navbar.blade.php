<div class="bg-dark border-right text-white" id="sidebar-wrapper" style="width: 250px; min-height: 100vh;">
    <div class="sidebar-heading p-3 text-center"><h5>An namiroh</h5></div>
    <div class="list-group list-group-flush">

        <a href="#transaksiSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Transaksi</a>
        <div class="collapse" id="transaksiSub">
             <a href="{{ route('admin-list-pembelian-hotel') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Penjualan Hotel</a>
            <a href="{{ route('admin-list-invoice') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Pengeluaran Paket</a>
        </div>

        <a href="#masterSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Master Data</a>
        <div class="collapse" id="masterSub">
            <a href="{{ route('admin-master-hotel') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Data Hotel</a>
            <a href="{{ route('admin-master-jamaah') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Data Jamaah / Mitra</a>
            {{-- <a href="{{ route('admin-list-pengeluaran') }}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Master Pengeluaran</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Data Paket</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Data Paket</a> --}}
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
        {{-- <a href="#transaksiSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Transaksi</a>
        <div class="collapse" id="transaksiSub">
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Pembayaran</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Pelunasan</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Refund</a>
        </div>

        <a href="#invSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Inventory</a>
        <div class="collapse" id="invSub">
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Stok Perlengkapan</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Pengadaan Barang</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Distribusi</a>
        </div>

        <a href="#reportSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Laporan</a>
        <div class="collapse" id="reportSub">
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Laporan Harian</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Laporan Bulanan</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Laporan Tahunan</a>
        </div>

        <a href="#settingSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Pengaturan</a>
        <div class="collapse" id="settingSub">
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Manajemen User</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Hak Akses (Roles)</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Konfigurasi Sistem</a>
        </div> --}}
         {{-- <div class="collapse" id="settingSub">
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Manajemen User</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Hak Akses (Roles)</a>
            <a href="#" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Konfigurasi Sistem</a>
        </div> --}}

    </div>
</div>
