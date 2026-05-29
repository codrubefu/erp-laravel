export function renderCustomFieldForm(container, fields, values = {}) {
    const sortedFields = [...fields].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

    container.innerHTML = '';

    sortedFields.forEach((field) => {
        const wrapper = document.createElement('label');
        wrapper.className = 'custom-field';
        wrapper.dataset.customFieldSlug = field.slug;

        const label = document.createElement('span');
        label.textContent = field.is_required ? `${field.name} *` : field.name;
        wrapper.append(label);
        wrapper.append(createInput(field, values[field.slug] ?? null));
        container.append(wrapper);
    });
}

function createInput(field, value) {
    if (field.type === 'textarea') {
        const input = document.createElement('textarea');
        applyCommonAttributes(input, field, value);
        return input;
    }

    if (field.type === 'select' || field.type === 'multi_select') {
        const input = document.createElement('select');
        input.multiple = field.type === 'multi_select';
        applyCommonAttributes(input, field);

        optionValues(field).forEach((option) => {
            const optionElement = document.createElement('option');
            optionElement.value = option.value;
            optionElement.textContent = option.label;
            optionElement.selected = Array.isArray(value) ? value.includes(option.value) : value === option.value;
            input.append(optionElement);
        });

        return input;
    }

    const input = document.createElement('input');
    input.type = inputType(field.type);
    applyCommonAttributes(input, field, value);

    if (field.type === 'checkbox' || field.type === 'boolean') {
        input.checked = Boolean(value);
    }

    return input;
}

function applyCommonAttributes(input, field, value = null) {
    input.name = `custom_fields[${field.slug}]`;
    input.required = Boolean(field.is_required);

    if (value !== null && !['checkbox', 'boolean'].includes(field.type)) {
        input.value = value;
    }
}

function optionValues(field) {
    const options = field.options?.choices ?? field.options?.options ?? field.options ?? [];

    return options.map((option) => {
        if (typeof option === 'object') {
            return {
                label: option.label ?? option.value ?? option.id,
                value: option.value ?? option.id ?? option.label,
            };
        }

        return { label: option, value: option };
    });
}

function inputType(type) {
    return {
        number: 'number',
        date: 'date',
        datetime: 'datetime-local',
        email: 'email',
        phone: 'tel',
        file: 'file',
        checkbox: 'checkbox',
        boolean: 'checkbox',
    }[type] ?? 'text';
}
