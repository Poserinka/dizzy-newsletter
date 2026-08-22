((blocks, element, components, editor) => {
    const el = element.createElement;

    blocks.registerBlockType('dizzy/newsletter-signup', {
        apiVersion: 2,
        title: 'Dizzy Newsletter Signup',
        icon: 'email-alt2',
        category: 'widgets',
        attributes: {
            title: { type: 'string', default: 'Keep Up to Date with Jazzcafe Dizzy' },
            description: { type: 'string', default: 'Sign up to be the first to receive special news and event updates from Jazzcafe Dizzy.' },
            namePlaceholder: { type: 'string', default: 'Enter Name' },
            placeholder: { type: 'string', default: 'Enter Email' },
            buttonText: { type: 'string', default: 'Sign Up' },
            tag: { type: 'string', default: 'website' },
            layout: { type: 'string', default: 'horizontal' },
            theme: { type: 'string', default: 'dark' },
            showName: { type: 'boolean', default: true },
        },
        edit: ({ attributes, setAttributes }) => el(
            element.Fragment,
            {},
            el(
                editor.InspectorControls,
                {},
                el(
                    components.PanelBody,
                    { title: 'Newsletter Settings' },
                    el(components.TextControl, { label: 'Title', value: attributes.title, onChange: value => setAttributes({ title: value }) }),
                    el(components.TextareaControl, { label: 'Description', value: attributes.description, onChange: value => setAttributes({ description: value }) }),
                    el(components.ToggleControl, { label: 'Show name field', checked: attributes.showName, onChange: value => setAttributes({ showName: value }) }),
                    el(components.TextControl, { label: 'Name placeholder', value: attributes.namePlaceholder, onChange: value => setAttributes({ namePlaceholder: value }) }),
                    el(components.TextControl, { label: 'Email placeholder', value: attributes.placeholder, onChange: value => setAttributes({ placeholder: value }) }),
                    el(components.TextControl, { label: 'Button text', value: attributes.buttonText, onChange: value => setAttributes({ buttonText: value }) }),
                    el(components.TextControl, { label: 'Audience tag', value: attributes.tag, onChange: value => setAttributes({ tag: value }) }),
                    el(components.SelectControl, {
                        label: 'Layout', value: attributes.layout,
                        options: [{ label: 'Horizontal', value: 'horizontal' }, { label: 'Vertical', value: 'vertical' }],
                        onChange: value => setAttributes({ layout: value }),
                    }),
                    el(components.SelectControl, {
                        label: 'Theme', value: attributes.theme,
                        options: [{ label: 'Dark', value: 'dark' }, { label: 'Light', value: 'light' }],
                        onChange: value => setAttributes({ theme: value }),
                    })
                )
            ),
            el(
                'div',
                {
                    className: 'dizzy-nl-editor-preview',
                    style: {
                        background: attributes.theme === 'dark' ? '#191919' : '#f4f4f4',
                        color: attributes.theme === 'dark' ? '#fff' : '#111',
                        alignItems: 'center', display: 'flex', gap: '36px', justifyContent: 'space-between', padding: '48px',
                    },
                },
                (attributes.title || attributes.description) && el('div', { style: { flex: '1 1 280px' } },
                    attributes.title && el('h3', { style: { margin: '0 0 10px' } }, attributes.title),
                    attributes.description && el('p', { style: { margin: 0, opacity: '.72' } }, attributes.description)
                ),
                el('div', { style: { display: 'flex', flex: '1 1 520px', gap: '12px' } },
                    attributes.showName && el('span', { style: { border: '1px solid #777', flex: '1', padding: '14px' } }, attributes.namePlaceholder),
                    el('span', { style: { border: '1px solid #777', flex: '1', padding: '14px' } }, attributes.placeholder),
                    el('button', { style: { border: 0, padding: '15px 26px' } }, attributes.buttonText)
                )
            )
        ),
        save: () => null,
    });
})(wp.blocks, wp.element, wp.components, wp.blockEditor);

