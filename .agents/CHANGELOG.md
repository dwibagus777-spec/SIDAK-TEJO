# 📝 CHANGELOG & VERIFIED RELEASE AUDIT TRAIL

## [Release D - Full Feeder Inspection Journey & Construction-Shape GIS] - 2026-08-09
### Added / Refactored
- Implemented **Full Feeder Planned-Asset Inspection Journey** in `app/Services/GISService.php`, `app/Controllers/GisController.php`, `app/Views/gis/index.php`, and `app/Views/inspections/guided.php`.
- Implemented **Construction-Specific SVG/HTML DivIcon Markers**: Tiang (`shape-pole`), GTT (`shape-gtt`), Gardu Distribusi (`shape-gardu`), Kubikel (`shape-kubikel`), and Trafo (`shape-trafo`) with distinct icons and shapes.
- Implemented **Secondary Visual State Badges**: Inspection status (`PENDING`, `CURRENT_TARGET`, `PASS`, `FAIL`) acts as secondary border accent/badge without changing construction shape identity.
- Implemented **Feeder Inspection Journey Card Panel**: Displays feeder planning title, progress (`X / N Inspected`), progress bar, and current target recommendation (`#047 GDG-047`).
- Implemented **Feeder Context Rehydration**: Returning from Guided Inspection via `/gis?penyulang_id=X&planning_id=Y` restores selected feeder & planning context while keeping all planned assets visible.
- Verified 100% boundary compliance: 5 Frozen Core Files untouched, 0% DDL, 100% SIDAK TEJO color scheme preserved.

## [v-nav-ux-reborn] - 2026-08-09
### Added / Refactored
- Implemented **Navigation UX Refactor (Performance-First / Zero Regression)** in `app/Views/layouts/admin.php`.
- Grouped 27 navigation items into 5 collapsible accordion categories (CORE & ANALYTICS, OPERASIONAL & LAPANGAN, AI & INTELLIGENCE, DATA & MASTER REFERENSI, SYSTEM & LAPORAN).
- Implemented **Desktop Collapsed Mode** (~68px width) via `#btn-collapse-sidebar` toggle with auto Leaflet map canvas expansion (`window.map.invalidateSize()`).
- Implemented **Mobile Field Bottom Navigation Bar** (`< 992px`) for field workers (Home, GIS, Inspeksi, More Drawer).
- Preserved 100% of existing color scheme (`--color-tosca-dark`, `#FF6B35` green energy accent), 0% backend changes, 0% DDL, and 0% frozen core engine modification.

## [vC-interactive-map-inspection-claim] - 2026-08-09
### Added
- Server-side validation & idempotent marker claim endpoint (`GET /inspections/start-by-asset?asset_id=X`) in `app/Controllers/InspectionController.php`.
- Integrated GIS Mobile Bottom Sheet & Nearest Asset Card `[ MULAI INSPEKSI ]` action with server-side planning & active asset verification.
- Verified 100% server-side security checks (`assets.deleted_at IS NULL`, `status = 'AKTIF'`, planning target contract, and inspector session re-use).

## [vB.2-markercluster-resolution] - 2026-08-09
### Fixed
- Updated `Leaflet.markercluster` CDN URL to CDNJS (`https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js`) in `app/Views/gis/index.php`.
- Implemented defensive capability detection `typeof L.markerClusterGroup === 'function'` in `app/Views/gis/index.php`.
- Completely resolved `Uncaught TypeError: L.markerClusterGroup is not a function`.

## [vB.1-js-syntax-fix] - 2026-08-09
### Fixed
- Fixed unclosed `getCurrentPosition` callback and `addEventListener` in `app/Views/gis/index.php`.
- Completely resolved `Uncaught SyntaxError: Unexpected end of input at gis:1869`.

## [vB-feeder-gis-network] - 2026-08-09
### Added
- Feeder-scoped GeoJSON LineString transline polyline API (`GET master-assets/feeder-network`).
- Viewport bounding-box asset markers API (`GET master-assets/feeder-assets`).
- Smart Proximity Haversine distance calculator & Mobile Bottom Sheet in `app/Views/gis/index.php`.

## [vA-asset-integrity] - 2026-08-09
### Fixed
- Added `deleted_at IS NULL` predicate to `BaselineService`, `InspectionController`, and `InspectionProgressController`.
- Added defensive JS guard clause `if (!p || !p.id) return;` in `guided.php`.
