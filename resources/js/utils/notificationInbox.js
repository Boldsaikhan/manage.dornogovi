/**
 * In-app мэдэгдлийн жагсаалт (localStorage) — push ирэхэд SW-ээс нэмэгдэнэ.
 */
const STORAGE_KEY = 'md_notifications_v1';
const MAX = 40;

export function loadNotifications() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const list = raw ? JSON.parse(raw) : [];

        return Array.isArray(list) ? list : [];
    } catch {
        return [];
    }
}

export function saveNotifications(list) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX)));
    } catch {
        // ignore
    }
}

export function addNotification(item) {
    const list = loadNotifications();
    const entry = {
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        title: item.title || 'Мэдэгдэл',
        body: item.body || '',
        url: item.url || '/dept-dashboard',
        at: item.at || new Date().toISOString(),
        read: false,
    };
    list.unshift(entry);
    saveNotifications(list);

    return entry;
}

export function unreadCount(list = loadNotifications()) {
    return list.filter((n) => ! n.read).length;
}

export function markOneRead(id, list = loadNotifications()) {
    const next = list.map((n) => (n.id === id ? { ...n, read: true } : n));
    saveNotifications(next);

    return next;
}

export function markAllRead(list = loadNotifications()) {
    const next = list.map((n) => ({ ...n, read: true }));
    saveNotifications(next);

    return next;
}

export function clearNotifications() {
    saveNotifications([]);

    return [];
}
