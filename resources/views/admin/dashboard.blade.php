@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header">Dashboard Utama</div>
    <div class="card-body">
        <h4>Selamat Datang, Admin!</h4>
        <hr>

        <div class="form-group">
            <label>Pilih Cabang (Contoh Select2):</label>
            <select class="form-control select2-custom" style="width: 100%">
                <option value="">-- Pilih --</option>
                @foreach($agen_list as $agen)
                    <option value="{{ $agen['id'] }}">{{ $agen['nama'] }}</option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-primary" id="btn-test-alert">Test SweetAlert</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Inisialisasi Select2
        $('.select2-custom').select2({
            placeholder: "Cari agen...",
            allowClear: true
        });

        // Event SweetAlert
        $('#btn-test-alert').on('click', function() {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Library SweetAlert2 bekerja dengan baik di Laravel 8.',
                icon: 'success',
                confirmButtonText: 'Mantap'
            });
        });
    });
</script>
@endpush
