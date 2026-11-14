export function notify(key, variant = 'error', message = null) {
    Shopware.Store.get('notification').createNotification({
        variant,
        title: 'global.default.' + variant,
        message: message ?? key,
    });
}
