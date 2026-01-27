/**
 * Main
 */

'use strict';





function convertStringDate(dateStr) {

  const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  // Create a new Date object from the string
  const date = new Date(dateStr);

  // Extract the day, date, month, year, and time
  const dayName = days[date.getDay()];
  const day = date.getDate();
  const month = months[date.getMonth()];
  const year = date.getFullYear();
  const hours = date.getHours().toString().padStart(2, '0');
  const minutes = date.getMinutes().toString().padStart(2, '0');

  // Construct the formatted string
  const formattedDate = `${dayName}, ${day} ${month} ${year} ${hours}.${minutes}`;
  return formattedDate
}

function formatCurrency(value, currency = 'IDR', decimals = 0) {
    if (value === null || value === undefined || value === '') return 'Rp 0';

    const number = Number(value);
    if (isNaN(number)) return 'Rp 0';

    return number.toLocaleString('id-ID', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: decimals
    });
}


function initGlobalDataTable(selector, url, columns, extraParams = {}) {
  return $(selector).DataTable({
    processing: true,
    serverSide: true,
    destroy: true, // Reset tabel jika dipanggil ulang
    ajax: function (data, callback, settings) {
      // Logic hitung halaman (Page Number)
      var page = Math.ceil(settings._iDisplayStart / settings._iDisplayLength) + 1;
      var keyword = data.search.value;

      // Menggabungkan parameter default + parameter tambahan
      let param_send = Object.assign({
        "limit": settings._iDisplayLength,
        "page": page,
        "keyword": keyword
      }, extraParams);

      $.ajax({
        url: url,
        type: 'GET',
        data: param_send,
        // beforeSend: function (request) {
        //   request.setRequestHeader("Authorization", 'Bearer ' + localStorage.getItem("token"));
        // },
        success: function (response) {
          callback({
            draw: settings.iDraw,
            recordsTotal: response.data.total,
            recordsFiltered: response.data.total,
            data: response.data.data
          });
        },
        error: function (data) {
          console.log('Error Load Data:', data);

          // Handle Error dari Backend
          let pesan = "Terjadi kesalahan pada server";
          if (data.responseJSON && (data.responseJSON.msg || data.responseJSON.message)) {
            pesan = data.responseJSON.msg || data.responseJSON.message;
          }

          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: pesan,
          }).then((result) => {
            // Redirect jika 401 (Unauthorized) atau user klik OK
            // if (data.status === 401 || result.isConfirmed) {
            //   window.location.href = '/login';
            // }
          });
        }
      });
    },
    columns: columns
  });
}

function initGlobalDataTableToken(selector, url, columns, extraParams = {}) {
  return $(selector).DataTable({
    processing: true,
    serverSide: true,
    destroy: true, // Reset tabel jika dipanggil ulang
    ajax: function (data, callback, settings) {
      // Logic hitung halaman (Page Number)
      var page = Math.ceil(settings._iDisplayStart / settings._iDisplayLength) + 1;
      var keyword = data.search.value;

      // Menggabungkan parameter default + parameter tambahan
      let param_send = Object.assign({
        "limit": settings._iDisplayLength,
        "page": page,
        "keyword": keyword
      }, extraParams);

      $.ajax({
        url: url,
        type: 'GET',
        data: param_send,
        beforeSend: function (request) {
          request.setRequestHeader("Authorization", 'Bearer ' + localStorage.getItem("token"));
        },
        success: function (response) {
          callback({
            draw: settings.iDraw,
            recordsTotal: response.data.total,
            recordsFiltered: response.data.total,
            data: response.data.data
          });
        },
        error: function (data,error, thrown) {
          console.log('Error Load Data:', data);
          $(selector + '_processing').hide();
          $( ".dt-empty" ).addClass( "d-none" );
          let pesan = "Terjadi kesalahan pada server";
          if (data.responseJSON && (data.responseJSON.msg || data.responseJSON.message)) {
            pesan = data.responseJSON.msg || data.responseJSON.message;
          }

          Swal.fire({
            icon: 'error',
            title: 'Oops... auth gagal',
            text: pesan,
          }).then((result) => {
            // Redirect jika 401 (Unauthorized) atau user klik OK
            // if (data.status === 401 || result.isConfirmed) {
            //   window.location.href = '/login';
            // }
          });
        }
      });
    },
    columns: columns
  });
}

//helper function ajax
async function ajaxRequest(url, method = 'GET', data = null, token = null) {
  try {
    const options = {
      method: method, // GET, POST, PUT, DELETE, etc.
      headers: {
        'Content-Type': 'application/json', //sebelum image
        'Accept': 'application/json',
      },
    };

    if (token) {
      options.headers['Authorization'] = `Bearer ${token}`;
    }

    if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
      if (data instanceof FormData) {
        options.body = data; // No need to stringify, FormData handles this
      } else if (data) {
        options.headers['Content-Type'] = 'application/json'; // For JSON payloads
        options.body = JSON.stringify(data);
      }
    } else if (data) {
      const queryParams = new URLSearchParams(data).toString();
      url += `?${queryParams}`;
    }

    const response = await fetch(url, options);

    if (!response.ok) {
      //throw new Error(`Error: ${response.status} - ${response.statusText}`);
      const errorData = await response.json(); // Extract error body as JSON
      //console.log('error if',errorData)
      throw { status: response.status, statusText: response.statusText, error: errorData };
    }
    //before
    //return await response.json();
    //after
    return {
      status: response.status,
      data: await response.json(),
    };
  } catch (error) {
    console.error('AJAX Request Failed', error);
    throw error;
  }
}
////helper function ajax

function getImageGloabalUrl(image_variable) {
  const baseUrl = window.location.origin;
  if (image_variable !== '') {
    return `${baseUrl}${image_variable}`;
  } else {
    return `${baseUrl}/frontend/img/no_img.jpg`;
  }
}

