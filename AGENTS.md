# SIDAK TEJO - Skills

Skills in `.kilo/skills/` (15 folders). Select only what the current task requires and read its `SKILL.md` before acting.

## Inventory (15 Skills)

- [code-graph-rag](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\code-graph-rag\SKILL.md): Use when a SIDAK TEJO change spans files or you need lightweight repository tracing across routes, controllers, services, models, views, or tests before editing.
- [context7-cli](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\context7-cli\SKILL.md): Use when the user mentions `ctx7` or `context7`, needs current external library documentation via the `ctx7` CLI, or wants to configure Context7 MCP.
- [database-architecture](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\database-architecture\SKILL.md): Use when designing or changing database schemas, migrations, relationships, data integrity, or query performance in an existing application.
- [firecrawl](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\firecrawl\SKILL.md): Use only when the user explicitly requests Firecrawl or public-site scraping through an already configured and authorized Firecrawl integration.
- [frontend-design](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\frontend-design\SKILL.md): Use when building new UI or reshaping an existing view/dashboard that needs intentional visual hierarchy, typography, layout, and responsive polish.
- [graphify](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\graphify\SKILL.md): Use when the user invokes `/graphify`, asks to build/query a persistent knowledge graph (`graphify-out/`), or wants corpus clustering, god-node analysis, or graph exports across code/docs/media.
- [kilo-ssh-triage](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\kilo-ssh-triage\SKILL.md): Use when Kilo Code reports HTTP 499 or upstream-provider errors, VS Code Remote-SSH hangs/fails, SSH port forwarding is refused, or a task cannot reach the remote workspace.
- [mattpocock](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\mattpocock\SKILL.md): Use when implementing, reviewing, or debugging TypeScript files, TypeScript API boundaries, or typed UI state in SIDAK TEJO.
- [omniroute](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\omniroute\SKILL.md): Use when adding or changing CodeIgniter 4 routes (`app/Config/Routes.php`), authentication filters, role access (`role:`), or diagnosing unauthorized route access.
- [playwright-cli](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\playwright-cli\SKILL.md): Use when automating browser interactions, verifying rendered UI/viewports, or running/debugging Playwright tests.
- [ponytail](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\ponytail\SKILL.md): Use for lazy senior dev mode: making the smallest complete code change without bloat or unrequested abstractions, and ending replies with skipped checks and risks.
- [prime-agent](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\prime-agent\SKILL.md): Use when creating or maintaining a skill for the Prime Agent runtime or adapting a Prime Agent skill for Kilo Code.
- [reverse-engineer-anything](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\reverse-engineer-anything\SKILL.md): Use when reverse engineering shipped binaries, APK/ASAR/packages, firmware, bytecode, recorded network/crash captures, or runtime targets with REA (skip for ordinary source-repo analysis).
- [router](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\router\SKILL.md): Use only when a task spans several areas and it is unclear which SIDAK TEJO project skills to combine before acting.
- [superpowers](E:\XAMPP\htdocs\SIDAK TEJO\.kilo\skills\superpowers\SKILL.md): Use when starting a conversation to check whether a specialized project skill applies, while honoring explicit user and `AGENTS.md` token-saving constraints.

## Token-Saving & Execution Rules

1. **Select only relevant skills:** Pick only the 1–2 skills that directly match the task; never load all 15 `SKILL.md` files at once.
2. **Read before use:** Read the selected `SKILL.md` once before applying its workflow; do not re-read or repeat instructions already in context.
3. **Prefer the most specific skill when overlapping:**
   - For ordinary SIDAK TEJO cross-file tracing, use `code-graph-rag` (lightweight `rg`/file tracing) instead of heavy `/graphify` builds or `reverse-engineer-anything`.
   - Use `database-architecture` for migrations, schema changes, and query optimization.
   - Use `graphify` only when `graphify-out/` already exists or the user explicitly wants a knowledge-graph build/query.
   - Use `reverse-engineer-anything` only for compiled/packaged artifacts, APKs, captures, or runtime targets—never for normal PHP/JS source files in this repo.
   - Skip `router` and `superpowers` overhead when the target domain skill (`omniroute`, `frontend-design`, `ponytail`, etc.) is already obvious.
4. **Avoid heavy/external flows unless required:** Do not run external CLIs, package installers (`npx`, `pip`, `uv`), MCP setup, or subagent swarms unless the task explicitly needs them and permission is granted.
5. **Preserve project & safety boundaries:** Do not change model/provider/routing, do not expose or send secrets/data externally, and follow priority `system > developer > user/project (AGENTS.md) > skill defaults`.
