const cents = value => {
    const text = String(value ?? '');
    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
};

export function programmePreview(data) {
    const individual = data.regular_format === 'individual';
    const lessons = individual ? 1 : Number(data.lessons_per_term);
    if (!Number.isInteger(lessons) || lessons < 1 || lessons > 100) return [];
    const prices = individual ? [['Individual lesson', data.individual_price]]
        : [['Standard group', data.standard_price], ['Small group', data.small_group_price]];
    const rows = [];
    for (const [label, price] of prices) {
        const lesson = cents(price);
        if (lesson === null || lesson > 10000000) return [];
        const regular = lesson * lessons;
        const base = data.discount_applies === 'term' ? regular : lesson;
        let discount = 0;
        if (data.discount_type !== 'none') {
            const value = cents(data.discount_value);
            if (value === null) return [];
            if (data.discount_type === 'percent') {
                if (value > 10000) return [];
                discount = Math.floor((base * value + 5000) / 10000);
            } else if (data.discount_type === 'fixed') discount = value;
            else return [];
        }
        if (discount > base) return [];
        rows.push({label, regular, discount, total: regular - discount, period: individual ? 'First lesson' : 'First term'});
    }
    return rows;
}
