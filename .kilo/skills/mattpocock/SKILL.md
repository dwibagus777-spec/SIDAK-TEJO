---
name: mattpocock
description: Use when implementing, reviewing, or debugging TypeScript in SIDAK TEJO, including TypeScript types, API boundaries, and typed UI state.
---

# TypeScript guidance

Apply this skill only to TypeScript files or a TypeScript-specific integration. SIDAK TEJO also contains PHP and JavaScript; do not add TypeScript requirements to those files or assume Alpine.js/Livewire is present in a given feature.

## Types and trust boundaries

- Prefer inferred types and precise types. When a value is untrusted (for example, decoded JSON or an API response), start from `unknown` and validate/narrow it before use.
- Avoid `any` where a safe alternative is practical. If legacy or third-party constraints require it, keep its scope small and explain the boundary.
- `as` and `satisfies` are compile-time tools; neither validates runtime data. Do not cast unvalidated API data to a `User` or role type.
- Derive role values from the actual backend contract or existing source of truth. Never invent or hard-code a shortened role union based on assumptions. Keep UI guards as presentation aids; backend authorization remains authoritative.
- Use discriminated unions when the actual domain has a verified discriminant and the union makes invalid states harder to represent.
- Use `satisfies` when it checks a value against a type without widening the inferred value; avoid a redundant `as T satisfies T` cast.

## Framework-specific state

Type Alpine stores, `x-data`, or Livewire state only when that framework is actually used in the files being changed. Follow the local package configuration and established test/build commands.
