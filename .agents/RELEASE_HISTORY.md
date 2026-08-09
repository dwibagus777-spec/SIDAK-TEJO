# 🚀 RELEASE HISTORY

| Version Tag | Release Name / Focus | Key Changes | Status |
| :--- | :--- | :--- | :--- |
| **`v2.3.0.35`** | Atomic Task Pool Ownership Claim | Implemented `assigned_inspector_id` locking in `InspectionController::storeStart()` | 🟢 LIVE |
| **`v2.3.0.36`** | Apache Asset Folder Collision Resolution | Updated GeoJSON route caller in `planning/create.php` to `master-assets/geojson` | 🟢 LIVE |
| **`v2.3.0.37`** | Linux PSR-4 Case-Sensitivity Alignment | Aligned `GISController` ➔ `GisController` filename and class name | 🟢 LIVE |
| **`v2.3.0.38`** | Query Builder Literal Escaping & CSRF Refresh | Added `false` to `$builder->select(..., false)` and added dynamic CSRF token refresh in `guided.php` | 🟢 LIVE |
| **`v2.3.0.39`** | Asset Detail Title Contract Fix | Added safe defensive coalescing `<?= esc($wo['judul_wo'] ?? $wo['judul_pekerjaan'] ?? '-') ?>` | 🟢 LIVE |
| **`v2.3.0.40`** | Smart WO & Asset Health Index Contract | Fixed `$wo['judul_wo']` in `smart_wo/index.php` and selected `NULL as kode_section` in `AssetHealthService.php` | 🟢 LIVE |
| **`v2.3.0.41`** | Smart WO Producer-Consumer Alignment | Enriched `$optimizedWos` directly in `SmartWorkOrderService` and added safe rendering in `smart_wo/index.php` | 🟢 LIVE |
| **`v2.3.0.42`** | Smart WO View Contract Hardening | Applied full defensive null coalescing `??` across all array keys in `smart_wo/index.php` loop | 🟢 LIVE |
| **`vA-asset-integrity`** | Release A: Asset Integrity & Pipeline Filtering | Added `deleted_at IS NULL` to Baseline, Guided, and Progress queries; added JS guard clause in `guided.php` | 🟢 LIVE |
| **`vB-feeder-gis-network`** | Release B: GIS Feeder Network Map | Implemented feeder-scoped GeoJSON LineString transline, viewport bounding-box loading, and Haversine proximity | 🟢 LIVE |
| **`vB.1-js-syntax-fix`** | Release vB.1: GIS View JS Syntax Fix | Fixed unclosed geolocation callback and missing closing brackets in `app/Views/gis/index.php` | 🟢 LIVE |
| **`vB.2-markercluster-resolution`** | Release vB.2: MarkerCluster Dependency Resolution | Updated MarkerCluster CDN URL to cdnjs and implemented defensive capability detection `typeof L.markerClusterGroup === 'function'` | 🟢 LIVE |
| **`vC-interactive-map-inspection-claim`** | Release C: Interactive Map Marker Inspection Selection | Added `startByAsset()` endpoint with 100% server-side validation & idempotent session claim from GIS map | 🟡 READY FOR RUNTIME VERIFICATION (C-08 Concurrency Evidence Pending) |
| **`v-nav-ux-reborn`** | Navigation UX Refactor (Performance-First / Zero Regression) | Refactored sidebar into 5 grouped collapsible categories, desktop collapsed mode (~68px), and mobile bottom nav bar | 🟡 DEPLOYED (Pending Runtime Verification) |
| **`Release D`** | Full Feeder Inspection Journey & Construction-Shape GIS | Implemented full feeder planned asset context, construction shape markers (Tiang, GTT, GDG, Kubikel, Trafo), secondary status badges, progress card, and query param state rehydration | 🟡 READY FOR RUNTIME VERIFICATION |
