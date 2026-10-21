---
name: code-graph-rag
description: Use when a SIDAK TEJO change spans files or you need to trace data flow, callers, routes, services, models, views, or tests before editing.
---

# Code graph and data-flow tracing

Use the repository itself as the source of truth. This skill describes a lightweight tracing method; it does not imply that a graph database, MCP server, or external code-graph tool is installed.

## Workflow

1. Find the entry point and search its callers with the available repository search tools (`rg` when available).
2. Trace only the affected path: route or UI entry → controller → service/model → response/view → relevant tests.
3. Write a small dependency outline only when it clarifies which files must change. Do not build a separate graph for a simple, local edit.
4. Make the smallest complete change inside that path; leave unrelated files and `.env` untouched.
5. Verify the changed path with the repository's existing checks and report any path that could not be exercised.

For route and role policy, combine this tracing with `omniroute` when relevant. Do not infer framework conventions from examples on other projects.
