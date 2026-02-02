<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Movies</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 100%;
            padding: 16px;
        }

        .header {
            background: var(--tg-theme-secondary-bg-color, #f4f4f5);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .header h2 {
            color: var(--tg-theme-text-color, #000000);
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .bot-button {
            display: inline-block;
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #ffffff);
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .bot-button:active {
            opacity: 0.8;
        }

        .movies-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
        }

        .movies-table thead {
            background: var(--tg-theme-secondary-bg-color, #f4f4f5);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .movies-table thead tr th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: var(--tg-theme-text-color, #000000);
            font-size: 14px;
            border-bottom: 2px solid var(--tg-theme-hint-color, #999999);
        }

        .movies-table tbody tr {
            background: var(--tg-theme-bg-color, #ffffff);
            border-bottom: 1px solid var(--tg-theme-section-separator-color, #e4e4e4);
            transition: background-color 0.2s;
        }

        .movies-table tbody tr:hover {
            background: var(--tg-theme-secondary-bg-color, #f9f9f9);
        }

        .movies-table tbody tr td {
            padding: 12px 8px;
            color: var(--tg-theme-text-color, #000000);
            font-size: 14px;
        }

        .movies-table tbody tr td:first-child {
            font-weight: 600;
            color: var(--tg-theme-hint-color, #999999);
            width: 50px;
        }

        .movie-name {
            font-weight: 500;
            color: var(--tg-theme-link-color, #3390ec);
        }

        .movie-code {
            background: var(--tg-theme-secondary-bg-color, #f4f4f5);
            padding: 4px 8px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            display: inline-block;
        }

        .movie-caption {
            color: var(--tg-theme-hint-color, #999999);
            font-size: 13px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            padding: 20px 0;
            gap: 8px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            background: var(--tg-theme-secondary-bg-color, #f4f4f5);
            color: var(--tg-theme-text-color, #000000);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #ffffff);
        }

        .pagination .active {
            background: var(--tg-theme-button-color, #3390ec);
            color: var(--tg-theme-button-text-color, #ffffff);
            font-weight: 600;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--tg-theme-hint-color, #999999);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 600px) {

            .movies-table thead tr th,
            .movies-table tbody tr td {
                padding: 10px 6px;
                font-size: 13px;
            }

            .movie-caption {
                max-width: 120px;
            }

            .header h2 {
                font-size: 20px;
            }
        }

        /* Dark theme optimization */
        @media (prefers-color-scheme: dark) {
            .movie-code {
                background: rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>🎬 Kinolar ro'yxati</h2>
            <button class="bot-button" onclick="window.open('https://t.me/{{ env('TELEGRAM_BOT_URL') }}', '_blank')">
                📱 Botga o'tish
            </button>
        </div>

        @if (count($movies) > 0)
            <table class="movies-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nomi</th>
                        <th>Kodi</th>
                        <th>Tavsif</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movies as $movie)
                        <tr>
                            <td>{{ $movie->id }}</td>
                            <td class="movie-name">{{ $movie->name }}</td>
                            <td><span class="movie-code">{{ $movie->code }}</span></td>
                            <td>
                                <div class="movie-info">
                                    @php
                                        // O'lchami olish
preg_match('/📏.*?(\d+\s*×\s*\d+)/u', $movie->caption, $size);
// Davomiyligi olish
preg_match('/⏳.*?(\d+:\d+)/u', $movie->caption, $duration);
// Hajmi olish
preg_match('/💽.*?([\d.]+\s*[KMG]B)/u', $movie->caption, $filesize);
                                    @endphp

                                    @if (isset($size[1]))
                                        <span class="info-item">📏 {{ $size[1] }}</span>
                                    @endif

                                    @if (isset($duration[1]))
                                        <span class="info-item">⏳ {{ $duration[1] }}</span>
                                    @endif

                                    @if (isset($filesize[1]))
                                        <span class="info-item">💽 {{ $filesize[1] }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $movies->links() }}
            </div>
        @else
            <div class="empty-state">
                <svg fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm2 0v8h12V6H4z" />
                </svg>
                <p>Hozircha kinolar mavjud emas</p>
            </div>
        @endif
    </div>

    <script>
        // Telegram WebApp initialization
        let tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();

        // Set theme
        document.body.style.backgroundColor = tg.themeParams.bg_color || '#ffffff';

        // Optional: Add haptic feedback on button clicks
        document.querySelectorAll('button, a').forEach(element => {
            element.addEventListener('click', () => {
                if (tg.HapticFeedback) {
                    tg.HapticFeedback.impactOccurred('light');
                }
            });
        });
    </script>
</body>

</html>
