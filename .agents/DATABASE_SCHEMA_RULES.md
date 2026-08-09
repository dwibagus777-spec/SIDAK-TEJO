# 🗄️ DATABASE SCHEMA RULES & VERIFIED TABLES

## 1. DDL RULES
- **0% DDL / ZERO ALTER TABLE / NO MIGRATION DDL**.
- Do NOT issue `ALTER TABLE`, `DROP COLUMN`, `ADD COLUMN`, `MODIFY COLUMN`, or `CREATE TABLE` statements.
- Adapt queries, repository filters, and service layers to existing physical tables.

## 2. PHYSICAL SCHEMA MAP & KNOWN COLUMNS
- **`work_orders`**:
  - Title column is physically named `judul_wo` (NOT `judul_pekerjaan`).
  - Views MUST use `<?= esc($wo['judul_wo'] ?? $wo['judul_pekerjaan'] ?? '-') ?>`.
- **`sections`**:
  - Allowed fields are `['penyulang_id', 'nama_section', 'status']`.
  - Table has NO `kode_section` column. Queries joining `sections s` MUST select `NULL as kode_section` using `$builder->select('..., NULL as kode_section', false)`.
- **`assets`**:
  - Contains `id`, `kode_asset`, `nama_asset`, `jenis_asset`, `ulp_id`, `penyulang_id`, `section_id`, `latitude`, `longitude`, `status`, `deleted_at`.
  - Soft-deleted assets have `deleted_at IS NOT NULL`. Active queries MUST filter `a.deleted_at IS NULL`.
- **`gardu_induk`**:
  - Master Gardu Induk table containing `id`, `kode_gi`, `nama_gi`, `lokasi`, `latitude`, `longitude`, `status`.
- **`penyulang`**:
  - Contains `id`, `kode_penyulang`, `nama_penyulang`, `gi_id`, `ulp_id`, `status`.
