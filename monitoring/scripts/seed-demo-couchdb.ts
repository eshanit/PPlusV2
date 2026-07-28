// Seeds ~50 realistic session docs (plus mentees, evaluators, gaps) directly into
// CouchDB so both the monitoring app and reporting/Filament show demo data after
// the next `php artisan sync:couchdb`. Everything is tagged `demo: true` and lives
// under a dedicated "Demo District" / "Demo Facility" so it can be cleanly removed
// with clear-demo-couchdb.ts before real client data starts flowing in.
//
// Run from the monitoring/ directory:
//   COUCHDB_URL=... COUCHDB_USER=... COUCHDB_PASSWORD=... pnpm dlx tsx scripts/seed-demo-couchdb.ts
import { counsellingTool, evaluationTools } from '../app/data/evaluationItemData'

const COUCHDB_URL = process.env.COUCHDB_URL ?? 'http://localhost:5984'
const COUCHDB_USER = process.env.COUCHDB_USER ?? ''
const COUCHDB_PASSWORD = process.env.COUCHDB_PASSWORD ?? ''
const DB_SESSIONS = process.env.COUCHDB_DB_SESSIONS ?? 'penplus_sessions'
const DB_GAPS = process.env.COUCHDB_DB_GAPS ?? 'penplus_gaps'
const DB_USERS = process.env.COUCHDB_DB_USERS ?? 'penplus_users'
const DB_DISTRICTS = process.env.COUCHDB_DB_DISTRICTS ?? 'penplus_districts'

const DEMO_DISTRICT_ID = 'demo-district'
const DEMO_DISTRICT_NAME = 'Demo District'
const DEMO_FACILITY_NAME = 'Demo Facility'

const now = Date.now()
const DAY = 24 * 60 * 60 * 1000

function authHeader(): string {
  return 'Basic ' + Buffer.from(`${COUCHDB_USER}:${COUCHDB_PASSWORD}`).toString('base64')
}

async function bulkDocs(dbName: string, docs: Record<string, unknown>[]): Promise<void> {
  if (docs.length === 0) return

  const res = await fetch(`${COUCHDB_URL.replace(/\/$/, '')}/${dbName}/_bulk_docs`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: authHeader() },
    body: JSON.stringify({ docs }),
  })

  if (!res.ok) {
    throw new Error(`_bulk_docs failed for ${dbName}: ${res.status} ${await res.text()}`)
  }

  const results = await res.json() as Array<{ id: string, error?: string, reason?: string }>
  const errors = results.filter(r => r.error)

  if (errors.length > 0) {
    console.error(`  ${errors.length} error(s) in ${dbName}:`, errors.slice(0, 5))
  }

  console.log(`  ${dbName}: inserted ${docs.length - errors.length}/${docs.length}`)
}

function randInt(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min
}

function pick<T>(arr: readonly T[]): T {
  return arr[randInt(0, arr.length - 1)]
}

const MENTEE_NAMES: Array<[string, string, string]> = [
  ['Tendai', 'Moyo', 'Nurse'],
  ['Chipo', 'Ndlovu', 'Clinical Officer'],
  ['Farai', 'Sibanda', 'Nurse'],
  ['Rumbidzai', 'Chikafu', 'Medical Officer'],
  ['Tapiwa', 'Gumbo', 'Nurse'],
  ['Nyasha', 'Mutasa', 'Clinical Officer'],
  ['Blessing', 'Chirwa', 'Nurse'],
  ['Kudzai', 'Mafunga', 'Medical Officer'],
  ['Tafadzwa', 'Marufu', 'Nurse'],
  ['Ropafadzo', 'Zhou', 'Clinical Officer'],
  ['Anesu', 'Chinyerere', 'Nurse'],
  ['Simbarashe', 'Dube', 'Medical Officer'],
]

const EVALUATOR_NAMES: Array<[string, string]> = [
  ['Grace', 'Mhlanga'],
  ['Tonderai', 'Mapfumo'],
]

type Outcome = 'competent' | 'fully_competent' | 'in_progress'

interface Journey {
  menteeIdx: number
  toolSlug: string
  sessions: number
  outcome: Outcome
}

// 15 journeys summing to 50 sessions, spread across 12 mentees and 4 tools,
// mixing in-progress / basic-competent / fully-competent outcomes.
const JOURNEYS: Journey[] = [
  { menteeIdx: 0, toolSlug: 'diabetes', sessions: 4, outcome: 'fully_competent' },
  { menteeIdx: 1, toolSlug: 'hypertension', sessions: 3, outcome: 'competent' },
  { menteeIdx: 2, toolSlug: 'cardiac', sessions: 5, outcome: 'fully_competent' },
  { menteeIdx: 3, toolSlug: 'respiratory', sessions: 2, outcome: 'in_progress' },
  { menteeIdx: 4, toolSlug: 'diabetes', sessions: 3, outcome: 'in_progress' },
  { menteeIdx: 5, toolSlug: 'hypertension', sessions: 4, outcome: 'competent' },
  { menteeIdx: 6, toolSlug: 'cardiac', sessions: 3, outcome: 'in_progress' },
  { menteeIdx: 7, toolSlug: 'respiratory', sessions: 5, outcome: 'competent' },
  { menteeIdx: 8, toolSlug: 'diabetes', sessions: 2, outcome: 'in_progress' },
  { menteeIdx: 9, toolSlug: 'hypertension', sessions: 4, outcome: 'competent' },
  { menteeIdx: 10, toolSlug: 'cardiac', sessions: 3, outcome: 'competent' },
  { menteeIdx: 11, toolSlug: 'respiratory', sessions: 2, outcome: 'in_progress' },
  { menteeIdx: 0, toolSlug: 'hypertension', sessions: 4, outcome: 'in_progress' },
  { menteeIdx: 3, toolSlug: 'diabetes', sessions: 3, outcome: 'competent' },
  { menteeIdx: 7, toolSlug: 'cardiac', sessions: 3, outcome: 'in_progress' },
]

const GAP_DESCRIPTIONS = [
  'Struggles to interpret lab results independently.',
  'Inconsistent dosage titration during follow-up visits.',
  'Needs support explaining the treatment plan to patients in local language.',
  'Documentation of vitals is incomplete in several visits.',
]

const GAP_DOMAINS = ['knowledge', 'clinical_skills', 'critical_reasoning', 'communication'] as const

async function main(): Promise<void> {
  console.log('Seeding demo data into', COUCHDB_URL)

  await bulkDocs(DB_DISTRICTS, [{
    _id: DEMO_DISTRICT_ID,
    district: DEMO_DISTRICT_NAME,
    facilities: [DEMO_FACILITY_NAME],
    demo: true,
  }])

  const evaluators = EVALUATOR_NAMES.map(([firstname, lastname], i) => ({
    _id: `demo-evaluator-${i + 1}`,
    type: 'user',
    firstname,
    lastname,
    username: `demo_mentor_${i + 1}`,
    profession: 'Clinical Mentor',
    facility: DEMO_FACILITY_NAME,
    district: DEMO_DISTRICT_NAME,
    syncStatus: 'synced',
    syncedAt: now,
    createdAt: now,
    updatedAt: now,
    demo: true,
  }))

  const mentees = MENTEE_NAMES.map(([firstname, lastname, profession], i) => ({
    _id: `demo-mentee-${i + 1}`,
    type: 'user',
    firstname,
    lastname,
    username: `demo_mentee_${i + 1}`,
    profession,
    facility: DEMO_FACILITY_NAME,
    district: DEMO_DISTRICT_NAME,
    syncStatus: 'synced',
    syncedAt: now,
    createdAt: now,
    updatedAt: now,
    demo: true,
  }))

  await bulkDocs(DB_USERS, [...evaluators, ...mentees])

  const allSessions: Record<string, unknown>[] = []
  const allGaps: Record<string, unknown>[] = []
  let gapCount = 0

  for (const journey of JOURNEYS) {
    const mentee = mentees[journey.menteeIdx]
    const evaluator = pick(evaluators)
    const tool = evaluationTools.find(t => t.slug === journey.toolSlug)

    if (!tool) {
      throw new Error(`Unknown tool slug: ${journey.toolSlug}`)
    }

    const evaluationGroupId = `${mentee._id}::${tool.slug}`
    const sessionCount = journey.sessions
    const lastSessionDaysAgo = journey.outcome === 'in_progress' ? randInt(3, 14) : randInt(20, 70)
    const spacingDays = 18

    const menteeRef = {
      id: mentee._id,
      firstname: mentee.firstname,
      lastname: mentee.lastname,
      username: mentee.username,
      facilityId: DEMO_FACILITY_NAME,
      districtId: DEMO_DISTRICT_NAME,
    }
    const evaluatorRef = {
      id: evaluator._id,
      firstname: evaluator.firstname,
      lastname: evaluator.lastname,
      username: evaluator.username,
      facilityId: DEMO_FACILITY_NAME,
      districtId: DEMO_DISTRICT_NAME,
    }

    for (let i = 0; i < sessionCount; i++) {
      const isLast = i === sessionCount - 1
      const progress = (i + 1) / sessionCount
      const evalDate = now - (lastSessionDaysAgo + (sessionCount - 1 - i) * spacingDays) * DAY

      const itemScores = tool.items.map((item) => {
        let score: number

        if (isLast && journey.outcome !== 'in_progress') {
          score = (item.isAdvanced && journey.outcome !== 'fully_competent')
            ? randInt(2, 4)
            : randInt(4, 5)
        } else if (isLast) {
          // in_progress: deliberately leave some items short so the journey stays open
          score = item.isAdvanced ? randInt(1, 3) : (Math.random() < 0.25 ? randInt(2, 3) : randInt(3, 5))
        } else {
          const base = 2 + progress * 2.5
          score = Math.max(1, Math.min(5, Math.round(base + randInt(-1, 1))))
        }

        return { itemSlug: item.slug, menteeScore: score }
      })

      const counsellingScores = counsellingTool.items.map((item) => {
        const base = 3 + progress * 2
        const score = Math.max(1, Math.min(5, Math.round(base + randInt(-1, 1))))
        return { itemSlug: item.slug, menteeScore: score }
      })

      const phase = i === 0
        ? 'initial_intensive'
        : (isLast && journey.outcome !== 'in_progress' ? 'supervision' : 'ongoing')

      allSessions.push({
        _id: `session::${evaluationGroupId}::${evalDate}`,
        type: 'session',
        evaluationGroupId,
        mentee: menteeRef,
        evaluator: evaluatorRef,
        toolSlug: tool.slug,
        evalDate,
        facilityId: DEMO_FACILITY_NAME,
        districtId: DEMO_DISTRICT_NAME,
        itemScores,
        counsellingScores,
        phase,
        notes: `Mentorship session ${i + 1} for ${tool.label}.`,
        syncStatus: 'synced',
        syncedAt: now,
        createdAt: evalDate,
        updatedAt: evalDate,
        demo: true,
      })
    }

    if (journey.outcome !== 'fully_competent' && gapCount < 8 && Math.random() < 0.6) {
      gapCount++
      const resolved = Math.random() < 0.4
      const identifiedAt = now - randInt(10, 40) * DAY

      allGaps.push({
        _id: `gap::${evaluationGroupId}::${now - gapCount}`,
        type: 'gap',
        evaluationGroupId,
        menteeId: mentee._id,
        evaluatorId: evaluator._id,
        toolSlug: tool.slug,
        identifiedAt,
        description: pick(GAP_DESCRIPTIONS),
        domains: [pick(GAP_DOMAINS)],
        coveredInMentorship: resolved ? true : null,
        coveringLater: !resolved,
        timeline: resolved ? undefined : 'Next 2 mentorship visits',
        supervisionLevel: 'ongoing_mentorship',
        resolutionNote: resolved ? 'Addressed through targeted case review.' : undefined,
        resolvedAt: resolved ? now - randInt(1, 9) * DAY : undefined,
        syncStatus: 'synced',
        syncedAt: now,
        createdAt: identifiedAt,
        updatedAt: now,
        demo: true,
      })
    }
  }

  await bulkDocs(DB_SESSIONS, allSessions)
  await bulkDocs(DB_GAPS, allGaps)

  console.log(`Done. ${allSessions.length} sessions, ${allGaps.length} gaps, ${mentees.length} mentees, ${evaluators.length} evaluators.`)
  console.log('Next: run `php artisan sync:couchdb` in reporting/ to pull this into MySQL.')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
