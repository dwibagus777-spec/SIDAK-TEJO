# TL-01 All-Network Transline Coverage Data Quality Report

**Scope**: 5,236 JTM Assets & 216 Translines across 134 Feeders  
**Status**: 🟢 **VERIFIED COMPLIANT WITH ZERO MUTATION**

---

## 1. Geometry & Coordinate Quality
- **Total Registered JTM Assets**: 5,236
- **Valid GPS Assets**: 5,236 (100.0%)
- **Invalid / Missing Coordinates**: 0 (0.0%)
- **Bounding Box Validation**: All coordinates lie within East Java / Sidoarjo operational area.

## 2. Topological Graph Integrity
- **Duplicate Edges**: `0`
- **Self-Loops**: `0`
- **Cross-Feeder Edges**: `0`
- **Cross-ULP Edges**: `0`
- **Invalid Endpoints**: `0` (100% of transline endpoints resolve to valid asset IDs).
- **Temuan Isolation**: `0` (Temuan records are never treated as network nodes).

## 3. Two-Layer Classification Data Quality Summary
- **Layer A Coverage**: 1 COMPLETE, 3 PARTIAL, 29 MISSING, 101 NO_ASSET_DATA (Total: 134).
- **Layer B Readiness**: 2 CANDIDATE_READY, 30 NEEDS_REVIEW, 1 BLOCKED_DATA_QUALITY, 101 NOT_ELIGIBLE_SCOPE (Total: 134).
- **Provenance Verification**: Banjar Kemantren 28 promoted translines (PK 191..218) verified 100% active with valid geometry.