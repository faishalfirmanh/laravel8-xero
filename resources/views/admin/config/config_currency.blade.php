@extends('layouts.app')

@section('content')

<div class="card">
    <form id="form_currency">
        <div class="form-group">
            <label for="exampleInputEmail1">Tiap Nominal 1 Rial</label>
            <input type="number" class="form-control" name="rial_rupiah" id="rial_rupiah" aria-describedby="emailHelp">
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</div>


@endsection

@push('scripts')
<script>
$(document).ready(function() {
    //console.log("token",localStorage.getItem("token"))

    function getData(){
          ajaxRequest( `{{ route('getByIdCurrency') }}`,'GET',{ id : 1 }, localStorage.getItem("token"))
            .then(response =>{
                if(response.status == 200){
                    let to_number = Number(response.data.data.nominal_rupiah_1_riyal);
                    $("#rial_rupiah").val(to_number)
                }
                //console.log('sxx',response.data.data.nominal_rupiah_1_riyal)
            })
            .catch((err)=>{
                console.log('aaa',err)
            })
    }

    getData();

    // --- 2. AJAX SUBMIT ---
    $('#form_currency').on('submit', function(e) {
        e.preventDefault();

        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('idHotelInput');
        let idHotel = (idInput && idInput > 0) ? idInput : null;


        let selectedData = {
            id: 1,
            nominal_rupiah_1_riyal:   $("#rial_rupiah").val()
        };

        let jsonResult = JSON.stringify(selectedData);
         ajaxRequest( `{{ route('saveConfigCurrency') }}`,'POST',selectedData, localStorage.getItem("token"))
            .then(response =>{
                if(response.status == 200){
                     Swal.fire({
                        icon: 'success',
                        title: 'Simpan Berhasil!',
                        html: `
                            <div style="text-align: left; font-size: 14px;">
                                <p class="mb-1"> berhasil simpan mata uang </p>
                                <hr>
                            </div>
                        `,
                        confirmButtonText: 'Sukses'
                    })

                }
            })
            .catch((err)=>{
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                console.log('error select2 invoice',err);
            })
    });

});
</script>
@endpush
