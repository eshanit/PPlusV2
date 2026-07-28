// Removes every CouchDB doc tagged `demo: true` by seed-demo-couchdb.ts.
// NOTE: this only clears CouchDB. Laravel's sync:couchdb never propagates deletes
// to MySQL (handleDeleted() just logs), so rows already synced into MySQL need a
// separate SQL cleanup — see the README section on demo data for that snippet.
//
// Run from the monitoring/ directory:
//   COUCHDB_URL=... COUCHDB_USER=... COUCHDB_PASSWORD=... pnpm dlx tsx scripts/clear-demo-couchdb.ts
const COUCHDB_URL = process.env.COUCHDB_URL ?? 'http://localhost:5984'
const COUCHDB_USER = process.env.COUCHDB_USER ?? ''
const COUCHDB_PASSWORD = process.env.COUCHDB_PASSWORD ?? ''

const DBS = [
  process.env.COUCHDB_DB_DISTRICTS ?? 'penplus_districts',
  process.env.COUCHDB_DB_USERS ?? 'penplus_users',
  process.env.COUCHDB_DB_SESSIONS ?? 'penplus_sessions',
  process.env.COUCHDB_DB_GAPS ?? 'penplus_gaps',
]

function authHeader(): string {
  return 'Basic ' + Buffer.from(`${COUCHDB_USER}:${COUCHDB_PASSWORD}`).toString('base64')
}

async function clearDb(dbName: string): Promise<void> {
  const base = COUCHDB_URL.replace(/\/$/, '')
  const res = await fetch(`${base}/${dbName}/_all_docs?include_docs=true`, {
    headers: { Authorization: authHeader() },
  })

  if (!res.ok) {
    throw new Error(`Failed to list ${dbName}: ${res.status} ${await res.text()}`)
  }

  const body = await res.json() as { rows: Array<{ doc: Record<string, unknown> & { _id: string, _rev: string } }> }
  const toDelete = body.rows
    .map(r => r.doc)
    .filter(doc => doc && doc.demo === true)
    .map(doc => ({ _id: doc._id, _rev: doc._rev, _deleted: true }))

  if (toDelete.length === 0) {
    console.log(`${dbName}: nothing to delete`)
    return
  }

  const delRes = await fetch(`${base}/${dbName}/_bulk_docs`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: authHeader() },
    body: JSON.stringify({ docs: toDelete }),
  })

  if (!delRes.ok) {
    throw new Error(`Failed to delete from ${dbName}: ${delRes.status} ${await delRes.text()}`)
  }

  console.log(`${dbName}: deleted ${toDelete.length} demo doc(s)`)
}

async function main(): Promise<void> {
  for (const db of DBS) {
    await clearDb(db)
  }

  console.log('Done. Demo docs removed from CouchDB.')
  console.log('Remember to also run the MySQL cleanup snippet — sync:couchdb does not propagate deletes.')
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
