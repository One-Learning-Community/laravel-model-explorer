export function isDateLike(value) {
    if (typeof value !== 'string' || value.length < 10 || value.length > 35) return false
    return /^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}:\d{2})?/.test(value)
}

export function formatValue(value) {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'boolean') return value ? 'true' : 'false'
    if (typeof value === 'object') return JSON.stringify(value)
    if (isDateLike(value)) {
        return String(value).replace('T', ' ').replace(/\.\d+/, '').replace(/Z$/, ' UTC')
    }
    return String(value)
}

export function prettyValue(value) {
    if (value === null || value === undefined) return '—'
    if (typeof value === 'object') return JSON.stringify(value, null, 2)
    return String(value)
}

export function isLong(value) {
    if (value === null || value === undefined) return false
    if (typeof value === 'object') return JSON.stringify(value).length > 80
    return String(value).length > 80
}
