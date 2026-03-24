export function encodeModel(className) {
    return btoa(className).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
}

export function shortName(fqcn) {
    return fqcn.split('\\').pop()
}
