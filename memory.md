# Instructions for Claude — RPPL project

This file is a running, repo-tracked list of standing instructions for how Claude should work on this project, going forward. It travels with the repo (unlike Claude's own local session memory), so anyone continuing this project — including a fresh Claude session — should read it first.

**When the user gives a new standing instruction ("going forward, do X", "always do X", "remember X"), add it here as a new dated entry. Never remove an entry unless the user says it no longer applies.**

## Instructions

### 2026-09-17 — Keep README.md detailed and current

`README.md` must always be comprehensive enough that reading it alone tells you what the project is and what features it currently has — not a short/generic summary. Whenever a new feature is built, add it to the README's Features section (in plain, user-facing terms — not internal phase/task numbers). Keep this `memory.md` file itself updated the same way: whenever the user gives a new standing instruction, add it here.

### 2026-09-17 — GitHub rulesets configured

Both `main` and `uat` now have GitHub Rulesets: `uat` requires a PR + the `build-and-test` CI check + blocks force pushes; `main` requires a PR (direct push blocked). No one is on the bypass list — even the repo owner must go through a PR.
