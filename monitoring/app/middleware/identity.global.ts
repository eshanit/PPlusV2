import { useUserStore } from '~/stores/userStore'

export default defineNuxtRouteMiddleware((to) => {
  if (to.path === '/setup') return

  const userStore = useUserStore()
  if (!userStore.isIdentified) {
    return navigateTo('/setup')
  }
})
