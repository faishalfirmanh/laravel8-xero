<div class="bg-dark border-right text-white" id="sidebar-wrapper" style="width: 250px; min-height: 100vh;">
    <div class="sidebar-heading p-3 text-center"><h5>An namiroh</h5></div>
    <div class="list-group list-group-flush" id="dynamic-menu">

    </div>
    <div>
         <a href="#accountSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Account</a>
        <div class="collapse" id="accountSub">
            <a data-toggle="modal" data-target="#loginModal" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Login</a>
            <a href="#" id="logout_btn" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Logout</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Ambil data dari localStorage
        const rawData = localStorage.getItem('user_menu');
        const menuContainer = document.getElementById('dynamic-menu');
        const baseUrl = window.location.origin; // Ambil base URL (misal: http://127.0.0.1:8000)

        function cekLogin(){
             ajaxRequest( `{{ route('me-auth') }}`,'POST',{}, null)
                .then(response =>{
                    console.log('sss',response)
                   // if(response.status == 200){
                        //console.log(response.access_token)
                     //   localStorage.setItem('token', response.data.access_token);
                       // localStorage.setItem('user_menu', JSON.stringify(response.data.menu));
                       // Swal.fire({
                     //       title: "Login sukses",
                         //   text: "Berhasil Login",
                       //     icon: "success"
                       // });
                       // window.location.reload();
                    //}else{
                     //     Swal.fire('Gagal!', response.statusText || 'Terjadi kesalahan.', 'error');
                   // }
                    //$("#loginModal").modal("hide")
                })
                .catch((err)=>{
                    localStorage.setItem('user_menu',null)
                    Swal.fire('Gagal!', err.error.message || 'Terjadi kesalahan.', 'error');
                    console.log('error api me ',err);
                   // window.location.reload();
                    //$("#loginModal").modal("hide")
                })
        }


       // cekLogin();

        if (rawData) {
            try {
                const menuData = JSON.parse(rawData);
                let menuHtml = '';

                menuData.forEach(parent => {
                    // ID unik untuk collapse (agar tidak bentrok antar menu)
                    const collapseId = `menu_collapse_${parent.id}`;

                    // Cek apakah punya anak (children)
                    const hasChildren = parent.children && parent.children.length > 0;

                    // Render Parent Menu
                    menuHtml += `
                        <a href="${hasChildren ? '#' + collapseId : (parent.slug ? baseUrl + '/' + parent.slug : '#')}"
                           ${hasChildren ? 'data-toggle="collapse"' : ''}
                           class="list-group-item list-group-item-action bg-dark text-white ${hasChildren ? 'dropdown-toggle' : ''} text-capitalize">
                            ${parent.nama_menu}
                        </a>
                    `;

                    // Render Child Menu jika ada
                    if (hasChildren) {
                        menuHtml += `<div class="collapse" id="${collapseId}">`;

                        parent.children.forEach(child => {
                            // Gabungkan Base URL dengan Slug
                            const childUrl = child.slug ? `${baseUrl}/${child.slug}` : '#';

                            menuHtml += `
                                <a href="${childUrl}" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small text-capitalize">
                                    ${child.nama_menu}
                                </a>
                            `;
                        });

                        menuHtml += `</div>`;
                    }
                });

                // Tambahkan menu Account statis di paling bawah (opsional)
                // menuHtml += `
                //     <a href="#accountSub" data-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white dropdown-toggle">Account</a>
                //     <div class="collapse" id="accountSub">
                //         <a href="#" id="logout_btn" class="list-group-item list-group-item-action bg-secondary text-white pl-4 small">Logout</a>
                //     </div>
                // `;

                menuContainer.innerHTML = menuHtml;

            } catch (e) {
                console.error("Gagal parsing JSON menu:", e);
                menuContainer.innerHTML = '<div class="p-3 text-danger">Gagal memuat menu.</div>';
            }
        } else {
            // Jika data menu kosong di localStorage
            menuContainer.innerHTML = '<div class="p-3 text-warning">Silakan login kembali.</div>';
        }

        // Handle Logout secara manual
        const logoutBtn = document.getElementById('logout_btn');
        if(logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                localStorage.removeItem('user_menu');
                localStorage.removeItem('access_token');
                window.location.href = '/'; // Sesuaikan route login Anda
            });
        }
    });
</script>
