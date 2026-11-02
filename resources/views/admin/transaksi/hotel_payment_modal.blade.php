<style>
#hpPaymentModal .modal-dialog { max-width: 860px; }
#hpPaymentModal .modal-content { max-height: calc(100vh - 40px); display: flex; flex-direction: column; }
#hpPaymentModal .modal-body { overflow-y: auto; overflow-x: hidden; flex: 1 1 auto; }
#hpPaymentModal td .select2-container { width: 100% !important; }
#hpPaymentModal .select2-container--bootstrap4 .select2-selection--single {
    height: 31px !important; display: flex !important; align-items: center !important;
    position: relative !important; border: 1px solid #ced4da !important; border-radius: 4px !important;
}
#hpPaymentModal .select2-selection__rendered {
    display: block !important; padding-left: 8px !important; padding-right: 42px !important;
    line-height: 29px !important; font-size: 12px !important; overflow: hidden !important;
    text-overflow: ellipsis !important; white-space: nowrap !important;
}
#hpPaymentModal .select2-selection__clear {
    position: absolute !important; right: 22px !important; top: 50% !important; transform: translateY(-50%) !important;
}
#hpPaymentModal .select2-selection__arrow {
    position: absolute !important; right: 2px !important; top: 0 !important; height: 29px !important;
}
</style>

<div class="modal fade" id="hpPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa fa-money-bill-wave mr-2"></i> Pembayaran Invoice Hotel</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="hp_inv_id">
        <input type="hidden" id="hp_row_id">

        <div class="text-center mb-3">
          <h5 class="font-weight-bold mb-1" id="hpContactName"></h5>
          <span class="badge badge-light border px-3 py-2">
            No Invoice: <span id="hpInvNumber" class="font-weight-bold text-primary">...</span>
          </span>
        </div>

        <div class="row mb-3 text-center">
          <div class="col-md-4"><div class="card border-primary mb-2"><div class="card-body py-2">
            <small class="text-muted font-weight-bold text-uppercase">Total Tagihan</small>
            <h5 class="font-weight-bold text-primary mb-0" id="hpTotal">0</h5>
          </div></div></div>
          <div class="col-md-4"><div class="card border-success mb-2"><div class="card-body py-2">
            <small class="text-muted font-weight-bold text-uppercase">Sudah Dibayar</small>
            <h5 class="font-weight-bold text-success mb-0" id="hpPaid">0</h5>
          </div></div></div>
          <div class="col-md-4"><div class="card border-danger mb-2"><div class="card-body py-2">
            <small class="text-muted font-weight-bold text-uppercase">Sisa Kekurangan</small>
            <h5 class="font-weight-bold text-danger mb-0" id="hpRemaining">0</h5>
          </div></div></div>
        </div>

        <h6 class="font-weight-bold mb-2"><i class="fa fa-history mr-1"></i> Riwayat Pembayaran</h6>
        <div class="table-responsive mb-3">
          <table class="table table-bordered table-striped table-sm text-center">
            <thead class="thead-dark">
              <tr>
                <th width="5%">No</th><th>Tanggal</th><th>Nama Bank</th>
                <th>Keterangan / Ref</th><th>Nominal</th><th width="12%">Aksi</th>
              </tr>
            </thead>
            <tbody id="hpHistoryBody">
              <tr><td colspan="6" class="text-muted">Belum ada data pembayaran</td></tr>
            </tbody>
          </table>
        </div>

        <div class="card bg-light">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="font-weight-bold text-primary mb-0" id="hpFormTitle">
                <i class="fa fa-plus-circle mr-1"></i> Tambah Pembayaran Baru
              </h6>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="hpBtnCancelEdit" style="display:none;">
                <i class="fa fa-times mr-1"></i> Batal Edit
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-1" style="font-size:12px;">
                <thead class="thead-dark">
                  <tr>
                    <th style="width:150px;">Tanggal Bayar</th>
                    <th style="min-width:240px;">Bank</th>
                    <th style="width:150px;">Nominal (<span class="hp-cur">SAR</span>)</th>
                    <th style="min-width:120px;">Catatan / Ref</th>
                    <th style="width:36px;"></th>
                  </tr>
                </thead>
                <tbody id="hpRowsBody"></tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="hpBtnAddRow">
                <i class="fa fa-plus mr-1"></i> Add Row
              </button>
              <button type="button" class="btn btn-success btn-sm font-weight-bold" id="hpBtnSave">
                <i class="fa fa-save mr-1"></i> <span id="hpBtnSaveLabel">Simpan Pembayaran</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-white">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fa fa-times mr-1"></i> Tutup
        </button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
    const routes = {
        detail: "{{ route('detail-sales-inv') }}",
        save  : "{{ route('save-pay-sales-inv') }}",
        update: "{{ route('update-pay-sales-inv') }}",
        remove: "{{ route('delete-pay-sales-inv') }}",
        unlock: "{{ route('unlock-sales-inv') }}",
        bank  : "{{ route('getbankselect2') }}",
    };
    const token = () => localStorage.getItem('token');

    let mode = 'new', cur = 'SAR', defaultRef = '', locked = false;

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function today() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function reloadHotelTable() {
        if ($.fn.DataTable.isDataTable('#tableHotel')) $('#tableHotel').DataTable().ajax.reload(null, false);
    }

    // ── baris form ──
    function rowHtml(d) {
        d = d || {};
        return `
        <tr>
          <td><input type="date" class="form-control form-control-sm hp-date" value="${esc(d.date || today())}"></td>
          <td><select class="form-control form-control-sm hp-bank" style="width:100%;"></select></td>
          <td><input type="number" class="form-control form-control-sm hp-nominal" min="0.01" step="any" placeholder="0" value="${esc(d.nominal || '')}"></td>
          <td><input type="text" class="form-control form-control-sm hp-ref" placeholder="wajib diisi" value="${esc(d.reference != null ? d.reference : defaultRef)}"></td>
          <td class="text-center">
            <button type="button" class="hp-del-row" title="Hapus baris"
                style="background:none;border:1px solid #e74c3c;color:#e74c3c;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:11px;">
              <i class="ti ti-trash"></i>
            </button>
          </td>
        </tr>`;
    }

    function initBank($row, bankId, bankName) {
        const $sel = $row.find('.hp-bank');
        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
        $sel.empty();
        if (bankId && bankName) $sel.append(new Option(String(bankName), String(bankId), true, true));

        $sel.select2({
            theme: 'bootstrap4', width: '100%', dropdownParent: $('#hpPaymentModal'),
            placeholder: 'Ketik nama bank...', allowClear: true, minimumInputLength: 1,
            language: {
                inputTooShort: () => 'Ketik minimal 1 huruf untuk mencari bank...',
                searching    : () => 'Mencari...',
                noResults    : () => 'Bank tidak ditemukan',
            },
            ajax: {
                url: routes.bank, type: 'GET', dataType: 'json', delay: 350, cache: false,
                data: p => ({ page: p.page || 1, keyword: p.term || '', limit: 10, kolom_name: 'name' }),
                processResults: r => ({
                    results: $.map(r.data.data || [], i => ({
                        id: String(i.id), text: i.name + ' (' + (i.currency_code || '-') + ')'
                    })),
                    pagination: { more: !!r.data.next_page_url }
                })
            }
        });
    }

    function destroyBanks() {
        $('#hpRowsBody .hp-bank').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
        });
    }

    function updateDelBtns() {
        $('#hpRowsBody .hp-del-row').prop('disabled', $('#hpRowsBody tr').length <= 1 && mode === 'new');
    }

    function addRow(d) {
        const $r = $(rowHtml(d));
        $('#hpRowsBody').append($r);
        initBank($r, d && d.bankId, d && d.bankName);
        updateDelBtns();
    }

    function resetForm() {
        mode = 'new';
        $('#hp_row_id').val('');
        destroyBanks();
        $('#hpRowsBody').empty();
        addRow();
        $('#hpFormTitle').html('<i class="fa fa-plus-circle mr-1"></i> Tambah Pembayaran Baru');
        $('#hpBtnSaveLabel').text('Simpan Pembayaran');
        $('#hpBtnAddRow').show();
        $('#hpBtnCancelEdit').hide();
    }

    function switchToEdit(d) {
        mode = 'edit';
        $('#hp_row_id').val(d.id);
        destroyBanks();
        $('#hpRowsBody').empty();
        addRow(d);
        $('#hpFormTitle').html('<i class="fa fa-pencil mr-1"></i> Edit Pembayaran');
        $('#hpBtnSaveLabel').text('Update Pembayaran');
        $('#hpBtnAddRow').hide();
        $('#hpBtnCancelEdit').show();
    }

    // ── riwayat ──
    function loadHistory(invId) {
        $('#hpHistoryBody').html('<tr><td colspan="6"><div class="spinner-border spinner-border-sm text-primary"></div> Memuat data...</td></tr>');

        return ajaxRequest(routes.detail, 'GET', { id: invId }, token())
            .then(function (res) {
                locked = true;
                const d = res.data.data;
                cur = d.code_curr || 'SAR';
                $('.hp-cur').text(cur);

                $('#hpContactName').text(d.contact_name || '-');
                $('#hpInvNumber').text(d.invoice_number || '-');
                $('#hpTotal').text(formatCurrency(d.invoice_total, cur));
                $('#hpPaid').text(formatCurrency(d.invoice_amount, cur));

                const over = Array.isArray(d.get_over_pay) ? (d.get_over_pay[0] || null) : (d.get_over_pay || null);
                const overTxt = (over && parseFloat(over.nominal_overpayment) > 0)
                    ? '  overpayment ' + formatCurrency(over.nominal_overpayment, cur) : '';
                $('#hpRemaining').text(formatCurrency(d.less_nominal, cur) + overTxt);

                const payments = (d.get_payment || []).slice()
                    .sort((a, b) => new Date(b.date_transaction) - new Date(a.date_transaction));

                if (payments.length === 0) {
                    $('#hpHistoryBody').html('<tr><td colspan="6" class="text-muted">Belum ada data pembayaran</td></tr>');
                    return;
                }

                let html = '';
                payments.forEach(function (p, i) {
                    const isReceive = parseFloat(p.nominal_receive) > 0;
                    const nominal   = isReceive ? parseFloat(p.nominal_receive) : parseFloat(p.nominal_spend);

                    // pembayaran via overpay (apply credit) dikelola dari halaman invoice global
                    const actions = isReceive ? `
                        <button type="button" class="btn btn-xs btn-warning mr-1 hp-btn-edit" style="padding:10px;" title="Edit"
                            data-id="${esc(p.id)}" data-nominal="${esc(nominal)}" data-date="${esc(p.date_transaction)}"
                            data-bank-id="${esc(p.uuid_bank)}" data-bank-name="${esc(p.name_bank)}"
                            data-reference="${esc(p.reference_detail || '')}">
                            <i class="ti ti-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-danger hp-btn-delete" title="Hapus"
                            data-id="${esc(p.id)}" data-nominal="${esc(formatCurrency(nominal, cur))}">
                            <i class="ti ti-trash"></i>
                        </button>` : '<small class="text-muted">via overpay</small>';

                    html += `
                        <tr ${isReceive ? '' : 'style="background-color:#FFC6BA;"'}>
                            <td>${i + 1}</td>
                            <td>${esc(p.date_transaction || '-')}</td>
                            <td>${esc(p.name_bank || '-')}</td>
                            <td>${esc(p.reference_detail || '-')}</td>
                            <td class="text-right">${formatCurrency(nominal, cur)}</td>
                            <td class="text-center">${actions}</td>
                        </tr>`;
                });
                $('#hpHistoryBody').html(html);
            })
            .catch(function (err) {
                if (err && err.status == 423) {
                    locked = false;
                    cathError(err);
                    $('#hpPaymentModal').modal('hide');
                } else {
                    $('#hpHistoryBody').html('<tr><td colspan="6" class="text-danger">Gagal memuat riwayat pembayaran</td></tr>');
                }
            });
    }

    // ── buka modal (dipanggil dari daftar hotel) ──
    window.hpOpenPayment = function (globalInvId, noInvoiceHotel) {
        $('#hp_inv_id').val(globalInvId);
        defaultRef = noInvoiceHotel || '';   // ref default = no_invoice_hotel, boleh diubah
        locked = false;
        resetForm();
        $('#hpPaymentModal').modal('show');
        loadHistory(globalInvId);
    };

    // ── event ──
    $('#hpBtnAddRow').on('click', function () { if (mode === 'new') addRow(); });

    $(document).on('click', '#hpPaymentModal .hp-del-row', function () {
        if ($('#hpRowsBody tr').length <= 1 && mode === 'new') return;
        const $tr = $(this).closest('tr');
        const $sel = $tr.find('.hp-bank');
        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
        $tr.remove();
        updateDelBtns();
    });

    $('#hpBtnCancelEdit').on('click', resetForm);

    $(document).on('click', '.hp-btn-edit', function () {
        const $b = $(this);
        switchToEdit({
            id: $b.data('id'), date: $b.data('date'),
            bankId: $b.data('bank-id'), bankName: $b.data('bank-name'),
            nominal: parseFloat($b.data('nominal')), reference: $b.data('reference'),
        });
    });

    $(document).on('click', '.hp-btn-delete', function () {
        const payId = $(this).data('id');
        const nominalStr = $(this).data('nominal');
        const invId = $('#hp_inv_id').val();

        Swal.fire({
            title: 'Hapus Pembayaran?',
            html: `Nominal <b>${esc(nominalStr)}</b> akan dihapus.<br>Sisa tagihan akan dikembalikan.`,
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) return;
            ajaxRequest(routes.remove, 'POST', { id: payId, parent_inv_id: invId }, token())
                .then(function (res) {
                    if (res.status == 200) {
                        Swal.fire({ icon: 'success', title: 'Dihapus!', timer: 1200, showConfirmButton: false });
                        resetForm();
                        loadHistory(invId);
                        reloadHotelTable();
                    }
                })
                .catch(err => cathError(err));
        });
    });

    $('#hpBtnSave').on('click', async function () {
        const $btn = $(this).prop('disabled', true);
        const invId = $('#hp_inv_id').val();

        const payloads = [];
        let error = null;

        $('#hpRowsBody tr').each(function (i) {
            const $r = $(this);
            const date = $r.find('.hp-date').val();
            const bank = $r.find('.hp-bank').val();
            const nominal = parseFloat($r.find('.hp-nominal').val());
            const ref = ($r.find('.hp-ref').val() || '').trim();

            if (!date || !bank || !nominal || nominal <= 0 || !ref) {
                error = 'Baris ' + (i + 1) + ': lengkapi tanggal, bank, nominal, dan catatan/ref.';
                return false;
            }
            payloads.push({
                date_transaction: date, uuid_bank: bank, nominal_receive: nominal,
                reference_detail: ref, parent_inv_id: invId,
            });
        });

        if (error) {
            Swal.fire('Peringatan', error, 'warning');
            $btn.prop('disabled', false);
            return;
        }

        try {
            if (mode === 'edit') {
                await ajaxRequest(routes.update, 'POST',
                    Object.assign({ id: $('#hp_row_id').val() }, payloads[0]), token());
                Swal.fire({ icon: 'success', title: 'Berhasil diupdate!', timer: 1500, showConfirmButton: false });
            } else {
                for (const p of payloads) {
                    await ajaxRequest(routes.save, 'POST', p, token());
                }
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: payloads.length + ' pembayaran berhasil disimpan.', timer: 1500, showConfirmButton: false });
            }
            resetForm();
            loadHistory(invId);
            reloadHotelTable();
        } catch (err) {
            cathError(err);
            loadHistory(invId); // baris yang sudah terlanjur tersimpan tetap tampil
        } finally {
            $btn.prop('disabled', false);
        }
    });

    // ── modal behaviour ──
    $('#hpPaymentModal').on('hide.bs.modal', function (e) {
        if ($('.select2-container--open').length > 0) { e.preventDefault(); e.stopPropagation(); return false; }
    });

    $('#hpPaymentModal').on('shown.bs.modal', function () {
        $(document).off('focusin.bs.modal');
    });

    $('#hpPaymentModal').on('hidden.bs.modal', function () {
        destroyBanks();
        const invId = $('#hp_inv_id').val();
        if (locked && invId) {
            locked = false;
            ajaxRequest(routes.unlock, 'POST', { id: invId }, token())
                .then(reloadHotelTable)
                .catch(err => cathError(err));
        }
    });
})();
</script>
@endpush