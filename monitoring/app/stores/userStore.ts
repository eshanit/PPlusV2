import { defineStore } from 'pinia'
import { useDb } from '~/composables/useDb'
import type { IUserRef } from '~/interfaces/IUserRef'

const STORAGE_KEY = 'penplus_current_user'
const NOT_FOUND_STATUS = 404
const FALLBACK_ID_RADIX = 16
const FALLBACK_ID_SUFFIX_LENGTH = 12
const FALLBACK_RANDOM_MAX = 0xffffffffffff

interface IStoredUser {
  _id: string
  _rev?: string
  type?: 'user'
  firstname: string
  lastname: string
  username: string
  profession?: string
  facility?: string
  district?: string
  syncStatus?: 'pending' | 'synced' | 'failed'
  syncedAt?: number
  createdAt?: number
  updatedAt?: number
}

export interface IUserInput {
  id?: string
  firstname: string
  lastname: string
  username?: string
  profession?: string
  facility?: string
  district?: string
}

function toUserRef(doc: IStoredUser): IUserRef {
  return {
    id: doc._id,
    firstname: doc.firstname,
    lastname: doc.lastname,
    username: doc.username,
    profession: doc.profession,
    facilityId: doc.facility,
    districtId: doc.district,
  }
}

function cleanText(value?: string): string {
  return value?.trim() ?? ''
}

function createUserId(): string {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID()
  }

  const suffix = Math
    .floor(Math.random() * FALLBACK_RANDOM_MAX)
    .toString(FALLBACK_ID_RADIX)
    .padStart(FALLBACK_ID_SUFFIX_LENGTH, '0')
    .slice(-FALLBACK_ID_SUFFIX_LENGTH)

  return `00000000-0000-4000-8000-${suffix}`
}

function isPouchStatus(error: unknown, status: number): boolean {
  return typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    error.status === status
}

export const useUserStore = defineStore('user', () => {
  const { usersDb } = useDb()

  const currentUser = ref<IUserRef | null>(null)
  const allUsers = ref<IUserRef[]>([])
  const loadingUsers = ref(false)
  const pulling = ref(false)
  const pullError = ref<string | null>(null)

  function loadFromStorage() {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return
    try {
      currentUser.value = JSON.parse(raw) as IUserRef
    } catch {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  function setCurrentUser(user: IUserRef) {
    currentUser.value = user
    localStorage.setItem(STORAGE_KEY, JSON.stringify(user))
  }

  function clearCurrentUser() {
    currentUser.value = null
    localStorage.removeItem(STORAGE_KEY)
  }

  async function loadUsers() {
    loadingUsers.value = true
    try {
      const result = await usersDb.allDocs<IStoredUser>({ include_docs: true })
      allUsers.value = result.rows
        .map(r => r.doc!)
        .filter(d => d.firstname && d.lastname)
        .map(toUserRef)
        .sort((a, b) => a.lastname.localeCompare(b.lastname) || a.firstname.localeCompare(b.firstname))
    } finally {
      loadingUsers.value = false
    }
  }

  async function findStoredUser(id: string): Promise<IStoredUser | null> {
    try {
      return await usersDb.get<IStoredUser>(id)
    } catch (err: unknown) {
      if (isPouchStatus(err, NOT_FOUND_STATUS)) {
        return null
      }

      throw err
    }
  }

  function usernameExists(username: string, exceptId?: string): boolean {
    const value = username.trim().toLowerCase()

    return allUsers.value.some(user =>
      user.id !== exceptId &&
      user.username.trim().toLowerCase() === value
    )
  }

  async function saveUser(input: IUserInput): Promise<IUserRef> {
    const firstname = cleanText(input.firstname)
    const lastname = cleanText(input.lastname)
    const profession = cleanText(input.profession)

    if (!firstname || !lastname) {
      throw new Error('First name and last name are required.')
    }

    const now = Date.now()
    const existing = input.id ? await findStoredUser(input.id) : null
    const doc: IStoredUser = {
      _id: existing?._id ?? input.id ?? createUserId(),
      _rev: existing?._rev,
      type: 'user',
      firstname,
      lastname,
      username: existing?.username ?? `user_${Date.now()}`,
      profession: profession || undefined,
      facility: cleanText(input.facility) || existing?.facility,
      district: cleanText(input.district) || existing?.district,
      syncStatus: 'pending',
      syncedAt: existing?.syncedAt,
      createdAt: existing?.createdAt ?? now,
      updatedAt: now,
    }

    const response = await usersDb.put(doc)
    doc._rev = response.rev

    const userRef = toUserRef(doc)
    const idx = allUsers.value.findIndex(user => user.id === userRef.id)

    if (idx >= 0) {
      allUsers.value[idx] = userRef
    } else {
      allUsers.value.push(userRef)
    }

    allUsers.value = [...allUsers.value]
      .sort((a, b) => a.lastname.localeCompare(b.lastname) || a.firstname.localeCompare(b.firstname))

    if (currentUser.value?.id === userRef.id) {
      setCurrentUser(userRef)
    }

    return userRef
  }

  async function removeUser(id: string): Promise<void> {
    const doc = await usersDb.get<IStoredUser>(id)
    await usersDb.remove(doc)
    allUsers.value = allUsers.value.filter(user => user.id !== id)

    if (currentUser.value?.id === id) {
      clearCurrentUser()
    }
  }

  async function pullFromCouchDb(remoteUsersUrl: string) {
    pulling.value = true
    pullError.value = null
    try {
      await usersDb.replicate.from(remoteUsersUrl, { batch_size: 200 })
      await loadUsers()
    } catch (err: unknown) {
      pullError.value = err instanceof Error ? err.message : String(err)
    } finally {
      pulling.value = false
    }
  }

  const isIdentified = computed(() => currentUser.value !== null)

  const currentUserFullName = computed(() =>
    currentUser.value ? `${currentUser.value.firstname} ${currentUser.value.lastname}` : ''
  )

  const currentUserInitials = computed(() =>
    currentUser.value
      ? `${currentUser.value.firstname[0] ?? ''}${currentUser.value.lastname[0] ?? ''}`.toUpperCase()
      : ''
  )

  return {
    currentUser,
    allUsers,
    loadingUsers,
    pulling,
    pullError,
    isIdentified,
    currentUserFullName,
    currentUserInitials,
    loadFromStorage,
    setCurrentUser,
    clearCurrentUser,
    loadUsers,
    saveUser,
    removeUser,
    usernameExists,
    pullFromCouchDb,
  }
})
