# CLAUDE.md — RPPL project

This file is auto-loaded by Claude Code at the start of every session in this
repo, regardless of which Claude account is logged in. It exists so that
switching accounts/machines does not lose the working conventions built up
on this project. Read [memory.md](memory.md) too — it's the repo-tracked,
dated log of standing instructions this file summarizes.

## Project

- **RPPL** = **RajaBhoj Pawar Premier League** ("RajaBhoj" is ONE word — never
  "Raja Bhoj" or "Rohit Pawar"). The acronym itself and anything built from it
  (registration numbers like `RPPL-2026-000001`, routes, namespaces, DB
  identifiers) never changes regardless of the full-name spelling.
- Laravel 12 tournament management system: admin panel + public website.
- GitHub repo: `Rohit-pawar52/RPPL`. Branches: `main` (production, protected,
  PR-only, no bypass even for the owner) and `uat` (staging, protected, PR +
  `build-and-test` CI check required, no force-push). Feature branches go off
  the latest `uat`, PR into `uat` → verify → PR `uat` into `main` to promote.
- CI (`.github/workflows/ci.yml`) runs on every PR into `main`/`uat`: builds
  frontend assets, runs the full PHP test suite, checks style with Pint.

## How to work with the user (Rohit)

- **Branch hygiene**: before creating a new feature/fix branch, check existing
  local + remote branches (besides `uat`/`main`). If only `uat`/`main` exist
  with nothing unfinished, just create the branch — no need to ask. If other
  unfinished feature branches exist, tell the user and ask whether to merge,
  close, or continue them before creating a new one. Always branch from the
  latest `uat`.
- **Commit messages**: every commit must be clean and detailed enough to
  understand later, from the log alone, what changed and why. Never "update",
  "fix", "changes", "wip". Short summary line + a body when the summary alone
  isn't self-explanatory.
- **Tests**: ship tests with every feature, in the same piece of work — don't
  defer. But only tests that genuinely add confidence (real behavior/business
  rules, edge cases that could actually break, authorization checks). Don't
  add a separate test per trivial validation rule, duplicate coverage, or
  tests of framework/library behavior instead of this project's own logic.
- **Resource-conscious test runs**: for a scoped phase/feature, run the
  focused/related test suite, not the full application suite — full suite
  only when there's a concrete cross-module reason. State explicitly when the
  full suite was/wasn't run and why.
- **Push/PR batching**: working solo — don't push or open a PR after every
  small edit. Keep committing locally (cleanly). Only push/PR after a
  genuinely bigger task/feature is complete, at end of day, or when the user
  explicitly says to push.
- **README.md**: keep it comprehensive and current, in plain user-facing
  terms (not internal phase/task numbers). Add new features to its Features
  section as part of the same piece of work that built them, not afterward.
- **memory.md**: whenever the user gives a new standing/going-forward
  instruction, append it there as a new dated entry (never remove an entry
  unless the user says it no longer applies).
- Large features on this project are typically given as detailed, numbered,
  user-authored phase specs to execute autonomously: audit existing code
  first (verify, don't assume the spec's own summary is accurate) → implement
  → focused tests → commit → a numbered final report → stop (don't start the
  next phase/module without being asked).

## Known gotchas worth re-checking in new code

- `Carbon::createFromFormat('Y-m-d', $date, $tz)` does NOT default to
  midnight — PHP fills the missing time-of-day with the current wall-clock
  time. Any date-only cutoff parsing needs an explicit `->startOfDay()`
  before converting timezones.
- Blade `x-form.select` / `x-form.input` components hardcode
  `id="{{ $name }}"` — don't pass a custom `id` prop (duplicate/invalid id).
  Wrap the `<form>` itself with a custom id and scope JS queries instead.
- New Admin controllers need `use App\Http\Controllers\Controller;` — easy to
  forget, shows up as a `route:list` class-not-found error.
- Status/badge string pairs (e.g. `not paid` / `partially paid` /
  `paid in full`) should be space-separated, human-readable strings used
  directly as both the internal value and the badge-component lookup key —
  `<x-status-badge>` uses CSS `capitalize`, which breaks on underscores.
