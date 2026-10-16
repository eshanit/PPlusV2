# Tablet UI pass

The monitoring app is used by mentors on tablets in clinics, often offline and
in bright light. This pass adjusts icons, text size, touch targets, layout width
and colour contrast for that setting. Phone layouts are unchanged apart from
slightly larger controls.

## Summary of changes

| Area | Before | After |
| --- | --- | --- |
| Icons | Fetched from the Iconify API at runtime, so blank when offline | Bundled into the client build, no network needed |
| Base text size on tablets | 16px root, so `text-xs` was 12px | 18px root at 768px and wider, so `text-xs` is 13.5px |
| Smallest text | 10px and 11px badges | 12px minimum (`text-xs`) everywhere |
| Score buttons | 40px tall, 65% opacity when unselected | 48px (54px on tablet), full opacity, thicker border |
| Selected score text | White on every colour | Dark text on scores 2, 3, 4 and N/A, white on 1 and 5 |
| Content width | `max-w-2xl` (672px) | `max-w-4xl` (about 1008px on tablet) |
| Default control size | Nuxt UI `md` | `lg` for buttons, inputs, selects, textareas, checkboxes |
| Primary actions | `sm` / `lg` buttons | `xl` buttons in the session form sticky bars and review save |
| Colour mode | Follows the system | Defaults to light |
| Sticky page headers | Stuck at `top-0`, hidden under the app header | Stuck at `top-14`, below the app header |
| Back navigation | Small unlabelled arrows on some pages, none on the session form or mentee list | One labelled Back button in the app header on every page except home |

## Details

### Offline icons

`nuxt.config.ts` now sets the Nuxt Icon module to `provider: 'none'` and
`fallbackToApi: false`. Two icon collections are installed as dev
dependencies so the build can embed them:

- `@iconify-json/heroicons` for every `i-heroicons-*` name used in `app/`.
  These are found automatically by `clientBundle.scan`.
- `@iconify-json/lucide` for the icons Nuxt UI uses inside its own components,
  such as select chevrons, modal close buttons and checkbox ticks. The scanner
  cannot see these, so they are listed in `clientBundle.icons`.

The build log reports the bundle size, currently 68 icons and about 18KB.

```
ℹ Nuxt Icon client bundle consist of 68 icons with 18.26KB(uncompressed) in size
```

**When adding an icon**, write the full literal name in source, for example
`'i-heroicons-calendar'`. Do not build names from string parts, because the
scanner only finds literal names. Prefer Heroicons so the app keeps one icon
family. If an icon renders blank, check that its name exists in the collection.

**If Nuxt UI is upgraded**, re-check its internal Lucide icons and update the
list in `nuxt.config.ts`. This command lists them:

```bash
grep -rhoE "i-lucide-[a-z0-9-]+" node_modules/@nuxt/ui/dist | sort -u
```

### Tablet scaling

`app/assets/css/main.css` raises the root font size to 112.5% at 768px and
wider. Tailwind and Nuxt UI size text, spacing and controls in `rem`, so the
whole interface scales in proportion. Use `rem`-based utilities such as
`text-xs` or `w-[22rem]`. Avoid `px` values like `text-[10px]`, because they do
not scale.

The same file sets `touch-action: manipulation` on buttons, links and labels.
This removes the tap delay and stops accidental double-tap zoom while scoring.

### Default control sizes

`app/app.config.ts` sets `lg` as the default size for Nuxt UI buttons, inputs,
textareas, selects, select menus, checkboxes and radio groups. An explicit
`size` prop still wins. Existing explicit sizes were raised one step:
`xs` became `md` and `sm` became `lg`. Badges were set back to `md`, since they
are labels and not tap targets.

### Score buttons and contrast

`SCORE_TEXT_COLORS`, `scoreBackground()` and `scoreTextColor()` are new exports
in `app/data/evaluationItemData.ts`. Use them wherever text sits on a score
colour. The score background hex values themselves are unchanged.

`ScoreRow.vue` now:

- uses 48px buttons with bold `text-base` labels,
- keeps unselected buttons at full opacity with a visible grey border,
- shows the item title at `text-base`,
- shows the selected score's meaning as a bold chip, such as
  "3 · Demonstrates satisfactory competency". The chip uses the legend colour
  as its background with the same text colour as the selected button. Coloured
  text on white was rejected because amber, yellow and light green are
  unreadable as text,
- on tablets, places the six score buttons to the right of the item title
  instead of below it, which shortens long forms.

`ScorePill.vue` and `ScoreDistribution.vue` use the same text colour helpers.

### Tool colours

Cardiac and Epilepsy both used purple with the same header tint. Epilepsy is now
indigo so the two are easy to tell apart. No other tool colour changed. The
paper tool uses its own pastel section shading, which does not map one-to-one
to the app themes. A full recolour should wait for client sign-off.

### Session form

On `sessions/new.vue`:

- planning list rows are at least 56px tall with `text-base` titles,
- section headings use `text-sm` instead of `text-xs`,
- round selector chips are at least 44px tall,
- Confirm plan, Back to plan and Review session use `xl` buttons.

### Back navigation

Every page except home shows a labelled **Back** button at the left of the app
header in `layouts/default.vue`. The setup page shows one too when someone is
changing identity. The old icon-only arrows on individual pages were removed so
there is one place to look. The session form keeps its in-page *Back to plan*
button, which moves between steps rather than pages.

The logic lives in `composables/useBackNavigation.ts`:

- If the user reached the page from inside the app, Back returns to the
  previous page.
- If there is no in-app history, for example after a reload or a cold start,
  Back goes to the page's logical parent instead of leaving the app.
- Route-leave guards still run, so the session form still asks before
  discarding unsaved scores.

| Page | Parent when there is no history |
| --- | --- |
| Mentees list | Home |
| Mentee detail | Mentees list |
| Journey | Mentee detail |
| New or edit session | Mentee detail |
| Session detail | Journey |
| Review, sync, setup | Home |

The Android hardware back button in `plugins/backButton.client.ts` uses the same
rules. It exits the app only from home, or from setup before anyone has signed
in. **When adding a page**, add its parent to `getParentRoute()` if it is not a
child of home.

### Review session page

The review page at `sessions/preview.vue` was reworked so that every score
carries its meaning and the page answers the mentor's main question before
saving: is this mentee competent yet, and where are the gaps?

- **Competency outcome card** at the top. It applies the journey rule to the
  session as it would be saved, and updates if the date changes. It says
  "Saving will mark this journey Basic Competent", "Journey stays ...", or
  "Not yet competent", and lists the items still below 4. Long lists show six
  items with a "Show all" toggle.
- **Score chips everywhere.** Every item shows "4 · Demonstrates advanced
  competency" instead of a bare number, so the separate legend box was removed.
- **Not scored is distinct from N/A.** Planned items that were never scored
  used to disappear from the review. They now appear with a dashed "Not scored"
  chip, and the summary row counts them.
- **Averages show meaning.** Each average reads like "3.4 / 5 · Satisfactory"
  and its tile takes the colour of the nearest score band.
- **Neutral summary row.** Items scored, marked N/A, tool average and
  counselling average replace the five colour-coded tiles.
- **Change since last session** on each item: Improved, Regressed or No change,
  with the previous score.
- **Advanced items marked** with the same grey card and badge as the scoring
  screen.
- **Grouped by section.** The session summary follows the tool's sections.
  Round-by-round detail is a collapsed section, shown only when there is more
  than one round.
- **Sticky action bar** with Back to edit and Save Session. When Save is
  disabled, the bar says why, for example "Select a mentorship phase to save."

Supporting code:

- `components/session/ScoreChip.vue` is the shared score-with-meaning chip. The
  scoring screen uses it too.
- `components/session/ItemScoresList.vue` gained advanced styling, not-scored
  rows and previous-score changes. The session detail page benefits as well.
- `getCompetencyBreakdown()` in `composables/useCompetency.ts` returns the
  status plus the items holding it back. `getCompetencyStatus()` now reads its
  status from it, so the two cannot drift apart.
- `SCORE_SHORT_LABELS`, `COMPETENT_SCORE` and `scoreBand()` in
  `data/evaluationItemData.ts`. `getScoreChangeDisplay()` and `averageValue()`
  in `composables/useSessionCalculations.ts`.

The session detail page also had a bug fixed. Its counselling summary was given
the tool's item list, so counselling rows showed slugs instead of titles.

### Echocardiogram activity counts

The paper tool's two "#" rows record, per visit, how many echocardiograms the
mentee performed under supervision and how many reached a correct final
diagnosis. The data model and validation were already right. This pass changed
how they are entered and shown.

**Entering counts** uses `components/session/EchoActivityCounts.vue` on the
session form:

- The section is titled "whole visit" and says to enter one total across all
  rounds, so mentors don't enter counts per round.
- Each count has large minus and plus buttons around the number, so the
  on-screen keyboard is not needed. Typing still works for larger numbers.
- The plus button on "correct final diagnosis" stops at the number performed,
  so the two counts cannot conflict when using the buttons.
- Errors only show after a field is left empty, or after the mentor taps the
  reason in the action bar. They no longer show the moment the page opens.
- A live line shows "Diagnostic accuracy this visit: 5 of 6 correct (83%)".

**Why Review is disabled** now shows in the sticky action bar, for example
"Enter the echocardiogram counts", "Select a mentorship phase" or "Choose an
evaluation date". Tapping it scrolls to the field that needs fixing.

**E8 context.** Item E8, "Correctly identifies diagnosis according to the
PEN-Plus protocol", shows this visit's accuracy under its title while scoring.
This is information only. It never sets or suggests a score. Remove the
`ECHO_DIAGNOSIS_ITEM` context in `sessions/new.vue` if the clinical team
prefers mentors to score E8 without it.

**Showing counts**:

- The review and session detail pages use
  `components/session/EchoActivitySummary.vue`, with performed, correct and
  diagnostic accuracy.
- The journey page shows `components/journey/EchoActivityCard.vue` for
  Echocardiogram journeys. It gives totals across the journey, overall
  accuracy, and a per-visit list oldest first, so the trend is visible.
  Sessions saved before the counts existed are skipped and counted separately.

`echoAccuracy()` in `composables/useSessionCalculations.ts` is the shared
calculation. With zero performed, accuracy shows as "None performed" rather
than 0%.

### Removed components

These components were not referenced anywhere and were deleted:

- `components/session/ItemCard.vue`, which also used a conflicting score palette
  where 3 was blue and 5 was teal,
- `components/session/SaveBar.vue`,
- `components/session/ProgressBar.vue`,
- `components/session/ScorePill.vue`, replaced by `ScoreChip.vue`.

## Verification

- `pnpm build` succeeds.
- Every icon name used in the app exists in the installed local collections.
- `vue-tsc` type check passes for all files changed in this pass. Three older
  errors remain in `sessions/new.vue`, from code last changed on 11 October.
- The competency breakdown was checked against sample journeys. It matched the
  original status rule in every case.
- The pages have not yet been checked visually on a physical tablet.

## Open items

- **Check on a real tablet.** Confirm the score row layout in portrait and
  landscape, and that sticky bars do not cover the last item. Test the Android
  back button on a device build.
- **Confirm tool colours with the client** against the paper tool.
- **pnpm 11 ignores `pnpm.onlyBuiltDependencies` in `package.json`.** The build
  warns about this and points to https://pnpm.io/settings. The allowlist
  needs moving to the location pnpm 11 reads, or native build scripts may be
  skipped on a fresh install. This predates the tablet pass.
