# PenPlus NCD: Reporting Dashboard Presentation Script

**Length:** about 15 minutes, then questions. It follows straight on from the monitoring demo.
**Signed in as:** Admin User (`admin@penplus.local`), who sees every district
**Format:** **Show** is what to do on screen. **Say** is roughly what to say. Keep it conversational, not word for word.

---

## Before you start

- [ ] Sign in to the dashboard in a second browser tab before the presentation.
- [ ] **After the monitoring demo, bring in the new session.** In `reporting/`, run `php artisan sync:couchdb` and then `php artisan cache:clear-reports --all`. On the server, the sync runs every 5 minutes on its own. Locally it only runs automatically if `php artisan schedule:work` is running.
- [ ] Afterwards, check that **Tendai Moyo · Diabetes** appears under **Recent completions** on the dashboard.
- [ ] Don't quote exact totals in advance. The live session in the monitoring demo changes them: five competent journeys afterwards instead of four, and one more gap.

---

## Part 1: From the tablet to the dashboard (1 minute)

**Say:**
> "Everything we just recorded on the tablet comes here. The tablet syncs to a central database, and the dashboard reads from it. Mentors capture data; programme and district staff use this side to see progress, find problems early and plan mentorship visits."

**Show:** the **Dashboard**. Point to **Recent completions**.

**Say:**
> "Here is Tendai, who became competent a few minutes ago."

---

## Part 2: The dashboard (2 minutes)

**Show:** the four cards across the top.

**Say:**
> "At the top: how many evaluation journeys there are, how many have reached basic competence, the average number of sessions it took, and how many gaps are still open. A journey is one mentee on one tool, and it closes when the mentee reaches basic competence."

**Show:** **Competence by tool**, then **Facilities**, then **Active journeys** and **Gap status**.

**Say:**
> "Below that is progress for each tool and for each facility. **Active journeys** lists the mentees not yet competent, with a link straight into their journey. **Gap status** shows open and resolved gaps."

---

## Part 3: Journey Status (2 minutes)

**Show:** **Journey Reports → Journey Status**. Filter by tool or district, then clear the filter.

**Say:**
> "This is one row per mentee and tool. It shows how many required competencies are achieved, the number of sessions and days to competence, and the open gaps.
>
> A word on how progress is measured. Mentors score only what they observed, so each visit covers a different set of competencies. Comparing visit averages would be misleading, because a visit that covered only easy items would look like a big improvement. So everything here is based on each competency's latest score, the same rule the tablet uses to decide competency. The tablet and the dashboard always agree."

---

## Part 4: Competency Coverage (2.5 minutes)

This report is new. It answers the feedback about seeing all competencies across journeys.

**Show:** **Journey Reports → Competency Coverage**, in the **By journey** view.

**Say:**
> "This report shows what each journey hasn't covered yet: required competencies that have **never been evaluated**, and low scores that **haven't been looked at for two or more sessions**."

**Show:** find **Nyasha Dube**. Expand her missed items.

**Say:**
> "Here is Nyasha, the mentee we picked up on the tablet. Twelve required competencies have never been evaluated, and S5 is still at 2 since her first visit. A district coordinator can use this to plan the next visit, or to tell a new mentor where to start."

**Show:** the filters: tool, district, evaluator, and how many sessions count as "recently". Then switch to **By competency**.

**Say:**
> "Switch to **By competency** and it shows which competencies are missed most often across all mentees. A competency that is rarely evaluated may be hard to observe, perhaps because the right patients are rare, and that helps in planning training. Everything here can be exported to CSV."

**Show:** go back to **By journey** and click **Heatmap →** on **Chipo Mutasa · Chronic Liver Disease**.

---

## Part 5: Journey Heatmap (2 minutes)

**Show:** Chipo's heatmap. Hover over a few cells.

**Say:**
> "The heatmap is the paper tool for one mentee, in colour. Competencies run down the side, sessions and rounds run across. Red is a low score, green is competent, and a blank means the item wasn't evaluated in that visit. You can see straight away that each visit covered different competencies, and where she improved within a single day between rounds."

**Show:** the **Insights** panel and the open gap areas.

**Say:**
> "Insights compares each competency's latest score with the score before it. **Needs attention** lists everything not yet at 4 or 5. Here that's L3 and L9, both at 3. **Most improved** shows real progress: L5 went from 2 to 5 at the last visit. Anything that has dropped or is stuck at the same score is flagged too. Open gaps are grouped by area, such as knowledge or clinical skills. The heatmap can also be downloaded as a PDF for the mentee's file."

---

## Part 6: Time to Competence (1.5 minutes)

**Show:** **Trend Analysis → Time to Competence**.

**Say:**
> "For completed journeys, this shows how many sessions and how many days it took to reach competence, by tool. This is the measure that wasn't possible when the old system capped every journey at five sessions. Over time it will show which tools take longest, and whether mentorship is getting mentees there faster."

---

## Part 7: Who needs help (2 minutes)

**Show:** **Journey Reports → Struggling Mentees**.

**Say:**
> "This ranks mentees from the lowest current standing. At the top is Tafadzwa, who has only had a first visit, so low scores are expected. The real concern is next: Simbarashe. His Epilepsy scores have gone up and down across five visits with two different mentors, and he's still low. That tells a coordinator where to put extra mentorship."

**Show:** **Safety → High-Risk Alerts**.

**Say:**
> "High-Risk Alerts lists every competency whose latest score is still 1 or 2, so these can be followed up before they affect patients."

---

## Part 8: Gaps (1.5 minutes)

**Show:** **Journey Reports → Gap Overview**.

**Say:**
> "These are the gaps mentors logged on the tablet, round by round. They're shown by tool, by area and by the supervision level the mentor recommended, along with the average number of days to resolve one. Recurring gaps in one area, such as communication, suggest a training need for the whole team, not just one person."

---

## Part 9: Tool and item analysis (1.5 minutes)

**Show:** **Journey Reports → Tool Analysis**. Select **Diabetes Mellitus**, then click an item.

**Say:**
> "Tool Analysis looks at one tool across all mentees: which competencies score lowest and which are often marked N/A. Clicking an item opens its own analysis: the score distribution, the trend by session, and which journeys have or haven't evaluated it yet."

---

## Part 10: Evaluators, access and exports (1 minute)

**Show:** **Evaluators → Evaluator Activity**.

**Say:**
> "This shows how many sessions each mentor recorded and when, which helps in planning supervision.
>
> On access: mentors can see every district, because they move between facilities and need to continue each other's work. District administrators see only their own district."

**Show:** **Overview → Exports**.

**Say:**
> "And the data can be exported as spreadsheets for your own analysis. The coverage report also exports to CSV, and each journey heatmap to PDF."

---

## Close and next steps (1 minute)

**Say:**
> "To sum up: the tablet captures each visit, offline if necessary. The dashboard turns that into progress, gaps and time to competence, using the same competency rule everywhere.
>
> A few things we need your input on before go-live:
> 1. Confirm which competencies are marked advanced (the grey rows). We've followed the paper tool.
> 2. Confirm the second **P8** in Palliative Care, which we've numbered **P9**.
> 3. Choose the pilot sites and mentors, so we can set up their accounts and roles.
>
> Thank you. Happy to take questions."

---

## If something goes wrong

| Problem | What to do |
|---|---|
| Tendai isn't under Recent completions | Run `php artisan sync:couchdb`, then `php artisan cache:clear-reports --all`, and reload. |
| A page is slow the first time | It's building its cache. Wait a moment. It's quick after that. |
| A question about why visit averages aren't compared | Each visit covers different competencies, so averages aren't comparable. Every figure uses each competency's latest score. |
| A question about who sees what | Admins and mentors see every district. District administrators see their own district only. |
