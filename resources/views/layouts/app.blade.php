<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.6/css/dataTables.dataTables.css" />
    <link rel="stylesheet" href="{{ asset('admin/css/style.css') }}">
</head>
<body>

    <div class="d-flex" id="wrapper">
        @include('layouts.navbar')

        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <span class="navbar-brand mb-0 h1">Admin Panel</span>
            </nav>

            <div class="container-fluid mt-4">
                @yield('content')
            </div>
        </div>
    </div>

    <!-- Modal Login -->
    <div class="modal fade" id="loginModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Login Admin</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="loginForm">
                        @csrf

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            Login
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>


    @include('layouts.footer')

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.3.6/js/dataTables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/main.js?v.2') }}"></script>
    <script>
        $("#loginForm").on("submit", function(e){
                e.preventDefault();
                const param_send = {
                    email: $('input[name="email"]').val(),
                    password: $('input[name="password"]').val()
                };
                console.log('login--',$(this).serialize())
            ajaxRequest( `{{ route('login') }}`,'POST',param_send, null)
                .then(response =>{
                    console.log('sss',response)
                    if(response.status == 200){
                        //console.log(response.access_token)
                        localStorage.setItem('token', response.data.access_token);
                        Swal.fire({
                            title: "Login sukses",
                            text: "Berhasil Login",
                            icon: "success"
                        });
                    }else{
                          Swal.fire('Gagal!', response.statusText || 'Terjadi kesalahan.', 'error');
                    }
                    $("#loginModal").modal("hide")
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                    //console.log('error select2 invoice',err);
                    $("#loginModal").modal("hide")
                })
        })

        $("#logout_btn").on("click", function(e){
               ajaxRequest( `{{ route('login') }}`,'POST',param_send, null)
                .then(response =>{
                    if(response.status == 200){

                        Swal.fire({
                            title: "Login sukses",
                            text: "Berhasil Login",
                            icon: "success"
                        });
                    }
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                })
        })
    </script>

    @stack('scripts')
</body>
</html>
