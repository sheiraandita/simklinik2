# SIM Klinik

## Deskripsi Proyek

SIM Klinik merupakan aplikasi berbasis web yang digunakan untuk membantu pengelolaan data dan aktivitas operasional klinik.

## Teknologi

* PHP
* MySQL
* HTML dan CSS
* Git dan GitHub

## Struktur Folder

* `assets/` — menyimpan file CSS dan kebutuhan tampilan aplikasi.
* `config/` — menyimpan konfigurasi aplikasi dan koneksi database.
* `database/` — menyimpan file SQL database.
* `models/` — menyimpan class PHP untuk pengelolaan data.
* File PHP di folder utama — halaman aplikasi dan fitur pengelolaan data.

## Persyaratan

* Laragon atau web server PHP lainnya.
* PHP dan MySQL.
* Browser web.
* Git untuk pengelolaan versi kode.

## Cara Menjalankan

1. Simpan folder proyek di direktori web server, misalnya `C:\laragon\www\simklinik2`.
2. Jalankan Apache dan MySQL melalui Laragon.
3. Buat database sesuai konfigurasi aplikasi.
4. Impor file SQL dari folder `database/klinik.sql` melalui phpMyAdmin.
5. Periksa konfigurasi koneksi database pada `config/database.php`.
6. Buka aplikasi melalui `http://localhost/simklinik2/`.

## Workflow Git

1. Periksa perubahan menggunakan `git status`.
2. Siapkan perubahan menggunakan `git add .`.
3. Simpan perubahan menggunakan `git commit -m "Pesan perubahan"`.
4. Kirim perubahan ke GitHub menggunakan `git push`.

## Repository

Kode sumber proyek dikelola menggunakan Git dan GitHub.