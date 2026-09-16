import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.solidarmed.penplus',
  appName: 'PenPlus Monitoring',
  webDir: '.output/public',
  android: {
    // CouchDB sync currently runs over plain HTTP — see network_security_config.xml
    allowMixedContent: true,
  },
}

export default config
