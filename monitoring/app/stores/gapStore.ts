import { defineStore } from 'pinia'
import { useDb } from '~/composables/useDb'
import type { IGapEntry } from '~/interfaces/IGapEntry'

export const useGapStore = defineStore('gaps', () => {
  const { gapsDb } = useDb()

  const gaps = ref<IGapEntry[]>([])
  const loading = ref(false)

  async function loadAll() {
    loading.value = true
    try {
      const result = await gapsDb.allDocs<IGapEntry>({ include_docs: true })
      gaps.value = result.rows
        .map(r => r.doc!)
        .filter(d => d.type === 'gap')
    } finally {
      loading.value = false
    }
  }

  async function save(gap: IGapEntry): Promise<IGapEntry> {
    const now = Date.now()
    const doc: IGapEntry = { ...gap, updatedAt: now }

    if (!doc._id) {
      doc._id = `gap::${doc.evaluationGroupId}::${now}`
      doc.createdAt = now
    }

    const response = await gapsDb.put(doc)
    doc._rev = response.rev

    const idx = gaps.value.findIndex(g => g._id === doc._id)
    if (idx >= 0) {
      gaps.value[idx] = doc
    } else {
      gaps.value.push(doc)
    }

    return doc
  }

  async function remove(id: string) {
    const doc = await gapsDb.get(id)
    await gapsDb.remove(doc)
    gaps.value = gaps.value.filter(g => g._id !== id)
  }

  function gapsForGroup(evaluationGroupId: string): IGapEntry[] {
    return gaps.value
      .filter(g => g.evaluationGroupId === evaluationGroupId)
      .sort((a, b) => a.identifiedAt - b.identifiedAt)
  }

  function unresolvedGaps(evaluationGroupId: string): IGapEntry[] {
    return gapsForGroup(evaluationGroupId).filter(g => !g.resolvedAt)
  }

  return { gaps, loading, loadAll, save, remove, gapsForGroup, unresolvedGaps }
})
