# Utilities Reference Guide

This guide documents available utility libraries for the PenPlus Monitoring app: **date-fns**, **VueUse**, and **lodash**.

---

## Installation Status

| Library | Status | Version |
| --- | --- | --- |
| `date-fns` | ✅ Installed | ^4.1.0 |
| `@vueuse/core` | ✅ Installed | ^13.0.0 |
| `@vueuse/nuxt` | ✅ Installed | ^13.0.0 |
| `lodash-es` | ✅ Installed | ^4.18.1 |
| `@types/lodash-es` | ✅ Installed (dev) | ^4.17.12 |

---

## 1. Date-FNS 📅

Used for: Date formatting, parsing, arithmetic, comparisons.

### Common Use Cases in Monitoring App

**Current Issue:** Manual date parsing in `sessions/new.vue`:
```ts
// ❌ Manual date handling
const evalDateStr = ref(new Date().toISOString().split('T')[0]!)
```

**Better with date-fns:**
```ts
import { format, parse, add, differenceInDays, isAfter, isBefore } from 'date-fns'

// Format date
format(new Date(), 'yyyy-MM-dd')  // "2026-04-25"

// Parse date string
parse('2026-04-25', 'yyyy-MM-dd', new Date())

// Date arithmetic
add(new Date(), { days: 7, hours: 2 })

// Date comparison
differenceInDays(new Date(), sessionDate)  // "3 days ago"
isAfter(date1, date2)
isBefore(date1, date2)

// Relative time
formatDistance(date1, date2)  // "about 2 hours"
formatRelative(date1, new Date())  // "yesterday at 2:30 PM"
```

### Recommended for These Pages

| Page | Use Case | Functions |
| --- | --- | --- |
| `sessions/new.vue` | Session date picker & validation | `format`, `parse`, `isValid` |
| `sessions/preview.vue` | Display session dates, session number | `format`, `formatRelative`, `differenceInDays` |
| `mentees/index.vue` | Last session date, sort by date | `format`, `compareAsc`, `sort` |
| `sync.vue` | Show "Last synced X minutes ago" | `formatDistance`, `formatRelative` |
| `setup.vue` | User creation date display | `format` |

---

## 2. VueUse 🎯

Used for: Reactive utilities, component logic, lifecycle hooks, state management.

### Already Installed - Ready to Use!

**Modules available:**
- `@vueuse/core` — Core composables
- `@vueuse/nuxt` — Nuxt-integrated composables

### Common Use Cases in Monitoring App

**Filtered Search (already used in mentees/index.vue, setup.vue):**
```ts
// Simple computed filter — VueUse has no useFuzzy composable
const filtered = computed(() =>
  mentees.value.filter(m =>
    `${m.firstname} ${m.lastname} ${m.facilityId}`
      .toLowerCase()
      .includes(search.value.toLowerCase())
  )
)
```

**Debounced Search:**
```ts
import { useDebounceFn } from '@vueuse/core'

const search = ref('')
const debouncedSearch = useDebounceFn((val) => {
  // Trigger search/filter after user stops typing
  filtered.value = applyFilter(val)
}, 300)

watch(search, debouncedSearch)
```

**Keyboard Shortcuts:**
```ts
import { onKeyStroke } from '@vueuse/core'

// Next/previous item in session scoring
onKeyStroke('ArrowRight', () => nextItem())
onKeyStroke('ArrowLeft', () => previousItem())
```

**Clipboard:**
```ts
import { useClipboard } from '@vueuse/core'

const { copy, copied } = useClipboard()
await copy(evaluationGroupId)  // Copy to clipboard
```

**Local Storage (already using localStorage manually):**
```ts
import { useStorage } from '@vueuse/core'

// Reactive localStorage - automatically persists
const currentUser = useStorage('penplus_current_user', null, localStorage, {
  serializer: StorageSerializers.object
})
```

**API Calls/Async:**
```ts
import { useAsyncState, useTimeoutFn } from '@vueuse/core'

// Simplified async state management
const { state: districts, execute: loadDistricts } = useAsyncState(
  () => districtStore.loadAll(),
  null
)

// Or: Delayed actions
useTimeoutFn(() => showForm.value = false, 2000)
```

### Recommended for These Pages

| Page | Composable | Benefit |
| --- | --- | --- |
| `mentees/index.vue` | `useDebounceFn` | Smooth search while typing |
| `sessions/new.vue` | `onKeyStroke` | Arrow keys to navigate items |
| `sessions/new.vue` | `useTitle` | Update browser tab title |
| `sessions/preview.vue` | `useClipboard` | Copy session ID |
| `sync.vue` | `useIntervalFn` | Auto-refresh sync status |
| `All pages` | `useStorage` | Replace manual localStorage |

---

## 3. Lodash / Lodash-ES 🛠️

Used for: Array/object manipulation, filtering, grouping, sorting, utility functions.

### Common Use Cases in Monitoring App

**Array Operations:**
```ts
import { groupBy, sortBy, uniq, compact, flatten } from 'lodash-es'

// Group sessions by mentee
const sessionsByMentee = groupBy(sessions, 'mentee.id')

// Sort by multiple criteria
const sorted = sortBy(mentees, ['district', 'facility', 'lastname'])

// Get unique facilities
const uniqueFacilities = uniq(facilities)

// Remove nulls
const validScores = compact(itemScores)

// Flatten nested arrays
const allItems = flatten(toolItems.map(t => t.items))
```

**Object Operations:**
```ts
import { pick, omit, merge, cloneDeep } from 'lodash-es'

// Extract specific fields
const minimal = pick(user, ['id', 'firstname', 'lastname'])

// Remove fields
const sanitized = omit(session, ['_rev', '_id'])

// Deep merge (for default config + user config)
const config = merge(defaults, userConfig)

// Deep clone (avoid reference issues)
const backup = cloneDeep(form)
```

**Finding & Filtering:**
```ts
import { findIndex, findLast, filter, partition } from 'lodash-es'

// Find index for updating
const idx = findIndex(items, { id: '123' })

// Find most recent
const lastSession = findLast(sessions, s => s.mentee.id === menteeId)

// Partition array (separate pass/fail)
const [advanced, basic] = partition(items, item => item.isAdvanced)
```

**Math & Stats:**
```ts
import { mean, min, max, sum, meanBy } from 'lodash-es'

// Average score
const avgScore = mean(scores)

// Range
const scoreRange = [min(scores), max(scores)]

// Sum
const totalPoints = sum(scores)

// Average by field
const avgByTool = meanBy(sessions, 'averageScore')
```

**String Operations:**
```ts
import { capitalize, startCase, truncate } from 'lodash-es'

// Format names
capitalize('john')  // "John"
startCase('john_doe')  // "John Doe"
truncate('Long text...', { length: 20 })  // "Long text..."
```

### Recommended for These Pages

| Page | Utility | Current Code | Improved |
| --- | --- | --- | --- |
| `sessions/preview.vue` | `sortBy` | Manual sort | `sortBy(sessions, 'evalDate')` |
| `mentees/index.vue` | `groupBy` | Manual grouping | Group mentees by district |
| `sessions/new.vue` | `uniq` | Manual dedup | Unique item slugs |
| `All stores` | `cloneDeep` | Direct copy | Safe nested cloning |
| `sync.vue` | `mean`, `max` | Manual calc | Show sync statistics |

---

## Import Patterns

### Date-FNS
```ts
// Specific imports (recommended - smaller bundle)
import { format, parse, addDays, differenceInDays } from 'date-fns'

// Or namespace (if using many functions)
import * as dfns from 'date-fns'
dfns.format(new Date(), 'yyyy-MM-dd')
```

### VueUse
```ts
// From @vueuse/core
import { useDebounceFn, useClipboard, onKeyStroke, useIntervalFn } from '@vueuse/core'

// Or auto-imported via @vueuse/nuxt (after importing in a component, available everywhere)
```

### Lodash-ES
```ts
// Tree-shakeable imports (recommended)
import { groupBy, sortBy, uniq } from 'lodash-es'

// Single-function imports
import groupBy from 'lodash-es/groupBy'
```

---

## Quick Reference: Common Patterns

### Pattern 1: Sort Sessions by Date, Most Recent First
```ts
import { sortBy } from 'lodash-es'
import { format } from 'date-fns'

const sortedSessions = sortBy(sessions, s => s.evalDate).reverse()
const display = sortedSessions.map(s => ({
  ...s,
  dateStr: format(s.evalDate, 'MMM d, yyyy')
}))
```

### Pattern 2: Group Sessions by Mentee & Tool, Show Summary
```ts
import { groupBy, mapValues } from 'lodash-es'

const groupedByMentee = groupBy(sessions, 'mentee.id')
const summary = mapValues(groupedByMentee, sessions => ({
  total: sessions.length,
  lastDate: sessions[sessions.length - 1].evalDate,
  tools: uniq(sessions.map(s => s.toolSlug))
}))
```

### Pattern 3: Reactive Search with Debounce
```ts
import { useDebounceFn } from '@vueuse/core'
import { filter } from 'lodash-es'

const search = ref('')
const filtered = ref<Mentee[]>([])

const performSearch = useDebounceFn((query) => {
  filtered.value = filter(allMentees, m =>
    startCase(m.firstname + ' ' + m.lastname)
      .toLowerCase()
      .includes(query.toLowerCase())
  )
}, 300)

watch(search, performSearch)
```

### Pattern 4: Form Clone Before Edit (Prevent Accidental Mutations)
```ts
import { cloneDeep } from 'lodash-es'

function openEdit(user: IUserRef) {
  formBackup.value = cloneDeep(user)  // Safe backup
  form.value = cloneDeep(user)  // Independent copy
}

function resetEdit() {
  form.value = cloneDeep(formBackup.value)
}
```

---

## Best Practices

✅ **DO:**
- Import specific functions, not the entire library
- Use `lodash-es` (not `lodash`) for better tree-shaking
- Cache computed results in `computed()` or `ref()`
- Use VueUse's reactive utilities instead of manual `watch/watchEffect`

❌ **DON'T:**
- Import entire libraries: `import _ from 'lodash'`
- Call expensive operations in templates
- Forget to cleanup/unsubscribe from VueUse composables
- Use date strings without parsing—always use Date objects with date-fns

---
