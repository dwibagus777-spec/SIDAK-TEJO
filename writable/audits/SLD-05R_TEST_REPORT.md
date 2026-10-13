# SLD-05R: Automated Regression Test Report

**Date:** 2026-09-16  
**PHP Version:** 8.2.12  
**PHPUnit Version:** 10.5.64  
**Target Feeder:** Feeder ID #15 — `BANJAR KEMANTREN` (GI Buduran)  
**Test Status:** 🟢 **ALL 109 TESTS PASS (100% OK, 8,582 ASSERTIONS)**  

---

## 1. Test Suite Summary

```
Runtime:       PHP 8.2.12
Configuration: E:\XAMPP\htdocs\SIDAK TEJO\phpunit.dist.xml

...............................................................  63 / 109 ( 57%)
..............................................                  109 / 109 (100%)

Time: 00:04.637, Memory: 22.00 MB
OK (109 tests, 8582 assertions)
```

---

## 2. Test File Inventory & Assertions Breakdown

| No | Test File | Description | Tests | Assertions | Status |
|---|---|---|---|---|---|
| 1 | `tests/unit/Sld05REngineeringReadabilityTest.php` | SLD-05R 5 Mandatory Amendments Verification | 7 | 69 | 🟢 PASS |
| 2 | `tests/unit/SldVisualRendererContractTest.php` | Visual presentation layer contract, theme tokens, symbology, drawer | 8 | 65 | 🟢 PASS |
| 3 | `tests/unit/SldLayoutCoordinateEngineServiceTest.php` | SLD-04 coordinate engine, deterministic positioning, routing, spacing | 10 | 608 | 🟢 PASS |
| 4 | `tests/unit/SldSemanticClassificationServiceTest.php` | SLD-03 semantic hierarchy, GTT subtype, keypoint classification, roles | 22 | 730 | 🟢 PASS |
| 5 | `tests/unit/SldTopologyReadModelServiceTest.php` | SLD-02 topology graph, live DB guard, edge filtering, connectivity | 25 | 843 | 🟢 PASS |
| 6 | `tests/unit/SldForensicPhase0Test.php` | Feeder 15 forensic baseline, asset integrity, graph cycle analysis | 36 | 6,266 | 🟢 PASS |
| 7 | `tests/unit/AllNetworkCoverageForensicTest.php` | Global 28-feeder read-only integrity contract | 1 | 1 | 🟢 PASS |
| **TOTAL** | **7 Test Files** | **Full SLD Regression Test Suite** | **109** | **8,582** | 🟢 **PASS** |

---

## 3. Dedicated SLD-05R Test Matrix (`Sld05REngineeringReadabilityTest.php`)

| Method Name | Amendment Verified | Assertions | Key Verification Criteria |
|---|---|---|---|
| `testAmendment1MainTrunkDerivedFromComponentNotGridY` | Amendment #1 | 7 | Verifies renderer engine derives `isMainTrunk` from `component_id === 'C15-01'`; confirms `grid_y === 0` is NOT used for trunk inference. |
| `testAmendment2ActiveTranslinesReconciledTo197Edges` | Amendment #2 | 6 | Verifies `SldTopologyReadModelService` filters `is_active = 1` and `status = 'ACTIVE'`, yielding exactly 197 active physical translines. |
| `testAmendment3LightEngineeringThemeTokens` | Amendment #3 | 12 | Verifies light canvas background (`#ffffff` / `#f8fafc`), high-contrast ink stroke (`#0f172a`), grid color (`#e2e8f0`), and CSS contrast ratios. |
| `testAmendment4GiBuduranSubstationAnchor` | Amendment #4 | 14 | Verifies Substation Header enclosure, solid black triangle `▲`, 20kV feeder bay badge, and red stepped cable takeoff to Incomer `#3231`. |
| `testAmendment5PlnIecSchematicGlyphs` | Amendment #5 | 16 | Verifies LBS quartered circle, Recloser bowtie, PMS neutral UNKNOWN amber diamond with `?`, and GTT 1T/2T solid triangle representations. |
| `testProgressiveZoomDensityThresholds` | Zoom Architecture | 8 | Verifies low zoom (<75%) hides pole IDs, medium zoom (75%-130%) reveals pole IDs, high zoom (>130%) exposes full engineering annotations. |
| `testCacheBusterPresentInSldView` | Cache Busting | 6 | Verifies `app/Views/sld/index.php` contains the dynamic cache buster parameter (`?v=sld05r_...`) on the script tag. |

---

## 4. Invariant Verification

1. **Zero Regression on SLD-01 to SLD-04**:
   - Every existing upstream test in SLD-01 (`SldForensicPhase0Test`), SLD-02 (`SldTopologyReadModelServiceTest`), SLD-03 (`SldSemanticClassificationServiceTest`), and SLD-04 (`SldLayoutCoordinateEngineServiceTest`) passed with zero failures.
2. **Determinism**:
   - Layout coordinate engine tests confirmed that running layout generation multiple times yields identical $(x, y)$ coordinates for all 205 nodes.
3. **Execution Speed**:
   - All 109 tests completed in 4.64 seconds, with memory usage capped at 22.00 MB.
