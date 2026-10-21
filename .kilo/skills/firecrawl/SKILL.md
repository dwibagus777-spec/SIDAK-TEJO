---
name: firecrawl
description: Use when the user requests Firecrawl or public-site scraping through a Firecrawl integration that is already configured and authorized.
---

# Firecrawl

This skill is for using an existing Firecrawl integration. It does not install software, provision credentials, or grant access to external services.

## Use

1. Confirm Firecrawl MCP/API tooling is available in the current session and that the requested URL is public and in scope.
2. Use the integration's documented interface; do not guess endpoints, CLI commands, or package names.
3. Summarize only the content relevant to the task and link the source URL.
4. If Firecrawl is unavailable, use an already available browser/search tool when appropriate, or explain the limitation. Do not request, create, or store an API key just to complete ordinary documentation research.

Never put API keys, tokens, or passwords in the project `.env`, source files, terminal output, or chat. Do not install global packages unless the user explicitly requests installation and the environment permits it.
