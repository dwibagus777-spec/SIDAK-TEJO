# 🛡️ ARCHITECTURE GUARDRAILS & MEMORY GOVERNANCE CONTRACT

Mulai sekarang `.agents/` bukan sekadar dokumentasi. `.agents/` adalah **ENGINEERING GOVERNANCE CONTRACT**. Semua perubahan source code HARUS mematuhi dokumen ini.

---

## 1. ZERO DDL & NO SCHEMA MUTATION ENFORCEMENT
- Secara default: **0% DDL / ZERO ALTER TABLE / NO MIGRATION DDL**.
- Dilarang `ALTER TABLE`, `DROP COLUMN`, `ADD COLUMN`, `MODIFY COLUMN`, `CHANGE COLUMN`, `RENAME COLUMN`, `CREATE TABLE`, atau `DROP TABLE` tanpa persetujuan eksplisit.
- Solusi query, alias, repository filter, dan service adapter WAJIB diutamakan.

## 2. CORE ENGINE FROZEN BOUNDARIES ENFORCEMENT
Core Execution Engine berikut berstatus **100% IMMUTABLE & FROZEN**:
1. `app/Services/AssetLifecycleService.php`
2. `app/Services/TemuanService.php`
3. `app/Services/InspectionExecutionService.php`
4. `app/Models/AssetHistoryModel.php`
5. `app/AI/WorkOrderOptimizer.php`

DILARANG: edit, refactor, rename, format ulang, me-touch whitespace, atau menambahkan import pada file frozen ini.

## 3. PRODUCER ➔ TRANSFORMER ➔ CONSUMER ENFORCEMENT
- Setiap bug data tidak boleh hanya ditambal pada Consumer (View/JS).
- Wajib melacak *data pipeline*:
  $$\text{Database} \longrightarrow \text{Repository (Producer)} \longrightarrow \text{Service (Transformer)} \longrightarrow \text{Controller} \longrightarrow \text{View (Consumer)}$$
- Perbaiki data contract di Producer/Service terlebih dahulu. Defensive rendering di View hanya sebagai *secondary safety layer*.

## 4. ROUTE NAMESPACING & APACHE COLLISION
- Direktori fisik di `public/` (seperti `public/assets/`) membajak URL route yang diawali dengan `assets/`.
- Selalu gunakan namespace `master-assets/` atau `api/` untuk route dinamis CodeIgniter 4.

## 5. LINUX PSR-4 CASE SENSITIVITY
- Hostinger Linux filesystem bersifat *case-sensitive*.
- Nama class PHP harus presisi sama dengan nama fisik file (contoh: `class GisController` pada `GisController.php`).

## 6. WORKFLOW CONTRACT
$$\text{Memory} \longrightarrow \text{Trace} \longrightarrow \text{Plan} \longrightarrow \text{Approval} \longrightarrow \text{Implement} \longrightarrow \text{Test} \longrightarrow \text{Diff Review} \longrightarrow \text{Update Memory}$$
