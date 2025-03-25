const { __ } = window.wp.i18n;
const { useState, useEffect, createElement, Fragment } = window.wp.element;
const { getSetting } = window.wc.wcSettings;
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

const settings = getSetting('helloasso_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || __('Helloasso', 'wc-helloasso');

const HelloassoContent = ({ eventRegistration }) => {
    const [selected, setSelected] = useState('one_time');
    const description = window.wp.htmlEntities.decodeEntities(settings.description || '');
    const paymentChoices = settings.payment_choices || [];

    useEffect(() => {
        if (eventRegistration) {
            eventRegistration.onPaymentSetup(() => {
                return {
                    type: 'success',
                    meta: {
                        paymentMethodData: {
                            payment_type: selected
                        }
                    }
                };
            });
        }
    }, [selected, eventRegistration]);

    const radioButtons = [];

    if (paymentChoices.includes('one_time')) {
        radioButtons.push(
            createElement('label', { key: 'one_time', style: { display: 'block', marginBottom: '4px' } },
                createElement('input', {
                    type: 'radio',
                    name: 'helloasso_payment_type',
                    value: 'one_time',
                    checked: selected === 'one_time',
                    onChange: (e) => setSelected(e.target.value),
                }),
                ' ',
                __('Paiement comptant', 'wc-helloasso')
            )
        );
    }
    if (paymentChoices.includes('three_times')) {
        radioButtons.push(
            createElement('label', { key: 'three_times', style: { display: 'block', marginBottom: '4px' } },
                createElement('input', {
                    type: 'radio',
                    name: 'helloasso_payment_type',
                    value: 'three_times',
                    checked: selected === 'three_times',
                    onChange: (e) => setSelected(e.target.value),
                }),
                ' ',
                __('Paiement en 3 fois sans frais', 'wc-helloasso')
            )
        );
    }

    return createElement(
        Fragment,
        null,
        createElement('p', null, description),
        settings.multi_enabled && paymentChoices.length > 1 &&
        createElement(
            'div',
            null,
            createElement('p', null, __('Choisissez votre mode de paiement:', 'wc-helloasso')),
            ...radioButtons
        )
    );
};

registerPaymentMethod({
    name: 'helloasso',
    label: label,
    content: createElement(HelloassoContent),
    edit: createElement(HelloassoContent),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    }
});