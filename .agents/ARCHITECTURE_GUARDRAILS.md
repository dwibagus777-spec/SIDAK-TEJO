# 🛡️ ARCHITECTURE GUARDRAILS & INVARIANTS

## 1. ZERO DDL & NO SCHEMA MUTATION
- Secara default: **0% DDL / ZERO ALTER TABLE / NO MIGRATION DDL**.
- Tidak boleh mengubah kolom, menambah tabel, atau mengubah tipe data di MySQL tanpa persetujuan tertulis dari user.
- Solusi query, alias, repository filter, dan service adapter WAJIB diutamakan.

## 2. CORE ENGINE FROZEN BOUNDARIES
Core Execution Engine berikut berstatus **100% IMMUTABLE & FROZEN**:
1. `app/Services/AssetLifecycleService.php`
2. `app/Services/TemuanService.php`
3. `app/Services/InspectionExecutionService.php`
4. `app/Models/AssetHistoryModel.php`
5. `app/AI/WorkOrderOptimizer.php`

## 3. PRODUCER ➔ TRANSFORMER ➔ CONSUMER CONTRACT
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
