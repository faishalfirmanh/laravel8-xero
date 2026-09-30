@extends('layouts.app')

@section('content')
<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Product Unit of Measure</h5>
        <button class="btn btn-primary" id="btnTambah">
            <i class="ti ti-plus"></i> Tambah UOM
        </button>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered" id="productUomTable">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Conversion factor</th>
                    <th>Sell price</th>
                    <th width="12%">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="productUomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productUomModalTitle">Tambah UOM</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="productUomForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="productUomId" name="id">
                    <div class="form-group">
                        <label for="itemId">Product</label>
                        <select class="form-control" id="itemId" name="item_id" required></select>
                    </div>
                    <div class="form-group">
                        <label for="unitSatuanId">Unit</label>
                        <select class="form-control" id="unitSatuanId" name="unit_satuan_id" required></select>
                    </div>
                    <div class="form-group">
                        <label for="conversionFactor">Conversion factor</label>
                        <input type="number" class="form-control" id="conversionFactor" name="conversion_factor" min="0.0001" step="any" required>
                    </div>
                    <div class="form-group">
                        <label for="sellPrice">Sell price</label>
                        <input type="number" class="form-control" id="sellPrice" name="sell_price" min="0" step="any" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveProductUom">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    let table;

    const columns = [
        {
            data: null,
            className: 'text-center',
            render: function (data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            }
        },
        {
            data: 'item',
            render: function (item) {
                return item ? `${item.nama_paket} <small class="text-muted">(${item.code || '-'})</small>` : '-';
            }
        },
        { data: 'unit.name', defaultContent: '-' },
        {   data: 'conversion_factor',
            render: function(data){
              return forCur(data)
        } ,defaultContent: '-' },
        { data: 'sell_price',render:function(data){
            return formatCurrency(data)
        }, defaultContent: '-' },
        {
            data: 'id',
            className: 'text-center',
            orderable: false,
            searchable: false,
            render: function (id) {
                return `<a href="#" class="text-primary edit-product-uom" data-id="${id}"><i class="ti ti-pencil"></i></a>`;
            }
        }
    ];

    table = initGlobalDataTableTokenSelected(
        '#productUomTable',
        `{{ route('getAllPaginateUom') }}`,
        columns,
        { kolom_name: 'id' }
    );

    function initSelect(element, url, placeholder, processText) {
        $(element).select2({
            dropdownParent: $('#productUomModal'),
            allowClear: true,
            placeholder: placeholder,
            ajax: {
                url: url,
                type: 'GET',
                dataType: 'json',
                delay: 250,
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('token')
                },
                data: function (params) {
                    return { keyword: params.term || '', page: params.page || 1, limit: 20, kolom_name: 'name' };
                },
                processResults: function (response, params) {
                    params.page = params.page || 1;
                    const pageData = response.data && response.data.data ? response.data : response.data;
                    const rows = pageData && pageData.data ? pageData.data : [];
                    return {
                        results: rows.map(function (row) {
                            return { id: row.id, text: processText(row) };
                        }),
                        pagination: { more: !!(pageData && pageData.next_page_url) }
                    };
                }
            }
        });
    }

    initSelect('#itemId', `{{ route('getProductUomItems') }}`, 'Pilih product', function (row) {
        return `${row.nama_paket} (${row.code || '-'})`;
    });
    initSelect('#unitSatuanId', `{{ route('get-all-unit') }}`, 'Pilih unit', function (row) {
        return row.name;
    });

    function resetForm() {
        $('#productUomForm')[0].reset();
        $('#productUomId').val('');
        $('#itemId, #unitSatuanId').val(null).trigger('change');
        $('#productUomModalTitle').text('Tambah UOM');
    }

    $('#btnTambah').on('click', function () {
        resetForm();
        $('#productUomModal').modal('show');
    });

    $('#productUomTable').on('click', '.edit-product-uom', function (event) {
        event.preventDefault();
        const id = $(this).data('id');
        resetForm();
        ajaxRequest(`{{ route('detailItemUom') }}`, 'GET', { id: id }, localStorage.getItem('token'))
            .then(function (response) {
                const row = response.data.data;
                $('#productUomId').val(row.id);
                $('#conversionFactor').val(row.conversion_factor);
                $('#sellPrice').val(row.sell_price);
                $('#itemId').append(new Option(`${row.item.nama_paket} (${row.item.code || '-'})`, row.item_id, true, true)).trigger('change');
                $('#unitSatuanId').append(new Option(row.unit.name, row.unit_satuan_id, true, true)).trigger('change');
                $('#productUomModalTitle').text('Edit UOM');
                $('#productUomModal').modal('show');
            })
            .catch(function (error) { cathError(error); });
    });

    $('#productUomForm').on('submit', function (event) {
        event.preventDefault();
        const form = this;
        const payload = {
            id: $('#productUomId').val() || null,
            item_id: $('#itemId').val(),
            unit_satuan_id: $('#unitSatuanId').val(),
            conversion_factor: $('#conversionFactor').val(),
            sell_price: $('#sellPrice').val()
        };

        $('#btnSaveProductUom').prop('disabled', true);
        ajaxRequest(`{{ route('save-item-productuom') }}`, 'POST', payload, localStorage.getItem('token'))
            .then(function () {
                $('#productUomModal').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Product UOM berhasil disimpan.' });
            })
            .catch(function (error) { cathError(error); })
            .finally(function () { $('#btnSaveProductUom').prop('disabled', false); });
    });
});
</script>
@endpush
