// js/script.js

// Pastikan skrip dijalankan setelah seluruh DOM (Document Object Model) selesai dimuat
document.addEventListener('DOMContentLoaded', function() {

    // ----------------------------------------------------------------------------------
    // Contoh 1: Mengaktifkan Bootstrap Tooltips (jika Anda menggunakannya)
    // ----------------------------------------------------------------------------------
    // Bootstrap tooltips memerlukan inisialisasi.
    // Tambahkan atribut data-bs-toggle="tooltip" dan title="Isi tooltip" pada elemen HTML Anda.
    /*
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    */


    // ----------------------------------------------------------------------------------
    // Contoh 2: Konfirmasi sebelum menghapus (alternatif selain inline onsubmit)
    // ----------------------------------------------------------------------------------
    // Anda bisa menambahkan class 'confirm-delete' pada form atau tombol hapus.
    const deleteForms = document.querySelectorAll('form.confirm-delete'); // Atau 'button.confirm-delete'
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const confirmation = confirm('Anda yakin ingin menghapus item ini? Tindakan ini tidak dapat diurungkan.');
            if (!confirmation) {
                event.preventDefault(); // Mencegah form dikirim jika pengguna membatalkan
            }
        });
    });
    // Catatan: Pada file PHP seperti kelola_layanan.php dan kelola_testimoni.php,
    // konfirmasi sudah menggunakan inline `onsubmit="return confirm(...)"`.
    // Anda bisa memilih salah satu metode. Jika menggunakan metode di atas,
    // hapus `onsubmit` dari tag form di PHP dan tambahkan class="confirm-delete" pada form tersebut.


    // ----------------------------------------------------------------------------------
    // Contoh 3: Smooth scroll untuk anchor links (jika Anda memiliki navigasi internal halaman)
    // ----------------------------------------------------------------------------------
    /*
    const internalLinks = document.querySelectorAll('a[href^="#"]');
    internalLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                window.scrollTo({
                    top: targetElement.offsetTop - 70, // -70 untuk offset jika ada navbar fixed
                    behavior: 'smooth'
                });
            }
        });
    });
    */

    // ----------------------------------------------------------------------------------
    // Contoh 4: Menampilkan/menyembunyikan tombol "Back to Top"
    // ----------------------------------------------------------------------------------
    // Anda perlu menambahkan elemen HTML untuk tombol ini, misalnya:
    // <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="fas fa-arrow-up"></i></a>
    // Dan CSS untuk styling .back-to-top (ada contoh di style.css yang saya berikan sebelumnya)
    /*
    const backToTopButton = document.querySelector('.back-to-top');
    if (backToTopButton) {
        const toggleBackToTop = () => {
            if (window.scrollY > 100) {
                backToTopButton.classList.add('active');
            } else {
                backToTopButton.classList.remove('active');
            }
        }
        window.addEventListener('load', toggleBackToTop);
        document.addEventListener('scroll', toggleBackToTop);

        backToTopButton.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    }
    */


    // ----------------------------------------------------------------------------------
    // Contoh 5: Preview gambar sebelum upload (untuk form tambah/edit layanan)
    // ----------------------------------------------------------------------------------
    // Ini akan menampilkan preview gambar yang dipilih pada input file.
    const imageUploadInput = document.getElementById('gambar_layanan'); // Pastikan ID ini sesuai dengan input file Anda
    const imagePreviewContainer = document.getElementById('gambar_preview_container'); // Tambahkan div ini di HTML
    // Contoh HTML untuk container preview di kelola_layanan.php (di dalam form):
    // <div id="gambar_preview_container" class="mt-2">
    //    <img id="gambar_preview" src="#" alt="Preview Gambar" class="img-preview img-thumbnail" style="display:none;">
    // </div>
    // Dan tambahkan id="gambar_preview" pada tag img di atas.

    /*
    if (imageUploadInput && imagePreviewContainer) {
        const imagePreview = document.getElementById('gambar_preview'); // Pastikan ID ini sesuai
        if(imagePreview){
            imageUploadInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.src = '#';
                    imagePreview.style.display = 'none';
                }
            });
        }
    }
    */
    const layananSwiperElement = document.querySelector('.swiper-container-layanan');
    if (typeof Swiper !== 'undefined' && layananSwiperElement) {
        new Swiper(layananSwiperElement, {
            direction: 'horizontal',
            loop: true,
            slidesPerView: 1,
            spaceBetween: 33,
            slidesPerGroup: 1,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            breakpoints: {
                768: {
                    slidesPerView: 2,
                    spaceBetween: 20
                },
                992: {
                    slidesPerView: 3,
                    spaceBetween: 20
                }
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    }

}); // Akhir dari DOMContentLoaded
