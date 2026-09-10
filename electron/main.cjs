const { app, BrowserWindow, Menu, ipcMain, shell, dialog, Notification, session } = require('electron');
const path = require('path');
const fs = require('fs');
const http = require('http');
const { autoUpdater } = require('electron-updater');

let mainWindow = null;
let isForceClosing = false;
let updateTimer = null;

const DEFAULT_CONFIG = {
    title: 'SIUPK Next Desktop',
    width: 1440,
    height: 900,
    minWidth: 1024,
    minHeight: 700,
    url: process.env.APP_URL 
        ? (process.env.APP_URL.includes('/login') ? process.env.APP_URL : `${process.env.APP_URL.replace(/\/$/, '')}/login`)
        : 'http://127.0.0.1:8000/login',
};

// Set App User Model ID for Windows Action Center Notifications
if (process.platform === 'win32') {
    app.setAppUserModelId('com.enpiistudio.siupk.desktop');
}


function initializeSqlitePath() {
    if (!process.env.DESKTOP_SQLITE_PATH) {
        const userDataDir = app.getPath('userData');
        const dbDir = path.join(userDataDir, 'database');
        const targetSqlitePath = path.join(dbDir, 'database.sqlite');

        if (!fs.existsSync(dbDir)) {
            fs.mkdirSync(dbDir, { recursive: true });
        }

        const legacyLocations = [
            path.join(app.getAppPath(), 'database', 'database.sqlite'),
            path.resolve(__dirname, '../database/database.sqlite'),
        ];

        if (!fs.existsSync(targetSqlitePath)) {
            for (const legacyPath of legacyLocations) {
                if (fs.existsSync(legacyPath)) {
                    try {
                        fs.copyFileSync(legacyPath, targetSqlitePath);
                        console.info(`[sqlite] Migrated legacy database from ${legacyPath} to ${targetSqlitePath}`);
                        break;
                    } catch (err) {
                        console.error(`[sqlite] Failed to migrate legacy database from ${legacyPath}:`, err);
                    }
                }
            }
        }

        process.env.DESKTOP_SQLITE_PATH = targetSqlitePath;
    }
}

function backupSqliteBeforeUpdate() {
    try {
        const sqlitePath = process.env.DESKTOP_SQLITE_PATH;
        if (!sqlitePath || !fs.existsSync(sqlitePath)) {
            console.warn('[backup] SQLite database file not found for pre-update backup:', sqlitePath);
            return;
        }

        const backupDir = path.join(path.dirname(sqlitePath), 'backups');
        if (!fs.existsSync(backupDir)) {
            fs.mkdirSync(backupDir, { recursive: true });
        }

        const filename = path.basename(sqlitePath);
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const timestamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
        const backupFileName = `${filename}.bak-${timestamp}`;
        const backupPath = path.join(backupDir, backupFileName);

        fs.copyFileSync(sqlitePath, backupPath);
        console.info(`[backup] Pre-update SQLite backup created at ${backupPath}`);

        const files = fs.readdirSync(backupDir)
            .filter((file) => file.startsWith(`${filename}.bak-`))
            .map((file) => {
                const fullPath = path.join(backupDir, file);
                return {
                    path: fullPath,
                    mtime: fs.statSync(fullPath).mtimeMs,
                };
            })
            .sort((a, b) => b.mtime - a.mtime);

        if (files.length > 3) {
            const toDelete = files.slice(3);
            for (const item of toDelete) {
                try {
                    fs.unlinkSync(item.path);
                    console.info(`[backup] Pruned older backup: ${item.path}`);
                } catch (err) {
                    console.error(`[backup] Failed to prune backup ${item.path}:`, err);
                }
            }
        }
    } catch (error) {
        console.error('[backup] Failed to create pre-update SQLite backup:', error);
    }
}

function createWindow() {
    mainWindow = new BrowserWindow({
        title: DEFAULT_CONFIG.title,
        width: DEFAULT_CONFIG.width,
        height: DEFAULT_CONFIG.height,
        minWidth: DEFAULT_CONFIG.minWidth,
        minHeight: DEFAULT_CONFIG.minHeight,
        frame: false, // Frameless window (custom app bar)
        titleBarStyle: 'hidden',
        show: false,
        backgroundColor: '#0f172a',
        webPreferences: {
            preload: path.join(__dirname, 'preload.cjs'),
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: false,
        },
    });

    // Attach desktop client identification header to all requests
    session.defaultSession.webRequest.onBeforeSendHeaders((details, callback) => {
        details.requestHeaders['X-Desktop-Client'] = '1';
        callback({ cancel: false, requestHeaders: details.requestHeaders });
    });

    let appUrl = process.env.APP_URL || DEFAULT_CONFIG.url;
    if (!appUrl.includes('/login') && !appUrl.includes('/dashboard')) {
        appUrl = `${appUrl.replace(/\/$/, '')}/login`;
    }
    mainWindow.loadURL(appUrl);

    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
    });

    mainWindow.on('maximize', () => {
        mainWindow?.webContents.send('desktop:maximize-change', true);
    });

    mainWindow.on('unmaximize', () => {
        mainWindow?.webContents.send('desktop:maximize-change', false);
    });

    // Intercept native close to allow graceful logout
    mainWindow.on('close', (event) => {
        if (!isForceClosing) {
            event.preventDefault();
            mainWindow.webContents.send('desktop:window-close-requested');

            // Safety timeout to force close if renderer hangs
            setTimeout(() => {
                isForceClosing = true;
                if (mainWindow && !mainWindow.isDestroyed()) {
                    mainWindow.destroy();
                }
            }, 1800);
        }
    });

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (url.startsWith('http:') || url.startsWith('https:')) {
            shell.openExternal(url);
            return { action: 'deny' };
        }
        return { action: 'allow' };
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    // Remove native application menu bar
    Menu.setApplicationMenu(null);
}

// Window Control IPC Handlers
ipcMain.on('desktop:window-minimize', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.minimize();
    }
});

function sendUpdateEvent(channel, payload = {}) {
    if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.webContents.send(channel, payload);
    }
}

function requireRestartAfterUpdate(force = false) {
    if (Notification.isSupported()) {
        new Notification({
            title: 'SIUPK Next Desktop',
            body: 'Update baru sudah diunduh. Restart untuk menerapkan pembaruan.',
        }).show();
    }

    const options = {
        type: 'question',
        title: 'Update Siap',
        message: 'Restart untuk update sekarang?',
        detail: 'Data lokal SQLite dan antrean sinkronisasi tidak akan diubah.',
        buttons: ['Restart', 'Nanti'],
        defaultId: 0,
        cancelId: force ? 0 : 1,
        noLink: true,
    };

    if (force) {
        options.closeable = false;
    }

    dialog.showMessageBox(mainWindow, options).then(({ response }) => {
        if (response === 0) {
            backupSqliteBeforeUpdate();
            autoUpdater.quitAndInstall();
        }
    }).catch(() => {});
}

function initializeAutoUpdater() {
    if (!app.isPackaged) {
        console.info('[updater] disabled in development');
        return;
    }

    autoUpdater.autoDownload = true;
    autoUpdater.on('update-available', (info) => {
        sendUpdateEvent('update:available', info);
    });
    autoUpdater.on('download-progress', (progress) => {
        sendUpdateEvent('update:download-progress', progress);
    });
    autoUpdater.on('update-downloaded', (info) => {
        sendUpdateEvent('update:downloaded', info);
        requireRestartAfterUpdate(false);
    });
    autoUpdater.on('error', (error) => {
        sendUpdateEvent('update:error', { message: error?.message || 'Unknown update error' });
    });
}

async function checkForUpdates(force = false) {
    if (!app.isPackaged) {
        console.info('[updater] update check skipped in development');
        return null;
    }

    sendUpdateEvent('update:checking', { force });
    return autoUpdater.checkForUpdates();
}

ipcMain.on('desktop:window-maximize', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
        if (mainWindow.isMaximized()) {
            mainWindow.unmaximize();
        } else {
            mainWindow.maximize();
        }
    }
});

ipcMain.on('desktop:window-close', () => {
    isForceClosing = true;
    if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.destroy();
    }
});

ipcMain.handle('desktop:is-maximized', () => {
    return mainWindow ? mainWindow.isMaximized() : false;
});

ipcMain.handle('update:check', (_, force = false) => checkForUpdates(Boolean(force)));

// Native OS Push Notification Handler (Zero Firebase required)
ipcMain.on('desktop:send-notification', (_, { title, body, icon, url }) => {
    if (Notification.isSupported()) {
        const iconPath = icon ? path.resolve(icon) : path.join(__dirname, '../public/favicon.ico');
        const notif = new Notification({
            title: title || 'SIUPK Next Desktop',
            body: body || '',
            icon: iconPath,
            silent: false,
        });

        if (url) {
            notif.on('click', () => {
                if (mainWindow) {
                    if (mainWindow.isMinimized()) mainWindow.restore();
                    mainWindow.show();
                    mainWindow.focus();
                    if (url.startsWith('/')) {
                        mainWindow.webContents.send('desktop:navigate', url);
                    }
                }
            });
        }

        notif.show();
    }
});

ipcMain.handle('desktop:get-info', () => {
    return {
        platform: process.platform,
        arch: process.arch,
        version: app.getVersion(),
        isDesktop: true,
        electronVersion: process.versions.electron,
        nodeVersion: process.versions.node,
        notificationsSupported: Notification.isSupported(),
    };
});

ipcMain.handle('desktop:check-connectivity', async (_, urlToCheck) => {
    const target = urlToCheck || 'https://www.google.com';
    return new Promise((resolve) => {
        try {
            const req = http.get(target, (res) => {
                resolve(res.statusCode >= 200 && res.statusCode < 400);
            });
            req.on('error', () => resolve(false));
            req.setTimeout(4000, () => {
                req.destroy();
                resolve(false);
            });
        } catch {
            resolve(false);
        }
    });
});

app.whenReady().then(() => {
    initializeSqlitePath();
    createWindow();
    initializeAutoUpdater();

    mainWindow?.webContents.once('did-finish-load', () => {
        setTimeout(() => {
            checkForUpdates().catch(() => {});
        }, 5000);
    });

    updateTimer = setInterval(() => {
        checkForUpdates().catch(() => {});
    }, 6 * 60 * 60 * 1000);

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (updateTimer) {
        clearInterval(updateTimer);
    }
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
