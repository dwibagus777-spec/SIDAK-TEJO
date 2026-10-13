# TL-01 All-Network Transline Coverage Test Report

**Test Suite**: `AllNetworkCoverageForensicTest.php`  
**Assertions Tested**: 13 Tests, 502 Assertions  
**Runtime Environment**: PHP 8.2.12, PHPUnit 10.5.64  
**Result**: 🟢 **PASS (100% GREEN, 0 ERRORS, 0 FAILURES)**

---

### Executed Tests:
1. `testProductionDatabaseIdentityVerified`: Verified production DB identity (`sidaktejo_prod_hostinger`) and timestamp.
2. `testAll134FeedersAndThreeUlpsScanned`: Verified 134 registered feeders and 3 ULPs scanned (33 with assets, 101 gap).
3. `testProductionBaselinesCapturedDynamically`: Verified dynamic capture of live 216 translines & 239 connected assets.
4. `testPrePostCryptographicFingerprintsIdentical`: Verified domain tables maintain 100% identical cryptographic hashes.
5. `testZeroOperationalMutations`: Verified 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL mutations across database.
6. `testZeroTemuanTopologyEndpoints`: Verified strict domain separation of temuan (`temuan_used_as_topology_endpoint = 0`).
7. `testDenominatorIntegrityInMatrixCsv`: Verified coverage ratio formula strictly uses eligible assets as denominator.
8. `testCandidateClassificationTerminologyPreserved`: Verified existing candidate classification constants preserved.
9. `testAllEightDeliverableArtifactsPresent`: Verified all 8 deliverables exist with substantive non-zero bytes.
10. `testFourActiveFeedersExactMetricsInCsv`: Verified exact metrics for all 4 feeders:
    - Feeder 15 (BANJAR KEMANTREN): 205 assets, 197 translines, 203 connected, 2 isolated (99.02%, COMPLETE)
    - Feeder 4 (GEMURUNG): 229 assets, 2 translines, 3 connected, 226 isolated (1.31%, PARTIAL)
    - Feeder 56 (BANJARAN): 403 assets, 9 translines, 17 connected, 386 isolated (4.22%, PARTIAL)
    - Feeder 131 (BY PASS): 406 assets, 8 translines, 16 connected, 390 isolated (3.94%, PARTIAL)
11. `testRegionalUlpAndGlobalMathematicalReconciliation`: Verified exact regional ULP & Global UP3 identities:
    - ULP Sidoarjo Kota: 199 translines, 206 connected assets, 791 isolated (20.66%)
    - ULP Krian: 17 translines, 33 connected assets, 4,206 isolated (0.78%)
    - ULP Porong: 0 translines, 0 connected assets, 0 isolated (0.00%)
    - Global UP3: 216 translines, 239 connected assets, 4,997 isolated (4.56%)
    - Endpoint Mutual Exclusivity: $\sum(203 + 3 + 17 + 16) = \bigcup(239) = 239$.
12. `testLayerBCandidateReadinessDeterministicRules`: Verified Layer B deterministic classifications across all 134 feeders:
    - `CANDIDATE_READY`: 2 feeders (Banjaran, By Pass)
    - `NEEDS_REVIEW`: 30 feeders (Gemurung, Gedangan, Gading Kirana, Ecco + 26 Krian feeders)
    - `BLOCKED_DATA_QUALITY`: 1 feeder (Banjar Kemantren - 99.02% complete, remaining 2 isolated poles blocked by safety gates)
    - `NOT_ELIGIBLE_SCOPE`: 101 feeders (0 JTM assets)
    - Total: $\sum = 134$ feeders.
13. `testBanjarKemantrenTwentyEightTranslineProvenance`: Verified complete audit trail for the 28 promoted translines in Banjar Kemantren:
    - PK range 191..218 verified active (`is_active = 1`, `status = 'ACTIVE'`).
    - Authorized creator: `Administrator`.
    - Zero self-loops, zero temuan endpoints.