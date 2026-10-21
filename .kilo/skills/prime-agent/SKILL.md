---
name: prime-agent
description: Use when creating or maintaining a skill specifically for the Prime Agent runtime, or when adapting one of its skills for Kilo Code.
---

# Prime Agent skill authoring

First identify the target runtime. Prime Agent and Kilo Code have different loading paths and may expose different tools; do not describe a Prime Agent path or Python capability as though Kilo provides it.

## Locations

- Prime Agent project skills use `.prime/agent/skills/<skill-name>/` when that runtime is installed and configured.
- Kilo project skills use `.kilo/skills/<skill-name>/SKILL.md`.
- For Kilo, use a folder name matching the frontmatter `name`, and include non-empty `name` and `description` fields. Keep the description focused on when the skill applies.

## Authoring checks

1. State which runtime the skill targets and verify that runtime is available before adding runtime-specific scripts or Python packages.
2. Keep a skill focused on one reusable workflow or reference. Put large references or reusable scripts in companion files and link them with paths relative to `SKILL.md`.
3. Verify every referenced path exists; do not leave instructions pointing to absent `references/` files.
4. Do not provision secrets or install dependencies without explicit need and authorization.
5. Re-read the frontmatter and test skill discovery/application in the target runtime before claiming it is ready.
