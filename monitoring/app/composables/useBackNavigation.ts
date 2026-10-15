import type { RouteLocationNormalizedLoaded, RouteLocationRaw, Router } from 'vue-router'

/** Pages that are top-level destinations and never show a Back button. */
const ROOT_PATHS = new Set(['/'])

function queryString(value: unknown): string | undefined {
  return typeof value === 'string' && value ? value : undefined
}

/**
 * The logical parent of a page. Used when there is no in-app history to go
 * back to, for example after a reload, a cold start or a deep link.
 */
export function getParentRoute(route: RouteLocationNormalizedLoaded): RouteLocationRaw {
  const menteeId = queryString(route.query.menteeId)
  const toolSlug = queryString(route.query.toolSlug)

  switch (route.path) {
    case '/mentees':
      return '/'
    case '/sessions/journey':
    case '/sessions/new':
      return menteeId ? `/mentees/${menteeId}` : '/mentees'
    case '/sessions/session':
      return menteeId && toolSlug
        ? { path: '/sessions/journey', query: { menteeId, toolSlug } }
        : '/mentees'
    default:
      if (route.path.startsWith('/mentees/')) return '/mentees'
      return '/'
  }
}

export function isRootRoute(route: RouteLocationNormalizedLoaded): boolean {
  return ROOT_PATHS.has(route.path)
}

/** True when vue-router recorded an in-app page before this one. */
function hasInAppHistory(): boolean {
  if (typeof window === 'undefined') return false
  return Boolean((window.history.state as { back?: string | null } | null)?.back)
}

/**
 * Go back one page if the user arrived from inside the app, otherwise go to
 * the page's logical parent. Route-leave guards (such as the unsaved-session
 * prompt on the session form) still run either way.
 */
export function navigateBack(router: Router, route: RouteLocationNormalizedLoaded) {
  if (hasInAppHistory()) {
    router.back()
    return
  }
  return router.replace(getParentRoute(route))
}

export function useBackNavigation() {
  const router = useRouter()
  const route = useRoute()

  const canGoBack = computed(() => !isRootRoute(route))

  function goBack() {
    return navigateBack(router, route)
  }

  return { canGoBack, goBack }
}
