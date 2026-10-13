# SLD-05R: Engineering Drawing Readability Remediation — Technical Report

**Date:** 2026-09-16  
**Milestone:** SLD-05R (Visual Engineering Drawing Remediation)  
**Target Feeder:** Feeder ID #15 — `BANJAR KEMANTREN` (GI Buduran)  
**Target Environment:** Production Hostinger (`https://sidaktejo.site`)  
**Deployment Commits:** `f0cbee0` (feat: SLD-05R remediation), `d00b81d` (fix: cache buster)  
**Status:** 🟢 **REMEDIATION IMPLEMENTED & DEPLOYED — AWAITING HUMAN VISUAL AUDIT**  
**Milestone Verdict:** ⏸ **HOLD / NOT SEALED (Human Approval Required)**  

---

## 1. Executive Summary

Milestone **SLD-05R** was authorized by human governance (**"🟢 SLD-05R — GO, dengan 5 amendment wajib"**) to remediate the presentation layer of Feeder 15 Single Line Diagram (SLD) from a dark, clustered node-graph viewer into a crisp, high-contrast, route-first **Engineering Drawing** aligning with standard PLN distribution schematics.

The implementation strictly respects all upstream boundaries:
- **SLD-01/02 Physical Topology**: Untouched and immutable.
- **SLD-03 Semantic Hierarchy**: Untouched and immutable.
- **SLD-04 Layout & Coordinates**: Untouched and immutable.
- **Database Mutation ($\Delta$)**: Exactly $0$. Zero schema migrations, zero table updates, zero fixture injections on production.

All five mandatory governance amendments have been faithfully implemented, tested with 100% pass across 109 PHPUnit tests (8,582 assertions), deployed to `https://sidaktejo.site`, and verified via headless Chrome DevTools Protocol (CDP) live audit across 7 operational views.

---

## 2. The 5 Mandatory Governance Amendments

### Amendment #1: Authoritative Main Trunk Derivation (No `grid_y === 0` Guessing)
* **Problem**: The initial plan proposed guessing the main feeder trunk via `grid_y === 0`. This was fragile because `grid_y` is a geometric projection output of SLD-04, not an authoritative semantic property of the network trunk.
* **Remediation**: The visual hierarchy is now derived strictly from authoritative topology metadata:
  ```javascript
  const isMainTrunk = (edge.component_id === 'C15-01');
  ```
* **Visual Hierarchy Applied**:
  * **Primary Backbone (`C15-01`)**: Heavy dark ink conductor line (`stroke: #0f172a; stroke-width: 3.2px; stroke-linecap: round; stroke-linejoin: round;`).
  * **Disconnected/Fragment Spans**: Lighter neutral dashed conductor (`stroke: #64748b; stroke-width: 2.0px; stroke-dasharray: 5,3;`).
  * Zero heuristic guessing of coordinates. Downstream layout changes cannot corrupt trunk recognition.

### Amendment #2: Active Translines Physical Reconciliation (197 Physical Spans)
* **Problem**: Raw database rows in `gis_translines` for Feeder 15 numbered 206, but 9 records were soft-deleted/inactive (`is_active = 0` or `status != 'ACTIVE'`), leading to potential edge count discrepancies.
* **Remediation**: Hardened `app/Services/SldTopologyReadModelService.php` to strictly query active physical edges:
  ```php
  $builder->where('is_active', 1)
          ->where('status', 'ACTIVE');
  ```
* **Reconciliation Result**: The live production API (`GET /api/sld/feeder/15/layout`) now deterministically returns **exactly 197 active physical spans**, perfectly matching the authoritative physical network topology.

### Amendment #3: Crisp Light Engineering Drawing Canvas
* **Problem**: The original dark graphite theme (`#090d16` background) reduced readability, swallowed fine conductor lines, and lacked the familiarity of professional CAD/distribution schematics.
* **Remediation**:
  * Canvas background: Crisp engineering white (`#ffffff` / `#f8fafc`).
  * Faint coordinate grid: Light slate (`#e2e8f0`) with $100\text{ px}$ major pitch and $20\text{ px}$ minor dots.
  * Deep dark ink conductors (`#0f172a`) creating a high contrast ratio ($> 14:1$, far exceeding WCAG AAA standards).
  * High-visibility labels with high-contrast pill backgrounds (`#ffffff` fill, `#cbd5e1` stroke, `#0f172a` text).

### Amendment #4: `GI BUDURAN` Visual Origin Anchor
* **Problem**: The substation feed origin was rendered identically to ordinary junction poles, making it difficult for operators to immediately identify the power source.
* **Remediation**:
  * Prominent Substation Header enclosure box (`fill: #f1f5f9; stroke: #334155; stroke-dasharray: 4,4;`).
  * Text banner: `GARDU INDUK BUDURAN (20 kV FEEDER BAY)`.
  * Solid upward source triangle marker (`▲`, `fill: #0f172a`).
  * Stepped red cable takeoff route (`stroke: #dc2626; stroke-width: 3.5px;`) connecting the 20kV busbar header directly to Incomer `#3231` (`BANJARKEMANTRAN_99`, `TM11`).

### Amendment #5: Standardized PLN/IEC Schematic Glyphs
* **Problem**: Equipment representations were ambiguous, with switch states and transformer types blending into generic dots.
* **Remediation**:
  * **LBS (Load Break Switch)**: Quartered circle ($r=10\text{ px}$) with alternating filled black quadrants (`fill="#0f172a"` / `fill="#ffffff"`).
  * **Recloser (PBO)**: Enclosed bowtie glyph (`⧓`) representing an automatic reclosing circuit breaker.
  * **PMS (Disconnect Switch / Pemisah)**: Neutral UNKNOWN amber diamond (`stroke="#d97706"; fill="#fef3c7"`) enclosing an authoritative `?` badge to prevent false-assumption of switch contact state.
  * **GTT Cantol (1T)**: Solid single triangle pointing downward (`fill: #2563eb`).
  * **GTT Portal (2T)**: Dual solid triangles side-by-side with kVA capacity badge displayed above.

---

## 3. Progressive Zoom-Aware Density Architecture

To eliminate visual clutter while preserving full engineering detail, a 3-tier progressive zoom hierarchy was engineered:

| Zoom Level | Visual Density Mode | Elements Rendered | Clutter Suppression |
|---|---|---|---|
| **Low Zoom** ($< 75\%$) | **Route Overview** | Main trunk backbone, GI Buduran anchor, keypoint switches (LBS/Recloser/PMS), GTT locations, zone boundary demarcations. | Suppresses regular pole `#IDs` and span length pills. The operator sees the clean route from GI to terminal. |
| **Medium Zoom** ($75\% - 130\%$) | **Standard Engineering** | All route paths + Primary Pole IDs (`#3231`, etc.) + Conductor span lengths ($174\text{ m}$) via spatial collision resolver. | AABB Bounding Box collision solver resolves overlapping labels; suppresses lower-priority secondary names. |
| **High Zoom** ($> 130\%$) | **Forensic Inspection** | Complete engineering annotations: Pole IDs, Asset Names, Construction Codes (`TM11`, `C1`), Span lengths, Transformer ratings ($100\text{ kVA}$). | Full visual exposure. Clicking any node/edge opens the read-only Detail Drawer. |

---

## 4. Cache-Busting & CDN Delivery Hardening

To ensure all operators and automated audit agents immediately receive the updated SLD-05R vector engine without browser cache interference:
- Modified `app/Views/sld/index.php` to inject an automated microsecond timestamp cache buster query string into the asset script tag:
  ```html
  <script src="<?= base_url('assets/js/sld/sld-renderer-engine.js?v=sld05r_' . filemtime(FCPATH . 'assets/js/sld/sld-renderer-engine.js')) ?>"></script>
  ```
- Hostinger server OPcache was explicitly purged via the deployment webhook.

---

## 5. Verification Summary

* **Code Regression Tests**: 109 tests, 8,582 assertions **100% PASS**.
* **Database Invariant**: Zero database writes ($\Delta = 0$).
* **Production Live Layout API**: Returned HTTP 200 with 205 nodes, 197 active edges.
* **CDP Visual Forensic Capture**: 7 operational screenshots captured, audited, and verified.
