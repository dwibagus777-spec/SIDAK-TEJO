# TL-01 Sub-Gate D4A — Environment & Baseline Reconciliation Report

**Audit Timestamp**: 2026-09-04T17:25:00+07:00  
**Sub-Gate**: TL-01 Sub-Gate D4A (Proposal Exception Review Workbench)  
**Classification**: **B. LOCAL DEVELOPMENT DATABASE**  
**UI Status**: 🟢 **PASS / VERIFIED**  
**Production Verification Status**: 🟡 **PENDING (Awaiting Production Environment Access)**  
**Sub-Gate D4B Status**: 🔒 **STRICTLY LOCKED**  

---

## 1. Environment Identification

| Parameter | Value | Notes |
|---|---|---|
| **Git HEAD** | `c74db8db2e0bf9f23101317cabe43337f93b7aff` | Clean commit |
| **Working Directory** | `E:\XAMPP\htdocs\SIDAK TEJO` | Local development workspace |
| **Machine Hostname** | `JTM-ULPSDK-INS` | Local workstation |
| **CI_ENVIRONMENT** | `development` | Defined in `.env` |
| **baseURL** | `https://localhost/SIDAK%20TEJO/public` | Local Apache subfolder virtual URL |
| **Browser Host URL** | `https://localhost/SIDAK%20TEJO/public/gis` | Accessed via Chromium CDP |
| **Default DB Connection**| `127.0.0.1:3306` (MySQLi) | Local MariaDB daemon |
| **Default DB Name** | `sidaktejo` | Local development database |
| **Test DB Connection** | `:memory:` (SQLite3) | `tests` connection group in CI4 |

---

## 2. Database Identification & Query Evidence

Executed read-only query on the active database connection:
```sql
SELECT DATABASE();
```
**Result**: `sidaktejo`

### Table Count Verification Query Results:

```sql
SELECT COUNT(*) FROM gis_translines;          -- Result: 0
SELECT COUNT(*) FROM gis_transline_proposals; -- Result: TABLE NOT FOUND (Read Model Fallback active)
SELECT COUNT(*) FROM assets;                  -- Result: 30
SELECT COUNT(*) FROM sections;                -- Result: 508
SELECT COUNT(*) FROM penyulang;               -- Result: 134
SELECT COUNT(*) FROM temuan;                  -- Result: 441
SELECT COUNT(*) FROM temuan_materials;        -- Result: TABLE NOT FOUND
```

---

## 3. Comparison with Sealed TL-01 Production Baseline

| Entity Table | Sealed Production Baseline | Observed Local Development (`sidaktejo`) | Status / Variance |
|---|:---:|:---:|:---:|
| `gis_translines` | **42** | **0** | Baseline not loaded in local DB |
| `gis_transline_proposals` | **5** | **0** (Table absent, read model fallback) | Baseline not loaded in local DB |
| `assets` | **2660** | **30** | Local sample data |
| `sections` | **134** | **508** | Local legacy section table |
| `penyulang` | **28** | **134** | Local feeder master table |
| `temuan` | **623** | **441** | Local inspection records |
| `temuan_materials` | **1842** | **0** (Table absent in local DB) | Local table absent |

---

## 4. Root Cause Determination

### Classification: **B. LOCAL DEVELOPMENT DATABASE**

**Root Cause Forensic**:
1. **Origin of Sealed Baseline (`42 / 5 / 2660 / 134 / 28 / 623 / 1842`)**:
   As documented in `scratch/verify_zero_write_d3.php` lines 122–300, this baseline was established as a **canonical in-memory SQLite fixture dataset** (`Database::connect('tests')`) specifically constructed for TL-01 automated test suites and regression pipelines.
2. **Origin of Observed Counts (`0 / 0 / 30 / 508 / 134 / 441 / 0`)**:
   These counts reside in the persistent local MariaDB instance (`127.0.0.1:3306`, database `sidaktejo`) which serves the local Apache web server. This is a **local development fixture**, not the production database.
3. **Implication**:
   The live Chromium browser session interacted with the local development database, not the sealed production database.
   Therefore, **no production zero-write claim can be made at this time**.

---

## 5. Browser Host Reconciliation

- **Accessed URL**: `https://localhost/SIDAK%20TEJO/public/gis`
- **Host Type**: **LOCAL DEVELOPMENT** (Local Apache instance on port 80/443 within XAMPP on Windows).
- **Actual Production URL**: Production is hosted on dedicated server infrastructure (external to the local development XAMPP instance).
- **Conclusion**: The Chromium CDP screenshots demonstrate UI/UX functioning correctly within the local development environment, but do NOT constitute verification against the production host.

---

## 6. D4A UI Preservation

Per the explicit gate mandate:
- **No changes were made to the D4A UI**.
- The `🤖 Proposal AI · 5` topbar entry point, `#offcanvas-proposals-drawer`, 6 state filter pills, 5 proposal cards, `#modal-proposal-workbench`, dual asset comparison, deterministic AI evidence inspector, and in-memory Leaflet focus map have all been preserved exactly as verified.
- The UI is confirmed working correctly and independently of the backend data provider.

---

## 7. Test Assertion Delta Breakdown (+20 Assertions)

### Previous Regression Status:
- **120 tests / 529 assertions** (100% PASS)

### Current Reported Status:
- **120 tests / 549 assertions** (100% PASS)

### Root Cause of +20 Assertions:

| Test Suite / File | Test Method | Previous Assertions | Current Assertions | Delta | Reason |
|---|---|:---:|:---:|:---:|---|
| `GisTranslineProposalSchemaTest.php` | `testProposalTableMigrationUpAndDown` | 19 | 39 | **+20** | Added exhaustive column existence checks (23 fields) and before/after table fingerprint hashes for 4 monitored tables |
| All other 7 test suites | *Unchanged* | 510 | 510 | 0 | Preserved identically |
| **Total** | | **529** | **549** | **+20** | **100% Accounted For** |

No assertions were removed, weakened, or bypassed. The 20 additional assertions represent stricter schema column verification in `GisTranslineProposalSchemaTest.php`.

---

## 8. Forensic Zero-Write Verification on Tested Environment

An automated 11-step interactive browser test (`scratch/run_forensic_zero_write_d4a.js`) was executed against the local environment (`sidaktejo`):

### Protocol Steps Executed:
1. Authenticate as admin via login form.
2. Navigate to `/gis` and open Stage 2 map workspace.
3. Open Proposals Queue Drawer.
4. Click all 6 state filter pills sequentially (`ALL`, `GOVERNANCE_ANOMALY`, `BLOCKED`, `HUMAN_REVIEW`, `READY`, `ACTIVE`).
5. Trigger `focusProposalOnMap(1)` (in-memory Leaflet highlight).
6. Open Workbench Modal for Proposal #1.
7. Close Workbench Modal.
8. Re-open Workbench Modal for Proposal #2.
9. Close Workbench Modal.
10. Re-open Queue Drawer.
11. Close Queue Drawer.

### Forensic Fingerprint Comparison:

| Table | Count Before | Count After | Delta | SHA-256 Hash Status |
|---|:---:|:---:|:---:|:---:|
| `assets` | 30 | 30 | 0 | 🔒 IDENTICAL (`c5572459...`) |
| `sections` | 508 | 508 | 0 | 🔒 IDENTICAL (`dee70c47...`) |
| `penyulang` | 134 | 134 | 0 | 🔒 IDENTICAL (`b56303b0...`) |
| `temuan` | 441 | 441 | 0 | 🔒 IDENTICAL (`ab3e75fc...`) |
| `gis_translines` | 0 | 0 | 0 | 🔒 IDENTICAL (`4f53cda1...`) |

### Official Forensic Label Assigned:
> **ZERO-WRITE VERIFIED ON NON-PRODUCTION ENVIRONMENT**  
> *(Explicitly NOT labeled as "Production Zero-Write Verified")*

---

## 9. Final Gate Classification

| Dimension | Classification / Status |
|---|:---:|
| **D4A Backend Read Model** | 🟢 **PASS** |
| **D4A UI Realization & UX** | 🟢 **PASS** |
| **D4A Mobile Viewport** | 🟢 **PASS** |
| **D4A Zero-Write Guarantee** | 🟢 **ZERO-WRITE VERIFIED ON NON-PRODUCTION ENVIRONMENT** |
| **D4A Production Verification**| 🟡 **PENDING (Environment is Local Development)** |
| **Sub-Gate D4B (Mutations)** | 🔒 **STRICTLY LOCKED** |

*No database migration, seeding, or mutation was performed. Zero writes executed.*
