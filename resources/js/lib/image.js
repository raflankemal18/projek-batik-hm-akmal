export function storageUrl(path) {
    if (!path) return 'https://placehold.co/400';
    return `/storage/${path.replace('public/', '')}`;
}
