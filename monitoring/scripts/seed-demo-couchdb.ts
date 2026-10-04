// Seeds deterministic synthetic reporting data into a development CouchDB.
// The docs are tagged `demo: true` so clear-demo-couchdb.ts can remove them.
//
// Run from monitoring/:
//   pnpm dlx tsx scripts/seed-demo-couchdb.ts
// Remote CouchDB targets require ALLOW_REMOTE_DEMO_SEED=1.
import { createHash } from 'node:crypto'
import { counsellingTool, evaluationTools } from '../app/data/evaluationItemData'

const COUCHDB_URL = process.env.COUCHDB_URL ?? 'http://localhost:5984'
const COUCHDB_USER = process.env.COUCHDB_USER ?? ''
const COUCHDB_PASSWORD = process.env.COUCHDB_PASSWORD ?? ''
const DB_SESSIONS = process.env.COUCHDB_DB_SESSIONS ?? 'penplus_sessions'
const DB_GAPS = process.env.COUCHDB_DB_GAPS ?? 'penplus_gaps'
const DB_USERS = process.env.COUCHDB_DB_USERS ?? 'penplus_users'
const DB_DISTRICTS = process.env.COUCHDB_DB_DISTRICTS ?? 'penplus_districts'
const SEED_DATE = process.env.DEMO_SEED_DATE ?? '2026-10-04'

const MENTEE_COUNT = 100
const EVALUATOR_COUNT = 15
const JOURNEYS_PER_MENTEE = 4
const DISTRICT_COUNT = 6
const FACILITIES_PER_DISTRICT = 4
const BULK_SIZE = 100
const LOOKUP_SIZE = 400
const DAY = 24 * 60 * 60 * 1000
const HOUR = 60 * 60 * 1000
const DEMO_DATASET = 'reporting-v2'

type CouchDoc = Record<string, unknown> & {
  _id: string
  _rev?: string
  demo?: boolean
}

type JourneyOutcome = 'in_progress' | 'basic_competent' | 'fully_competent' | 'reopened'

interface DemoLocation {
  id: string
  name: string
  facilities: Array<{ name: string }>
}

function stableInt(seed: string, max: number): number {
  const digest = createHash('sha256').update(seed).digest()
  return digest.readUInt32BE(0) % max
}

function stableId(seed: string): string {
  const hash = createHash('sha256').update(`penplus-demo-v2:${seed}`).digest('hex').slice(0, 32)
  const variant = ((Number.parseInt(hash[16]!, 16) & 0x3) | 0x8).toString(16)

  return `${hash.slice(0, 8)}-${hash.slice(8, 12)}-5${hash.slice(13, 16)}-${variant}${hash.slice(17, 20)}-${hash.slice(20)}`
}

function score(seed: string, min: number, max: number): number {
  return min + stableInt(seed, max - min + 1)
}

function authHeader(): string {
  return `Basic ${Buffer.from(`${COUCHDB_USER}:${COUCHDB_PASSWORD}`).toString('base64')}`
}

function couchDbBaseUrl(): URL {
  const url = new URL(COUCHDB_URL)
  const isLocal = ['localhost', '127.0.0.1', '::1'].includes(url.hostname)

  if (!isLocal && process.env.ALLOW_REMOTE_DEMO_SEED !== '1') {
    throw new Error('Refusing to seed a remote CouchDB. Set ALLOW_REMOTE_DEMO_SEED=1 only for an approved test server.')
  }

  return url
}

async function getExistingDocs(dbName: string, ids: string[]): Promise<Map<string, CouchDoc>> {
  const existing = new Map<string, CouchDoc>()
  const base = couchDbBaseUrl().toString().replace(/\/$/, '')

  for (let offset = 0; offset < ids.length; offset += LOOKUP_SIZE) {
    const res = await fetch(`${base}/${dbName}/_all_docs?include_docs=true`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: authHeader(),
      },
      body: JSON.stringify({ keys: ids.slice(offset, offset + LOOKUP_SIZE) }),
    })

    if (!res.ok) {
      throw new Error(`Failed to check existing documents in ${dbName}: ${res.status} ${await res.text()}`)
    }

    const body = await res.json() as {
      rows: Array<{ doc?: CouchDoc }>
    }

    for (const row of body.rows) {
      if (row.doc) existing.set(row.doc._id, row.doc)
    }
  }

  return existing
}

async function bulkUpsert(dbName: string, docs: CouchDoc[]): Promise<void> {
  if (docs.length === 0) return

  const base = couchDbBaseUrl().toString().replace(/\/$/, '')
  const existing = await getExistingDocs(dbName, docs.map(doc => doc._id))

  for (const [id, doc] of existing) {
    if (doc.demo !== true) {
      throw new Error(`Refusing to overwrite non-demo document ${id} in ${dbName}.`)
    }
  }

  const updatedDocs = docs.map((doc) => {
    const previous = existing.get(doc._id)
    return previous ? { ...doc, _rev: previous._rev } : doc
  })

  let written = 0
  for (let offset = 0; offset < updatedDocs.length; offset += BULK_SIZE) {
    const batch = updatedDocs.slice(offset, offset + BULK_SIZE)
    const res = await fetch(`${base}/${dbName}/_bulk_docs`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: authHeader(),
      },
      body: JSON.stringify({ docs: batch }),
    })

    if (!res.ok) {
      throw new Error(`_bulk_docs failed for ${dbName}: ${res.status} ${await res.text()}`)
    }

    const results = await res.json() as Array<{ id: string, error?: string, reason?: string }>
    const errors = results.filter(result => result.error)

    if (errors.length > 0) {
      const summary = errors.slice(0, 5).map(result => `${result.id}: ${result.error} (${result.reason ?? 'no reason'})`)
      throw new Error(`${errors.length} document(s) failed in ${dbName}: ${summary.join('; ')}`)
    }

    written += batch.length
  }

  console.log(`  ${dbName}: upserted ${written} demo document(s)`)
}

function buildLocations(): DemoLocation[] {
  return Array.from({ length: DISTRICT_COUNT }, (_, districtIndex) => {
    const districtName = `Demo District ${String(districtIndex + 1).padStart(2, '0')}`

    return {
      id: stableId(`district:${districtIndex + 1}`),
      name: districtName,
      facilities: Array.from({ length: FACILITIES_PER_DISTRICT }, (_, facilityIndex) => {
        const facilityName = `${districtName} Facility ${String(facilityIndex + 1).padStart(2, '0')}`

        return { name: facilityName }
      }),
    }
  })
}

function buildUsers(locations: DemoLocation[], now: number): { mentees: CouchDoc[], evaluators: CouchDoc[] } {
  const professions = ['Nurse', 'Clinical Officer', 'Medical Officer', 'Midwife', 'Pharmacist']
  const evaluators = Array.from({ length: EVALUATOR_COUNT }, (_, index) => {
    const location = locations[index % locations.length]!
    const facility = location.facilities[index % location.facilities.length]!
    const number = String(index + 1).padStart(2, '0')

    return {
      _id: stableId(`evaluator:${index + 1}`),
      type: 'user',
      firstname: 'Demo',
      lastname: `Mentor ${number}`,
      username: `demo_mentor_${number}`,
      profession: 'Clinical Mentor',
      facility: facility.name,
      district: location.name,
      syncStatus: 'synced',
      syncedAt: now,
      createdAt: now,
      updatedAt: now,
      demo: true,
      demoDataset: DEMO_DATASET,
    }
  })

  const mentees = Array.from({ length: MENTEE_COUNT }, (_, index) => {
    const location = locations[Math.floor(index / (MENTEE_COUNT / locations.length))]!
    const facility = location.facilities[index % location.facilities.length]!
    const number = String(index + 1).padStart(3, '0')

    return {
      _id: stableId(`mentee:${index + 1}`),
      type: 'user',
      firstname: 'Demo',
      lastname: `Mentee ${number}`,
      username: `demo_mentee_${number}`,
      profession: professions[index % professions.length]!,
      facility: facility.name,
      district: location.name,
      syncStatus: 'synced',
      syncedAt: now,
      createdAt: now,
      updatedAt: now,
      demo: true,
      demoDataset: DEMO_DATASET,
    }
  })

  return { mentees, evaluators }
}

function getOutcome(journeyNumber: number, hasAdvancedItems: boolean): JourneyOutcome {
  if (journeyNumber % 12 === 11) return 'reopened'

  const outcome = journeyNumber % 6
  if (outcome === 0 || outcome === 1) return 'in_progress'
  if ((outcome === 2 || outcome === 5) && hasAdvancedItems) return 'basic_competent'
  return 'fully_competent'
}

function getItemScore(
  item: (typeof evaluationTools)[number]['items'][number],
  reopenedLowItemSlug: string,
  sessionIndex: number,
  competencyIndex: number,
  outcome: JourneyOutcome,
  terminalOutcome: 'basic_competent' | 'fully_competent',
  journeyNumber: number,
  toolSlug: string,
): number | null {
  const isCompetencySession = sessionIndex === competencyIndex && outcome !== 'in_progress'
  const isReopenedSession = outcome === 'reopened' && sessionIndex > competencyIndex

  if (isCompetencySession) {
    if (terminalOutcome === 'basic_competent' && item.isAdvanced) {
      return score(`${journeyNumber}:${toolSlug}:${item.slug}:advanced`, 1, 3)
    }

    return score(`${journeyNumber}:${toolSlug}:${item.slug}:competent`, 4, 5)
  }

  if (isReopenedSession) {
    return item.slug === reopenedLowItemSlug
      ? score(`${journeyNumber}:${toolSlug}:reopened-low`, 1, 3)
      : score(`${journeyNumber}:${toolSlug}:${item.slug}:reopened`, 3, 5)
  }

  const drift = score(`${journeyNumber}:${toolSlug}:${item.slug}:${sessionIndex}`, 0, 2)
  const result = Math.min(3, 1 + Math.floor((sessionIndex / Math.max(competencyIndex, 1)) * 2) + drift)

  if (sessionIndex !== competencyIndex && stableInt(`${journeyNumber}:${toolSlug}:${item.slug}:${sessionIndex}:na`, 40) === 0) {
    return null
  }

  return result
}

function roundLayout(journeyNumber: number, sessionCount: number): number {
  if (sessionCount < 3 || journeyNumber % 3 !== 0) return 0
  if (sessionCount >= 4 && journeyNumber % 9 === 0) return 3
  return 2
}

function buildJourneySessions(
  mentee: CouchDoc,
  evaluator: CouchDoc,
  location: DemoLocation,
  facilityName: string,
  tool: (typeof evaluationTools)[number],
  journeyNumber: number,
  menteeIndex: number,
  journeyIndex: number,
  anchor: number,
): { sessions: CouchDoc[], outcome: JourneyOutcome, rounds: number } {
  const evaluationGroupId = `${mentee._id}::${tool.slug}`
  const sessionCount = 3 + ((menteeIndex + journeyIndex * 2) % 4)
  const hasAdvancedItems = tool.items.some(item => item.isAdvanced)
  const outcome = getOutcome(journeyNumber, hasAdvancedItems)
  const terminalOutcome = outcome === 'basic_competent' || (outcome === 'reopened' && journeyNumber % 2 === 0)
    ? 'basic_competent'
    : 'fully_competent'
  const competencyIndex = outcome === 'reopened' ? sessionCount - 2 : sessionCount - 1
  const basicItems = tool.items.filter(item => !item.isAdvanced)
  const reopenedLowItemSlug = basicItems[journeyNumber % basicItems.length]!.slug
  const roundCount = roundLayout(journeyNumber, sessionCount)
  const spacingDays = 14 + ((menteeIndex + journeyIndex * 3) % 8) * 7
  const lastSessionDaysAgo = 3 + stableInt(`${evaluationGroupId}:date`, 100)
  const lastDayIndex = sessionCount - roundCount
  const sessions: CouchDoc[] = []

  const menteeRef = {
    id: mentee._id,
    firstname: mentee.firstname,
    lastname: mentee.lastname,
    username: mentee.username,
    profession: mentee.profession,
    facilityId: facilityName,
    districtId: location.name,
  }
  const evaluatorRef = {
    id: evaluator._id,
    firstname: evaluator.firstname,
    lastname: evaluator.lastname,
    username: evaluator.username,
    profession: evaluator.profession,
    facilityId: evaluator.facility,
    districtId: evaluator.district,
  }

  for (let sessionIndex = 0; sessionIndex < sessionCount; sessionIndex++) {
    const inRoundGroup = roundCount > 0 && sessionIndex < roundCount
    const dayIndex = inRoundGroup ? 0 : sessionIndex - roundCount + 1
    const evalDate = anchor - (lastSessionDaysAgo + (lastDayIndex - dayIndex) * spacingDays) * DAY
    const roundOfDay = inRoundGroup ? sessionIndex + 1 : 1
    const createdAt = inRoundGroup
      ? evalDate + (roundCount - roundOfDay) * HOUR
      : evalDate + 6 * HOUR
    const isCompetencySession = sessionIndex === competencyIndex && outcome !== 'in_progress'
    const isReopenedSession = outcome === 'reopened' && sessionIndex > competencyIndex
    const itemScores = tool.items.map(item => ({
      itemSlug: item.slug,
      menteeScore: getItemScore(
        item,
        reopenedLowItemSlug,
        sessionIndex,
        competencyIndex,
        outcome,
        terminalOutcome,
        journeyNumber,
        tool.slug,
      ),
    }))
    const counsellingScores = counsellingTool.items.map((item) => {
      const progress = Math.min(sessionIndex / Math.max(competencyIndex, 1), 1)
      const base = Math.min(4, 2 + Math.floor(progress * 2))
      const menteeScore = stableInt(`${evaluationGroupId}:${sessionIndex}:${item.slug}:na`, 30) === 0 && !isCompetencySession
        ? null
        : Math.max(1, Math.min(5, base + score(`${evaluationGroupId}:${sessionIndex}:${item.slug}`, -1, 1)))

      return { itemSlug: item.slug, menteeScore }
    })
    const session: CouchDoc = {
      _id: stableId(`session:${evaluationGroupId}:${sessionIndex + 1}`),
      type: 'session',
      evaluationGroupId,
      mentee: menteeRef,
      evaluator: evaluatorRef,
      toolSlug: tool.slug,
      evalDate,
      facilityId: facilityName,
      districtId: location.name,
      itemScores,
      counsellingScores,
      phase: sessionIndex === 0
        ? 'initial_intensive'
        : (isCompetencySession || (isReopenedSession && terminalOutcome === 'fully_competent') ? 'supervision' : 'ongoing'),
      notes: isReopenedSession
        ? 'Demo scenario: follow-up after a previously competent journey.'
        : `Demo mentorship visit ${sessionIndex + 1} for ${tool.label}.`,
      syncStatus: 'synced',
      syncedAt: anchor,
      createdAt,
      updatedAt: createdAt,
      demo: true,
      demoDataset: DEMO_DATASET,
    }

    if (inRoundGroup || stableInt(`${evaluationGroupId}:${sessionIndex}:round`, 11) !== 0) {
      session.roundOfDay = roundOfDay
    }

    if (isReopenedSession) {
      session.reopenReason = 'Demo scenario: competency was recorded in error and the journey was reopened.'
    }

    sessions.push(session)
  }

  return { sessions, outcome, rounds: roundCount }
}

function buildGaps(
  mentee: CouchDoc,
  evaluator: CouchDoc,
  toolSlug: string,
  evaluationGroupId: string,
  journeyNumber: number,
  anchor: number,
): CouchDoc[] {
  if (journeyNumber % 2 !== 0 && journeyNumber % 7 !== 0) return []

  const domains = ['knowledge', 'critical_reasoning', 'clinical_skills', 'communication', 'attitude'] as const
  const supervisionLevels = ['intensive_mentorship', 'ongoing_mentorship', 'independent_practice'] as const
  const resolved = journeyNumber % 3 !== 0
  const identifiedAt = anchor - (5 + stableInt(`${evaluationGroupId}:gap-date`, 180)) * DAY
  const domainStart = journeyNumber % domains.length
  const selectedDomains = [
    domains[domainStart]!,
    domains[(domainStart + 2) % domains.length]!,
  ]
  const gap: CouchDoc = {
    _id: stableId(`gap:${evaluationGroupId}`),
    type: 'gap',
    evaluationGroupId,
    menteeId: mentee._id,
    evaluatorId: evaluator._id,
    toolSlug,
    identifiedAt,
    description: [
      'Needs support interpreting clinical findings independently.',
      'Requires practice with safe medication titration and follow-up.',
      'Would benefit from strengthening patient communication and counselling.',
      'Documentation and escalation decisions need further mentorship.',
      'Needs additional supervised practice with complex cases.',
    ][journeyNumber % 5]!,
    domains: selectedDomains,
    coveredInMentorship: resolved ? true : (journeyNumber % 4 === 0 ? false : null),
    coveringLater: !resolved,
    supervisionLevel: supervisionLevels[journeyNumber % supervisionLevels.length],
    syncStatus: 'synced',
    syncedAt: anchor,
    createdAt: identifiedAt,
    updatedAt: resolved ? anchor - stableInt(`${evaluationGroupId}:gap-resolution`, 4 * DAY) : anchor,
    demo: true,
    demoDataset: DEMO_DATASET,
  }

  if (resolved) {
    gap.resolutionNote = 'Addressed through focused case review and observed practice.'
    gap.resolvedAt = gap.updatedAt
  } else {
    gap.timeline = journeyNumber % 2 === 0 ? 'Next mentorship visit' : 'Within 2 weeks'
  }

  return [gap]
}

async function main(): Promise<void> {
  const couchUrl = couchDbBaseUrl()
  const dateMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(SEED_DATE)

  if (!dateMatch) {
    throw new Error(`Invalid DEMO_SEED_DATE [${SEED_DATE}]. Use YYYY-MM-DD.`)
  }

  const anchor = Date.UTC(Number(dateMatch[1]), Number(dateMatch[2]) - 1, Number(dateMatch[3]), 12)
  const parsedDate = new Date(anchor)
  if (parsedDate.toISOString().slice(0, 10) !== SEED_DATE) {
    throw new Error(`Invalid DEMO_SEED_DATE [${SEED_DATE}].`)
  }

  console.log(`Seeding deterministic demo data into CouchDB at ${couchUrl.hostname}`)
  console.log(`Dataset: ${DEMO_DATASET}; seed date: ${SEED_DATE}`)

  const locations = buildLocations()
  const { mentees, evaluators } = buildUsers(locations, anchor)
  const districtDocs: CouchDoc[] = locations.map(location => ({
    _id: location.id,
    district: location.name,
    facilities: location.facilities.map(facility => facility.name),
    demo: true,
    demoDataset: DEMO_DATASET,
  }))
  const facilityCount = locations.reduce((count, location) => count + location.facilities.length, 0)
  const sessions: CouchDoc[] = []
  const gaps: CouchDoc[] = []
  const outcomes: Record<JourneyOutcome, number> = {
    in_progress: 0,
    basic_competent: 0,
    fully_competent: 0,
    reopened: 0,
  }
  let multiRoundJourneys = 0
  let multiRoundSessions = 0
  let nullScores = 0
  let journeyNumber = 0

  for (let menteeIndex = 0; menteeIndex < mentees.length; menteeIndex++) {
    const mentee = mentees[menteeIndex]!
    const location = locations[Math.floor(menteeIndex / (MENTEE_COUNT / locations.length))]!

    for (let journeyIndex = 0; journeyIndex < JOURNEYS_PER_MENTEE; journeyIndex++) {
      const tool = evaluationTools[(menteeIndex * JOURNEYS_PER_MENTEE + journeyIndex * 3) % evaluationTools.length]!
      const evaluatorIndex = (menteeIndex * 7 + journeyIndex * 3) % evaluators.length
      const evaluator = evaluators[evaluatorIndex]!
      const facility = location.facilities[(menteeIndex + journeyIndex) % location.facilities.length]!
      const generated = buildJourneySessions(
        mentee,
        evaluator,
        location,
        facility.name,
        tool,
        journeyNumber,
        menteeIndex,
        journeyIndex,
        anchor,
      )
      const evaluationGroupId = `${mentee._id}::${tool.slug}`

      sessions.push(...generated.sessions)
      gaps.push(...buildGaps(mentee, evaluator, tool.slug, evaluationGroupId, journeyNumber, anchor))
      outcomes[generated.outcome]++
      if (generated.rounds > 1) {
        multiRoundJourneys++
        multiRoundSessions += generated.rounds
      }
      nullScores += generated.sessions.reduce((count, session) => {
        const itemScores = session.itemScores as Array<{ menteeScore: number | null }>
        const counsellingScores = session.counsellingScores as Array<{ menteeScore: number | null }>
        return count + [...itemScores, ...counsellingScores].filter(item => item.menteeScore === null).length
      }, 0)
      journeyNumber++
    }
  }

  await bulkUpsert(DB_DISTRICTS, districtDocs)
  await bulkUpsert(DB_USERS, [...evaluators, ...mentees])
  await bulkUpsert(DB_SESSIONS, sessions)
  await bulkUpsert(DB_GAPS, gaps)

  console.log(`Done: ${districtDocs.length} districts, ${facilityCount} facilities, ${mentees.length} mentees, ${evaluators.length} mentors.`)
  console.log(`Journeys: ${journeyNumber} (${outcomes.in_progress} in progress, ${outcomes.basic_competent} basic-only, ${outcomes.fully_competent} fully competent, ${outcomes.reopened} reopened).`)
  console.log(`Sessions: ${sessions.length}; journeys with multiple same-day rounds: ${multiRoundJourneys}; visits in those rounds: ${multiRoundSessions}; N/A scores: ${nullScores}; gaps: ${gaps.length}.`)
  console.log('Next: run `php artisan sync:couchdb` from reporting/ to import the data into MySQL.')
}

main().catch((error: unknown) => {
  console.error(error)
  process.exit(1)
})
