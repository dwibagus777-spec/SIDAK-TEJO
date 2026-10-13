# SLD-05: Visual Remediation Hardening — Final Readability Check & Forensic Audit

**Date:** 2026-09-15  
**Milestone:** SLD-05 (Visual Presentation Layer Hardening)  
**Status:** 🟡 **VISUAL_REMEDIATION_PASS — AWAITING PRODUCTION DEPLOYMENT**  
**Milestone Verdict:** ⏸ **HOLD — NOT SEALED (Production Gates Pending)**  
**Target Environments:**  
- Target Production Host: `https://sidaktejo.site/sld/view/15`  
- Local Execution Sandbox: `E:\XAMPP\htdocs\SIDAK TEJO`  

---

## 1. Executive Summary & 3-Layer Acceptance Architecture

Following authoritative architectural review, Milestone **SLD-05** is governed by a **3-Layer Acceptance Framework**:

```
Layer A — Code Engineering Contract
├── 102 automated PHPUnit tests
└── 8,513 assertions passing
└── Status: 🟢 PASS

Layer B — Local Visual Remediation & Readability
├── Geometric AABB label collision resolver (LabelOccupancyIndex)
├── Panoramic Main Trunk initial viewport (2200 x 1150)
├── Conductor span length pills (authoritative edge.length_meters)
├── Zoom-aware density control & interactive highlighting
├── PMS UNKNOWN neutral state & GTT 1T/2T distinction
└── Status: 🟢 PASS

Layer C — Production Environment Deployment & Audit
├── Code synchronization to Hostinger (via origin/main webhook)
├── Live API verification (GET /api/sld/feeder/15/layout)
├── Live DB source verification (mode: PRODUCTION_LIVE_DATABASE)
├── Live topology completeness guard (status: DATA_NOT_READY until TL-01)
├── Production visual screenshot audit at 1366x768 & 1920x1080
└── Status: ⏸ PENDING (Awaiting Human Authorization)
```

**Final Seal Rule:** Milestone SLD-05 may ONLY be SEALED when **Layer A + Layer B + Layer C = ALL PASS**.

---

## 2. Hardened Visual Solutions Implemented

### A. Geometric AABB Collision Protection (`LabelOccupancyIndex`)
* **Critique Addressed:** Simple vertical staggering does not mathematically guarantee zero collision across intersecting lines, vertical taps, and neighboring symbols.
* **Hardened Engine Implementation:**
  The renderer now executes an **Axis-Aligned Bounding Box (AABB) 2D Spatial Occupancy Index**:
  ```javascript
  class LabelOccupancyIndex {
      collides(box, padding = 2) {
          // Evaluates screen projection intersections: ax1 < bx2 && ax2 > bx1 && ay1 < by2 && ay2 > by1
      }
  }
  ```
* **Strict Priority Hierarchy:**
  1. **Priority 1: Node Physical Symbol** (Registered first; NO label or annotation may ever encroach upon a node's physical footprint).
  2. **Priority 2: Primary Asset Identifier** (`#${aId}`, `PMS`, `GTT`). Evaluates above candidate; if collides, evaluates below candidate; if both collide, suppresses text to prevent visual clutter (available on hover/click).
  3. **Priority 3: Conductor Span Annotation Pill** (Evaluates offset above or below the line midpoint).
  4. **Priority 4: Semantic Role Badge** (`GI BUDURAN INCOMER`, `GTT PORTAL`).
  5. **Priority 5: Secondary Asset Name** (Evaluated only at high zoom or in slide-out drawer).
* **Immutability Invariant:** Bounding box resolution operates **strictly at screen projection level**. SLD-04 coordinates (`grid_x`, `grid_y`) remain **100% IMMUTABLE**.

### B. Panoramic Main Trunk Initial Viewport
* **Critique Addressed:** Initial viewport must not be so zoomed in that only 2–3 nodes appear, nor so zoomed out that 205 nodes are squashed.
* **Hardened Engine Implementation:**
  * Initial Viewport dimensions set to **$2,200 \times 1,150\text{ px}$** centered on the Substation Header (GI Buduran) and the primary backbone of Feeder 15.
  * Displays:
    * GI Buduran Demarcation on the left
    * Node `#3231` (Banjar Kemantren 99, TM11)
    * The Main Trunk flowing horizontally across the canvas
    * Lateral branches extending vertically
    * PMS switch candidates and GTT transformers
  * Scale factor is near 1:1 on desktop displays ($1366 \times 768$ and $1920 \times 1080$).
* **5 Explicit Viewport Controls:**
  * `[ Zoom In ]`: Incremental center zoom (0.8x viewBox).
  * `[ Zoom Out ]`: Incremental center zoom (1.25x viewBox).
  * `[ Fit Main Network ]`: Bounding box of Zone 1 (C15-01) with $+100\text{ px}$ padding.
  * `[ Fit All ]`: Shows all 3 zones ($8,000 \times 4,000\text{ px}$).
  * `[ Reset ]`: Re-centers on the panoramic Main Trunk initial viewport.

### C. Conductor / Span Length Readability & Zoom Density
* **Authoritative Data Contract:** Directly renders `edge.length_meters`.
* **Zero Invention Rule:** If `length_meters` is null or zero, renders `Length unavailable` or `-`. Zero distance calculation from GPS.
* **Visual Presentation:** Rendered in an SVG contrast pill (`fill="#0f172a" stroke="#334155"`).
* **Zoom-Aware Gating:**
  * **Low Zoom ($< 60\%$):** Suppresses individual span pills to maintain clear topology.
  * **Medium Zoom ($60\% - 120\%$):** Displays compact span lengths ($174\text{ m}$) where space permits.
  * **High Zoom ($\ge 120\%$):** Full engineering annotation display.
  * **Edge Hover:** Highlights line in cyan (`#38bdf8`, width $4.5\text{ px}$) and activates glowing span pill with transline ID and length.

### D. Audit of Header KPI ("Panjang Konduktor Aktif: 0.00 km")
* **Source:** `$sldData['topology_summary']['total_conductor_length_km']` in legacy `app/Views/sld/index.php`.
* **Query:** `DynamicSldEngineService::renderFeederSld()` $\to$ `section_conductors` (CR-06F).
* **Semantic Meaning:** Represents operational active conductor configured under CR-06F, NOT GIS physical translines.
* **Governed Decision:** Preserved unchanged. In the new SLD-05 template, this legacy KPI is not displayed to prevent operational/physical confusion.

---

## 3. Strict Boundary & Zero-Write Verification ($\Delta = 0$)

### Unchanged Upstream Services (100% Sealed 🔒)
- `App\Services\SldTopologyReadModelService` — **UNTOUCHED**
- `App\Services\SldSemanticClassificationService` — **UNTOUCHED**
- `App\Services\SldLayoutCoordinateEngineService` — **UNTOUCHED**
- Database Tables (`assets`, `gis_translines`, `sections`, `penyulang`, `temuan`, etc.) — **UNTOUCHED**

### Zero-Write Verification Evidence
```text
INSERT   = 0
UPDATE   = 0
DELETE   = 0
TRUNCATE = 0
ALTER    = 0
DROP     = 0

SldForensicPhase0Test:
..............                                                    14 / 14 (100%)
OK (14 tests, 142 assertions) — Delta = 0 Verified
```

---

## 4. Full Regression Suite Results

Execution of the entire SLD test suite across all 6 test classes:
```text
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.12
Configuration: E:\XAMPP\htdocs\SIDAK TEJO\phpunit.dist.xml

...............................................................  63 / 102 ( 61%)
.......................................                         102 / 102 (100%)

Time: 00:03.991, Memory: 20.00 MB
OK (102 tests, 8513 assertions)
```

1. `AllNetworkCoverageForensicTest.php` (28 Feeders coverage) — **PASS**
2. `SldForensicPhase0Test.php` (Zero DB writes, schema invariants) — **PASS**
3. `SldTopologyReadModelServiceTest.php` (20 tests, including Live DB Guard Test #25) — **PASS**
4. `SldSemanticClassificationServiceTest.php` (Semantic hierarchy & GTT 15/4/4) — **PASS**
5. `SldLayoutCoordinateEngineServiceTest.php` (Deterministic coordinate calculation) — **PASS**
6. `SldVisualRendererContractTest.php` (9 tests, including AABB Collision Resolver) — **PASS**

---

## 5. Stop Gate & Final Status

* **Status:** 🟡 **VISUAL_REMEDIATION_PASS — AWAITING PRODUCTION DEPLOYMENT**
* **Milestone SLD-05:** ⏸ **NOT SEALED**
* **Milestones SLD-06 & SLD-07:** 🛑 **STRICTLY LOCKED & UNTOUCHED**
* **Production Database:** Zero mutation. No fake translines injected.

**Deployment Package Ready (Code Only):**
1. `app/Config/Routes.php`
2. `app/Controllers/DynamicSldController.php`
3. `app/Views/sld/index.php`
4. `app/Controllers/Api/SldApiController.php`
5. `app/Services/SldTopologyReadModelService.php`
6. `app/Services/SldSemanticClassificationService.php`
7. `app/Services/SldLayoutCoordinateEngineService.php`
8. `public/assets/js/sld/sld-renderer-engine.js`
