# "Not assessable" competencies

**Status:** reasons agreed on 2026-10-26. Built in both apps on 2026-10-26.
**Requested by:** the client, after the 2026-10-23 presentation.

## The problem

Some facilities lack the medicines, tests, equipment or services that a competency depends on. For example, a hospital without ACE inhibitors can't assess **C11** ("Monitors renal function & potassium levels in patients with ACE inhibitors and/or spironolactone"). A mentee there could score 4 or 5 on everything else and still never reach Basic or Fully Competent, through no fault of their own.

The paper tool already allows for this in places, with wording like "if available" and "when available": R4, R24, H2, H13, H25, S7, S27 and S33.

## The decision

A mentor can mark a competency as **Not assessable** for a mentee and pick a reason from a fixed list. That competency then no longer stops the mentee from reaching competency.

### How it differs from N/A

| | N/A | Not assessable |
|---|---|---|
| Meaning | Couldn't observe it **this visit**, e.g. no suitable patient came in | **Can't be assessed at this facility** |
| How long it lasts | One visit | The rest of the journey, until the item is scored |
| Effect on competency | None: the item still has to be achieved | The item is left out of what's required |
| Reason | None | Required, from the list below |

To make the difference clear, N/A will be relabelled **"Not observed this visit"**. The stored value is still `null`.

### Behaviour

1. **Where it's marked.** It's a separate link under the score buttons, **"Can't be assessed here…"**, not a seventh score button. Tapping it opens the list of reasons.
2. **One mentee only.** It applies to this mentee and tool (`evaluationGroupId`), as the client asked. It isn't a setting for the whole facility.
3. **It carries forward.** Once marked, the item stays not assessable for the rest of the journey, and the mentor doesn't re-mark it each visit.
4. **A later score replaces it.** This follows the existing rule that an item's latest entry counts. If a mentee moves to a facility that has the medicine and a mentor scores the item, that score applies again. The journey page will also have an option to clear it.
5. **Storage.** It's recorded on the session's score for the item, so no new database or document type is needed and it syncs like any other score.

### Competency rule (updated)

- **Basic Competent:** every required item that isn't marked not assessable has a latest score of 4 or 5.
- **Fully Competent:** the same for every item, including advanced items.
- A competent journey with items set aside is labelled, for example, **"Basic Competent · 2 items not assessable"**, so it isn't mistaken for a full assessment.
- The rule must change in two places at the same time: `getCompetencyBreakdown()` in `monitoring/app/composables/useCompetency.ts` and the `v_evaluation_group_status` view. Coverage (`useCoverage.ts`, `v_item_coverage`) treats not-assessable items as neither missed nor stale.

## Reasons

Each reason is stored as a code, so reports group cleanly and the wording can change later without affecting the data. Every reason can have a short note. **Other** requires one.

| Code | Reason | Examples from the tool |
|---|---|---|
| `medicine_unavailable` | Medicine not available | C11 (ACE inhibitors, spironolactone), S5–S10 (hydroxyurea), S27–S28 (morphine), R8 (inhaled steroids), S45 (LMWH), D28 and H25 (statins), L11 (lactulose) |
| `lab_test_unavailable` | Laboratory test not available | C11, H6 and H11 (creatinine, potassium), D5 (HbA1c), D19 (ketones), S2 (Hb electrophoresis), S7 (FBC), H18 (urine dipstick), L1 (APRI) |
| `equipment_unavailable` | Equipment or imaging not available | R4 and R24 (spirometry), C14 and C19 (ECG), C15 and R23 (X-ray), H2 and L1 (ultrasound), C4 (echo report), D29 (glucometer) |
| `referral_unavailable` | Referral service not available | C20 and C25 (cardiologist), H13 (nephrology), H11 (dialysis), D25 (urologist), S33 (physical therapy), P8 (mental health), P13 (home-based care) |
| `service_not_offered` | Service not offered at this facility | S34–S38 (inpatient management, no ward), D35 and C36 (no antenatal care integration) |
| `patient_group_not_seen` | Patient group not seen at this facility | Pregnancy sections (D35–D37, C32–C36, S41–S45, E17), children (C23, E4, S12–S13, P10) |
| `not_locally_relevant` | Not relevant in the local context | D8 (Ramadan insulin titration) |
| `outside_scope_of_practice` | Outside the mentee's scope of practice | A nurse who may not prescribe morphine or start ACE inhibitors under national policy |
| `other` | Other (please specify) | A note is required |

`patient_group_not_seen` means the group **never** comes to the facility. A suitable patient simply not coming in on the day is still **N/A**.

## Reporting

- Journey reports show the count of not-assessable items next to competency status.
- A new report lists not-assessable items **by facility and reason**. If several mentees at one facility have C11 marked "Medicine not available", that points to a supply gap. This gives the programme data about resources, not just about mentees.

## Still to confirm with the client

1. **Critical items:** should any competencies always have to be assessed? If none are excluded, a mentor could reach competency by setting items aside.
2. **Who can clear it:** proposed as any evaluator, either by scoring the item or with the clear option on the journey page.

## Implementation (monitoring)

- **Data:** `IItemScore.notAssessable` (`{ reason, note? }`, with `menteeScore: null`). It's stored on the round's score and on the session-level summary.
- **Reasons:** `NOT_ASSESSABLE_REASONS` and `notAssessableLabel()` in `app/data/evaluationItemData.ts`. `NA_LABEL` is now "Not observed this visit".
- **Rule:** `getNotAssessableItems()` in `useCompetency.ts` finds each item's latest entry. A score lifts a mark, and N/A neither sets nor lifts one. `getCompetencyBreakdown()` leaves marked items out and returns them as `notAssessable`. Coverage gives them the status `not_assessable`.
- **UI:**
  - `ScoreRow`: the "Can't be assessed here…" link and the reason picker.
  - Plan screen: a "Not assessable · reason" chip for marks carried from earlier sessions.
  - Review page, `ScoreChip` and `ItemScoresList`: show the mark.
  - `CompetencyGrid`: a ⊘ cell and a "Not assessable" status.
  - `journey/NotAssessableCard.vue`: lists the marks, with **Clear**, which turns them back into N/A.
  - Journey banner and mentee page: show the count.

## Implementation (reporting)

- **Migration `2026_10_26_000001_add_not_assessable_items`:** adds `session_item_scores.not_assessable_reason` and `not_assessable_note`, and redefines these views with the same latest-entry rule as the app:
  - `v_evaluation_group_status`: competency leaves marked items out and adds `not_assessable_items`.
  - `v_journey_progress`: `required_items` and `total_items` exclude marked items, and adds `not_assessable_items`. `v_journey_summary` passes it through.
  - `v_item_coverage`: `coverage_status = 'not_assessable'`, with the reason and note.
  - `v_session_item_scores`: adds the reason and note.
  - `v_latest_item_scores`: drops items whose latest entry is a mark, so alerts and watchlists skip them.
- **Sync:** `SyncCouchDb::sessionScoreRows()` stores `notAssessable` from unscored entries.
- **Reports:**
  - **Not Assessable** (`/not-assessable`, `NotAssessableController`): by facility and reason, most common items, every mark, and a CSV export. Filters: tool, district, facility, reason.
  - **Journey Status** and **Competency Coverage:** show the count.
  - **Journey heatmap:** shows ⊘ cells.
- **Reason labels** are listed in three places and must stay in step: `NOT_ASSESSABLE_REASONS` (monitoring), `NotAssessableController::REASONS`, and `NOT_ASSESSABLE_LABELS` in `JourneyHeatmap.vue`.
- **Demo data:** Rudo Chirwa's CKD has H15 marked "Referral service not available", so it shows Fully Competent with 1 not assessable. Nyasha Dube's Sickle Cell has S27 and S28 marked "Medicine not available" and S2 marked "Laboratory test not available".
