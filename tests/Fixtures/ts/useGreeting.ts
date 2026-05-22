// TypeScript composable for greetings.
import { computed, type ComputedRef } from 'vue'

export function useGreeting(): ComputedRef<string> {
    return computed(() => t('TS greeting'))
}

export const buttons = {
    save: __('buttons.save'),
    cancel: trans('buttons.cancel'),
}

export function plural(count: number): string {
    return tc('typed.items', count)
}
