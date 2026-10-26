// Seeds a small, hand-designed demo dataset into a development CouchDB: ten
// mentees whose journeys each show one feature of the app (see SCENARIOS).
// Docs are tagged `demo: true` (dataset `showcase-v1`), so clear-demo-couchdb.ts
// removes them. Districts are reference data and are not tagged.
//
// The presenter evaluates as the existing evaluator@penplus.local dashboard user
// (push it to CouchDB first with `php artisan couchdb:push-users` in reporting/).
// Three colleague evaluators are created as demo users with the evaluator role.
// Run from monitoring/:
//   COUCHDB_URL=... COUCHDB_USER=... COUCHDB_PASSWORD=... pnpm dlx tsx scripts/seed-showcase-couchdb.ts
// Remote CouchDB targets require ALLOW_REMOTE_DEMO_SEED=1.
// SHOWCASE_ONLY=rudo:ckd,nyasha:sickle_cell rewrites just those journeys'
// sessions and gaps, leaving everything else as it is.
import { createHash } from 'node:crypto'
import { counsellingTool, getToolBySlug } from '../app/data/evaluationItemData'
import type { ITool } from '../app/interfaces/ITool'
import type { INotAssessable } from '../app/interfaces/IItemScore'

const COUCHDB_URL = process.env.COUCHDB_URL ?? 'http://localhost:5984'
const COUCHDB_USER = process.env.COUCHDB_USER ?? ''
const COUCHDB_PASSWORD = process.env.COUCHDB_PASSWORD ?? ''
const DB_SESSIONS = process.env.COUCHDB_DB_SESSIONS ?? 'penplus_sessions'
const DB_GAPS = process.env.COUCHDB_DB_GAPS ?? 'penplus_gaps'
const DB_USERS = process.env.COUCHDB_DB_USERS ?? 'penplus_users'
const DB_DISTRICTS = process.env.COUCHDB_DB_DISTRICTS ?? 'penplus_districts'
const DEMO_DATASET = 'showcase-v1'
const HOUR = 60 * 60 * 1000

type Score = 1 | 2 | 3 | 4 | 5 | null
type CouchDoc = Record<string, unknown> & { _id: string, _rev?: string, demo?: boolean }
type Phase = 'initial_intensive' | 'ongoing' | 'supervision'

// ── Reference data ──────────────────────────────────────────────────────────
// Same ids and facility names as the districts already in the reporting
// database, so the sync updates those rows instead of adding new ones.
const DISTRICTS = [
  { _id: 'cc4f656ed6d56a102e88066b6a0007c1', district: 'Chiredzi', facilities: ['Chikombedzi', 'Chilonga', 'Hippo Valley', 'Musisinyane', 'Triangle'] },
  { _id: 'cc4f656ed6d56a102e88066b6a001fd6', district: 'Masvingo', facilities: ['Eastville', 'MPH', 'Mucheke', 'Rujeko'] },
  { _id: 'cc4f656ed6d56a102e88066b6a002c2d', district: 'Silveira', facilities: ['Bikita North', 'Bikita South', 'Nyika', 'Silveira Rural'] },
  { _id: 'cc4f656ed6d56a102e88066b6a004459', district: 'Zaka', facilities: ['Bere', 'Bota', 'Mashoko', 'Muroi', 'Musiso', 'Ndanga'] },
]

// `me` is the evaluator@penplus.local login used to present the field app.
const PRESENTER_ID = 'a0476c4f-f589-4407-abe3-457cf2a56a18'

// Colleagues: one who shares journeys with the presenter, one who has moved
// on and left a journey to pick up, and one in the other district.
const COLLEAGUES = {
  grace: { firstname: 'Grace', lastname: 'Mapfumo', district: 'Chiredzi', facility: 'Chilonga' },
  blessing: { firstname: 'Blessing', lastname: 'Nyathi', district: 'Chiredzi', facility: 'Triangle' },
  memory: { firstname: 'Memory', lastname: 'Chuma', district: 'Masvingo', facility: 'Rujeko' },
} as const
type EvaluatorKey = 'me' | keyof typeof COLLEAGUES

const MENTEES = {
  tendai: { firstname: 'Tendai', lastname: 'Moyo', profession: 'Nurse', district: 'Chiredzi', facility: 'Chikombedzi' },
  rudo: { firstname: 'Rudo', lastname: 'Chirwa', profession: 'Clinical Officer', district: 'Chiredzi', facility: 'Chilonga' },
  farai: { firstname: 'Farai', lastname: 'Ncube', profession: 'Medical Officer', district: 'Chiredzi', facility: 'Triangle' },
  chipo: { firstname: 'Chipo', lastname: 'Mutasa', profession: 'Nurse', district: 'Chiredzi', facility: 'Hippo Valley' },
  nyasha: { firstname: 'Nyasha', lastname: 'Dube', profession: 'Nurse', district: 'Chiredzi', facility: 'Musisinyane' },
  tatenda: { firstname: 'Tatenda', lastname: 'Sibanda', profession: 'Clinical Officer', district: 'Chiredzi', facility: 'Chikombedzi' },
  kudzai: { firstname: 'Kudzai', lastname: 'Banda', profession: 'Nurse', district: 'Masvingo', facility: 'Rujeko' },
  simba: { firstname: 'Simbarashe', lastname: 'Gumbo', profession: 'Clinical Officer', district: 'Masvingo', facility: 'Mucheke' },
  rumbi: { firstname: 'Rumbidzai', lastname: 'Zhou', profession: 'Nurse', district: 'Masvingo', facility: 'Eastville' },
  tafadzwa: { firstname: 'Tafadzwa', lastname: 'Mhlanga', profession: 'Midwife', district: 'Masvingo', facility: 'MPH' },
} as const
type MenteeKey = keyof typeof MENTEES

// ── Scenario description ────────────────────────────────────────────────────
interface GapSpec {
  round?: number
  description: string
  domains: Array<'knowledge' | 'critical_reasoning' | 'clinical_skills' | 'communication' | 'attitude'>
  covered: boolean | null
  timeline?: string
  supervisionLevel?: 'intensive_mentorship' | 'ongoing_mentorship' | 'independent_practice'
  resolved?: { on: string, note: string }
}

interface SessionSpec {
  date: string
  by: EvaluatorKey
  phase: Phase
  /** Round labels. Two or more split the session's items between rounds. */
  rounds?: string[]
  /** Item numbers this visit looked at. Achieved items are never re-rated. */
  cover?: (code: string) => boolean
  /** Score range for items rated this visit. */
  level: [number, number]
  /** Rate every remaining item 4 or 5 (the visit where competency is reached). */
  competent?: boolean
  /** Rate advanced items too when `competent` (fully competent). */
  includeAdvanced?: boolean
  /** Fixed scores for particular items, rated even if outside `cover`. */
  set?: Record<string, Score>
  notes?: Record<string, string>
  /** Items marked not assessable this visit (see NOT_ASSESSABLE.md). */
  notAssessable?: Record<string, INotAssessable>
  echo?: [performed: number, correct: number]
  gaps?: GapSpec[]
}

interface JourneySpec {
  mentee: MenteeKey
  tool: string
  sessions: SessionSpec[]
}

const range = (from: number, to: number, prefix: string) => (code: string) => {
  const n = Number(code.slice(prefix.length))
  return code.startsWith(prefix) && n >= from && n <= to
}

// One journey per feature the demo walks through.
const SCENARIOS: JourneySpec[] = [
  // 1. Tendai: one item away from Basic Competent (diabetes), plus a second tool just begun.
  { mentee: 'tendai', tool: 'diabetes', sessions: [
    { date: '2026-07-09', by: 'me', phase: 'initial_intensive', level: [1, 3] },
    { date: '2026-08-06', by: 'me', phase: 'initial_intensive', level: [2, 4] },
    { date: '2026-09-10', by: 'me', phase: 'ongoing', level: [3, 5], set: { D23: 2 } },
    { date: '2026-10-15', by: 'me', phase: 'ongoing', level: [4, 5], competent: true, set: { D23: 3 },
      notes: { D23: 'Adjusts insulin correctly but still unsure of sick-day rules' },
      gaps: [{ description: 'Sick-day rules for insulin: when to reduce, hold or continue doses', domains: ['knowledge', 'critical_reasoning'], covered: false, timeline: 'Next visit', supervisionLevel: 'ongoing_mentorship' }] },
  ] },
  { mentee: 'tendai', tool: 'hypertension', sessions: [
    { date: '2026-10-15', by: 'me', phase: 'initial_intensive', level: [2, 3] },
  ] },

  // 2. Rudo: CKD Basic Competent (advanced H15 still low), hypertension Fully Competent.
  { mentee: 'rudo', tool: 'ckd', sessions: [
    { date: '2026-06-11', by: 'grace', phase: 'initial_intensive', level: [1, 3],
      gaps: [{ description: 'Does not stage CKD from eGFR and urine protein', domains: ['knowledge'], covered: true, supervisionLevel: 'intensive_mentorship', resolved: { on: '2026-08-13', note: 'Staged three patients correctly during the clinic round' } }] },
    { date: '2026-07-16', by: 'grace', phase: 'initial_intensive', level: [2, 4] },
    { date: '2026-08-13', by: 'grace', phase: 'ongoing', level: [3, 5] },
    { date: '2026-09-17', by: 'grace', phase: 'supervision', level: [4, 5], competent: true,
      notAssessable: { H15: { reason: 'referral_unavailable', note: 'No palliative care service or dialysis referral pathway in the district' } } },
  ] },
  { mentee: 'rudo', tool: 'hypertension', sessions: [
    { date: '2026-07-16', by: 'grace', phase: 'initial_intensive', level: [2, 3] },
    { date: '2026-08-13', by: 'grace', phase: 'ongoing', level: [3, 4] },
    { date: '2026-09-17', by: 'grace', phase: 'supervision', level: [4, 5], competent: true },
  ] },

  // 3. Farai: Heart diseases Fully Competent, advanced items included, two evaluators.
  { mentee: 'farai', tool: 'cardiac', sessions: [
    { date: '2026-05-21', by: 'grace', phase: 'initial_intensive', level: [1, 3] },
    { date: '2026-06-25', by: 'grace', phase: 'initial_intensive', level: [2, 3] },
    { date: '2026-07-23', by: 'me', phase: 'ongoing', level: [2, 4] },
    { date: '2026-08-27', by: 'me', phase: 'ongoing', level: [3, 5] },
    { date: '2026-09-24', by: 'me', phase: 'supervision', level: [4, 5], competent: true, includeAdvanced: true },
  ] },

  // 4. Chipo: liver, different competencies in each session and round (grid, heatmap).
  { mentee: 'chipo', tool: 'liver', sessions: [
    { date: '2026-07-07', by: 'me', phase: 'initial_intensive', rounds: ['Ward round', 'Outpatient clinic'], cover: range(1, 8, 'L'), level: [1, 3],
      notes: { L4: 'Missed shifting dullness on two patients' },
      gaps: [{ round: 1, description: 'Does not yet recognise signs of hepatic encephalopathy and its triggers', domains: ['knowledge', 'clinical_skills'], covered: false, timeline: 'Next visit', supervisionLevel: 'intensive_mentorship', resolved: { on: '2026-10-06', note: 'Identified encephalopathy and its triggers correctly on the ward round' } }] },
    { date: '2026-08-04', by: 'me', phase: 'initial_intensive', rounds: ['Ward round', 'Outpatient clinic'], cover: range(1, 12, 'L'), level: [2, 4],
      gaps: [{ round: 2, description: 'Unsure of indications for paracentesis in refractory ascites', domains: ['knowledge', 'critical_reasoning'], covered: true, supervisionLevel: 'ongoing_mentorship' }] },
    { date: '2026-09-01', by: 'me', phase: 'ongoing', rounds: ['Ward round'], cover: range(3, 15, 'L'), level: [2, 4] },
    { date: '2026-10-06', by: 'me', phase: 'ongoing', rounds: ['Ward round', 'Outpatient clinic'], cover: range(5, 15, 'L'), level: [3, 5],
      gaps: [{ round: 1, description: 'Diuretic titration not linked to electrolyte and kidney function monitoring', domains: ['clinical_skills'], covered: false, timeline: 'Within 4 weeks', supervisionLevel: 'ongoing_mentorship' }] },
  ] },

  // 5. Nyasha: sickle cell, started by Blessing, who has moved on. Items S31+ never
  //    evaluated and S5 not rated since the first visit: a journey to pick up.
  { mentee: 'nyasha', tool: 'sickle_cell', sessions: [
    { date: '2026-06-18', by: 'blessing', phase: 'initial_intensive', cover: range(1, 30, 'S'), level: [1, 3], set: { S5: 2 } },
    { date: '2026-07-23', by: 'blessing', phase: 'initial_intensive', cover: c => range(1, 30, 'S')(c) && c !== 'S5', level: [2, 4],
      gaps: [{ description: 'Hydroxyurea dosing and blood count monitoring not yet understood', domains: ['knowledge'], covered: false, timeline: 'Next visit', supervisionLevel: 'intensive_mentorship' }] },
    { date: '2026-08-20', by: 'blessing', phase: 'ongoing', cover: c => range(1, 30, 'S')(c) && c !== 'S5', level: [3, 5],
      notAssessable: {
        S27: { reason: 'medicine_unavailable', note: 'No oral morphine stocked at Musisinyane' },
        S28: { reason: 'medicine_unavailable', note: 'No oral morphine stocked at Musisinyane' },
        S2: { reason: 'lab_test_unavailable', note: 'Hb electrophoresis only at the provincial hospital' },
      } },
  ] },

  // 6. Tatenda: respiratory, required items never evaluated and items not rated recently.
  { mentee: 'tatenda', tool: 'respiratory', sessions: [
    { date: '2026-07-02', by: 'me', phase: 'initial_intensive', cover: range(1, 18, 'R'), level: [1, 3] },
    { date: '2026-08-06', by: 'me', phase: 'initial_intensive', cover: range(1, 10, 'R'), level: [2, 4] },
    { date: '2026-09-03', by: 'me', phase: 'ongoing', cover: range(1, 10, 'R'), level: [3, 5],
      gaps: [{ description: 'Inhaler technique teaching is rushed; patients not asked to demonstrate', domains: ['communication', 'clinical_skills'], covered: true, supervisionLevel: 'ongoing_mentorship' }] },
    { date: '2026-10-08', by: 'me', phase: 'ongoing', cover: range(1, 10, 'R'), level: [3, 5] },
  ] },

  // 7. Kudzai: echocardiogram with activity counts and diagnostic accuracy. No counselling.
  { mentee: 'kudzai', tool: 'echo', sessions: [
    { date: '2026-07-14', by: 'memory', phase: 'initial_intensive', level: [1, 3], set: { E5: 2 }, echo: [3, 1] },
    { date: '2026-08-11', by: 'memory', phase: 'initial_intensive', level: [2, 4], set: { E5: 2 }, echo: [5, 3] },
    { date: '2026-09-15', by: 'memory', phase: 'ongoing', level: [3, 5], set: { E5: 3 }, echo: [6, 5] },
    { date: '2026-10-13', by: 'memory', phase: 'ongoing', level: [4, 5], set: { E5: 3 }, echo: [8, 7],
      notes: { E5: 'Measurements correct but views still need repeating' } },
  ] },

  // 8. Simbarashe: struggling. Low scores that rise and fall, open gaps.
  { mentee: 'simba', tool: 'epilepsy', sessions: [
    { date: '2026-06-09', by: 'memory', phase: 'initial_intensive', level: [1, 2] },
    { date: '2026-07-07', by: 'memory', phase: 'initial_intensive', level: [1, 3],
      gaps: [{ description: 'Cannot describe first-line management of status epilepticus', domains: ['knowledge', 'critical_reasoning'], covered: true, supervisionLevel: 'intensive_mentorship' }] },
    { date: '2026-08-04', by: 'memory', phase: 'initial_intensive', level: [1, 3], set: { E1: 3, E2: 3 } },
    { date: '2026-09-08', by: 'me', phase: 'initial_intensive', level: [1, 2], set: { E1: 2, E2: 1 },
      notes: { E2: 'Seizure history taken without witness account' },
      gaps: [{ description: 'Seizure history does not include a witness account or triggers', domains: ['clinical_skills', 'communication'], covered: false, timeline: 'Within 2 weeks', supervisionLevel: 'intensive_mentorship' }] },
    { date: '2026-10-13', by: 'me', phase: 'initial_intensive', level: [1, 3],
      gaps: [{ description: 'Does not counsel on medication adherence and driving safety', domains: ['communication', 'attitude'], covered: null, supervisionLevel: 'intensive_mentorship' }] },
  ] },
  { mentee: 'simba', tool: 'diabetes', sessions: [
    { date: '2026-08-04', by: 'memory', phase: 'initial_intensive', level: [1, 2] },
    { date: '2026-09-08', by: 'me', phase: 'initial_intensive', level: [1, 3] },
  ] },

  // 9. Rumbidzai: palliative, gaps found in different rounds, some resolved. Diabetes completed.
  { mentee: 'rumbi', tool: 'palliative', sessions: [
    { date: '2026-07-21', by: 'memory', phase: 'initial_intensive', rounds: ['Ward round', 'Home visit'], level: [1, 3],
      gaps: [
        { round: 1, description: 'Pain assessment does not use a pain scale', domains: ['clinical_skills'], covered: true, supervisionLevel: 'intensive_mentorship', resolved: { on: '2026-08-18', note: 'Used the pain scale consistently on the ward round' } },
        { round: 2, description: 'Avoids discussing prognosis with the family', domains: ['communication', 'attitude'], covered: false, timeline: 'Next home visit', supervisionLevel: 'intensive_mentorship' },
      ] },
    { date: '2026-08-18', by: 'memory', phase: 'ongoing', rounds: ['Ward round', 'Home visit'], level: [2, 4],
      gaps: [{ round: 2, description: 'Morphine breakthrough dosing calculated incorrectly', domains: ['knowledge', 'clinical_skills'], covered: true, supervisionLevel: 'ongoing_mentorship', resolved: { on: '2026-09-29', note: 'Calculated breakthrough doses correctly for two patients' } }] },
    { date: '2026-09-29', by: 'me', phase: 'ongoing', rounds: ['Ward round', 'Home visit'], level: [3, 4],
      gaps: [{ round: 1, description: 'Bowel care not started alongside opioids', domains: ['clinical_skills'], covered: false, timeline: 'Within 2 weeks', supervisionLevel: 'ongoing_mentorship' }] },
  ] },
  { mentee: 'rumbi', tool: 'diabetes', sessions: [
    { date: '2026-05-12', by: 'memory', phase: 'initial_intensive', level: [2, 3] },
    { date: '2026-06-16', by: 'memory', phase: 'ongoing', level: [3, 4] },
    { date: '2026-07-21', by: 'memory', phase: 'supervision', level: [4, 5], competent: true },
  ] },

  // 10. Tafadzwa: two tools, first visit for each.
  { mentee: 'tafadzwa', tool: 'hypertension', sessions: [
    { date: '2026-10-20', by: 'me', phase: 'initial_intensive', level: [1, 3] },
  ] },
  { mentee: 'tafadzwa', tool: 'ckd', sessions: [
    { date: '2026-10-20', by: 'me', phase: 'initial_intensive', cover: range(1, 12, 'H'), level: [1, 3] },
  ] },
]

// ── Helpers ─────────────────────────────────────────────────────────────────
function stableInt(seed: string, max: number): number {
  return createHash('sha256').update(seed).digest().readUInt32BE(0) % max
}

function stableId(seed: string): string {
  const hash = createHash('sha256').update(`penplus-showcase:${seed}`).digest('hex').slice(0, 32)
  const variant = ((Number.parseInt(hash[16]!, 16) & 0x3) | 0x8).toString(16)
  return `${hash.slice(0, 8)}-${hash.slice(8, 12)}-5${hash.slice(13, 16)}-${variant}${hash.slice(17, 20)}-${hash.slice(20)}`
}

function pick(seed: string, [min, max]: [number, number]): Score {
  return (min + stableInt(seed, max - min + 1)) as Score
}

const dayStart = (date: string) => Date.parse(`${date}T08:00:00Z`)

function authHeader(): string {
  return `Basic ${Buffer.from(`${COUCHDB_USER}:${COUCHDB_PASSWORD}`).toString('base64')}`
}

function baseUrl(): string {
  const url = new URL(COUCHDB_URL)
  if (!['localhost', '127.0.0.1', '::1'].includes(url.hostname) && process.env.ALLOW_REMOTE_DEMO_SEED !== '1') {
    throw new Error('Refusing to seed a remote CouchDB. Set ALLOW_REMOTE_DEMO_SEED=1 only for an approved test server.')
  }
  return url.toString().replace(/\/$/, '')
}

async function couch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${baseUrl()}${path}`, {
    ...init,
    headers: { 'Content-Type': 'application/json', Authorization: authHeader() },
  })
  if (!res.ok) throw new Error(`${path}: ${res.status} ${await res.text()}`)
  return await res.json() as T
}

async function upsert(db: string, docs: CouchDoc[], { allowNonDemo = false } = {}): Promise<void> {
  const { rows } = await couch<{ rows: Array<{ id: string, doc?: CouchDoc }> }>(`/${db}/_all_docs?include_docs=true`, {
    method: 'POST',
    body: JSON.stringify({ keys: docs.map(doc => doc._id) }),
  })
  const existing = new Map(rows.filter(row => row.doc).map(row => [row.id, row.doc!]))
  for (const [id, doc] of existing) {
    if (!allowNonDemo && doc.demo !== true) throw new Error(`Refusing to overwrite non-demo document ${id} in ${db}.`)
  }
  const results = await couch<Array<{ id: string, error?: string, reason?: string }>>(`/${db}/_bulk_docs`, {
    method: 'POST',
    body: JSON.stringify({ docs: docs.map(doc => existing.has(doc._id) ? { ...doc, _rev: existing.get(doc._id)!._rev } : doc) }),
  })
  const errors = results.filter(result => result.error)
  if (errors.length) throw new Error(`${db}: ${errors.map(e => `${e.id} ${e.error} ${e.reason ?? ''}`).join('; ')}`)
  console.log(`  ${db}: ${docs.length} document(s)`)
}

// ── Builders ────────────────────────────────────────────────────────────────
interface UserRef { id: string, firstname: string, lastname: string, username: string, profession?: string, facilityId?: string, districtId?: string }

function buildJourney(spec: JourneySpec, mentee: CouchDoc, evaluators: Record<EvaluatorKey, UserRef>) {
  const tool = getToolBySlug(spec.tool) as ITool
  const groupId = `${mentee._id}::${tool.slug}`
  const withCounselling = tool.includesCounselling !== false
  const latest = new Map<string, Score>()
  const menteeRef: UserRef = {
    id: mentee._id,
    firstname: mentee.firstname as string,
    lastname: mentee.lastname as string,
    username: mentee.username as string,
    profession: mentee.profession as string,
    facilityId: mentee.facility as string,
    districtId: mentee.district as string,
  }
  const sessions: CouchDoc[] = []
  const gaps: CouchDoc[] = []

  spec.sessions.forEach((s, sessionIndex) => {
    const start = dayStart(s.date)
    const sessionId = `session::${groupId}::${start}`
    const seed = `${groupId}:${sessionIndex}`

    // Items rated this visit: those in scope that aren't achieved yet, plus fixed ones.
    const due = tool.items.filter((item) => {
      if (s.notAssessable && item.number in s.notAssessable) return true
      if (s.set && item.number in s.set) return true
      if (s.cover && !s.cover(item.number)) return false
      if ((latest.get(item.slug) ?? 0) >= 4) return false
      // Basic Competent visits leave advanced items as they were.
      return !(s.competent && item.isAdvanced && !s.includeAdvanced)
    })
    const scoreFor = (item: (typeof tool.items)[number]): Score => {
      if (s.set && item.number in s.set) return s.set[item.number]!
      if (s.competent && (!item.isAdvanced || s.includeAdvanced)) return pick(`${seed}:${item.slug}`, [4, 5])
      // An occasional N/A on early visits.
      if (!s.competent && stableInt(`${seed}:${item.slug}:na`, 30) === 0) return null
      return pick(`${seed}:${item.slug}`, s.level)
    }

    const labels = s.rounds?.length ? s.rounds : ['Round 1']
    const perRound = Math.ceil(due.length / labels.length)
    const rounds = labels.map((label, roundIndex) => {
      const items = due.slice(roundIndex * perRound, (roundIndex + 1) * perRound)
      const itemScores = items.map((item) => {
        const mark = s.notAssessable?.[item.number]
        if (mark) return { itemSlug: item.slug, menteeScore: null, notAssessable: mark }
        const menteeScore = scoreFor(item)
        const note = s.notes?.[item.number]
        return { itemSlug: item.slug, menteeScore, ...(note && menteeScore !== null ? { notes: note } : {}) }
      })
      // In a two-round visit, the second round re-rates the first item seen
      // that morning, one point higher: a same-day improvement.
      if (roundIndex === 1 && labels.length > 1 && due[0] && !s.notAssessable?.[due[0].number]) {
        const first = scoreFor(due[0])
        if (first !== null && first < 5) itemScores.unshift({ itemSlug: due[0].slug, menteeScore: (first + 1) as Score })
      }
      const counsellingScores = withCounselling && roundIndex === 0
        ? counsellingTool.items.map(item => ({
            itemSlug: item.slug,
            menteeScore: pick(`${seed}:${item.slug}`, [Math.min(s.level[0] + 1, 4), Math.min(s.level[1] + 1, 5)] as [number, number]),
          }))
        : []
      const startedAt = start + roundIndex * 3 * HOUR
      return {
        roundId: `${sessionId}::r${roundIndex + 1}`,
        label,
        itemScores,
        counsellingScores,
        selectedItemSlugs: [...new Set(itemScores.map(score => score.itemSlug))],
        selectedCounsellingSlugs: counsellingScores.map(score => score.itemSlug),
        startedAt,
        completedAt: startedAt + 2 * HOUR,
      }
    })

    // Session-level summary: each item's latest non-null score across its rounds.
    const summarise = (key: 'itemScores' | 'counsellingScores') => {
      const map = new Map<string, { itemSlug: string, menteeScore: Score }>()
      for (const round of rounds) {
        for (const score of round[key]) {
          if (score.menteeScore !== null || !map.has(score.itemSlug)) map.set(score.itemSlug, score)
        }
      }
      return [...map.values()]
    }
    const itemScores = summarise('itemScores')
    for (const score of itemScores) {
      if (score.menteeScore !== null) latest.set(score.itemSlug, score.menteeScore)
    }

    const completedAt = rounds.at(-1)!.completedAt
    sessions.push({
      _id: sessionId,
      type: 'session',
      evaluationGroupId: groupId,
      mentee: menteeRef,
      evaluator: evaluators[s.by],
      toolSlug: tool.slug,
      evalDate: start,
      facilityId: menteeRef.facilityId,
      districtId: menteeRef.districtId,
      itemScores,
      counsellingScores: summarise('counsellingScores'),
      rounds,
      selectedItemSlugs: [...new Set(rounds.flatMap(round => round.selectedItemSlugs))],
      selectedCounsellingSlugs: [...new Set(rounds.flatMap(round => round.selectedCounsellingSlugs))],
      ...(s.echo ? { echocardiogramsPerformed: s.echo[0], echocardiogramsCorrectDiagnosis: s.echo[1] } : {}),
      phase: s.phase,
      roundOfDay: 1,
      syncStatus: 'synced',
      syncedAt: completedAt,
      createdAt: completedAt,
      updatedAt: completedAt,
      demo: true,
      demoDataset: DEMO_DATASET,
    })

    for (const [gapIndex, gap] of (s.gaps ?? []).entries()) {
      const roundIndex = Math.min(gap.round ?? 1, rounds.length)
      const round = rounds[roundIndex - 1]!
      const resolvedAt = gap.resolved ? dayStart(gap.resolved.on) + 4 * HOUR : undefined
      gaps.push({
        _id: `gap::${groupId}::${stableId(`${seed}:gap:${gapIndex}`)}`,
        type: 'gap',
        evaluationGroupId: groupId,
        menteeId: mentee._id,
        evaluatorId: evaluators[s.by].id,
        toolSlug: tool.slug,
        identifiedAt: round.completedAt,
        sessionId,
        roundId: round.roundId,
        roundIndex,
        description: gap.description,
        domains: gap.domains,
        coveredInMentorship: gap.covered,
        coveringLater: gap.covered === false,
        ...(gap.timeline ? { timeline: gap.timeline } : {}),
        ...(gap.supervisionLevel ? { supervisionLevel: gap.supervisionLevel } : {}),
        ...(gap.resolved ? { resolvedAt, resolutionNote: gap.resolved.note } : {}),
        syncStatus: 'synced',
        syncedAt: resolvedAt ?? round.completedAt,
        createdAt: round.completedAt,
        updatedAt: resolvedAt ?? round.completedAt,
        demo: true,
        demoDataset: DEMO_DATASET,
      })
    }
  })

  return { sessions, gaps }
}

async function main(): Promise<void> {
  console.log(`Seeding showcase data into CouchDB at ${new URL(COUCHDB_URL).hostname}`)

  const now = Date.now()
  const colleagueDocs: CouchDoc[] = Object.entries(COLLEAGUES).map(([key, c]) => ({
    _id: stableId(`evaluator:${key}`),
    type: 'user',
    ...c,
    username: `${c.firstname}.${c.lastname}`.toLowerCase(),
    profession: 'Clinical Mentor',
    role: 'evaluator',
    syncStatus: 'synced',
    syncedAt: now,
    createdAt: now,
    updatedAt: now,
    demo: true,
    demoDataset: DEMO_DATASET,
  }))
  const presenter = await couch<CouchDoc>(`/${DB_USERS}/${PRESENTER_ID}`).catch(() => {
    throw new Error(`Evaluator ${PRESENTER_ID} is not in ${DB_USERS}. Run php artisan couchdb:push-users first.`)
  })
  const toRef = (doc: CouchDoc): UserRef => ({
    id: doc._id,
    firstname: doc.firstname as string,
    lastname: doc.lastname as string,
    username: (doc.username as string) ?? doc._id,
    ...(doc.profession ? { profession: doc.profession as string } : {}),
    ...(doc.facility ? { facilityId: doc.facility as string } : {}),
    ...(doc.district ? { districtId: doc.district as string } : {}),
  })
  const evaluators = {
    me: toRef(presenter),
    ...Object.fromEntries(Object.keys(COLLEAGUES).map((key, i) => [key, toRef(colleagueDocs[i]!)])),
  } as Record<EvaluatorKey, UserRef>

  const mentees = Object.fromEntries(Object.entries(MENTEES).map(([key, m]) => [key, {
    _id: stableId(`mentee:${key}`),
    type: 'user',
    ...m,
    username: `${m.firstname}.${m.lastname}`.toLowerCase(),
    syncStatus: 'synced',
    syncedAt: now,
    createdAt: now,
    updatedAt: now,
    demo: true,
    demoDataset: DEMO_DATASET,
  }])) as unknown as Record<MenteeKey, CouchDoc>

  const only = (process.env.SHOWCASE_ONLY ?? '').split(',').map(v => v.trim()).filter(Boolean)
  const sessions: CouchDoc[] = []
  const gaps: CouchDoc[] = []
  for (const spec of SCENARIOS.filter(spec => !only.length || only.includes(`${spec.mentee}:${spec.tool}`))) {
    const journey = buildJourney(spec, mentees[spec.mentee], evaluators)
    sessions.push(...journey.sessions)
    gaps.push(...journey.gaps)
  }

  if (!only.length) {
    await upsert(DB_DISTRICTS, DISTRICTS, { allowNonDemo: true })
    await upsert(DB_USERS, [...colleagueDocs, ...Object.values(mentees)])
  }
  await upsert(DB_SESSIONS, sessions)
  await upsert(DB_GAPS, gaps)

  console.log(`Done: ${Object.keys(mentees).length} mentees, ${SCENARIOS.length} journeys, ${sessions.length} sessions, ${gaps.length} gaps.`)
  console.log('Next: run `php artisan sync:couchdb` from reporting/ to import the data into MySQL.')
}

main().catch((error: unknown) => {
  console.error(error)
  process.exit(1)
})
