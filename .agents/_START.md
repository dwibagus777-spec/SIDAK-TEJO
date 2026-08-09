# 🚀 SIDAK TEJO - AGENT BOOTSTRAP PROTOCOL

Mulai setiap sesi kerja dengan membaca file-file memori berikut di folder `.agents/` sebelum menyentuh atau mengubah kode aplikasi:

## 📖 BACA BERURUTAN (READING ORDER):

1. `PROJECT_CONTEXT.md` - Konteks proyek, teknologi, dan arsitektur umum.
2. `ARCHITECTURE_GUARDRAILS.md` - Batasan arsitektur (Zero DDL, Core Engine Frozen, Producer-Consumer Contract).
3. `FROZEN_FILES.md` - Daftar file yang HARUS 100% UNTOUCHABLE & FROZEN.
4. `BUG_REGISTRY.md` - Riwayat bug historis agar tidak mengulang kesalahan lama.
5. `DECISION_LOG.md` - Catatan keputusan arsitektur yang sudah dikunci.
6. `RELEASE_HISTORY.md` - Riwayat rilis (v2.3.0.35 s/d vB.2).
7. `PERFORMANCE_RULES.md` - Aturan performa GIS (Feeder Scoped, Viewport Loading, Single LineString, Lazy Detail).
8. `SESSION_PROTOCOL.md` - Urutan kerja setiap task (Diagnose ➔ Trace ➔ Propose ➔ Wait Approval ➔ Implement ➔ Test ➔ Diff Review ➔ Update Memory).

---

## 🔒 ABSOLUTE GUARDRAILS:

- ❌ **NO UNAPPROVED DDL**: Dilarang `ALTER TABLE`, `DROP COLUMN`, `ADD COLUMN`, atau `CREATE TABLE` tanpa izin eksplisit.
- ❌ **FROZEN FILES ARE SACRED**: Dilarang mengubah `AssetLifecycleService.php`, `TemuanService.php`, `InspectionExecutionService.php`, `AssetHistoryModel.php`, dan `WorkOrderOptimizer.php`.
- ❌ **NO GUESSING POLICY**: Dilarang menebak nama kolom, route, field array, atau dependency. Wajib *tracing* kode fisik terlebih dahulu.
- ❌ **NO BLIND PATCHING**: Dilarang hanya memperbaiki View apabila data producer yang rusak. Producer HARUS di-trace terlebih dahulu.
