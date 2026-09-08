# TL-01 Visual Realization: Authoritative Transline GIS API Contract
**Document Version:** 1.0.0-SEALED  
**Classification:** Operational Security & Architecture Standard  
**Phase / Sub-Gate:** TL-01 Visual Realization (Sub-Gate D4B LOCKED)  
**Endpoint:** `GET /gis/api-translines`  

---

## 1. Executive Summary

This contract defines the authoritative, strictly read-only HTTP API interface for querying JTM transmission lines (`gis_translines`) in the SIDAK TEJO GIS Map (`/gis`).

The endpoint implements four mandatory invariants ratified by governance:
1. **Zero-Fallback Integrity**: When no translines exist or match the filter, an honest empty array (`[]`) is returned. No canonical fixtures are ever injected into production queries.
2. **Domain-Typed Temuan Firewall**: Any query or topology reference specifying `source_type=TEMUAN` or `target_type=TEMUAN` is immediately rejected with HTTP 422 `TEMUAN_ENDPOINT_FORBIDDEN`.
3. **Tenant & Scope Authorization**: Feeder-to-ULP isolation enforces that users cannot query feeder networks outside their authorized ULP (HTTP 403 `UNAUTHORIZED_FEEDER_ACCESS`). Cross-scope anomalies are surfaced as non-mutating diagnostics.
4. **Dual-Key Compatibility**: Responses provide dual keys (`translines` & `data`, `source_asset_id` & `from_asset_id`, `target_asset_id` & `to_asset_id`, `length_meter` & `length_m`, `conductor_label` & `conductor_type`) to guarantee zero frontend breakage across legacy and new UI components.

---

## 2. Endpoint Specification

### 2.1 Route & Method
- **Method:** `GET`
- **URI:** `/gis/api-translines`
- **Controller:** `App\Controllers\GisController::apiGetTranslines()`
- **Service:** `App\Services\GisTranslineService::getAuthoritativeTranslines()`
- **Default Authentication:** Session-based authentication via `auth` filter.

### 2.2 Request Query Parameters

| Parameter | Type | Required | Default | Validation & Rules |
| :--- | :--- | :--- | :--- | :--- |
| `feeder_id` | Integer | No | `null` | Must be a positive integer matching an existing `penyulang.id`. Enforces ULP boundary check. |
| `section_id` | Integer | No | `null` | Must be a positive integer. Filters assets by section. |
| `ulp_id` | Integer | No | User's ULP | If user has session `ulp_id`, this parameter is overridden or restricted by user's authorized scope. |
| `source_type`| String | No | `'ASSET'`| Allowed: `'ASSET'`. If `'TEMUAN'`, triggers HTTP 422. |
| `target_type`| String | No | `'ASSET'`| Allowed: `'ASSET'`. If `'TEMUAN'`, triggers HTTP 422. |

---

## 3. Response Schemas

### 3.1 HTTP 200 OK — Successful Retrieval (Authoritative Translines)

Returned when scope query succeeds and translines are retrieved (or legitimately empty).

```json
{
  "status": "success",
  "mode": "READ_ONLY_AUTHORITATIVE",
  "gate": "TL-01_VISUAL_REALIZATION",
  "d4b_status": "LOCKED",
  "meta": {
    "total": 42,
    "filtered": 42,
    "scope": {
      "feeder_id": 1,
      "section_id": null,
      "ulp_id": 1
    },
    "diagnostics": {
      "cross_scope_count": 0,
      "anomalies": []
    }
  },
  "translines": [
    {
      "id": 1,
      "transline_code": "TL-BBS01-001",
      "code": "TL-BBS01-001",
      "source_asset_id": 101,
      "from_asset_id": 101,
      "target_asset_id": 102,
      "to_asset_id": 102,
      "source_asset_name": "TIANG-BBS01-001",
      "target_asset_name": "TIANG-BBS01-002",
      "feeder_id": 1,
      "feeder_name": "BABAT 01",
      "section_id": 10,
      "section_name": "SECTION BBS01-A",
      "conductor_type": "AAAC 150 mm2",
      "conductor_label": "AAAC 150 mm2",
      "length_meter": 45.5,
      "length_m": 45.5,
      "status": "ACTIVE",
      "coordinates": [
        [-7.123456, 112.123456],
        [-7.123890, 112.123890]
      ]
    }
  ],
  "data": [
    /* Exact mirror of translines for legacy frontend compatibility */
  ]
}
```

### 3.2 HTTP 200 OK — Honest Empty State (No Fallback Injected)

Returned when a feeder has zero authoritative translines.

```json
{
  "status": "success",
  "mode": "READ_ONLY_AUTHORITATIVE",
  "gate": "TL-01_VISUAL_REALIZATION",
  "d4b_status": "LOCKED",
  "meta": {
    "total": 0,
    "filtered": 0,
    "scope": {
      "feeder_id": 999,
      "section_id": null,
      "ulp_id": 1
    },
    "diagnostics": {
      "cross_scope_count": 0,
      "anomalies": []
    }
  },
  "translines": [],
  "data": []
}
```

### 3.3 HTTP 422 Unprocessable Entity — Domain-Typed Temuan Firewall Violation

Triggered whenever a caller attempts to use inspection findings as transmission line endpoints.

```json
{
  "status": "error",
  "error_code": "TEMUAN_ENDPOINT_FORBIDDEN",
  "message": "Findings (temuan) are inspection evidence and strictly forbidden from being transline network endpoints.",
  "meta": {
    "rejected_source_type": "TEMUAN",
    "rejected_target_type": "ASSET",
    "invariant": "ASSET_TO_ASSET_ONLY"
  }
}
```

### 3.4 HTTP 403 Forbidden — Unauthorized Feeder Access

Triggered if a user attempts to query a feeder outside their assigned ULP boundary.

```json
{
  "status": "error",
  "error_code": "UNAUTHORIZED_FEEDER_ACCESS",
  "message": "Feeder #5 belongs to ULP #2, but current session is restricted to ULP #1."
}
```

---

## 4. Diagnostics & Anomaly Reporting Contract

The `meta.diagnostics.anomalies` array surfaces data quality issues without mutating the underlying database:

| Anomaly Code | Condition | Behavior |
| :--- | :--- | :--- |
| `ORPHAN_ENDPOINT` | Either `source_asset_id` or `target_asset_id` does not exist in `assets`. | Line is omitted from visual rendering; logged in diagnostics. |
| `IDENTICAL_ENDPOINTS` | `source_asset_id` == `target_asset_id`. | Zero-length self-loop omitted; logged in diagnostics. |
| `MISSING_COORDINATE` | Asset latitude or longitude is null, zero, or out of bounds. | Line cannot be drawn; logged in diagnostics. |
| `CROSS_SCOPE_ENDPOINT` | Source and Target assets belong to different feeders. | Line is included with diagnostic warning flag. |

---

## 5. Security & Operation Standards
1. **Zero HTTP Mutation**: Only `GET` requests are processed. Any `POST`, `PUT`, `PATCH`, or `DELETE` request to this endpoint returns `405 Method Not Allowed`.
2. **Sub-Gate D4B Enforcement**: All responses explicitly include `"d4b_status": "LOCKED"` and `"mode": "READ_ONLY_AUTHORITATIVE"`.
3. **Frontend Protection**: The UI map layer (`/gis`) consumes `translines` and displays dedicated read-only cards with no confirmation, modification, or deletion action buttons.
