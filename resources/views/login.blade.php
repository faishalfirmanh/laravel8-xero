@extends('template.app')

@section('content')
<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card card-login bg-white p-4" style="width: 400px;">
        <h3 class="text-center mb-4">API Login</h3>

        <div id="alert-msg" class="alert d-none"></div>

        <form id="formLogin">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="email" class="form-control" placeholder="admin@example.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" class="form-control" placeholder="********" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="btnLogin">Sign In</button>
        </form>

        <div id="result-area" class="mt-3 d-none">
            <hr>
            <small class="text-muted">Token Result:</small>
            <textarea id="token-result" class="form-control" rows="3" readonly style="font-size: 11px;"></textarea>
            <button class="btn btn-danger btn-sm btn-block mt-2" id="btnLogout">Test Logout</button>
        </div>
    </div>
</div>
@endsection

@section('load_js')
<script>
    $(document).ready(function() {
        let userToken = localStorage.getItem('api_token');

        // Cek jika sudah login sebelumnya
        if(userToken) {
            showLoggedInState(userToken);
        }

        $('#formLogin').submit(function(e) {
            e.preventDefault();
            let email = $('#email').val();
            let password = $('#password').val();
            let btn = $('#btnLogin');

            btn.prop('disabled', true).text('Loading...');
            $('#alert-msg').addClass('d-none');

            $.ajax({
                url: '/api/login',
                type: 'POST',
                data: {
                    email: email,
                    password: password
                },
                success: function(response) {
                    // Simpan Token
                    localStorage.setItem('api_token', response.access_token);

                    $('#alert-msg').removeClass('d-none alert-danger').addClass('alert-success').text('Login Berhasil!');
                    showLoggedInState(response.access_token);
                },
                error: function(xhr) {
                    let err = xhr.responseJSON;
                    // Handle jika server down atau error structure berbeda
                    let msg = (err && err.message) ? err.message : 'Login Gagal, Cek koneksi server';
                    $('#alert-msg').removeClass('d-none alert-success').addClass('alert-danger').text(msg);
                },
                complete: function() {
                    btn.prop('disabled', false).text('Sign In');
                }
            });
        });

        $('#btnLogout').click(function() {
            let token = localStorage.getItem('api_token');

            $.ajax({
                url: '/api/logout',
                type: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token
                },
                success: function() {
                    localStorage.removeItem('api_token');
                    location.reload();
                },
                error: function() {
                    // Jika token expired di server, paksa logout di client
                    localStorage.removeItem('api_token');
                    location.reload();
                }
            });
        });

        function showLoggedInState(token) {
            $('#formLogin').hide();
            $('#result-area').removeClass('d-none');
            $('#token-result').val(token);
            $('.card-login h3').text('Welcome User!');
        }
    });
</script>
@endsection
