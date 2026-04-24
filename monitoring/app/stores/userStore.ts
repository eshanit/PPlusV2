import { defineStore } from 'pinia'
import { useDb } from '~/composables/useDb'
import type { IUserRef } from '~/interfaces/IUserRef'

const STORAGE_KEY = 'penplus_current_user'

interface IStoredUser {
  _id: string
  _rev?: string
  firstname: string
  lastname: string
  username: string
  profession?: string
}

function toUserRef(doc: IStoredUser): IUserRef {
  return { id: doc._id, firstname: doc.firstname, lastname: doc.lastname, username: doc.username }
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
    pullFromCouchDb,
  }
})
