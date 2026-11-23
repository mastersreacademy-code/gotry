export function formatAbn(value) {
    const digits = value.replace(/\s/g, '');
    if (!/^\d{11}$/.test(digits)) return value.trim();
    return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{3})$/, '$1 $2 $3 $4');
}

export function formatWebsite(value) {
    let website = value.trim();
    if (!website || website.includes('://')) return website;
    const host = website.split('/')[0];
    if (/^(?:[a-z0-9-]+\.[a-z]{2,}|[a-z0-9-]+\.(?:com|net|org)\.au)$/i.test(host)) {
        website = 'www.' + website;
    }
    return 'https://' + website;
}

if (typeof document !== 'undefined') {
    const abn = document.getElementById('abn');
    const website = document.getElementById('website');
    abn?.addEventListener('blur', () => { abn.value = formatAbn(abn.value); });
    website?.addEventListener('blur', () => { website.value = formatWebsite(website.value); });
}
