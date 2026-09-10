# Runbook Update Aplikasi Desktop

## Alur rilis
1. Perbarui `version` di `package.json` (contoh: `1.2.0`).
2. Commit dan tag rilis: `git tag desktop-v1.2.0 && git push origin desktop-v1.2.0`.
3. GitHub Actions menjalankan build Vite, build NSIS, mengunggah artifact, dan membuat GitHub Release non-draft berisi `.exe`, `.blockmap`, serta `latest.yml`.
4. Setelah selesai, perbarui konfigurasi server:
   - `DESKTOP_LATEST_VERSION=1.2.0`
   - `DESKTOP_MIN_VERSION=<versi minimum yang masih boleh push>`
   - `DESKTOP_DOWNLOAD_URL=https://github.com/nurfandi31/siupknext/releases/latest/download/SIUPK%20Next%20Desktop%20Setup%201.2.0.exe`
   - `DESKTOP_RELEASE_NOTES_URL=https://github.com/nurfandi31/siupknext/releases/latest`
   - `DESKTOP_SHA512=<hash SHA-512 installer, opsional>`
5. Pastikan `GET /api/v1/desktop/sync/update/check?platform=win&current_version=<versi>` mengembalikan manifest yang benar.

## Catatan teknis
- Feed `electron-updater` memakai provider generic dengan URL `https://github.com/nurfandi31/siupknext/releases/latest/download`, sehingga `latest.yml` dibaca otomatis dari release terbaru.
- Server update check tetap tersedia untuk kontrol rilis, subscription gate, dan kesiapan mobile.
- Data SQLite dan outbox ada di user-data directory. Installer NSIS tidak menghapus direktori tersebut saat update, sehingga data lokal dan antrean sinkronisasi tetap aman.
- CI tidak mengunggah installer ke `app.siupk.id`; server hanya menyimpan metadata versi dan URL.

## Troubleshooting
- **Signature tidak valid:** pastikan Windows code signing certificate dikonfigurasi di environment CI sebelum `electron-builder`, lalu pastikan sertifikat belum kedaluwarsa.
- **Gagal unduh atau firewall:** pastikan domain `github.com` dan `objects.githubusercontent.com` dapat diakses client, serta endpoint GitHub Release dapat diakses tanpa autentikasi.
- **Update tidak terdeteksi:** pastikan `.exe`, `.blockmap`, dan `latest.yml` ada pada release, nama file sesuai `latest.yml`, dan feed URL tidak terblokir.
- **Versi tidak berubah:** pastikan tag baru dibuat dari commit yang memuat bump `package.json` dan `DESKTOP_LATEST_VERSION` sudah diperbarui.
