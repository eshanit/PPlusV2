import PouchDB from 'pouchdb'
import PouchDBAdapterIdb from 'pouchdb-adapter-idb'

PouchDB.plugin(PouchDBAdapterIdb)

const sessionsDb = new PouchDB('penplus_sessions', { adapter: 'idb' })
const gapsDb = new PouchDB('penplus_gaps', { adapter: 'idb' })
const usersDb = new PouchDB('penplus_users', { adapter: 'idb' })

export function useDb() {
  return { sessionsDb, gapsDb, usersDb }
}
