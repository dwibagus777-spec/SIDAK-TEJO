# 📝 CHANGELOG & VERIFIED RELEASE AUDIT TRAIL

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
