# 🔄 SESSION PROTOCOL & MEMORY GOVERNANCE ENFORCEMENT MODE

Mulai sekarang `.agents/` bukan sekadar dokumentasi. `.agents/` adalah **ENGINEERING GOVERNANCE CONTRACT**. Semua perubahan source code HARUS mematuhi dokumen tersebut.

---

## 1. 🛫 PRE-FLIGHT GATE (WAJIB SEBELUM KODING)

SEBELUM mengubah SATU BARIS kode pun, agen WAJIB membaca 10 dokumen memori berikut:
1. `.agents/_START.md`
2. `.agents/PROJECT_CONTEXT.md`
3. `.agents/ARCHITECTURE_GUARDRAILS.md`
4. `.agents/FROZEN_FILES.md`
5. `.agents/BUG_REGISTRY.md`
6. `.agents/DECISION_LOG.md`
7. `.agents/RELEASE_HISTORY.md`
8. `.agents/DATABASE_SCHEMA_RULES.md`
9. `.agents/PERFORMANCE_RULES.md`
10. `.agents/SESSION_PROTOCOL.md`

Kemudian jalankan:
- `git status`
- `git diff`

Jika salah satu memory file tidak dapat dibaca: **STOP! Jangan melakukan coding.**

---

## 2. 📝 CHANGE AUTHORIZATION GATE

Sebelum coding, buat **CHANGE PLAN**:
- **TASK**: ...
- **ROOT CAUSE**: ...
- **PRODUCER / TRANSFORMER / CONSUMER**: ...
- **FILES TO CHANGE**: ...
- **FILES TO PROTECT (FROZEN)**: ...
- **DATABASE DDL**: `NONE / REQUIRED`
- **REGRESSION RISK**: `LOW / MEDIUM / HIGH`
- **PERFORMANCE IMPACT**: ...
- **RELATED HISTORICAL BUG**: ...
- **RELATED DECISION / RELEASE**: ...

Jika informasi tersebut belum dapat dibuktikan: **STATUS = DIAGNOSTIC REQUIRED**. Jangan implementasi.

---

## 3. 🔒 FROZEN FILE ENFORCEMENT
Jika file termasuk `FROZEN_FILES.md` (`AssetLifecycleService.php`, `TemuanService.php`, `InspectionExecutionService.php`, `AssetHistoryModel.php`, `WorkOrderOptimizer.php`): **DO NOT MODIFY!**
- Tidak boleh edit, refactor, rename, format ulang, atau me-touch whitespace.
- Jika diperlukan: **STOP + REQUEST APPROVAL.**

---

## 4. 🗄️ ZERO DDL ENFORCEMENT
Database schema adalah immutable (**NO ALTER TABLE, NO DROP/ADD/MODIFY COLUMN**).
Jika menemukan masalah schema: **STOP! Cari isolated solution terlebih dahulu.**

---

## 5. 🔍 ANTI-REGRESSION ENFORCEMENT
Setiap error WAJIB dicari di `.agents/BUG_REGISTRY.md`. Jika error memiliki kemiripan dengan bug sebelumnya:
- **STOP!** Tampilkan **POTENTIAL REGRESSION REPORT**.
- Jelaskan bug lama, root cause lama, fix lama, dan risiko regression sebelum patch dibuat.

---

## 6. ⚡ PERFORMANCE & GIS ENFORCEMENT
- **FAST > ACCURATE > FIELD-FIRST > SCALABLE > AUDITABLE**
- GIS Rules: **NEVER load all assets globally**. Mandatory: Feeder Scoping + Viewport Bounding Box + Clustering + Lazy Detail + Single LineString Polyline.

---

## 7. 🧪 POST-CHANGE VERIFICATION & MEMORY UPDATE GATE
Setelah implementasi:
1. `git diff` & `git status`
2. PHP syntax check (`php -l`)
3. Pastikan file yang berubah persis sesuai rencana dan frozen files untouchable.
4. Update `CHANGELOG.md`, `BUG_REGISTRY.md`, `DECISION_LOG.md`, dan `RELEASE_HISTORY.md`.

---

## 8. 📋 FINAL ENGINEERING REPORT CONTRACT

Setiap task diakhiri dengan **ENGINEERING REPORT**:
- **Task**: ...
- **Root Cause**: ...
- **Implementation**: ...
- **Files Changed**: ...
- **Files Untouched**: ...
- **Frozen Boundary**: `PASS / FAIL`
- **Database / DDL**: `UNCHANGED / NONE`
- **Tests & Acceptance Gates**: ...
- **Regression Risk & Performance Impact**: ...
- **Memory Updated**: `YES`
- **Git Status & Diff**: ...
- **Final Status**: `DIAGNOSTIC / IMPLEMENTED / READY FOR VERIFICATION / PASS`

DILARANG mengatakan "berhasil" hanya karena kode telah diedit.
