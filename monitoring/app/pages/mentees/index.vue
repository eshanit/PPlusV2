<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useUserStore, type IUserInput } from '~/stores/userStore'
import { useDistrictStore } from '~/stores/districtStore'
import { useSessionStore } from '~/stores/sessionStore'
import { useGapStore } from '~/stores/gapStore'
import { useSyncStore } from '~/stores/syncStore'
import type { IUserRef } from '~/interfaces/IUserRef'

type FormMode = 'create' | 'edit'

interface MenteeForm {
  firstname: string
  lastname: string
  profession: string
  district: string
  facility: string
}

const userStore = useUserStore()
const districtStore = useDistrictStore()
const sessionStore = useSessionStore()
const gapStore = useGapStore()
const syncStore = useSyncStore()
const toast = useToast()

const { allUsers, currentUser, loadingUsers } = storeToRefs(userStore)
const { facilityOptions, loading: loadingDistricts, districts } = storeToRefs(districtStore)

const needSync = computed(() => !loadingDistricts.value && districts.value.length === 0)

const search = ref('')
const showForm = ref(false)
const showDelete = ref(false)
const showSyncAlert = ref(false)
const formMode = ref<FormMode>('create')
const editingUserId = ref<string | null>(null)
const deleteTarget = ref<IUserRef | null>(null)
const saving = ref(false)
const deleting = ref(false)
const syncing = ref(false)

const form = reactive<MenteeForm>({
  firstname: '',
  lastname: '',
  profession: '',
  district: '',
  facility: '',
})

const districtOptions = computed(() =>
  districts.value.map(d => d.district).sort()
)

const facilityOptionsForDistrict = computed(() => {
  if (!form.district) return []
  return districtStore.getFacilities(form.district)
})

const mentees = computed(() =>
  allUsers.value.filter(user => user.id !== currentUser.value?.id)
)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return mentees.value
  return mentees.value.filter(user =>
    user.firstname.toLowerCase().includes(q) ||
    user.lastname.toLowerCase().includes(q) ||
    user.profession?.toLowerCase().includes(q) ||
    user.facilityId?.toLowerCase().includes(q)
  )
})

const formTitle = computed(() =>
  formMode.value === 'create' ? 'Add mentee' : 'Edit mentee'
)

const isFormValid = computed(() =>
  form.firstname.trim() !== '' &&
  form.lastname.trim() !== '' &&
  form.district.trim() !== '' &&
  form.facility.trim() !== ''
)

const deleteSessionCount = computed(() =>
  deleteTarget.value ? sessionCount(deleteTarget.value.id) : 0
)

const deleteGapCount = computed(() =>
  deleteTarget.value ? openGaps(deleteTarget.value.id) : 0
)

function resetForm() {
  form.firstname = ''
  form.lastname = ''
  form.profession = ''
  form.district = ''
  form.facility = ''
  editingUserId.value = null
}

function openCreate() {
  if (needSync.value) {
    toast.add({
      title: 'Sync required',
      description: 'Please sync facilities first',
      color: 'warning',
      icon: 'i-heroicons-exclamation-triangle',
    })
    return
  }
  resetForm()
  formMode.value = 'create'
  showForm.value = true
}

function openEdit(user: IUserRef) {
  formMode.value = 'edit'
  editingUserId.value = user.id
  form.firstname = user.firstname
  form.lastname = user.lastname
  form.profession = user.profession ?? ''
  form.facility = user.facilityId ?? ''
  const dist = districts.value.find(d => d.facilities.includes(form.facility))
  form.district = dist?.district ?? ''
  showForm.value = true
}

async function saveMentee() {
  if (!isFormValid.value || saving.value) return

  saving.value = true
  try {
    const payload: IUserInput = {
      id: editingUserId.value ?? undefined,
      firstname: form.firstname,
      lastname: form.lastname,
      profession: form.profession,
      facility: form.facility,
      district: form.district,
    }

    const saved = await userStore.saveUser(payload)
    toast.add({
      title: formMode.value === 'create' ? 'Mentee added' : 'Mentee updated',
      description: `${saved.firstname} ${saved.lastname}`,
      color: 'success',
      icon: 'i-heroicons-check-circle',
    })
    showForm.value = false
    resetForm()
  } catch (err: unknown) {
    toast.add({
      title: 'Save failed',
      description: err instanceof Error ? err.message : String(err),
      color: 'error',
      icon: 'i-heroicons-x-circle',
    })
  } finally {
    saving.value = false
  }
}

function confirmDelete(user: IUserRef) {
  deleteTarget.value = user
  showDelete.value = true
}

async function deleteMentee() {
  if (!deleteTarget.value || deleting.value) return

  deleting.value = true
  try {
    const name = `${deleteTarget.value.firstname} ${deleteTarget.value.lastname}`
    await userStore.removeUser(deleteTarget.value.id)
    toast.add({
      title: 'Mentee deleted',
      description: name,
      color: 'success',
      icon: 'i-heroicons-trash',
    })
    showDelete.value = false
    deleteTarget.value = null
  } catch (err: unknown) {
    toast.add({
      title: 'Delete failed',
      description: err instanceof Error ? err.message : String(err),
      color: 'error',
      icon: 'i-heroicons-x-circle',
    })
  } finally {
    deleting.value = false
  }
}

function initials(user: IUserRef): string {
  return `${user.firstname[0] ?? ''}${user.lastname[0] ?? ''}`.toUpperCase()
}

function sessionCount(menteeId: string): number {
  return sessionStore.sessions.filter(session => session.mentee.id === menteeId).length
}

function openGaps(menteeId: string): number {
  return gapStore.gaps.filter(gap => gap.menteeId === menteeId && !gap.resolvedAt).length
}

function summaryText(menteeId: string): string {
  const count = sessionCount(menteeId)
  if (count === 0) return 'No sessions yet'
  return `${count} session${count > 1 ? 's' : ''}`
}

function metaText(user: IUserRef): string {
  const parts = [user.profession, user.facilityId].filter(Boolean)
  return parts.join(' - ')
}

async function doSync() {
  syncing.value = true
  
  try {
    // Try pulling from CouchDB first
    await syncStore.pullAll()
    
    // Then load from local storage
    await districtStore.loadAll()
    
    if (districtStore.districts.length === 0) {
      throw new Error('No districts found after sync')
    }
    
    toast.add({
      title: 'Sync complete',
      description: `${districtStore.districts.length} districts loaded`,
      color: 'success',
    })
  } catch (err: any) {
    console.error('Sync error:', err)
    toast.add({
      title: 'Sync failed',
      description: err?.message || String(err),
      color: 'error',
      icon: 'i-heroicons-x-circle',
    })
  } finally {
    syncing.value = false
  }
}

onMounted(() => {
  districtStore.loadAll()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Mentees</h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
          {{ mentees.length }} active profile{{ mentees.length !== 1 ? 's' : '' }}
        </p>
      </div>
      <div class="flex gap-2">
        <UButton
          color="warning"
          icon="i-heroicons-arrow-path"
          size="sm"
          :loading="syncing"
          @click="doSync"
        >
          Sync Facilities
        </UButton>
        <UButton
          color="primary"
          icon="i-heroicons-plus"
          size="sm"
          @click="openCreate"
        >
          Add
        </UButton>
      </div>
    </div>

    <!-- Search -->
    <UInput
      v-model="search"
      placeholder="Search by name or facility..."
      icon="i-heroicons-magnifying-glass"
      size="lg"
    />

    <!-- Loading -->
    <div v-if="loadingUsers" class="py-12 text-center text-gray-400">
      <UIcon name="i-heroicons-arrow-path" class="size-8 animate-spin mx-auto mb-2" />
      <p class="text-sm">Loading...</p>
    </div>

    <!-- Empty search -->
    <div v-else-if="filtered.length === 0" class="py-10 text-center text-gray-400 text-sm">
      <UIcon name="i-heroicons-users" class="size-10 mx-auto mb-2 text-gray-300" />
      <p v-if="search">No mentee matches "{{ search }}"</p>
      <p v-else-if="needSync">No facilities loaded. Please sync first.</p>
      <p v-else>No mentees yet.</p>
      <UButton
        v-if="!search && !needSync"
        class="mt-4"
        icon="i-heroicons-plus"
        color="primary"
        variant="soft"
        @click="openCreate"
      >
        Add first mentee
      </UButton>
      <UButton
        v-else-if="needSync"
        class="mt-4"
        icon="i-heroicons-arrow-path"
        color="warning"
        variant="soft"
        :loading="syncing"
        @click="doSync"
      >
        Sync Facilities
      </UButton>
    </div>

    <!-- Mentee list -->
    <ul v-else class="space-y-2">
      <li
        v-for="mentee in filtered"
        :key="mentee.id"
        class="flex items-stretch gap-2 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800"
      >
        <NuxtLink
          :to="`/mentees/${mentee.id}`"
          class="flex min-w-0 flex-1 items-center gap-3 px-4 py-3.5 rounded-l-xl hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors"
        >
          <!-- Avatar -->
          <div class="size-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm shrink-0">
            {{ initials(mentee) }}
          </div>

          <!-- Name & stats -->
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 dark:text-white truncate">
              {{ mentee.firstname }} {{ mentee.lastname }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
              {{ metaText(mentee) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              {{ summaryText(mentee.id) }}
            </p>
          </div>

          <!-- Indicators -->
          <div class="flex items-center gap-2 shrink-0">
            <UBadge
              v-if="openGaps(mentee.id) > 0"
              :label="`${openGaps(mentee.id)} gap${openGaps(mentee.id) > 1 ? 's' : ''}`"
              color="warning"
              variant="soft"
              size="xs"
            />
            <UIcon name="i-heroicons-chevron-right" class="size-4 text-gray-400" />
          </div>
        </NuxtLink>

        <div class="flex shrink-0 flex-col justify-center gap-1 border-l border-gray-100 dark:border-gray-800 px-1.5">
          <UButton
            variant="ghost"
            color="neutral"
            size="xs"
            icon="i-heroicons-pencil-square"
            :aria-label="`Edit ${mentee.firstname} ${mentee.lastname}`"
            @click="openEdit(mentee)"
          />
          <UButton
            variant="ghost"
            color="error"
            size="xs"
            icon="i-heroicons-trash"
            :aria-label="`Delete ${mentee.firstname} ${mentee.lastname}`"
            @click="confirmDelete(mentee)"
          />
        </div>
      </li>
    </ul>

    <!-- Create/edit modal -->
    <UModal
      v-model:open="showForm"
      :title="formTitle"
      :description="formMode === 'create' ? 'Create a local profile for mentorship sessions.' : 'Update this mentee profile.'"
      :dismissible="!saving"
    >
      <template #body>
        <form class="space-y-4" @submit.prevent="saveMentee">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="space-y-1.5">
              <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                First name
              </span>
              <UInput v-model="form.firstname" autocomplete="given-name" autofocus />
            </label>

            <label class="space-y-1.5">
              <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Last name
              </span>
              <UInput v-model="form.lastname" autocomplete="family-name" />
            </label>
          </div>

          <label class="space-y-1.5 block">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Profession
            </span>
            <UInput v-model="form.profession" placeholder="Nurse, clinician, doctor..." />
          </label>

          <label class="space-y-1.5 block">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              District <span class="text-red-500">*</span>
            </span>
            <USelect
              v-model="form.district"
              :items="districtOptions"
              placeholder="Select district"
              :loading="loadingDistricts"
            />
          </label>

          <label class="space-y-1.5 block">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Facility <span class="text-red-500">*</span>
            </span>
            <USelect
              v-model="form.facility"
              :items="facilityOptionsForDistrict"
              placeholder="Select facility"
              :disabled="!form.district"
              :loading="loadingDistricts"
            />
            <p v-if="!form.district" class="text-xs text-gray-400 mt-1">
              Please select a district first
            </p>
          </label>
        </form>
      </template>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            :disabled="saving"
            @click="showForm = false"
          >
            Cancel
          </UButton>
          <UButton
            color="primary"
            :loading="saving"
            :disabled="!isFormValid"
            @click="saveMentee"
          >
            {{ formMode === 'create' ? 'Create' : 'Save' }}
          </UButton>
        </div>
      </template>
    </UModal>

    <!-- Delete modal -->
    <UModal
      v-model:open="showDelete"
      title="Delete mentee"
      description="The local profile will be removed from this device and synced as deleted."
      :dismissible="!deleting"
    >
      <template #body>
        <div v-if="deleteTarget" class="space-y-3">
          <p class="text-sm text-gray-700 dark:text-gray-300">
            Delete
            <span class="font-semibold text-gray-900 dark:text-white">
              {{ deleteTarget.firstname }} {{ deleteTarget.lastname }}
            </span>
            ?
          </p>
          <div
            v-if="deleteSessionCount > 0 || deleteGapCount > 0"
            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
          >
            This mentee has {{ deleteSessionCount }} session{{ deleteSessionCount !== 1 ? 's' : '' }}
            and {{ deleteGapCount }} open gap{{ deleteGapCount !== 1 ? 's' : '' }} on this device.
            Existing session records keep their embedded mentee snapshot.
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            :disabled="deleting"
            @click="showDelete = false"
          >
            Cancel
          </UButton>
          <UButton
            color="error"
            :loading="deleting"
            @click="deleteMentee"
          >
            Delete
          </UButton>
        </div>
      </template>
    </UModal>

    <!-- Sync required alert -->
    <UModal
      v-model:open="showSyncAlert"
      title="Sync Required"
      :dismissible="!syncing"
    >
      <template #body>
        <div class="space-y-3">
          <div class="flex items-start gap-3">
            <UIcon name="i-heroicons-cloud-arrow-down" class="size-6 text-primary-500 shrink-0 mt-0.5" />
            <p class="text-sm text-gray-600 dark:text-gray-300">
              Before adding mentees, please sync to load facilities from the server.
            </p>
          </div>
          <p class="text-xs text-gray-500">
            This will download the latest facility list from the server.
          </p>
        </div>
      </template>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            :disabled="syncing"
            @click="showSyncAlert = false"
          >
            Cancel
          </UButton>
          <UButton
            color="primary"
            icon="i-heroicons-arrow-path"
            :loading="syncing"
            @click="doSync"
          >
            Sync Now
          </UButton>
        </div>
      </template>
    </UModal>
  </div>
</template>
