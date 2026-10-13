# TL-01 All-Network Transline Coverage Summary

**Audience**: Executive Management, Engineering Leadership, Operator Team  
**Scope**: 134 Feeders across 3 ULPs (Sidoarjo Kota, Krian, Porong)  
**Status**: 🟢 **AUDIT COMPLETE — ZERO WRITE ENFORCED**

---

### Key Takeaways at a Glance:

| Metric | Measured Reality | Status / Insight |
|---|---|---|
| **Total Feeders Registered** | **134** | Full administrative coverage of UP3 Sidoarjo |
| **Feeders with Physical Assets** | **33** | 24.63% of network mapped with GIS coordinates |
| **Feeders with Zero Assets** | **101** | Upstream asset data gap |
| **Total JTM Assets** | **5,236** | 100% valid GPS coordinates |
| **Authoritative Translines** | **216** | Distributed across 4 active feeders (199 Kota, 17 Krian) |
| **Connected Assets** | **239 (4.56%)** | Network connectivity in production ($\sum = \bigcup = 239$) |
| **Isolated Assets** | **4,997 (95.44%)** | Assets awaiting topological connection |

---

### Dua Lapisan Klasifikasi (Two-Layer Architecture):

#### Layer A — Authoritative Coverage Status:
- **🟢 COMPLETE (1 Feeder)**: Banjar Kemantren (99.02% connected, 197 TL, 203 connected).
- **🟡 PARTIAL (3 Feeders)**: Gemurung (1.31%, 2 TL), Banjaran (4.22%, 9 TL), By Pass (3.94%, 8 TL).
- **🔴 MISSING (29 Feeders)**: Aset fisik tersedia (misal Gedangan, Gading Kirana, Ecco), namun 0 transline terpasang.
- **⚪ NO_ASSET_DATA (101 Feeders)**: Penyulang terdaftar belum memiliki koordinat aset fisik JTM.

#### Layer B — Candidate Readiness:
- **🚀 CANDIDATE_READY (2 Feeders)**: Banjaran (1 auto-match) & By Pass (7 auto-match).
- **👀 NEEDS_REVIEW (30 Feeders)**: Kandidat geometri tersedia namun memerlukan verifikasi rekayasa manusia.
- **🛑 BLOCKED_DATA_QUALITY (1 Feeder)**: Banjar Kemantren (99.02% stabil; sisa 2 tiang diblokir safety gate bentang).
- **⚪ NOT_ELIGIBLE_SCOPE (101 Feeders)**: 0 aset fisik JTM.

---

### Provenance 28 Transline Tambahan Banjar Kemantren:
- Baseline: 169 transline &bull; Promosi: 28 transline (PK 191 s/d 218 disahkan `Administrator`) &bull; Total: 197 transline.

---

### Strategic Direction:
Do NOT execute bulk auto-writes. Proceed with prioritized, controlled human review starting with **Gemurung**, **Gedangan**, and **Gading Kirana** in Sidoarjo Kota, followed by **By Pass** and **Banjaran** in Krian.