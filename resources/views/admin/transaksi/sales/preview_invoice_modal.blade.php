<div class="modal fade" id="modalPreviewInvoice" tabindex="-1" role="dialog"
     aria-labelledby="labelModalPreviewInvoice" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content" style="height: 90vh;">

            <div class="modal-header">
                <h5 class="modal-title" id="labelModalPreviewInvoice">
                    <i class="ti ti-file-invoice mr-1"></i> Preview Invoice
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-0 position-relative" style="overflow:hidden;">
                <div id="previewInvoiceLoading" class="text-center"
                     style="position:absolute; inset:0; display:flex; flex-direction:column;
                            align-items:center; justify-content:center; background:#fff; z-index:2;">
                    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="mt-3 text-muted font-weight-bold">Memuat invoice...</div>
                </div>

               <iframe id="previewInvoiceFrame"
                    src=""
                    style="width:100%; height:100%; border:0; display:none;">
               </iframe>
            </div>

        </div>
    </div>
</div>