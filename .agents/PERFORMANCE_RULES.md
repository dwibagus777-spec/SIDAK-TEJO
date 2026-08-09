# ⚡ PERFORMANCE & FIELD USABILITY RULES

## 1. PERFORMANCE FIRST PRINCIPLE
$$\text{FAST} \longrightarrow \text{ACCURATE} \longrightarrow \text{FIELD-FIRST} \longrightarrow \text{SCALABLE} \longrightarrow \text{AUDITABLE}$$

## 2. GIS RENDERING RULES
- ❌ **NO GLOBAL SELECT ALL**: Never issue `SELECT *` across all 50,000 assets to pass to the browser.
- ✅ **FEEDER SCOPED**: Always filter assets strictly by `penyulang_id`.
- ✅ **VIEWPORT SCOPED**: Fetch point markers matching visible map coordinates (`min_lat`, `max_lat`, `min_lng`, `max_lng`) via `GET /master-assets/feeder-assets`.
- ✅ **SINGLE LINESTRING TRANSLINE**: Render feeder transline as 1 single GeoJSON `LineString` polyline layer instead of thousands of individual DOM line segments.
- ✅ **LEAFLET MARKERCLUSTER THRESHOLDS**:
  - Far Zoom ➔ Cluster Badge (`◉ 127`).
  - Medium Zoom ➔ Lightweight dots.
  - Close Zoom ➔ Asset marker.
- ✅ **LAZY LOAD ASSET DETAILS**: Fetch full asset information lazily via `GET /master-assets/detail/{id}` when a marker is tapped.

## 3. SMART PROXIMITY GPS
- Calculate real-time Haversine distance from inspector's GPS location to nearest marker.
- Display smart proximity cards: **`GDG-047 • 18 m dari Anda`** + **`[ MULAI INSPEKSI ]`**.
- GPS is a helpful proximity guide, NOT a hard lockout mechanism (30m smart tolerance).
