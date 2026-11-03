# SIDAK TEJO - Skills

Skills in `.kilo/skills/` (22 folders). Select only what the current task requires and read its `SKILL.md` before acting.

## Inventory (22 Skills)

- [code-graph-rag](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\code-graph-rag\SKILL.md): Use when a SIDAK TEJO change spans files or you need lightweight repository tracing across routes, controllers, services, models, views, or tests before editing.
- [context7-cli](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\context7-cli\SKILL.md): Use when the user mentions `ctx7` or `context7`, needs current external library documentation via the `ctx7` CLI, or wants to configure Context7 MCP.
- [database-architecture](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\database-architecture\SKILL.md): Use when designing or changing database schemas, migrations, relationships, data integrity, or query performance in an existing application.
- [firecrawl](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\firecrawl\SKILL.md): Use only when the user explicitly requests Firecrawl or public-site scraping through an already configured and authorized Firecrawl integration.
- [frontend-design](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\frontend-design\SKILL.md): Use when building new UI or reshaping an existing view/dashboard that needs intentional visual hierarchy, typography, layout, and responsive polish.
- [graphify](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\graphify\SKILL.md): Use when the user invokes `/graphify`, asks to build/query a persistent knowledge graph (`graphify-out/`), or wants corpus clustering, god-node analysis, or graph exports across code/docs/media.
- [K6](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\K6\SKILL.md): Use when designing, writing, or executing automated HTTP/API load and stress testing using Grafana k6 CLI, evaluating virtual user concurrency, response latency thresholds (p95/p99), and SLO failure rates.
- [kilo-ssh-triage](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\kilo-ssh-triage\SKILL.md): Use when Kilo Code reports HTTP 499 or upstream-provider errors, VS Code Remote-SSH hangs/fails, SSH port forwarding is refused, or a task cannot reach the remote workspace.
- [mattpocock](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\mattpocock\SKILL.md): Use when implementing, reviewing, or debugging TypeScript files, TypeScript API boundaries, or typed UI state in SIDAK TEJO.
- [omniroute](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\omniroute\SKILL.md): Use when adding or changing CodeIgniter 4 routes (`app/Config/Routes.php`), authentication filters, role access (`role:`), or diagnosing unauthorized route access.
- [performance-security](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\PERFORMANCE_SECURITY\SKILL.md): Use when auditing web performance (Core Web Vitals, unused CSS/JS, caching, render-blocking) or backend/network security (DDoS, rate limiting, security headers, SQLi/XSS prevention).
- [playwright-cli](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\playwright-cli\SKILL.md): Use when automating browser interactions, verifying rendered UI/viewports, or running/debugging Playwright tests.
- [ponytail](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\ponytail\SKILL.md): Use for lazy senior dev mode: making the smallest complete code change without bloat or unrequested abstractions, and ending replies with skipped checks and risks.
- [prime-agent](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\prime-agent\SKILL.md): Use when creating or maintaining a skill for the Prime Agent runtime or adapting a Prime Agent skill for Kilo Code.
- [repository-hygiene](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\repository-hygiene\SKILL.md): Use when auditing repository file organization, decluttering root directory reports, planning safe file migrations without breaking CI/workflows, and safeguarding against credential/dump leakage.
- [reverse-engineer-anything](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\reverse-engineer-anything\SKILL.md): Use when reverse engineering shipped binaries, APK/ASAR/packages, firmware, bytecode, recorded network/crash captures, or runtime targets with REA (skip for ordinary source-repo analysis).
- [router](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\router\SKILL.md): Use only when a task spans several areas and it is unclear which SIDAK TEJO project skills to combine before acting.
- [SECURITY-AUDIT](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\SECURITY-AUDIT\SKILL.md): Use when performing formal multi-phase codebase security audits following Cloudflare methodology (Reconnaissance, Coverage-Led Hunting, Adversarial Validation, Structured Verdicts, and Reporting).
- [sidaktejo-qa-automation-package](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\sidaktejo-qa-automation-package\SKILL.md): Use when orchestrating end-to-end automated QA crawling, Playwright functional regression, console/network error trapping, and Lighthouse performance auditing specifically for sidaktejo.site.
- [STRIX](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\STRIX\SKILL.md): Use when running autonomous AI penetration testing, OWASP Top 10 dynamic scanning, and containerized vulnerability verification/auto-fix workflows.
- [superpowers](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\superpowers\SKILL.md): Use when starting a conversation to check whether a specialized project skill applies, while honoring explicit user and `AGENTS.md` token-saving constraints.
- [ui-ux-pro-max](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\ui-ux-pro-max\SKILL.md): Use when designing, building, reviewing, or fixing interfaces across web, mobile, and desktop with design intelligence: layout, accessibility (contrast 4.5:1, touch target ≥44px), typography, color palettes, responsive UX, and visual Polish.

## Multi-Skill Composition Guidance

When a task spans multiple layers (e.g. database + route + UI + QA verification), do not get trapped in a single skill or load unnecessary skills all at once. Combine complementary skills in sequential phases:

1. **Fullstack Feature / Bugfix with Verification**:
   - Phase 1 (Recon & Tracing): [`code-graph-rag`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\code-graph-rag\SKILL.md) to map callers and impacts.
   - Phase 2 (Implementation): [`omniroute`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\omniroute\SKILL.md) (routes/filters) + [`database-architecture`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\database-architecture\SKILL.md) (schema/queries) + [`ponytail`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\ponytail\SKILL.md) (minimal working diff).
   - Phase 3 (Verification): [`playwright-cli`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\playwright-cli\SKILL.md) or [`sidaktejo-qa-automation-package`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\sidaktejo-qa-automation-package\SKILL.md) to run headless browser/E2E assertions.

2. **Mobile UX & Accessibility Enhancements**:
   - Phase 1 (Design & A11y): [`ui-ux-pro-max`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\ui-ux-pro-max\SKILL.md) or [`frontend-design`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\frontend-design\SKILL.md) (viewport geometries, contrast, bottom sheet interactions).
   - Phase 2 (Validation): [`playwright-cli`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\playwright-cli\SKILL.md) (mobile viewport emulation, touch target audits).

3. **Performance & Security Audits**:
   - Phase 1 (Core Web Vitals & Hardening): [`performance-security`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\PERFORMANCE_SECURITY\SKILL.md) (caching, headers, LCP/TBT).
   - Phase 2 (Load & Stress): [`K6`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\K6\SKILL.md) (virtual users, SLO verification).
   - Phase 3 (Vulnerability & Pen-testing): [`SECURITY-AUDIT`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\SECURITY-AUDIT\SKILL.md) or [`STRIX`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\STRIX\SKILL.md).

4. **Repository Hygiene & Safe Organization**:
   - Phase 1 (Inventory & Pre-Move Dependency Grep): [`repository-hygiene`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\repository-hygiene\SKILL.md) + [`code-graph-rag`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\code-graph-rag\SKILL.md).
   - Phase 2 (Impact Matrix & Staged Migration): [`repository-hygiene`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\repository-hygiene\SKILL.md) + [`ponytail`](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\ponytail\SKILL.md).
   - Phase 3 (Integrity Verification): Run test runner and check zero broken references.

## Token-Saving & Execution Rules

1. **Select only relevant skills:** Activate only 1–3 skills strictly required for the active phase; never load all 22 `SKILL.md` files at once.
2. **Read before use:** Read the selected `SKILL.md` once before applying its workflow; do not re-read or repeat instructions already in context.
3. **Prefer the most specific skill when overlapping:**
   - For ordinary SIDAK TEJO cross-file tracing, use `code-graph-rag` (lightweight file/symbol tracing) instead of heavy `/graphify` builds or `reverse-engineer-anything`.
   - Use `database-architecture` for migrations, schema changes, and query optimization.
   - Use `ui-ux-pro-max` or `frontend-design` for UI/UX enhancements and visual design decisions.
   - Use `graphify` only when `graphify-out/` already exists or the user explicitly wants a knowledge-graph build/query.
   - Use `reverse-engineer-anything` only for compiled/packaged artifacts, APKs, captures, or runtime targets—never for normal PHP/JS source files in this repo.
   - Skip `router` and `superpowers` overhead when the target domain skill (`omniroute`, `frontend-design`, `ponytail`, etc.) is already obvious.
4. **Zero-Fabrication Verification Principle:**
   - Never report test pass counts, coverage percentages, or verification claims without executing the actual test runner (`php`, `node`, `playwright`, etc.) on the current working tree.
   - Always report the actual command, exit code, and stdout/stderr summary.
5. **Avoid heavy/external flows unless required:** Do not run external CLIs, package installers (`npx`, `pip`, `uv`), MCP setup, or subagent swarms unless the task explicitly needs them and permission is granted.
6. **Preserve project & safety boundaries:** Do not change model/provider/routing, do not expose or send secrets/data externally, and follow priority `system > developer > user/project (AGENTS.md) > skill defaults`.
