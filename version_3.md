# Prompt: PEN-Plus Mentorship Tool 2.0 — Monitoring App Alignment Refactor (v2, client-answers incorporated)

You are working on the `PPlusV2/monitoring/` Nuxt 4 app. Read `CLAUDE.md` first — it contains the authoritative data model, architectural decisions, and known gotchas for this project. Then read this prompt carefully; it supersedes the current session-form UX and the earlier draft refactor prompt where they conflict, and it resolves all previously open questions.

The client has reviewed v2 of the monitoring platform and rejected the current session-entry UX. They preferred v1 for its list-based layout and exact scoring wording, and they have added four new functional requirements. The overarching mandate is: **the monitoring collection tool must mirror the paper `PEN-Plus Mentorship Tool 2.0_April_2026.pdf` as closely as possible — layout, wording, grouping, grey-shading of advanced items, and color coding.**

---

## 0. Non-negotiable constraints (carry over from CLAUDE.md)

- **Mentee score only.** Do not reintroduce mentor/autonomy scoring.
- **No `sessionNumber` stored** on source docs. Compute dynamically per `evaluationGroupId` by sorting on `evalDate`, then `createdAt`.
- **No 5-session cap.** Journeys remain unlimited until competency is reached.
- **One CouchDB/PouchDB document per session visit.** Do not nest an entire journey in one doc.
- **Counselling DC1–DC9 stay cross-cutting** and are evaluated in every session.
- **Tool-namespaced item slugs** (`diabetes-D1`, `echo-E1`, etc.).
- **Advanced items stay flagged** (`isAdvanced: true`) and remain excluded from `basic_competent` closure.
- **`evaluationGroupId = `${mentee.id}::${toolSlug}``** — unchanged.

---

## 1. Change summary (client-confirmed)

1. **List layout, not one-item-per-swipe.** Session form renders all selected items in a single scrollable list.
2. **Scoring scale wording matches the PDF mentee segment exactly** (score 2 = "Demonstrates basic competency" — the `version_3.md` wording was a typo, use the PDF).
3. **Item subset selection at planning stage**, prompted per segment (group), before the observation list is shown.
4. **Session editing.** Mentors can reopen and edit a saved session; changes upsert by `_id`.
5. **Item grouping.** Items are visually grouped under the exact section headings from the PDF.
6. **Rounds within a day's session** = multiple observational passes on the same day (e.g. morning ward + afternoon OPD). A session is still one visit = one calendar day.
7. **Overall look & feel mirrors the paper tool**, including PDF color coding for scoring and per-tool themes (e.g. lime green for Diabetes Mellitus, purple for Heart Diseases).
8. **Advanced items are rendered grey**, matching the PDF.

---

## 2. Client decisions on previously open questions

These are now settled. Do not re-litigate them.

| # | Question | Decision |
| --- | --- | --- |
| 1 | Rounds semantics | Multiple observational passes **on the same day** (e.g. morning ward + afternoon OPD). One session = one day. |
| 2 | Subset selection default | **Prompt per segment/group at planning stage** (after mentee + tool chosen). After confirmation, the observation list shows only the selected items. |
| 3 | Aggregation across rounds | Within the same day: **most recent round's score** for each item. Across days: compare **last round of last session** vs **first round of first session**. |
| 4 | Advanced items visual treatment | **Grey them**, as in the PDF. |
| 5 | Score-2 label | **"Demonstrates basic competency"** (PDF is authoritative). |
| 6 | Color coding | **Follow the PDF** — per-tool color themes and score-color coding. |

---

## 3. Data model changes

### 3.1 Scoring scale (interfaces + UI)

Persist a single source of truth. Score labels must be exactly:

| Score | Mentee label (exact PDF wording) |
| --- | --- |
| 1 | Does not demonstrate competency |
| 2 | Demonstrates basic competency |
| 3 | Demonstrates satisfactory competency |
| 4 | Demonstrates advanced competency |
| 5 | Demonstrates exceptional competency |
| N/A | The competency cannot be evaluated |

```ts
export const SCORE_LABELS: Record<1 | 2 | 3 | 4 | 5, string> = {
  1: 'Does not demonstrate competency',
  2: 'Demonstrates basic competency',
  3: 'Demonstrates satisfactory competency',
  4: 'Demonstrates advanced competency',
  5: 'Demonstrates exceptional competency',
}
export const NA_LABEL = 'The competency cannot be evaluated'
```

`ScoreButtons`, `ScoreLegend`, `ScorePill`, and any other score UI consume these labels — no hardcoded strings.

**Score color coding (mirror the PDF):** define a `SCORE_COLORS` map (or Tailwind classes) driven by the PDF's colouring of the 1–5/N/A row. If the PDF's exact hex values aren't obvious from the doc, use a sensible graduated red→green scale and leave a `TODO(client-confirm)` with the chosen palette; the client will supply exact values if needed. Do not invent tool-specific score colours — scores share one palette across tools.

### 3.2 Item groups (new)

Add a `groups` concept to the tool data so the UI can render the exact section headings from the PDF. Extend `ITool` (or add a parallel export):

```ts
export interface IItemGroup {
  slug: string              // e.g. 'diabetes-assessment-classification'
  label: string             // exact PDF heading, e.g. 'PATIENT ASSESSMENT AND CLASSIFICATION'
  itemSlugs: string[]       // ordered, tool-namespaced
}
```

Populate `groups` for every tool from the PDF headings, preserving order and wording. Examples (extract every heading verbatim from the PDF):

- **Diabetes:** `PATIENT ASSESSMENT AND CLASSIFICATION` (D1–D3), `TREATMENT ADJUSTMENT AND INDIVIDUALIZATION type 1 DM` (D4–D11), `TREATMENT ADJUSTMENT AND INDIVIDUALIZATION type 2 DM` (D12–D16), `SAFETY` (D17–D21), `SCREENING and INITIAL MANAGEMENT FOR CHRONIC COMPLICATIONS` (D22–D28), `PATIENT EDUCATION` (D29–D34), `SPECIAL SITUATION: PREGNANCY` (D35–D37, advanced).
- **Cardiac:** `CLINICAL ASSESSMENT HEART FAILURE`, `GENERAL MANAGEMENT OF HEART FAILURE`, `RHEUMATIC HEART DISEASES`, `CONGENITAL HEART DISEASES`, `PATIENT EDUCATION`, `HEART FAILURE MANAGEMENT IN PREGNANCY` (advanced).
- **Echo:** `Basic Echocardiography competencies` (+ the two numeric counters).
- **Sickle Cell:** `DIAGNOSE AND ASSESS SCD`, `MANAGEMENT OF SCD`, `MANAGEMENT OF ACUTE AND CHRONIC COMPLICATIONS`, `PAIN MANAGEMENT`, `INPATIENT MANAGEMENT`, `PATIENT EDUCATION`, `MANAGEMENT OF SCD DURING AND BEFORE PREGNANCY` (advanced).
- **Respiratory (Asthma + COPD):** `ASTHMA: ASSESSMENT AND DIAGNOSIS`, `MANAGEMENT OF ASTHMA`, `PATIENT EDUCATION`, `MANAGEMENT OF ASTHMA EXACERBATIONS`, `ASSESSMENT, DIAGNOSIS OF OTHER CHRONIC RESPIRATORY DISEASES (COPD AND POST-TUBERCULOSIS LUNG DISEASE)`, `MANAGEMENT OF COPD AND POST-LUNG DISEASES`.
- **Severe Hypertension:** `ASSESSMENT AND DIAGNOSIS`, `MANAGEMENT`, `MONITORING AND SAFETY`, `EMERGENCY AND URGENT CARE`, `PATIENT EDUCATION`.
- **CKD:** `ASSESSMENT AND DIAGNOSIS OF CKD`, `MANAGEMENT OF CKD`, `COORDINATION OF CARE AND REFERRAL`, `ASSESSMENT AND DIAGNOSIS OF NEPHROTIC SYNDROME`, `MANAGEMENT OF NEPHROTIC SYNDROME`.
- **Epilepsy:** `ASSESSMENT AND DIAGNOSIS`, `MANAGEMENT`, `PATIENT EDUCATION`, `PREGNANCY AND EPILEPSY` (advanced).
- **Palliative:** `Assessment of needs and symptoms`, `Pain and symptom management`, `Psychosocial, cultural and spiritual support`, `Care Coordination & End of life Planning`.
- **Chronic Liver Disease:** `ASSESSMENT AND DIAGNOSIS`, `GENERAL MANAGEMENT AND OF COMPLICATIONS`.
- **Counselling:** single group `General Counselling Competencies (SME)` containing DC1–DC9.

Groups must appear in every tool's data, including for items that are advanced. Do not alter item ordering within a group.

### 3.3 Per-tool color themes (new)

Add a `theme` (or `color`) field to `ITool`:

```ts
export interface IToolTheme {
  primary: string        // hex or tailwind token
  headerBg: string
  headerText: string
  accent: string
}
```

Populate from the PDF. Client-supplied examples to start from:

- `diabetes` → lime green
- `cardiac` → purple

For all other tools, extract the PDF colors if legible; otherwise pick distinguishable, accessible colors and mark `TODO(client-confirm)` in a comment near the palette. Apply the theme to: the tool header on the session form, the group section headers, the journey card accent, and (optionally) the score row background when nothing is scored yet. Do not overload the score buttons themselves with tool colors — those follow the shared score palette.

### 3.4 Rounds within a session (same day, multiple passes)

```ts
export interface IRound {
  roundId: string            // uuid, stable for editing
  label?: string             // e.g. "Round 1 — morning ward"
  itemScores: IItemScore[]   // only items scored in this round
  counsellingScores: IItemScore[]
  notes?: string
  startedAt: number
  completedAt?: number
}

export interface ISession {
  _id: string
  _rev?: string
  type: 'session'
  evaluationGroupId: string
  mentee: IUserRef
  evaluator: IUserRef
  toolSlug: string
  evalDate: number           // one calendar day per session
  facilityId: string
  districtId: string
  rounds: IRound[]           // replaces flat itemScores / counsellingScores
  selectedItemSlugs: string[]      // the plan-level subset chosen in step 3.5
  selectedCounsellingSlugs: string[]
  phase: MentorshipPhase | null
  notes?: string             // session-level notes
  syncStatus: SyncStatus
  syncedAt?: number
  createdAt: number
  updatedAt: number
}
```

Rules:

- A session must have **at least one round**. Default new sessions to a single round labelled "Round 1".
- Rounds are ordered and reorderable before saving; add/delete per round.
- `evalDate` is a single day; do not allow rounds to cross midnight without warning. If the client ever needs a multi-day observation, that's a new session.
- The plan-level `selectedItemSlugs` is stored on the session (not just in component state) so re-opening for edit restores the plan.

### 3.5 Item subset selection — planning stage, per segment

The mentor's flow after picking mentee + tool:

1. **Plan screen** (`sessions/new.vue` step 1): the tool's **groups are listed**. For each group, the mentor toggles which items they intend to evaluate today.
   - Defaults: all items **unselected**. (Client said "prompt the user per segment to select which ones they want to evaluate", which implies an explicit choice per segment.)
   - Per group, offer "Select all in group" / "Clear group" controls.
   - Show a running count "N items selected across M groups".
   - Require at least one item selected to proceed; Counselling DC1–DC9 is separate and defaults to all selected (it's cross-cutting — but still confirmable).
2. **Confirm plan** → the observation screen appears showing **only the selected items**, grouped by their PDF heading.
3. **During observation**, the mentor records rounds against the selected items only.
4. Items that were selected in the plan but not scored in any round are treated as **not scored** (not as failing). Items not in the plan are invisible during this session.
5. The plan is editable before save (return to step 1 without losing recorded rounds — if items are removed, drop their scores with a confirmation; if items are added, they appear unscored in the current round).

### 3.6 Editable sessions

- From `sessions/session.vue` and each `SessionCard` on `sessions/journey.vue`, expose an **Edit** action that opens the session in edit mode pre-filled (plan, rounds, scores, notes).
- Save **upserts by `_id`** using the existing PouchDB pattern, bumps `updatedAt`, and resets `syncStatus` to `'pending'` if the session was previously synced.
- Reporting side: `processSession()` in `SyncCouchDb.php` delete/reinserts `session_item_scores`; **update the schema and processor to account for rounds and the plan subset** (see §5).
- Add a confirmation modal before discarding unsaved edits.

---

## 4. Aggregation & competency rules (client-confirmed)

### 4.1 Within a session (same day, multiple rounds)

- For each item, the session-level score is the **score from the most recent round in which the item was scored** (latest non-null wins).
- Session average = mean of those latest-per-item scores, over items with a non-null score.
- Counselling items follow the same rule.

### 4.2 Across sessions (different days)

- **Initial baseline** = first round of the first session in the journey.
- **Current state** = last round of the most recent session in the journey.
- Improvement metrics, competency checks, and "gap over time" reporting compare these two anchors. Intermediate sessions remain available for drill-down but do not redefine the anchors.

### 4.3 Journey competency closure

- `basic_competent`: for every **non-advanced** item that was in the journey's plan at least once, the item has a non-null score ≥ 4 in the **last round of the last session** (using the latest-non-null rule within that session).
- `fully_competent`: same but including advanced items.
- Items that were **never planned in any session** do not block closure. Items that were planned but never scored keep the journey `in_progress`.
- Advanced items, whether planned or not, never block `basic_competent`.

If any of the above conflicts with the client's clinical understanding, flag it in a comment rather than guessing.

---

## 5. Reporting / MySQL / Laravel changes

- **Schema:** add a `round_index` column to `session_item_scores` **or** introduce a `session_rounds` table + `round_id` FK. Prefer the lighter option (`round_index` smallint on `session_item_scores`, ordered per session) unless multi-round queries need first-class round rows. Document the choice.
- Add `selected_item_slugs` / `selected_counselling_slugs` columns on `evaluation_sessions` (JSON), or a normalised `session_plan_items` table if you need to query "which items were planned" efficiently across the fleet.
- **Sync processor** (`processSession()`): accept both legacy flat `itemScores` / `counsellingScores` docs **and** new `rounds`-based docs. Legacy is wrapped as a single round labelled "Round 1" during ingest (do not modify the CouchDB doc).
- **Views:** update `v_sessions_numbered` and `v_session_averages` to respect the "latest non-null score per item per session" rule and the plan subset. Add a `v_evaluation_group_status` view if not yet present (per `CLAUDE.md` §10) that derives `basic_competent`, `fully_competent`, `sessions_to_competence`, `days_to_competence`, `first_session_date`, `competent_session_date` using the anchors in §4.2.
- **Filament `EvaluationSessionResource`:** surface round count, per-round item scores, and the plan subset. `GapEntryResource` unchanged.
- **Seeds:** re-run tool/item seeds after adding groups; groups live in `evaluation_items` as a new `group_slug` / `group_label` column, or as a dedicated `tool_item_groups` table if the client wants groups reusable across tools (they are not, currently — keep it simple unless asked).

---

## 6. UI / UX details

### 6.1 Session form flow (`sessions/new.vue`, edit-mode reuses it)

**Step 1 — Plan (per segment):**
- Header: mentee, tool (with tool color theme), date, phase, facility.
- List of groups in PDF order; each group shows its items as checkboxes with the exact PDF item text.
- Per group: "Select all" / "Clear".
- Counselling section: DC1–DC9, default selected, same controls.
- "Confirm plan" button → step 2.

**Step 2 — Observe (list layout):**
- Round tabs at the top: "Round 1", "Round 2", … + "Add round". Default Round 1.
- Inside the active round: grouped sections in PDF order. Each section header uses the exact PDF heading and the tool's theme colour. Each row: item number, item text, and the score row (`1 2 3 4 5 N/A` in that order, matching the PDF legend).
- **Advanced items rendered grey**, matching the PDF, with an "Advanced" chip.
- Per-round notes field.
- Counselling section repeats per round (it's per-round because different passes may emphasise different counselling behaviours).
- Sticky `SaveBar`: Save / Cancel.

**Step 3 — Review (preview):**
- Summary of the plan, rounds, and scores.
- Save.

### 6.2 Score buttons & legend

- `ScoreButtons` renders `1 2 3 4 5 N/A` in that order.
- Each button shows the number; the label is available via `ScoreLegend` (always visible above the list) and via tooltip/long-press on mobile.
- Apply the PDF's score-color coding (single palette, shared across tools).
- Once a score is selected on a competency card, immediately show its exact score label from §3.1 on that card. For example, score 2 displays "Demonstrates basic competency"; N/A displays "The competency cannot be evaluated". Update this text reactively whenever the selected score changes, and show no selected-score label before a score is recorded.
- Each clinical competency card also shows the most recent previous assessment for that item, when available: the previous score (with its score label, or N/A), assessment date, journey session number, and round number. Search earlier rounds in the current session first, then earlier sessions in reverse chronological journey order; use the most recent round containing an assessment of that item. Legacy flat-score sessions count as Round 1. Do not show the current round as its own previous assessment.
- When a numeric score from 1–5 is selected and a prior numeric score exists, show an accessible change indicator relative to that prior score: green upward trend for a higher score (improvement), red downward trend for a lower score (regression), or a grey dash for an equal score. Recalculate immediately when either score changes. Do not classify N/A as improved, regressed, or unchanged; if the prior assessment was N/A, show its details but no directional indicator.
- Previous-assessment details must use computed journey session numbers; never persist a session number in the session document. Within the current session, use the round's 1-based position.

### 6.3 Preview / detail (`sessions/preview.vue`, `sessions/session.vue`)

- Top: session summary — plan count, round count, session aggregate (per §4.1), date, phase.
- Below: per-round breakdown, each round reusing the grouped rendering.
- **Edit** action prominent.

### 6.4 Journey view (`sessions/journey.vue`)

- Keep the existing competency banner and gap management.
- Each `SessionCard` shows: date, phase, round count, session aggregate, and an **Edit** action.
- Optionally show a small sparkline of session aggregates over time (uses the §4.2 anchors).

### 6.5 Paper-tool fidelity checklist

- Group headings verbatim from the PDF.
- Score row order `1 2 3 4 5 N/A`.
- Advanced items grey.
- Tool themes as per PDF (lime green diabetes, purple cardiac, etc.).
- No mentor/autonomy column anywhere.

---

## 7. Legacy data migration

Existing session docs have flat `itemScores` / `counsellingScores` and no plan. On read, migrate in memory:

```ts
function migrateLegacySession(doc: any): ISession {
  if (Array.isArray(doc.rounds)) return doc as ISession
  const itemScores = doc.itemScores ?? []
  const counsellingScores = doc.counsellingScores ?? []
  return {
    ...doc,
    rounds: [{
      roundId: crypto.randomUUID(),
      label: 'Round 1',
      itemScores,
      counsellingScores,
      startedAt: doc.createdAt ?? Date.now(),
      completedAt: doc.createdAt ?? Date.now(),
    }],
    selectedItemSlugs: itemScores.map((s: any) => s.itemSlug),
    selectedCounsellingSlugs: counsellingScores.map((s: any) => s.itemSlug),
  }
}
```

Apply on load in `sessionStore`. Write the new shape on the next edit/save. Do **not** bulk-rewrite CouchDB docs without an explicit migration script; the reporting `processSession()` must accept both shapes during the transition.

---

## 8. Files to touch (indicative, not exhaustive)

**Data:**
- `app/data/evaluationItemData.ts` — add `groups`, `theme` per tool, `SCORE_LABELS`, `NA_LABEL`, `SCORE_COLORS`; keep namespaced slugs and advanced flags.
- `app/interfaces/ISession.ts` — add `IRound`, replace flat arrays, add plan fields.
- `app/interfaces/ITool.ts` / `IEvalItem.ts` — add `IItemGroup`, `IToolTheme`.

**Stores / composables:**
- `stores/sessionStore.ts` — upsert for edits, legacy migration on load.
- `composables/useCompetency.ts` — session aggregation (§4.1) + journey anchors (§4.2) + closure rules (§4.3).
- `composables/useSessionCalculations.ts` — update helpers for rounds + plan subset.

**Components:**
- `components/session/EvalItemRow.vue` — one row (grouped-list-friendly), PDF labels, grey advanced.
- `components/session/ItemCard.vue` — retire or repurpose as `ItemRow`.
- `components/session/ScoreButtons.vue`, `ScoreLegend.vue`, `ScorePill.vue` — use shared labels/colors.
- New: `components/session/PlanSelector.vue`, `RoundTabs.vue`, `RoundSection.vue`, `ItemGroupSection.vue`, `ToolThemeHeader.vue`.
- `components/journey/SessionCard.vue` — round count + Edit.

**Pages:**
- `pages/sessions/new.vue` — plan → observe → review steps.
- `pages/sessions/preview.vue` — grouped preview with rounds.
- `pages/sessions/session.vue` — grouped detail with rounds + Edit.
- `pages/sessions/journey.vue` — Edit action.
- Edit mode reuses `new.vue` (query `?edit=<id>`) or a sibling `pages/sessions/[id]/edit.vue`.

**Reporting (Laravel):**
- Migration for `round_index` on `session_item_scores` (or `session_rounds` table) and plan columns on `evaluation_sessions`.
- `app/Console/Commands/SyncCouchDb.php` → `processSession()`.
- `app/Models/EvaluationSession.php`, `SessionItemScore.php` (+ `SessionRound.php` if used).
- Views `v_sessions_numbered`, `v_session_averages`, new `v_evaluation_group_status`.
- Filament `EvaluationSessionResource`.

---

## 9. Testing / verification

- Load a legacy session → renders with one round, plan derived from scored items, correct labels.
- New session: plan a subset per group → confirm → record 2 rounds → save → reopen → edit (add item, change a score) → re-save → CouchDB doc is a single updated doc with new `_rev`.
- Sync to MySQL → `session_item_scores` reflects rounds + plan subset; views produce sensible aggregates.
- Competency banner on `journey.vue` works for a legacy journey and a new multi-round journey; advanced items are excluded from `basic_competent`.
- Score labels match the PDF exactly (side-by-side check).
- Advanced items are grey; tool themes match the PDF (lime green diabetes, purple cardiac, etc.).
- `php artisan sync:couchdb --reset` still runs end-to-end.

---

## 10. Deliverables

1. Updated `evaluationItemData.ts` with groups, themes, and score label/color constants.
2. Updated interfaces (`ISession`, `IRound`, `IItemGroup`, `IToolTheme`).
3. Reworked session flow: plan (per segment) → observe (list, rounds) → review; plus edit.
4. Updated preview / detail / journey views.
5. Updated `useCompetency` and `useSessionCalculations` per §4.
6. Laravel migration + sync processor + views + Filament resource updates.
7. A short `CHANGES.md` at `PPlusV2/monitoring/` documenting the new session shape, migration approach, and any `TODO(client-confirm)` items (notably the full tool color palette and score color hex values).

---

## 11. Remaining `TODO(client-confirm)` items (do not block on these)

1. **Full tool color palette** — client has supplied lime green (Diabetes) and purple (Cardiac). Get the rest from the PDF; if illegible, propose a palette and mark it clearly.
2. **Score button colors** — if the PDF's 1–5/N/A color coding is legible, use it; otherwise propose a red→green scale and mark it clearly.
3. **Counselling default selection** — currently defaulting all DC1–DC9 to selected. Confirm this is fine.