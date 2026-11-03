---
name: repository-hygiene
description: Use when auditing repository structure, organizing cluttered root reports, planning safe file relocations without breaking CI/workflows, and preventing credential or data dump leaks.
---

# Repository Hygiene & Safe Restructuring Skill

This skill governs the auditing, classification, decluttering, and restructuring of files across the SIDAK TEJO repository. It ensures repository cleanliness while strictly preventing broken links, CI/CD disruption, data loss, or secret leakage.

## Principles of Safe Repository Hygiene

1. **Zero-Destruction / Audit Evidence Preservation**:
   - Historical audit reports, forensic logs, and deployment verification documents are evidence records. Never delete audit markdown or JSON files simply because they appear old.
   - Do not perform mass deletions or blanket wildcard operations (`rm *.md`, `git rm *.json`).

2. **Pre-Move Dependency & Reference Check**:
   - Before proposing or moving any file (tracked or untracked), verify every potential caller or reference across:
     - Application source code (`app/`, `public/`)
     - CI/CD workflows (`.github/workflows/`)
     - Build scripts & composer configuration (`composer.json`, `package.json`, `spark`)
     - Testing suites (`tests/`, Playwright configs)
     - Documentation links (`README.md`, `DEPLOYMENT.md`, `AGENTS.md`)
   - If a file is referenced, update the caller synchronously or keep a backward-compatible proxy/symlink if external tools depend on it.

3. **Git History & Link Integrity**:
   - For tracked files, use `git mv` (when committing is authorized) so Git traces file renames across commit trees.
   - When moving documents, update all relative markdown links (`[text](../path)`) and image links (`![caption](../path)`).

4. **Security & Data Confidentiality Boundary**:
   - Never track or stage:
     - Real database dumps (`.sql`, database backups)
     - Private credential files (`.env`, secrets, token caches)
     - Local runtime logs (`request_log.txt`, `.log` files)
     - Large binary assets or ad-hoc builds (`.apk`, test output archives)
   - Ensure these patterns are permanently guarded in `.gitignore`.

5. **Impact Matrix First**:
   - Always draft an explicit Impact Assessment before relocating tracked files:
     - File Current Path
     - Target Destination
     - Referenced By (Callers/CI)
     - Required Link Updates
     - Risk Level & Verification Method

---

## Standard SIDAK TEJO Directory Taxonomy

| Category | Standard Target Location | Examples |
| :--- | :--- | :--- |
| **Active Application Source** | `app/`, `public/` | Controllers, Views, Models, JS/CSS assets |
| **System & Framework Config** | Root, `app/Config/` | `.env.example`, `composer.json`, `phpunit.dist.xml` |
| **CI / CD Automation** | `.github/workflows/` | `ci.yml`, `qa-audit.yml` |
| **Project & Agent Guides** | Root, `.agents/`, `.kilo/` | `AGENTS.md`, `README.md`, `DEPLOYMENT.md` |
| **Historical Audits & Evidence** | `docs/audits/<subsystem>/` | `GIS01_*.md`, `TL01_*.md`, `UI_REVAMP_*.md` |
| **Technical Architecture RFCs** | `docs/architecture/` | `TRANSLINE_MULTI_FEEDER_*.md` |
| **Regression & Integration Tests**| `tests/` | `test_*.php`, `*.spec.js` |
| **Local Runtime & Scratch Files** | `writable/audits/`, `writable/scratch/` | Scratch scripts, benchmark outputs |

---

## Safe Execution Workflow

### Step 1: File Inventory & Status Audit
Run `git status -s` and `git ls-files` to partition candidate files into:
- **Tracked Active**: Core application files (Do not move without major architectural RFC).
- **Tracked Reports/Documentation**: Root markdown/json files that belong in `docs/audits/`.
- **Untracked Scratch/Dumps**: Ad-hoc scripts or dumps that belong in `writable/` or `.gitignore`.
- **Rogue / Corrupted Artifacts**: Erroneous CLI files (e.g. `-w`) or stray logs.

### Step 2: Caller Grep
Search for references across the repository:
```bash
git grep -n "FILENAME_OR_BASE"
```
Check if any workflow in `.github/workflows/` references the filename.

### Step 3: Plan & Impact Review
Prepare a clear relocation table. Confirm that no production or deployment script depends on root file paths.

### Step 4: Staged Relocation & Reference Update
- Relocate verified files.
- Update documentation and markdown references.
- Verify tests and workflows still pass with zero broken paths.
