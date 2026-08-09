# 🔄 SESSION PROTOCOL & STEP-BY-STEP WORKFLOW

## SETIAP SESI KERJA WAJIB MENGIKUTI 15-STEP PROTOCOL:

1. **LOAD MEMORY**: Baca `.agents/_START.md` dan file memori di `.agents/`.
2. **SCAN WORKSPACE**: Inspect status codebase aktual.
3. **CHECK GIT STATUS**: Jalankan `git status` dan `git diff`.
4. **CHECK BUG REGISTRY**: Pastikan masalah yang dilaporkan bukan bug historis yang pernah diperbaiki.
5. **NO GUESSING TRACING**: Trace data pipeline: Database ➔ Model ➔ Repository ➔ Service ➔ Controller ➔ View ➔ JS.
6. **PROPOSE MINIMAL PATCH**: Susun usulan perbaikan paling minimal dan terisolasi.
7. **CHECK FROZEN BOUNDARIES**: Pastikan diff TIDAK menyentuh 5 Frozen Files dan TIDAK melakukan DDL.
8. **WAIT FOR APPROVAL**: Berikan laporan diagnostic dan tunggu persetujuan formal user.
9. **IMPLEMENT FIX**: Lakukan edit kode secara terisolasi.
10. **PHP SYNTAX CHECK**: Jalankan `php -l` pada seluruh file yang diubah.
11. **VERIFY GIT DIFF**: Pastikan jumlah file yang berubah persis sesuai dengan yang diajukan.
12. **COMMIT & TAG RELEASE**: Commit dengan pesan terstruktur dan buat tag versi rilis.
13. **PUSH TO PRODUCTION**: Push ke GitHub `main` dan pemicu Auto Deploy.
14. **UPDATE MEMORY**: Catat rilis baru dan bug fix pada `.agents/BUG_REGISTRY.md`, `RELEASE_HISTORY.md`, dan `CHANGELOG.md`.
15. **SESSION SUMMARY REPORT**: Berikan ringkasan terstruktur kepada user.
