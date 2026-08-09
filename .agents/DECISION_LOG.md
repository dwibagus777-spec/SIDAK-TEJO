# 📜 DECISION LOG & ARCHITECTURAL RECORDS

| Decision ID | Date | Decision & Description | Reason / Rationale | Impact / Boundary |
| :--- | :--- | :--- | :--- | :--- |
| **DEC-001** | 2026-08-05 | **Atomic Inspector Claim & Locking** | Prevents race conditions when multiple inspectors in a ULP pool view the same task | Sets `assigned_inspector_id = user_id` and `status = IN_PROGRESS` on claim |
| **DEC-002** | 2026-08-06 | **`master-assets/` Route Namespace** | Resolves Apache physical directory collision on `public/assets/` folder | All dynamic application asset routes MUST use `master-assets/` or `api/` |
| **DEC-003** | 2026-08-06 | **PSR-4 Controller Class Name Case Sensitivity** | Hostinger Linux filesystem is case-sensitive | `GisController.php` physical filename matches `class GisController` |
| **DEC-004** | 2026-08-07 | **Dynamic CSRF Token Refresh in Guided Execution** | Prevents HTTP 403 Forbidden during multi-point sequential asset submissions | Response includes `csrf_token` & `csrf_hash`; JS updates headers dynamically |
| **DEC-005** | 2026-08-09 | **Single GeoJSON LineString Polyline Layer for GIS** | Renders feeder transline as 1 single geometry layer instead of thousands of individual DOM line segments | Lightning-fast rendering (<2s, <200KB payload) |
| **DEC-006** | 2026-08-09 | **Viewport Bounding-Box Asset Loading** | Only fetches & renders point markers visible in current map viewport (`min_lat`, `max_lat`, `min_lng`, `max_lng`) | Prevents browser freeze on feeders with thousands of assets |
| **DEC-007** | 2026-08-09 | **Smart Proximity Haversine Distance Calculation** | Calculates real-time distance in meters from inspector's GPS to nearest asset with 30m smart tolerance | Displays `GDG-047 • 18 m dari Anda` + `[ MULAI INSPEKSI ]` |
