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


//helper function ajax
async function ajaxRequest(url, method = 'GET', data = null, token = null) {
  try {
    const options = {
      method: method, // GET, POST, PUT, DELETE, etc.
      headers: {
        // 'Content-Type': 'application/json', //sebelum image
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

