<script setup>
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import authV2ForgotPasswordIllustrationDark from '@images/pages/auth-v2-forgot-password-illustration-dark.png'
import authV2ForgotPasswordIllustrationLight from '@images/pages/auth-v2-forgot-password-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'

const first_name = ref('')
const last_name = ref('')
const authThemeImg = useGenerateImageVariant(authV2ForgotPasswordIllustrationLight, authV2ForgotPasswordIllustrationDark)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const router = useRouter()
const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone
const selected_timezone = ref(userTimezone)

const timezones = Intl.supportedValuesOf('timeZone').map(zone => ({
  name: zone.replace(/_/g, ' '),
  value: zone,
}))

definePage({
  meta: {
    layout: 'blank',
  },
})

const submitForm = async () => {
  try {
    const res = await $api('/auth/update-details', {
      method: 'POST',
      body: {
        first_name: first_name.value,
        last_name: last_name.value,
        timezone: selected_timezone.value,
        first_login: false,
      },
      onResponseError({ response }) {
        errors.value = response._data.errors
      },
    })

    useCookie('userData', { default: null }).value = res.user

    router.push({ name: 'dashboard' })
  } catch (err) {
    console.error(err)
  }
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    class="auth-wrapper bg-surface"
    no-gutters
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 150px;"
        >
          <VImg
            max-width="468"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <img
          class="auth-footer-mask"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            Hello!
          </h4>
          <p class="mb-0">
            Please enter your account details to continue.
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="submitForm">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="first_name"
                  autofocus
                  label="First Name"
                  type="text"
                  placeholder="John"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="last_name"
                  autofocus
                  label="Last Name"
                  type="text"
                  placeholder="Doe"
                />
              </VCol>
              <VCol cols="12">
                <AppAutocomplete
                  v-model="selected_timezone"
                  :items="timezones"
                  item-title="name"
                  item-value="value"
                  label="Select Timezone"
                  placeholder="Choose your timezone"
                  dense
                  outlined
                />
              </VCol>
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                >
                  Continue to Dashboard
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";
</style>
