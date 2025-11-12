// sw.js
self.addEventListener('push', function(event) {
    console.log('Push event received:', event);
    
    if (!event.data) {
        console.log('No data received in push event');
        return;
    }

    try {
        const data = event.data.json();
        console.log('Push data received:', data);
        
        const options = {
            body: data.body,
            icon: data.icon || '/icons/icon-192x192.png',
            badge: data.badge || '/icons/badge-72x72.png',
            tag: data.tag || 'default',
            data: data.data || {},
            requireInteraction: data.requireInteraction || true,
            actions: data.actions || [
                {
                    action: 'open',
                    title: 'Open'
                },
                {
                    action: 'close',
                    title: 'Close'
                }
            ],
            vibrate: [100, 50, 100]
        };

        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    } catch (error) {
        console.error('Error processing push event:', error);
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    if (event.notification.data && event.notification.data.url) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});