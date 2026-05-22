<template>
    <div>
        <h1>{{ $t('Inertia page title') }}</h1>
        <p>{{ t('pages.dashboard.welcome') }}</p>
    </div>
</template>

<script setup lang="ts">
const heading = t('Inertia heading')
const subtitle = trans('inertia.subtitle')
</script>
