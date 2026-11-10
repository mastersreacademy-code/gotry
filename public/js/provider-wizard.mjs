import {programmePreview} from './programme-pricing.mjs';

const form = document.getElementById('activity-form');
const preview = document.getElementById('price-preview');
const money = cents => new Intl.NumberFormat('en-AU', {style: 'currency', currency: 'AUD'}).format(cents / 100);

function update() {
    const value = name => form.elements.namedItem(name)?.value;
    for (const field of form.querySelectorAll('[data-field]')) {
        let visible = true;
        if (field.dataset.whenField) {
            visible = value(field.dataset.whenField) === field.dataset.whenValue;
            if (field.dataset.whenField === 'multiple_days' && value('regular_format') !== 'group') visible = false;
        }
        if (['discount_value', 'discount_applies'].includes(field.dataset.field)) visible = value('discount_type') !== 'none';
        field.hidden = !visible;
        for (const input of field.querySelectorAll('input,select,textarea')) input.disabled = !visible;
    }
    if (!preview) return;
    const data = Object.fromEntries(new FormData(form));
    const rows = programmePreview(data);
    preview.replaceChildren();
    const heading = document.createElement('h2');
    heading.textContent = 'Parent price preview';
    preview.append(heading);
    if (!rows.length) {
        const message = document.createElement('p');
        message.textContent = 'Enter valid prices and discount details to preview the first payment period.';
        preview.append(message);
        return;
    }
    for (const row of rows) {
        const article = document.createElement('article');
        const title = document.createElement('h3');
        title.textContent = row.label;
        article.append(title);
        for (const [label, amount] of [['Regular price', row.regular], ['GoTry discount', -row.discount], [row.period + ' price', row.total]]) {
            const line = document.createElement('p');
            line.textContent = label + ': ' + money(amount);
            article.append(line);
        }
        preview.append(article);
    }
}

if (form) {
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
}
