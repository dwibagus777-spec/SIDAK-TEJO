---
name: router
description: Use when a task spans several areas and you need to choose which SIDAK TEJO project skills are relevant before acting.
---

# Skill router for SIDAK TEJO

This is an optional aid for choosing relevant skills. Kilo can select project skills from their descriptions; a task does not have to begin with a special `/router` command.

## Select only what the task needs

- Route, authentication, role filter, or access-control change → `omniroute`.
- Cross-file change or unclear data flow in repository source → `code-graph-rag`.
- Building or querying a persistent `graphify-out/` knowledge graph (`/graphify`) → `graphify` (prefer `code-graph-rag` for ordinary PHP/JS source tracing).
- Reverse engineering shipped binaries, APK/ASAR packages, firmware, or captures → `reverse-engineer-anything` (never use on ordinary repository source).
- Building new UI or reshaping dashboard/view layout and visual hierarchy → `frontend-design`.
- Browser automation, viewport verification, or Playwright tests → `playwright-cli`.
- TypeScript implementation or type error → `mattpocock`.
- Fetching up-to-date external library docs via `ctx7` or configuring Context7 MCP → `context7-cli`.
- User explicitly requests Firecrawl, with an integration already available → `firecrawl`.
- Diagnosing Kilo HTTP 499, upstream provider errors, or VS Code Remote-SSH failures → `kilo-ssh-triage`.
- Creating/porting a Prime Agent skill → `prime-agent`.
- Small, focused implementation or a request for minimal code → `ponytail`.
- Conversation-start skill check (subordinate to `AGENTS.md` token-saving rules) → `superpowers`.
- Use this router only when task boundaries overlap or skill selection is unclear.

Before using a skill, confirm its `SKILL.md` exists, its metadata matches its folder, and its prerequisites are available. Combine skills only for distinct needs. Do not invoke them through `@name` aliases unless Kilo actually exposes that syntax in the active interface.

## Project-specific reminders

- SIDAK TEJO uses CodeIgniter 4: routes are in `app/Config/Routes.php`, and route inspection uses `php spark routes`. Do not use Laravel's `routes/web.php` or `php artisan` commands.
- There is no project `hostinger` skill or `.kilo/rules/memory.md` file by default. For deployment, inspect the real deployment documentation and require an authorized SSH/SFTP or CI path; do not infer credentials or deploy capability.
- Use the repository's actual tests/build scripts. Do not claim checks that were not run.
