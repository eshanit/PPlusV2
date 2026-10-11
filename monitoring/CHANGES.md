# Monitoring app alignment changes

This document summarises the first implementation pass aligning the monitoring app to the paper `PEN-Plus Mentorship Tool 2.0_April_2026.pdf` and the v3 refactor brief.

## Implemented

- Added the shared score labels and score palette constants in `app/data/evaluationItemData.ts`.
- Added group and theme metadata to the tool model (`IItemGroup`, `IToolTheme`, `groups`, `theme`).
- Added round-based session support (`IRound`) while keeping legacy flat `itemScores` / `counsellingScores` compatibility.
- Added legacy-session migration support in `sessionStore` so older docs are normalized in memory.
- Updated the competency logic to read score values from either the legacy flat arrays or the new round-based format.

## Notes

- The app still retains the legacy flat session arrays for compatibility with the current UI while the list-based, grouped, round-aware workflow is implemented.
- Tool palette values are now wired in a structured way, but the remaining PDF colour matching should still be confirmed with the client before release.
- The advanced-item grey treatment remains represented by `isAdvanced`, and the competency rules continue to exclude advanced items from `basic_competent`.

## TODOs for follow-up

- Confirm the remaining PDF tool colours and exact score-button hex values against the client’s design signoff.
- Move the session form itself from the legacy flat-score layout to the plan → observe → review round-based workflow.
- Extend the reporting sync logic to ingest the round-aware session shape and plan subset.
