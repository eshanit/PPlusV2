# Monitoring app alignment changes

This document summarises the first implementation pass aligning the monitoring app to the paper `PEN-Plus Mentorship Tool 2.0_April_2026.pdf` and the v3 refactor brief.

## Implemented

- Added the shared score labels and score palette constants in `app/data/evaluationItemData.ts`.
- Added group and theme metadata to the tool model (`IItemGroup`, `IToolTheme`, `groups`, `theme`).
- Added round-based session support (`IRound`) while keeping legacy flat `itemScores` / `counsellingScores` compatibility.
- Added legacy-session migration support in `sessionStore` so older docs are normalized in memory.
- Updated the competency logic to read score values from either the legacy flat arrays or the new round-based format.
- Replaced one-item-at-a-time entry with a planning step that selects tool items by section; counselling items default to selected.
- Added grouped observation lists with multiple rounds, per-round item/session notes, shared score colors, and grey treatment for advanced items.
- Added a review step that summarizes planned items and scores per round before saving.
- Session saves now persist round details and plan slugs while maintaining legacy flat score arrays for existing app consumers.
- Added edit entry points on session detail and journey cards; edits reuse the same `_id` and PouchDB revision.
- Session detail and review pages show round-by-round scores as well as the session-level latest-score summary.
- Echocardiogram sessions collect the paper tool's supervised-procedure and correct-final-diagnosis counts after rating; the counts are validated, stored on the session independently of competency scoring, and synced to nullable `evaluation_sessions` fields by the reporting migration.
- Competency closure now evaluates planned items only and uses the latest non-null score in the latest session's rounds.
- Laravel sync now stores planned slugs and per-round score rows; reporting uses a per-session effective-score view for session-level analyses, while the score export preserves each observation round.
- Round-aware MySQL views now cover session numbering, session averages, day progress, and planned-item journey competency status.

## Notes

- Legacy flat score arrays are retained as a session-level latest-non-null summary for existing consumers. The `rounds` arrays and selected-item slugs are authoritative for new sessions.
- Echocardiogram activity counts are optional session fields for backwards compatibility; new Echocardiogram sessions require explicit non-negative whole-number counts, with correct diagnoses no greater than procedures performed.
- Tool palette values are now wired in a structured way, but the remaining PDF colour matching should still be confirmed with the client before release.
- The advanced-item grey treatment remains represented by `isAdvanced`, and the competency rules continue to exclude advanced items from `basic_competent`.

## TODOs for follow-up

- Confirm the remaining PDF tool colours and exact score-button hex values against the client’s design signoff.
- Validate the reporting migration and views against the supported MySQL 8 deployment before release; the local SQLite test suite is blocked by an earlier migration that uses MySQL-only `CREATE OR REPLACE VIEW` syntax.
