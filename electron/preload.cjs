const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktopAPI', {
    isDesktop: true,
    platform: process.platform,
    getAppInfo: () => ipcRenderer.invoke('desktop:get-info'),
    checkConnectivity: (url) => ipcRenderer.invoke('desktop:check-connectivity', url),

    // Automatic update channel
    checkForUpdates: (force = false) => ipcRenderer.invoke('update:check', force),
    onUpdateAvailable: (callback) => {
        const listener = (_, info) => callback(info);
        ipcRenderer.on('update:available', listener);
        return () => ipcRenderer.removeListener('update:available', listener);
    },
    onUpdateDownloadProgress: (callback) => {
        const listener = (_, progress) => callback(progress);
        ipcRenderer.on('update:download-progress', listener);
        return () => ipcRenderer.removeListener('update:download-progress', listener);
    },
    onUpdateDownloaded: (callback) => {
        const listener = (_, info) => callback(info);
        ipcRenderer.on('update:downloaded', listener);
        return () => ipcRenderer.removeListener('update:downloaded', listener);
    },
    onUpdateError: (callback) => {
        const listener = (_, error) => callback(error);
        ipcRenderer.on('update:error', listener);
        return () => ipcRenderer.removeListener('update:error', listener);
    },
    
    // Native OS Push Notifications (No Firebase needed)
    sendNotification: ({ title, body, icon, url }) => {
        ipcRenderer.send('desktop:send-notification', { title, body, icon, url });
    },
    
    // Window control actions
    minimize: () => ipcRenderer.send('desktop:window-minimize'),
    maximize: () => ipcRenderer.send('desktop:window-maximize'),
    close: () => ipcRenderer.send('desktop:window-close'),
    isMaximized: () => ipcRenderer.invoke('desktop:is-maximized'),
    onMaximizeChange: (callback) => {
        const listener = (_, val) => callback(val);
        ipcRenderer.on('desktop:maximize-change', listener);
        return () => ipcRenderer.removeListener('desktop:maximize-change', listener);
    },
    onCloseRequested: (callback) => {
        const listener = () => callback();
        ipcRenderer.on('desktop:window-close-requested', listener);
        return () => ipcRenderer.removeListener('desktop:window-close-requested', listener);
    },
    onNavigate: (callback) => {
        const listener = (_, url) => callback(url);
        ipcRenderer.on('desktop:navigate', listener);
        return () => ipcRenderer.removeListener('desktop:navigate', listener);
    },
});
