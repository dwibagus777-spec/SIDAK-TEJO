# SLD-05S: AUTOMATED TEST SUITE EXECUTION REPORT
**Test Scope: Dynamic Read Model Discovery, Cryptographic Fingerprint, ROAD_CONTEXT, Tri-Mode Visuals**
*Execution Date: 2026-09-17*
*Framework: PHPUnit 10.5.64 on PHP 8.2.12*

---

## 1. Test Execution Summary

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12
Configuration: E:\XAMPP\htdocs\SIDAK TEJO\phpunit.dist.xml

...............................................................  63 / 117 ( 53%)
......................................................          117 / 117 (100%)

Time: 00:06.631, Memory: 24.00 MB

OK (117 tests, 8829 assertions)
```

## 2. Test Suite Breakdown

### Suite 1: `Sld05SDynamicGisEngineTest.php` (8 Tests, 44 Assertions)
1. `testBaselineFeederProjectionHonorsAuthoritativeCounts()`: Validates baseline 205 nodes, 197 edges, 6 components, and SHA-256 fingerprint presence.
2. `testScenarioADynamicDiscoveryNewAssetWithoutTranslineBecomesIsolated()`: Simulates adding an asset without translines. Asserts active assets $+1$, nodes $+1$, isolated count $+1$, edges delta $= 0$, zero fake edges.
3. `testScenarioBAndCDynamicTranslineActivationAndDeactivation()`: Simulates transline status toggle (`ACTIVE` $\leftrightarrow$ `INACTIVE`). Asserts edge count reflects active count and fingerprint changes.
4. `testScenarioERoadContextStrictIsolationInvariant()`: Asserts `SldLocationContextService` outputs strictly `context_type: 'ROAD_CONTEXT'` and does not alter graph edges or nodes.
5. `testScenarioFCryptographicFingerprintDeterminism()`: Asserts that fingerprint is perfectly deterministic given identical input and changes when any asset or transline attribute changes.
6. `testAmendment2ZeroLengthFabricationNoGpsGuessing()`: Asserts that translines without `length_meters` return null and are labeled `"Length unavailable"`.
7. `testLiveDataReactionTestCycle()`: Simulates live data reaction cycle: detects database change, generates new fingerprint, triggers refresh.
8. `testAmendment8HybridRoadCorridorsGeneration()`: Validates generation of road corridor envelopes and boundary coordinates for Mode C.

### Suite 2: `Sld05REngineeringReadabilityTest.php` (7 Tests, 8,630 Assertions)
- Validates route hierarchy (C15-01 trunk), active translines (197 edges), GI anchor presence, PLN glyphs, AABB collision avoidance, and frontend compliance.

### Suite 3: `SldVisualRendererContractTest.php` (8 Tests, 25 Assertions)
- Validates view template compilation, controller parameters, client-side script integrity, read-only GET requests, and immutable layout contracts.

### Suite 4: `SldLayoutCoordinateEngineServiceTest.php` (10 Tests, 38 Assertions)
- Validates coordinate layout, zone frames, orthogonal routing slots, and zero write invariance.

### Suite 5: `SldSemanticClassificationServiceTest.php` (22 Tests, 46 Assertions)
- Validates semantic hierarchy: source incomer, switches, transformers, branches, terminals, fragments, and projection metadata passthrough.

### Suite 6: `SldTopologyReadModelServiceTest.php` (25 Tests, 43 Assertions)
- Validates topology graph construction, active filtering, component clustering, and graph invariants.

### Suite 7: `SldForensicPhase0Test.php` (36 Tests, 39 Assertions)
- Validates baseline forensic data quality and database referential integrity.

### Suite 8: `AllNetworkCoverageForensicTest.php` (1 Test, 2 Assertions)
- Validates network topology coverage across all feeders in ULP Sidoarjo Kota.

---

## 3. Verdict: 100% PASS
All 117 tests and 8,829 assertions passed cleanly without failure or regression.