This is an excellent project. The PEN-Plus Mentorship Tool is rich with structured data, and digitizing it unlocks powerful analysis capabilities beyond what's possible with paper.

Here is a breakdown of the analyses you can generate from a data collection and reporting app built around this tool, categorized by who would use them.

### Core Metrics & Scoring Logic (The Foundation)

First, the app must calculate two key scores for each competency assessment:

1.  **Mentee Score (1-5):** Based on the "Mentee" row's descriptors.
2.  **Mentor Support Score (1-5):** Based on the "Mentor" row's descriptors (e.g., "I did not need to be there" = 5).

The "Goal level" is **4 or 5**. A score of 3 might be "developing" and <3 is "gap".

---

### Part 1: Individual Mentee-Level Analysis (For the Mentee & their direct Mentor)

These reports help track progress and guide day-to-day teaching.

**1. Competency Heatmap / Traffic Light Report**
- **Visual:** A grid with competencies (rows) and dates (columns). Color-coded:
    - 🟢 **Green (4 or 5):** Competent/Independent.
    - 🟡 **Yellow (3):** Developing, needs review.
    - 🔴 **Red (1 or 2):** Significant Gap, needs intensive teaching.
    - ⚪ **Grey:** Advanced competency (not required for basic competence).
- **Analysis:** Instantly shows which specific skills are weak (e.g., red on `D7. Titrates insulin for sick day`) and which are strong.

**2. Autonomy Trend Line**
- **Visual:** A line chart plotting the *average* Mentor Support Score (or % of competencies scored 4/5) over Date 1 to Date n (Session 1 to Session n).
- **Analysis:** Quantifies progress toward the "Phases of Mentorship" goals. Is the trend clearly upward? Has it plateaued?

**3. Phase Progression Dashboard**
- **Logic:** Automatically calculates the two key milestones:
    - **Ready for "Ongoing Mentorship"?** -> `Overall autonomy ≥ 3` on repeated observations AND `≥70%` of basic competencies in each disease are ≥3.
    - **Ready for "Supervision Model"?** -> `Consistent domain scores ≥ 4` AND `≥70%` of basic competencies in each disease are ≥4.
- **Output:** A clear status: **"Current Phase: Initial Intensive"** and **"Milestone to Next Phase: NOT YET MET (72% of Diabetes competencies are ≥3)"**

**4. Gap Analysis Summary (from the GAP mapping grid)**
- **Analysis:** Aggregate all logged gaps. Show a prioritized list:
    - Most frequent gap domain (e.g., "60% of gaps are in Clinical Skills").
    - Most common specific competency GAP (e.g., "C4. Interprets echo report" appears in 4 of n sessions).
    - Average time to address a gap.

**5. Condition-Specific Readiness**
- **Analysis:** For each condition (Diabetes Type 1, Heart Failure, Sickle Cell, etc.), calculate the percentage of basic competencies scored at 4 or 5. This answers: "Is this mentee safe to manage a sickle cell patient independently, or do they still need supervision for diabetes?"

---

### Part 2: Program / Cohort-Level Analysis (For the Clinical Director or Program Manager)

These reports help evaluate the mentorship program's effectiveness and allocate resources.

**1. Mentee Cohort Progress Summary**
- **Visual:** Bar chart showing the number of mentees in each Phase (Intensive/Ongoing/Supervision). A funnel chart showing progression from start to exit.
- **Analysis:** What % of mentees are "on track"? Are many getting stuck in the "Intensive" phase for too long?

**2. Competency "Hot Spots" (Program-Wide Gaps)**
- **Analysis:** Across all mentees, which are the hardest competencies to achieve? The app can rank every competency by the average score.
    - *Example Insight:* "Across the entire program, `C13. Seeks timely specialist support` has an average score of only 2.3. We need a program-wide workshop on referral criteria." Or, "`S7. Monitors HU toxicity` is a common red-flag for SCD."

**3. Mentor Effectiveness Comparison**
- **Analysis:** Compare cohorts of mentees assigned to different mentors.
    - *Example Insight:* "Mentee of Mentor A achieve `≥4` in Echo competencies an average of 2 months faster than mentees of other mentors. What is Mentor A doing differently?" (Could be additional teaching techniques or better prior selection).

**4. Clinical Site / Service Line Analysis**
- **Analysis:** Compare the performance of the PEN-Plus clinic at Site X vs. Site Y.
- **Output:** "Site X has excellent scores in managing RHD but poor scores in managing Asthma. Site Y has the opposite. Let's facilitate cross-site mentorship."

**5. Return on Investment (ROI) of Mentorship**
- **Analysis:** Track the "time to independence" (from Date 1 to when Phase 2 milestone is met). Average this over time. If you introduce a new simulation-based training, does the average "time to independence" drop from 6 months to 4 months?

---

### Part 3: Quality Improvement & Clinical Risk Analysis

**1. High-Risk Competency Alerts**
- **Logic:** Flag any assessment where a **high-severity, low-frequency** competency (e.g., `H17. Manages hypertensive emergency`, `S18. Identifies acute chest syndrome`) is scored as a **Gap (1 or 2)** .
- **Analysis:** Generate a weekly report: "These 5 mentees have not yet demonstrated competency in recognizing acute chest syndrome." This allows for targeted, urgent remediation.

**2. Correlation Analysis**
- **Analysis:** The app can look for correlations in the data.
    - *Example:* Does a strong score in `DC6. Involves patient in decision making` correlate with better patient adherence or clinical outcomes? (This would require linking to patient records).
    - *Example:* Do mentees who struggle with `E10. Stepwise anti-seizure protocol` also struggle with `D5. Assesses glycemic control and adjust treatment`? (Suggests a fundamental gap in treatment algorithms).

**3. Echocardiogram Competency Tracker**
- **Analysis:** Simple but powerful. Track the **# of supervised echos** and the **# with correct final diagnosis**. A mentee may need a quota (e.g., 50 supervised echos) before being signed off as independent.

---

### Essential Reporting Dashboards (User Stories)

1.  **For the Mentee:** "My Progress Report" - A simple view of my heatmap, my trend line, and the specific gaps my mentor has identified for me to study before the next session.
2.  **For the Mentor:** "My Mentees Dashboard" - A list of my mentees, their current phase, days since last assessment, and a key "At Risk" alert for any mentee with a red competency in a critical skill (e.g., insulin sick-day dosing).
3.  **For the Program Manager:** "Cohort Health Dashboard" - Overall phase distribution, top 5 program-wide gaps, average time-to-independence, and mentor comparison charts.
4.  **For the Clinical Director:** "Clinical Safety Report" - A quarterly report listing all assessed providers and their readiness (or lack thereof) to manage critical, life-threatening conditions independently.

### Summary of Key Data Points to Capture

To enable these analyses, the database must capture:

- **Mentee ID** & **Mentor ID** (linked).
- **Date of Session.**
- **For each competency:** Mentee Score (1-5), Mentor Score (1-5), N/A flag.
- **Flag for "Advanced Competency" (Grey).**
- **GAP Mapping Grid Data:** Date, Specific Gap Text, Domain (dropdown: Knowledge, Critical Reasoning, Clinical Skills, Communication, Attitude), Addressed? (Y/N), Timeline.
- **Calculated Field:** Phase Milestone status.

By building this app, you move from a static paper checklist to a dynamic learning management system that can identify system-wide weaknesses, celebrate effective mentors, and, most importantly, ensure patient safety by verifying provider competence before they practice independently.