# SIDAK TEJO UI/UX REVAMP — PHASE 1
## MENU PRESERVATION MATRIX (40 CANONICAL ENTRIES)
**Classification**: PLN UP3 SIDOARJO — ENTERPRISE FIELD INTELLIGENCE  
**Status**: MANDATORY FUNCTIONAL PRESERVATION BASELINE  
**Date**: 2026-09-12 18:56:24 WIB  
**Invariant**: ZERO ORPHAN MENU | ZERO BROKEN ROUTE | ZERO DATA LOSS | ZERO ROLE LEAK

---

### Ringkasan Penjaminan 40 Menu Fungsional
Dokumen ini menetapkan pemetaan presisi bagi seluruh 40 menu dan aksi sistem eksisting. Setiap menu memiliki jalur akses terjamin di Desktop (Sidebar + Command Bar), Tablet (Compact Sidebar + Command Bar), dan Mobile (Fullscreen + Dock 5 Slot + Sheet 'More' + Command Bar).

| ID | Nama Menu | Kategori | Rute URL | Controller & Method | Role Gate | Lokasi UI Lama | Lokasi UI Baru | Desktop | Tablet | Mobile | Cmd Bar | Dock / Sheet | Direct URL | Pre HTTP | Post HTTP | Status |
|---|---|---|---|---|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 01 | Dashboard Utama | CORE | `/dashboard` | `Dashboard::index` | ALL | Sidebar Core | Sidebar Core + Mobile Dock #1 (Home) | ✓ | ✓ | ✓ | ✓ | Slot 1 (Home) | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 02 | Executive Analytics | CORE | `/executive-dashboard` | `ExecutiveDashboardController::index` | ALL | Sidebar Core | Sidebar Core + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 03 | Peta Jaringan (GIS) | CORE | `/gis` | `GisController::index` | ALL | Sidebar Core + BottomNav B | Sidebar Core + Mobile Dock #2 (GIS) | ✓ | ✓ | ✓ | ✓ | Slot 2 (GIS) | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 04 | Planning Inspeksi | OPERASIONAL | `/planning` | `InspectionPlanningController::index` | ALL | Sidebar Operasional | Sidebar Operasional + Mobile Dock #3 (Tugas) | ✓ | ✓ | ✓ | ✓ | Slot 3 (Tugas) | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 05 | Progress Inspeksi (Live) | OPERASIONAL | `/inspection-progress` | `InspectionProgressController::index` | ALL | Sidebar Operasional | Sidebar Operasional + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 06 | Tugas Inspeksi Saya | OPERASIONAL | `/my-inspections` | `InspectionPlanningController::myInspections` | ALL | Sidebar Operasional + BottomNav B | Sidebar Operasional + Mobile Dock #3 (Tugas) | ✓ | ✓ | ✓ | ✓ | Slot 3 (Tugas) | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 07 | Progress Saya | OPERASIONAL | `/my-progress` | `InspectionProgressController::myProgress` | ALL | Sidebar Operasional | Sidebar Operasional + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 08 | Riwayat Saya | OPERASIONAL | `/my-history` | `InspectionProgressController::myHistory` | ALL | Sidebar Operasional | Sidebar Operasional + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 09 | Input Temuan | OPERASIONAL | `/temuan/create` | `Temuan::create` | administrator, admin_ulp, inspeksi | Sidebar Operasional + BottomNav A (FAB) | Sidebar Operasional + Mobile Dock #4 (Center FAB +) | ✓ | ✓ | ✓ | ✓ | Slot 4 (Center FAB) | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 10 | Data Temuan | OPERASIONAL | `/temuan` | `Temuan::index` | ALL | Sidebar Operasional + BottomNav A | Sidebar Operasional + Sheet More (Temuan) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 11 | Work Orders | OPERASIONAL | `/work-orders` | `WorkOrderController::index` | ALL | Sidebar Operasional | Sidebar Operasional + Sheet More (WO) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 12 | Smart WO Center | OPERASIONAL | `/smart-wo` | `SmartWoController::index` | ALL | Sidebar Operasional | Sidebar Operasional + Sheet More (Smart WO) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 13 | Update Pekerjaan | OPERASIONAL | `/temuan/update-pekerjaan` | `Temuan::updatePekerjaan` | !supervisor_up3 | Sidebar Operasional | Sidebar Operasional + Sheet More (Update) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 14 | SIDAK AI Copilot | AI & INTELLIGENCE | `/ai-copilot` | `AiCopilotController::index` | ALL | Sidebar AI | Sidebar AI + Mobile Dock #5 (AI/More) | ✓ | ✓ | ✓ | ✓ | Slot 5 (AI/More) | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 15 | AI Center & Predictive | AI & INTELLIGENCE | `/ai-center` | `AiPredictiveController::index` | ALL | Sidebar AI | Sidebar AI + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 16 | Auto Ranking | AI & INTELLIGENCE | `/ranking` | `PersonalDashboardController::ranking` | ALL | Sidebar AI | Sidebar AI + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 17 | Asset Health Index | DATA & MASTER | `/asset-health` | `AssetHealthController::index` | ALL | Sidebar Data | Sidebar Data + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 18 | Master Asset PLN | DATA & MASTER | `/master-assets` | `AssetController::index` | ALL | Sidebar Data | Sidebar Data + Sheet More (Assets) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 19 | Temuan Terdekat | DATA & MASTER | `/temuan/terdekat` | `Temuan::terdekat` | ALL | Sidebar Data | Sidebar Data + Quick Action Bar | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 20 | Eviden Kubikel | DATA & MASTER | `/eviden/kubikel` | `Eviden::kubikel` | !har_row | Sidebar Data (Dropdown) + BottomNav A | Sidebar Data + Sheet More (Eviden) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 21 | Eviden Trafo | DATA & MASTER | `/eviden/trafo` | `Eviden::trafo` | !har_row | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Eviden) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 22 | Eviden Management Trafo | DATA & MASTER | `/eviden/management` | `Eviden::management` | !har_row | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Eviden) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 23 | Master Gardu Induk | DATA & MASTER | `/master-gi` | `GarduIndukController::index` | administrator, admin_ulp | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Master) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 24 | Master ULP | DATA & MASTER | `/ulps` | `Ulp::index` | administrator | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Master) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 25 | Master Penyulang | DATA & MASTER | `/penyulang` | `Penyulang::index` | administrator, admin_ulp | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Master) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 26 | Master Section | DATA & MASTER | `/sections` | `Section::index` | administrator, admin_ulp | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Master) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 27 | Master User | DATA & MASTER | `/users` | `User::index` | administrator, admin_ulp | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Master) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 28 | Import Master Data | DATA & MASTER | `/import` | `Import::index` | administrator, admin_ulp | Sidebar Data (Dropdown) | Sidebar Data + Sheet More (Master) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 29 | Command Center ECC | SYSTEM & LAPORAN | `/ecc` | `ExecutiveCommandCenter::index` | ALL | Sidebar System | Sidebar System + Command Bar | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 30 | Notifikasi Center | SYSTEM & LAPORAN | `/notifications` | `Notification::index` | ALL | Sidebar System + Bell Header | Sidebar System + Bell Header + Sheet More | ✓ | ✓ | ✓ | ✓ | Bell / Sheet | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 31 | My Dashboard | SYSTEM & LAPORAN | `/my-dashboard` | `PersonalDashboardController::index` | ALL | Sidebar System | Sidebar System + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 32 | Laporan Temuan | SYSTEM & LAPORAN | `/laporan/temuan` | `Laporan::temuan` | ALL | Sidebar Laporan (Dropdown) + BottomNav A | Sidebar Laporan + Sheet More (Laporan) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 33 | Laporan Eviden | SYSTEM & LAPORAN | `/laporan/eviden` | `Laporan::eviden` | ALL | Sidebar Laporan (Dropdown) | Sidebar Laporan + Sheet More (Laporan) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 34 | Laporan Management Trafo | SYSTEM & LAPORAN | `/laporan/management` | `Laporan::management` | ALL | Sidebar Laporan (Dropdown) | Sidebar Laporan + Sheet More (Laporan) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 35 | Dokumen Center | SYSTEM & LAPORAN | `/documents` | `DocumentCenter::index` | ALL | Sidebar System | Sidebar System + Sheet More (Documents) | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 36 | Audit Trail & Evidence | SYSTEM & LAPORAN | `/audit-log` | `AuditTrailController::index` | ALL | Sidebar System | Sidebar System + Sheet More | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 37 | Integration Center | SYSTEM & LAPORAN | `/integration` | `IntegrationCenter::index` | administrator, admin_pusat | Sidebar System | Sidebar System + Command Bar | ✓ | ✓ | ✓ | ✓ | Sheet More | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 38 | Backup Database | SYSTEM & LAPORAN | `/backup-database` | `DatabaseBackup::index` | administrator, admin_pusat | Sidebar System + Header Btn | Sidebar System + Header Btn + Sheet More | ✓ | ✓ | ✓ | ✓ | Header / Sheet | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 39 | Ganti Password | SYSTEM & LAPORAN | `/change-password` | `Auth::changePassword` | ALL | Sidebar System + Header Btn | Sidebar System + Header Btn + User Profile | ✓ | ✓ | ✓ | ✓ | Profile / Sheet | ✓ | 200 | PENDING | VERIFIED_BASELINE |
| 40 | Logout System | SYSTEM & LAPORAN | `/logout` | `Auth::logout` | ALL | Sidebar System + Header Btn + BottomNav B | Sidebar System + Header Btn + Mobile Sheet Logout | ✓ | ✓ | ✓ | ✓ | Profile / Sheet | ✓ | 302 | PENDING | VERIFIED_BASELINE |

---

### Verifikasi Konsolidasi Mobile Bottom Nav
1. **Komponen A (Baris 1158)**: `<nav class="mobile-bottom-nav">` berisi 5 item (Dashboard, Temuan, FAB (+) Input, Eviden, Laporan).
2. **Komponen B (Baris 2152)**: `<div class="mobile-bottom-nav d-lg-none">` berisi 5 item (Home, GIS, QR Scanner -14px, Inspeksi, Logout).
3. **Unified Component Target**: Single DOM Container `<nav id="sidak-mobile-dock" class="sidak-mobile-dock">`:
   - Slot 1: `Home` (`/dashboard`)
   - Slot 2: `GIS` (`/gis`)
   - Slot 3: `Tugas` (`/planning` / `/my-inspections`)
   - Slot 4: `Center FAB (+)` (`/temuan/create` terangkat dengan role gate)
   - Slot 5: `AI / More` (Membuka Bottom Sheet terpadu berisi akses cepat ke Temuan, Eviden, Laporan, QR Scanner, Work Orders, Dokumen, Profil, dan Logout).

