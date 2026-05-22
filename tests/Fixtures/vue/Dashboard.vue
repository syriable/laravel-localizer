<template>
    <div>
        <h1>{{ $t('Dashboard heading') }}</h1>
        <p>{{ $tc('messages.items', count) }}</p>
        <button>{{ t('buttons.save') }}</button>
    </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const greeting = t('Hello there')
const subtitle = i18n.t('dashboard.subtitle')
const dynamic = t(someVariable) // not extracted
</script>

<style scoped>
/* nothing translatable here */
</style>
