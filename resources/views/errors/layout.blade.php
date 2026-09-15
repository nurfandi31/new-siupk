<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#002746">
    <meta name="apple-mobile-web-app-title" content="siupk Next">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%230284c7'/%3E%3Ctext x='50%25' y='54%25' text-anchor='middle' dominant-baseline='middle' font-family='Arial,sans-serif' font-size='38' font-weight='800' fill='white'%3ES%3C/text%3E%3C/svg%3E">

    <title>@yield('title', 'Terjadi Kesalahan') - {{ config('app.name', 'siupk Next') }}</title>

    <!-- Google Fonts & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Material+Symbols+Outlined:opsz,wght,FILL@20..48,100..700,0..1" rel="stylesheet">

    <script>
        (function () {
            try {
                var t = localStorage.getItem('siupk-theme');
                var ok = { classic:1, forest:1, amber:1, violet:1, ocean:1, rose:1, midnight:1 };
                if (t && ok[t]) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>

    <style>
        :root {
            --font-sans: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --color-primary: #002746;
            --color-primary-deep: #001d36;
            --color-primary-container: #0b3d66;
            --color-on-primary: #ffffff;
            --color-secondary: #006d3d;
            --color-secondary-container: #97f3b5;
            --color-on-secondary: #ffffff;
            --color-surface: #f8fafc;
            --color-card: #ffffff;
            --color-text-main: #0f172a;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --color-error: #ba1a1a;
            --color-error-container: #ffdad6;
            --color-warning: #d97706;
            --color-info: #0284c7;
        }

        [data-theme="forest"] {
            --color-primary: #0b3d2a;
            --color-primary-deep: #062819;
            --color-primary-container: #145c3f;
            --color-secondary: #0b6e99;
            --color-secondary-container: #b8e6fb;
            --color-surface: #f4faf6;
        }

        [data-theme="amber"] {
            --color-primary: #3b2818;
            --color-primary-deep: #291b10;
            --color-primary-container: #5d3f27;
            --color-secondary: #006d3d;
            --color-secondary-container: #a3f7c4;
            --color-surface: #fbf8f3;
        }

        [data-theme="midnight"] {
            --color-primary: #0f172a;
            --color-primary-deep: #020617;
            --color-primary-container: #1e293b;
            --color-secondary: #10b981;
            --color-secondary-container: #064e3b;
            --color-surface: #0b0f17;
            --color-card: #131b2a;
            --color-text-main: #f1f5f9;
            --color-text-muted: #94a3b8;
            --color-border: #1e293b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--color-surface);
            color: var(--color-text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient Glow Orbs */
        .ambient-glow-1 {
            position: fixed;
            top: -10vw;
            left: -10vw;
            width: 40vw;
            height: 40vw;
            min-width: 300px;
            min-height: 300px;
            background: radial-gradient(circle, rgba(0, 39, 70, 0.08) 0%, rgba(0, 39, 70, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .ambient-glow-2 {
            position: fixed;
            bottom: -10vw;
            right: -10vw;
            width: 45vw;
            height: 45vw;
            min-width: 350px;
            min-height: 350px;
            background: radial-gradient(circle, rgba(0, 109, 61, 0.08) 0%, rgba(0, 109, 61, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        /* Header Navbar */
        .error-navbar {
            position: relative;
            z-index: 10;
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }

        .brand-logo-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-container) 100%);
            color: #ffffff;
            display: grid;
            place-items: center;
            box-shadow: 0 4px 12px rgba(0, 39, 70, 0.15);
        }

        .brand-text-title {
            font-size: 1.125rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.2;
            color: var(--color-primary);
        }

        [data-theme="midnight"] .brand-text-title {
            color: #ffffff;
        }

        .brand-text-badge {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--color-secondary);
        }

        .system-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 0, 0, 0.04);
            border: 1px solid var(--color-border);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-text-muted);
        }

        [data-theme="midnight"] .system-status-pill {
            background: rgba(255, 255, 255, 0.06);
        }

        .status-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--color-warning);
            box-shadow: 0 0 8px var(--color-warning);
        }

        /* Main Container */
        .main-wrapper {
            position: relative;
            z-index: 10;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        .error-card {
            background-color: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: 1.75rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.07), 0 0 1px 1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            transition: all 0.3s ease;
        }

        @media (min-width: 900px) {
            .error-card {
                grid-template-columns: 42% 58%;
            }
        }

        /* Left Visual Stage */
        .visual-stage {
            background: linear-gradient(145deg, rgba(0, 39, 70, 0.03) 0%, rgba(0, 109, 61, 0.05) 100%);
            border-bottom: 1px solid var(--color-border);
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 900px) {
            .visual-stage {
                border-bottom: none;
                border-right: 1px solid var(--color-border);
                padding: 4rem 2.5rem;
            }
        }

        .code-watermark {
            position: absolute;
            font-size: 11rem;
            font-weight: 900;
            line-height: 1;
            color: rgba(0, 39, 70, 0.04);
            user-select: none;
            z-index: 0;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            letter-spacing: -0.05em;
        }

        [data-theme="midnight"] .code-watermark {
            color: rgba(255, 255, 255, 0.03);
        }

        .illustration-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 260px;
            margin: 0 auto 1.5rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.08));
            animation: floatSlow 4s ease-in-out infinite alternate;
        }

        @keyframes floatSlow {
            0% { transform: translateY(0px); }
            100% { transform: translateY(-8px); }
        }

        .error-code-badge {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1.1rem;
            border-radius: 9999px;
            font-weight: 800;
            font-size: 0.9rem;
            letter-spacing: 0.05em;
            background: #ffffff;
            color: var(--color-primary);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--color-border);
        }

        [data-theme="midnight"] .error-code-badge {
            background: #1e293b;
            color: #ffffff;
            border-color: #334155;
        }

        /* Right Content Stage */
        .content-stage {
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @media (min-width: 900px) {
            .content-stage {
                padding: 3.5rem 3rem;
            }
        }

        .error-category {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--color-secondary);
            margin-bottom: 0.6rem;
        }

        .error-heading {
            font-size: 1.875rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.25;
            color: var(--color-text-main);
            margin-bottom: 0.85rem;
        }

        @media (min-width: 640px) {
            .error-heading {
                font-size: 2.25rem;
            }
        }

        .error-description {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--color-text-muted);
            margin-bottom: 1.5rem;
        }

        /* Custom Exception Callout */
        .exception-notice {
            background-color: rgba(186, 26, 26, 0.05);
            border-left: 4px solid var(--color-error);
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #7f1d1d;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            word-break: break-word;
        }

        [data-theme="midnight"] .exception-notice {
            background-color: rgba(186, 26, 26, 0.2);
            color: #fca5a5;
        }

        /* Recommendation Checklist */
        .recommendations-box {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 0.875rem;
            padding: 1.15rem 1.25rem;
            margin-bottom: 1.75rem;
        }

        .recommendations-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-main);
            margin-bottom: 0.65rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .recommendation-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .recommendation-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--color-text-muted);
            line-height: 1.4;
        }

        .recommendation-icon {
            font-size: 1.1rem;
            color: var(--color-secondary);
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Action Buttons */
        .actions-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.35rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 0.75rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-container) 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 39, 70, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 39, 70, 0.28);
            filter: brightness(1.08);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--color-text-main);
            border-color: var(--color-border);
        }

        .btn-secondary:hover {
            background-color: rgba(0, 0, 0, 0.04);
            border-color: #cbd5e1;
        }

        [data-theme="midnight"] .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .btn-ghost {
            background-color: transparent;
            color: var(--color-text-muted);
            padding: 0.75rem 1rem;
        }

        .btn-ghost:hover {
            color: var(--color-primary);
            background-color: rgba(0, 39, 70, 0.04);
        }

        /* Diagnostic Info Panel */
        .diagnostic-panel {
            border-top: 1px solid var(--color-border);
            padding-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--color-text-muted);
        }

        .diag-item {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .diag-copy-btn {
            background: none;
            border: 1px solid var(--color-border);
            border-radius: 0.4rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--color-text-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
        }

        .diag-copy-btn:hover {
            background-color: rgba(0, 0, 0, 0.04);
            color: var(--color-text-main);
        }

        /* Debug Stack Trace (Debug Mode only) */
        .debug-details {
            margin-top: 1rem;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Footer */
        .error-footer {
            position: relative;
            z-index: 10;
            padding: 1.5rem 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--color-text-muted);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
            font-size: inherit;
        }

        /* Toast notification */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #0f172a;
            color: #ffffff;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            z-index: 100;
        }

        #toast.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <!-- Top Navigation Brand Bar -->
    <header class="error-navbar">
        <a href="/" class="brand-link" title="siupk Next">
            <div class="brand-logo-icon">
                <span class="material-symbols-outlined" style="font-size: 1.5rem;">account_balance</span>
            </div>
            <div>
                <p class="brand-text-title">siupk <span style="color: var(--color-secondary);">Next</span></p>
                <p class="brand-text-badge">BUMDesma LKD Financial System</p>
            </div>
        </a>

        <div class="system-status-pill">
            <span class="status-dot"></span>
            <span>HTTP @yield('code', 'ERR') &bull; @yield('status_text', 'Error')</span>
        </div>
    </header>

    <!-- Main Content Stage -->
    <main class="main-wrapper">
        <div class="error-card">
            <!-- Left Visual Stage -->
            <div class="visual-stage">
                <div class="code-watermark" aria-hidden="true">@yield('code', '500')</div>
                
                <div class="illustration-container">
                    @yield('illustration')
                </div>

                <div class="error-code-badge">
                    <span class="material-symbols-outlined" style="color: var(--color-secondary); font-size: 1.1rem;">@yield('badge_icon', 'error')</span>
                    <span>Status @yield('code', '500')</span>
                </div>
            </div>

            <!-- Right Content Stage -->
            <div class="content-stage">
                <div class="error-category">
                    <span class="material-symbols-outlined" style="font-size: 0.95rem;">@yield('category_icon', 'info')</span>
                    <span>@yield('category', 'Sistem & Navigasi')</span>
                </div>

                <h1 class="error-heading">@yield('title', 'Terjadi Kesalahan')</h1>

                <p class="error-description">
                    @yield('message', 'Permintaan Anda tidak dapat diproses saat ini. Silakan coba kembali beberapa saat lagi.')
                </p>

                @if(!empty($exception) && $exception->getMessage() && !in_array($exception->getMessage(), ['Forbidden', 'Unauthorized', 'Not Found', 'Internal Server Error', 'Page Expired', 'Too Many Requests', 'Service Unavailable']))
                    <div class="exception-notice">
                        <span class="material-symbols-outlined" style="font-size: 1.2rem; flex-shrink: 0;">report_problem</span>
                        <div>
                            <strong>Catatan Sistem:</strong> {{ $exception->getMessage() }}
                        </div>
                    </div>
                @endif

                <!-- Recommendations / Action Steps -->
                <div class="recommendations-box">
                    <div class="recommendations-title">
                        <span class="material-symbols-outlined" style="color: var(--color-secondary); font-size: 1rem;">lightbulb</span>
                        <span>Langkah yang disarankan</span>
                    </div>
                    <ul class="recommendation-list">
                        @section('recommendations')
                            <li class="recommendation-item">
                                <span class="material-symbols-outlined recommendation-icon">check_circle</span>
                                <span>Periksa kembali alamat URL atau tombol yang Anda tuju.</span>
                            </li>
                            <li class="recommendation-item">
                                <span class="material-symbols-outlined recommendation-icon">check_circle</span>
                                <span>Muat ulang halaman untuk memastikan koneksi dan status sesi terkini.</span>
                            </li>
                            <li class="recommendation-item">
                                <span class="material-symbols-outlined recommendation-icon">check_circle</span>
                                <span>Hubungi tim pengelola/administrator jika kendala ini terus berulang.</span>
                            </li>
                        @show
                    </ul>
                </div>

                <!-- Main Actions -->
                <div class="actions-group">
                    @section('actions')
                        <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                            <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
                            <span>Ke Dashboard</span>
                        </a>

                        <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn btn-secondary">
                            <span class="material-symbols-outlined" style="font-size: 1.15rem;">arrow_back</span>
                            <span>Halaman Sebelumnya</span>
                        </button>

                        <button onclick="window.location.reload()" class="btn btn-ghost" title="Muat ulang halaman">
                            <span class="material-symbols-outlined" style="font-size: 1.15rem;">refresh</span>
                            <span>Refresh</span>
                        </button>
                    @show
                </div>

                <!-- Diagnostic & Support Info -->
                <div class="diagnostic-panel">
                    <div class="diag-item">
                        <span class="material-symbols-outlined" style="font-size: 0.95rem;">schedule</span>
                        <span>{{ now()->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i:s') }} WIB</span>
                    </div>
                    <div class="diag-item">
                        <span class="material-symbols-outlined" style="font-size: 0.95rem;">link</span>
                        <span style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">/{{ ltrim(request()->path(), '/') }}</span>
                    </div>
                    <button class="diag-copy-btn" onclick="copyDiagnosticInfo()">
                        <span class="material-symbols-outlined" style="font-size: 0.85rem;">content_copy</span>
                        <span>Salin Info Error</span>
                    </button>
                </div>

                @if(config('app.debug') && !empty($exception))
                    <details style="margin-top: 1rem;">
                        <summary style="font-size: 0.75rem; color: var(--color-error); font-weight: 600; cursor: pointer; padding: 0.25rem 0;">
                            Lihat Detail Teknis (Debug Mode Aktif)
                        </summary>
                        <div class="debug-details">
                            <strong>{{ get_class($exception) }}</strong>: {{ $exception->getMessage() ?: 'No message' }}<br>
                            File: {{ $exception->getFile() }}:{{ $exception->getLine() }}<br><br>
                            {{ $exception->getTraceAsString() }}
                        </div>
                    </details>
                @endif
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="error-footer">
        <p>&copy; {{ date('Y') }} <strong>siupk Next</strong> &bull; Tata Kelola Dana Bergulir BUMDesma LKD &bull; Standar SAK EP & PP No. 11/2021</p>
    </footer>

    <!-- Toast Component -->
    <div id="toast">
        <span class="material-symbols-outlined" style="color: var(--color-secondary-container); font-size: 1.2rem;">check_circle</span>
        <span id="toast-message">Info error berhasil disalin!</span>
    </div>

    <script>
        function showToast(message) {
            var toast = document.getElementById('toast');
            var msgEl = document.getElementById('toast-message');
            msgEl.innerText = message || 'Berhasil disalin ke clipboard';
            toast.classList.add('show');
            setTimeout(function () {
                toast.classList.remove('show');
            }, 3000);
        }

        function copyDiagnosticInfo() {
            var info = [
                '=== siupk Next Error Report ===',
                'Status Code: @yield("code", "500")',
                'Error Title: @yield("title", "Terjadi Kesalahan")',
                'Path: ' + window.location.pathname,
                'Timestamp: ' + new Date().toISOString(),
                'User Agent: ' + navigator.userAgent
            ].join('\n');

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(info).then(function() {
                    showToast('Info error berhasil disalin!');
                }).catch(function() {
                    fallbackCopyText(info);
                });
            } else {
                fallbackCopyText(info);
            }
        }

        function fallbackCopyText(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showToast('Info error berhasil disalin!');
            } catch (err) {
                alert('Gagal menyalin info error.');
            }
            document.body.removeChild(textArea);
        }
    </script>
</body>
</html>
