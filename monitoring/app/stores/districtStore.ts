import { defineStore } from 'pinia'
import { useDb } from '~/composables/useDb'

export interface IDistrict {
  _id: string
  _rev?: string
  district: string
  facilities: string[]
}

export interface IFacilityOption {
  district: string
  facility: string
}

export const useDistrictStore = defineStore('district', () => {
  const { districtsDb } = useDb()

  const districts = ref<IDistrict[]>([])
  const loading = ref(false)

  async function loadAll() {
    loading.value = true
    try {
      const result = await districtsDb.allDocs<IDistrict>({ include_docs: true })
      districts.value = result.rows
        .map(r => r.doc!)
        .filter(d => d.district)
        .sort((a, b) => a.district.localeCompare(b.district))
    } finally {
      loading.value = false
    }
  }

  const facilityOptions = computed((): IFacilityOption[] => {
    const opts: IFacilityOption[] = []
    for (const d of districts.value) {
      for (const f of d.facilities ?? []) {
        opts.push({ district: d.district, facility: f })
      }
    }
    return opts.sort((a, b) => a.facility.localeCompare(b.facility))
  })

  function getDistricts(): string[] {
    return districts.value.map(d => d.district)
  }

  function getFacilities(district: string): string[] {
    const d = districts.value.find(d => d.district === district)
    return d?.facilities ?? []
  }

  return {
    districts,
    loading,
    loadAll,
    facilityOptions,
    getDistricts,
    getFacilities,
  }
})