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

### 2026-09-17 — Don't push/PR for every small change

We're working solo — don't push/pull/open a PR after every small edit. Batch small changes locally (commit as usual for a clean history) and only push/PR: after a genuinely bigger task is complete, at the end of the day, or whenever the user explicitly says to push. Small/minor tweaks should just accumulate as local commits until one of those points.

### 2026-10-05 — Pictures: always a default when missing, and every upload is a preview box

Everywhere a picture is shown — on the public website AND in the admin panel — a missing one (no path, file gone from storage, broken link) must show a default picture, never a broken-image icon or its alt text: `public/images/default-user.jpeg` for a person (players, contributors), `public/images/default.png` for anything else. Use `<x-media-image :path="..." kind="user|image" alt="" />` (or `media_url($path, $kind)`) instead of a raw `<img src="{{ Storage::url(...) }}">`; a script in every layout also swaps pictures that fail in the browser. Everywhere a picture is uploaded — admin and public — use `<x-form.image-upload>` (a clickable box showing the saved picture or the default, previewing the chosen file), never a bare "Choose file" input. Only non-picture uploads (spreadsheet imports) keep `<x-form.file>`. Sponsor ads are the exception to the default picture (a broken sponsor removes its own slot).

### 2026-10-09 — UI/UX refresh and the editable theme

Every page (public + admin) follows one premium, mobile-first design, and every task should take as few clicks as possible: if a flow needs two or four pointless clicks, redesign the flow (inline actions, whole-row links, sticky action bars, pre-filled forms). Colours that belong to the brand or to actions come from the database through CSS variables, never hardcoded: use the `.btn` family and the utilities `bg-action text-action-fg hover:bg-action-hover text-brand bg-brand-soft text-link hover:bg-hover bg-navy-*` (Settings > General > Theme: primary / secondary / header colours, button colour + hover + text colour, link hover, hover highlight, button shape; optional ones are Auto). Green / red / amber are for meaning only (paid, failed, pending). A new page must be checked at 390 / 820 / 1280 px with no sideways scroll, and use `<x-media-image>` / `<x-form.image-upload>` for pictures.

## Project setup log

### 2026-09-17 — GitHub repository and workflow set up

The project was put on GitHub (`Rohit-pawar52/RPPL`) with the same branching/CI workflow used on a prior project: `main` (production) and `uat` (staging) as protected branches, feature branches go off `uat`, PR into `uat` → verify → PR `uat` into `main` to promote. A GitHub Actions workflow (`.github/workflows/ci.yml`) runs on every PR into either branch — builds frontend assets, runs the full PHP test suite, and checks code style with Pint — and both branches have a GitHub Ruleset requiring that check (plus a PR in general) before merging, with no bypass for anyone including the repo owner. The codebase had never been run through Pint before, so a one-time formatting pass was applied across the whole project to make that check pass from day one (whitespace/style only, verified with a full test run — no behavior changed).
