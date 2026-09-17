# Instructions for Claude — RPPL project

This file is a running, repo-tracked list of standing instructions for how Claude should work on this project, going forward. It travels with the repo (unlike Claude's own local session memory), so anyone continuing this project — including a fresh Claude session — should read it first.

**When the user gives a new standing instruction ("going forward, do X", "always do X", "remember X"), add it here as a new dated entry. Never remove an entry unless the user says it no longer applies.**

## Instructions

### 2026-09-17 — Keep README.md detailed and current

`README.md` must always be comprehensive enough that reading it alone tells you what the project is and what features it currently has — not a short/generic summary. Whenever a new feature is built, add it to the README's Features section (in plain, user-facing terms — not internal phase/task numbers). Keep this `memory.md` file itself updated the same way: whenever the user gives a new standing instruction, add it here.

### 2026-09-17 — GitHub rulesets configured

Both `main` and `uat` now have GitHub Rulesets: `uat` requires a PR + the `build-and-test` CI check + blocks force pushes; `main` requires a PR (direct push blocked). No one is on the bypass list — even the repo owner must go through a PR.

### 2026-09-17 — Commit messages must be clean and detailed

Every commit must clearly explain what actually changed and why — detailed enough that reading the commit message alone (without re-reading the diff or this conversation) tells you what was built/changed. Never use vague messages like "update", "fix", "changes", or "wip". Prefer a short summary line plus, when the change isn't self-explanatory from the summary alone, a body describing what was added/changed and the reason. This matters because the whole point of a clean git history is to let someone understand later, from the log alone, what each change actually did.

### 2026-09-17 — Add tests as you go, but only the necessary ones

Every new feature must come with tests, added in the same piece of work — don't defer this. But only add tests that genuinely increase confidence (the real behavior/business rule, the edge cases that could actually break, authorization). Do not add excessive/low-value tests (e.g. a separate test per trivial validation rule, duplicate coverage of something another test already proves, testing framework/library behavior instead of this project's own logic) — those just slow down the test suite and CI on every merge without adding real safety.
