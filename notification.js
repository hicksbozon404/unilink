// notifications.js
class NotificationManager {
    constructor() {
        this.permission = null;
        this.swRegistration = null;
        this.pollingInterval = null;
        this.init();
    }

    async init() {
        await this.registerServiceWorker();
        await this.requestPermission();
        this.startPolling();
        this.setupUI();
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registered');
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }

    async requestPermission() {
        if (!('Notification' in window)) {
            console.log('This browser does not support notifications');
            return;
        }

        this.permission = Notification.permission;

        if (this.permission === 'default') {
            this.permission = await Notification.requestPermission();
        }

        if (this.permission === 'granted') {
            console.log('Notification permission granted');
            this.subscribeToPush();
        }
    }

    async subscribeToPush() {
        if (!this.swRegistration) return;

        try {
            console.log('Requesting push subscription...');
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array('BBZ3uxDfGe-7IVJijrzxPESc12ffMjC-11RqR7TKnSDKTvuiI_Y-wxsqo7jnpuECGM4AU2JZMQU2seJGVAcO_TI')
            });

            console.log('Push subscription obtained:', subscription);

            // Send subscription to server
            await this.saveSubscription(subscription);
        } catch (error) {
            console.error('Failed to subscribe to push:', error);
        }
    }

    async saveSubscription(subscription) {
        console.log('Saving subscription to server:', subscription);
        try {
            const response = await fetch('/api/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ subscription })
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('Failed to save subscription:', response.status, text);
                return { success: false, status: response.status, message: text };
            }

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error saving subscription:', error);
            return { success: false, error: error.message || error };
        }
    }

    startPolling() {
        // Check for new notifications every 30 seconds
        this.pollingInterval = setInterval(() => {
            this.checkNewNotifications();
        }, 30000);

        // Initial check
        this.checkNewNotifications();
    }

    async checkNewNotifications() {
        try {
            const response = await fetch('/api/get-notifications.php?unread=true');
            const notifications = await response.json();

            if (notifications.length > 0) {
                this.showDesktopNotifications(notifications);
                this.updateNotificationBadge(notifications.length);
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    showDesktopNotifications(notifications) {
        if (this.permission !== 'granted') return;

        notifications.forEach(notification => {
            if (this.shouldShowNotification(notification)) {
                this.showNotification(notification);
            }
        });
    }

    shouldShowNotification(notification) {
        // Check if notification is recent (last 5 minutes)
        const notificationTime = new Date(notification.created_at);
        const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000);
        return notificationTime > fiveMinutesAgo;
    }

    showNotification(notification) {
        const options = {
            body: notification.message,
            icon: this.getNotificationIcon(notification.type),
            badge: '/icons/badge-72x72.png',
            tag: notification.notification_id,
            renotify: true,
            data: {
                url: notification.action_url || '/'
            }
        };

        if ('serviceWorker' in navigator) {
            this.swRegistration.showNotification(notification.title, options);
        } else {
            // Fallback to regular notifications
            const notif = new Notification(notification.title, options);
            
            notif.onclick = () => {
                window.focus();
                if (notification.action_url) {
                    window.location.href = notification.action_url;
                }
                notif.close();
            };
        }

        // Mark as read on server
        this.markAsRead(notification.notification_id);
    }

    getNotificationIcon(type) {
        const icons = {
            info: '/icons/info.png',
            success: '/icons/success.png',
            warning: '/icons/warning.png',
            error: '/icons/error.png',
            marketplace: '/icons/marketplace.png',
            academic: '/icons/academic.png'
        };
        return icons[type] || icons.info;
    }

    updateNotificationBadge(count) {
        // Update badge in UI
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }

        // Update browser tab title
        if (count > 0) {
            document.title = `(${count}) UniLink`;
        } else {
            document.title = 'UniLink';
        }
    }

    setupUI() {
        // Create notification bell in header
        this.createNotificationBell();
        
        // Listen for custom notification events
        document.addEventListener('new-notification', (event) => {
            this.handleCustomNotification(event.detail);
        });
    }

    createNotificationBell() {
        const navActions = document.querySelector('.nav-actions');
        if (!navActions) return;

        const notificationBell = document.createElement('div');
        notificationBell.className = 'notification-bell';
        notificationBell.innerHTML = `
            <button class="nav-btn notification-btn" id="notificationToggle">
                <i class="fas fa-bell"></i>
                <span class="notification-badge"></span>
            </button>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h4>Notifications</h4>
                    <button class="mark-all-read">Mark all as read</button>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="loading-notifications">Loading...</div>
                </div>
                <div class="notification-footer">
                    <a href="notifications.php">View All Notifications</a>
                </div>
            </div>
        `;

        navActions.insertBefore(notificationBell, navActions.firstChild);
        this.setupNotificationDropdown();
    }

    setupNotificationDropdown() {
        const toggle = document.getElementById('notificationToggle');
        const dropdown = document.getElementById('notificationDropdown');

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
            this.loadNotificationDropdown();
        });

        document.addEventListener('click', () => {
            dropdown.classList.remove('active');
        });

        // Mark all as read
        dropdown.querySelector('.mark-all-read').addEventListener('click', () => {
            this.markAllAsRead();
        });
    }

    async loadNotificationDropdown() {
        const list = document.getElementById('notificationList');
        list.innerHTML = '<div class="loading-notifications">Loading...</div>';

        try {
            const response = await fetch('/api/get-notifications.php?limit=10');
            const notifications = await response.json();

            if (notifications.length === 0) {
                list.innerHTML = '<div class="no-notifications">No notifications</div>';
                return;
            }

            list.innerHTML = notifications.map(notification => `
                <div class="notification-item ${notification.is_read ? 'read' : 'unread'}" 
                     data-id="${notification.notification_id}">
                    <div class="notification-icon">
                        <i class="fas ${this.getNotificationIconClass(notification.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                        <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                        <div class="notification-time">${this.formatTime(notification.created_at)}</div>
                    </div>
                    ${!notification.is_read ? '<div class="notification-dot"></div>' : ''}
                </div>
            `).join('');

            // Add click handlers
            list.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', () => {
                    const notificationId = item.dataset.id;
                    this.markAsRead(notificationId);
                    
                    if (notifications.find(n => n.notification_id == notificationId)?.action_url) {
                        window.location.href = notifications.find(n => n.notification_id == notificationId).action_url;
                    }
                });
            });

        } catch (error) {
            list.innerHTML = '<div class="error-notifications">Error loading notifications</div>';
        }
    }

    getNotificationIconClass(type) {
        const icons = {
            info: 'fa-info-circle',
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-times-circle',
            marketplace: 'fa-shopping-cart',
            academic: 'fa-graduation-cap'
        };
        return icons[type] || icons.info;
    }

    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    async markAsRead(notificationId) {
        try {
            await fetch('/api/mark-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            this.updateNotificationBadge(await this.getUnreadCount());
        } catch (error) {
            console.error('Error marking as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            await fetch('/api/mark-all-read.php', {
                method: 'POST'
            });
            
            this.updateNotificationBadge(0);
            this.loadNotificationDropdown();
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    async getUnreadCount() {
        try {
            const response = await fetch('/api/get-unread-count.php');
            const data = await response.json();
            return data.count;
        } catch (error) {
            return 0;
        }
    }

    handleCustomNotification(detail) {
        this.showNotification(detail);
    }

    // Utility function for VAPID key conversion
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
});