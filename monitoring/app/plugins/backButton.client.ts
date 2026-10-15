import { App } from '@capacitor/app'
import { Capacitor } from '@capacitor/core'
import { isRootRoute, navigateBack } from '~/composables/useBackNavigation'
import { useUserStore } from '~/stores/userStore'

export default defineNuxtPlugin((nuxtApp) => {
  if (!Capacitor.isNativePlatform()) return

  const router = nuxtApp.$router as ReturnType<typeof useRouter>

  // The Android back button follows the same rules as the on-screen Back
  // button. It only exits the app from the home page.
  App.addListener('backButton', () => {
    const route = router.currentRoute.value
    const onFirstRunSetup = route.path === '/setup' && !useUserStore().isIdentified
    if (isRootRoute(route) || onFirstRunSetup) {
      App.exitApp()
      return
    }
    navigateBack(router, route)
  })
})
