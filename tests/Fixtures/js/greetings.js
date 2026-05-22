// Sample JS module using translation helpers.
import { t } from './i18n.js'

export function greet() {
    return __('Hello from JS')
}

export function farewell() {
    return trans('farewell.message')
}

export function status(count) {
    return tc('status.online_users', count)
}

export const errors = {
    network: i18n.t('errors.network'),
    timeout: i18next.t('errors.timeout'),
}

// Dynamic — not extracted.
export const dynamic = (key) => __(key)
