<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Notification Test</title>
</head>
<body>
    <h1>Push Notification Test</h1>
    <button id="enableNotifications">Enable Notifications</button>
    <div id="status"></div>

    <script>
        const status = document.getElementById('status');
        const button = document.getElementById('enableNotifications');
        
        async function enableNotifications() {
            try {
                // Check if service workers are supported
                if (!('serviceWorker' in navigator)) {
                    throw new Error('Service Workers are not supported');
                }
                
                status.textContent = 'Registering service worker...';
                const registration = await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registered:', registration);
                
                // Check notification permission
                status.textContent = 'Checking notification permission...';
                if (Notification.permission === 'denied') {
                    throw new Error('Notification permission denied');
                }
                
                if (Notification.permission === 'default') {
                    status.textContent = 'Requesting permission...';
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        throw new Error('Permission not granted');
                    }
                }
                
                // Subscribe to push notifications
                status.textContent = 'Subscribing to push notifications...';
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: 'BBZ3uxDfGe-7IVJijrzxPESc12ffMjC-11RqR7TKnSDKTvuiI_Y-wxsqo7jnpuECGM4AU2JZMQU2seJGVAcO_TI'
                });
                
                console.log('Push subscription:', subscription);
                
                // Send subscription to server
                status.textContent = 'Saving subscription...';
                const response = await fetch('/api/subscribe.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ subscription })
                });
                
                const result = await response.json();
                console.log('Server response:', result);
                
                if (result.success) {
                    status.textContent = 'Push notifications enabled successfully!';
                    button.disabled = true;
                } else {
                    throw new Error(result.error || 'Failed to save subscription');
                }
            } catch (error) {
                console.error('Error:', error);
                status.textContent = 'Error: ' + error.message;
            }
        }
        
        button.addEventListener('click', enableNotifications);
        
        // Check initial state
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                status.textContent = 'Notifications are already enabled';
                button.disabled = true;
            }
        } else {
            status.textContent = 'Notifications are not supported';
            button.disabled = true;
        }
    </script>
</body>
</html>