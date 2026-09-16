import { App } from '@capacitor/app'
import { Capacitor } from '@capacitor/core'

export default defineNuxtPlugin(() => {
  if (!Capacitor.isNativePlatform()) return

  App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack) {
      window.history.back()
    } else {
      App.exitApp()
    }
  })
})
