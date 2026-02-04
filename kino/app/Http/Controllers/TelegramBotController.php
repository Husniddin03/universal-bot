<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    private array $adminId;
    private string $channelId;

    public function __construct()
    {
        $this->adminId = explode(',', env('TELEGRAM_ADMIN_ID'));
        $this->channelId = env('TELEGRAM_CHANNEL_ID');
    }

    public function webhook()
    {
        $update = Telegram::getWebhookUpdate();

        $chatId = null;
        $text = null;
        $message = null;
        $video = null;

        if ($update->isType('message')) {
            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $text = $message->getText();
        } elseif ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            $message = $callbackQuery->getMessage();
            $chatId = $message->getChat()->getId();
            $text = $callbackQuery->getData();
        }

        $user = User::where('chat_id', $chatId)->first();

        if (!$user) {
            $firstName = $message->getFrom()->getFirstName(); // Ismi
            $lastName = $message->getFrom()->getLastName();   // Familiyasi (agar bo'lsa)
            $username = $message->getFrom()->getUsername();
            $user = User::create([
                'name' => $firstName . " " . $lastName,
                'email' => 'telegram_' . $chatId . '@kino.bot',
                'chat_id' => $chatId,
                'username' => $username,
                'password' => Hash::make($chatId)
            ]);
        }


        if (in_array($chatId, $this->adminId)) {

            $movie = Movie::latest()->first();

            /* ======================
                1. VIDEO YUBORILDI
            ====================== */
            if ($message->has('video')) {

                // Agar tugallanmagan kino bo‘lsa — STOP
                $movie = Movie::where('status', '!=', 'ready')->first();
                if ($movie) {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "⚠️ Avvalgi kino hali yakunlanmagan."
                    ]);
                }

                $video = $message->video;

                $caption  = "🎬 <b>Video haqida ma'lumot</b>\n";
                $caption .= "━━━━━━━━━━━━━━━━━━\n";
                $caption .= "⏳ <b>Davomiyligi:</b> " . gmdate("H:i:s", $video->duration) . "\n";
                $caption .= "💽 <b>Hajmi:</b> " . round($video->file_size / (1024 * 1024), 2) . " MB\n";
                $caption .= "📏 <b>O‘lchami:</b> {$video->width} × {$video->height}\n";
                $caption .= "━━━━━━━━━━━━━━━━━━\n";
                $caption .= "🤖 <b>Bot orqali davom etish:</b>\n";
                $caption .= "👉 <a href=\"https://t.me/" . env('TELEGRAM_BOT_URL') . "\">@" . env('TELEGRAM_BOT_URL') . "</a>\n";


                Movie::create([
                    'message_id' => $message->getMessageId(), // admin chatdagi video
                    'file_id' => $video->file_id,
                    'caption' => $caption,
                    'status' => 'waiting_name'
                ]);

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "🎬 Video qabul qilindi.\nIltimos, kino nomini yuboring."
                ]);
            }



            /* ======================
                2. KINO NOMI
            ====================== */
            $movie = Movie::where('status', 'waiting_name')->first();

            if ($movie && $text) {

                // Agar kanalga allaqachon yuborilgan bo‘lsa — STOP
                if ($movie->status === 'ready') {
                    return;
                }

                // 🔢 Kod
                $lastCode = Movie::whereNotNull('code')
                    ->orderByRaw('CAST(code AS UNSIGNED) DESC')
                    ->value('code');

                $newCode = $lastCode ? ((int)$lastCode + 1) : 100;

                // 📢 VIDEO FAQAT 1 MARTA COPY QILINADI
                $sent = Telegram::copyMessage([
                    'chat_id' => $this->channelId,
                    'from_chat_id' => $chatId,
                    'message_id' => $movie->message_id
                ]);

                // 📝 Captionni faqat TAHIRLAYMIZ
                $finalCaption =
                    "🎬 <b>Nomi:</b> {$text}\n" .
                    "🆔 <b>Kod:</b> {$newCode}\n\n" .
                    "━━━━━━━━━━━━━━━━━━\n\n" .
                    $movie->caption;

                Telegram::editMessageCaption([
                    'chat_id' => $this->channelId,
                    'message_id' => $sent->getMessageId(),
                    'caption' => $finalCaption,
                    'parse_mode' => 'HTML'
                ]);

                // 💾 MUHIM: status darhol ready qilinadi
                $movie->update([
                    'name' => $text,
                    'code' => $newCode,
                    'message_id' => $sent->getMessageId(),
                    'status' => 'ready'
                ]);

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "✅ Kino kanalga joylandi!\n🆔 Kod: {$newCode}"
                ]);
            }
        }


        if (is_numeric($text)) {

            $movie = Movie::where('code', $text)
                ->where('status', 'ready')
                ->first();

            if ($movie) {

                try {
                    // 2. copyMessage orqali yuboramiz va reply_markup qo'shamiz
                    Telegram::copyMessage([
                        'chat_id' => $chatId,
                        'from_chat_id' => $this->channelId,
                        'message_id' => $movie->message_id,
                    ]);

                    return response('ok');
                } catch (\Exception $e) {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => '❌ Kino yuborishda xatolik yuz berdi.'
                    ]);
                }
            }

            // Kino topilmasa
            return Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Kino topilmadi'
            ]);
        } else if (filter_var($text, FILTER_VALIDATE_URL)) {

            if (isTelegramLink($text)) {

                // Invite link
                if (isInviteLink($text)) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => '🔗 Bu Telegram taklif linki qabul qilindi. Botga yozish huquqini bering, shunda bu xabarlarni saqlay oladi.'
                    ]);
                    $user->link = $text;
                    $user->save();
                    return response('ok', 200);
                }

                // Public kanal/guruh
                if (isPublicTelegram($text)) {

                    if (checkPublicChat($text)) {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => '✅ Haqiqiy Telegram kanal/guruh linki qabul qilindi. Botga yozish huquqini bering, shunda bu xabarlarni saqlay oladi.'
                        ]);
                        $user->link = $text;
                        $user->save();
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => '❌ Bunday kanal/guruh topilmadi'
                        ]);
                    }

                    return response('ok', 200);
                }
            } else {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Telegramga tegishli emas'
                ]);
            }


            // $escapedUrl = escapeshellarg($text);
            // $outputPath = storage_path('app/videos/video_' . time());

            // // Container ichidagi to'liq yo'l
            // $ytdlpPath = '/usr/local/bin/yt-dlp';

            // // Avval yt-dlp mavjudligini tekshirish
            // if (!file_exists($ytdlpPath)) {
            //     Log::error('yt-dlp topilmadi: ' . $ytdlpPath);
            //     Telegram::sendMessage([
            //         'chat_id' => $chatId,
            //         'text' => 'Server xatosi. Iltimos, keyinroq urinib ko\'ring.'
            //     ]);
            //     return response('error', 500);
            // }

            // $command = "{$ytdlpPath} " .
            //     "-f 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best' " .
            //     "--merge-output-format mp4 " .
            //     "-o {$outputPath}.mp4 " .
            //     "{$escapedUrl} 2>&1";

            // Log::info('Command: ' . $command);

            // exec($command, $output, $returnCode);

            // Log::info('Output: ' . implode("\n", $output));
            // Log::info('Return code: ' . $returnCode);

            // $videoPath = $outputPath . '.mp4';

            // if (!file_exists($videoPath)) {
            //     Telegram::sendMessage([
            //         'chat_id' => $chatId,
            //         'text' => 'Video yuklab olinmadi.'
            //     ]);
            //     return response('error', 400);
            // }

            // try {
            //     Telegram::sendVideo([
            //         'chat_id' => $chatId,
            //         'video' => InputFile::create($videoPath),
            //     ]);
            // } finally {
            //     if (file_exists($videoPath)) {
            //         unlink($videoPath);
            //     }
            // }

            return response('ok', 200);
        } else {
            if (strlen($text) < 3) {
                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Kamida 3 ta belgi kiriting!'
                ]);
            }

            // Umumiy xabar
            $defaultText = "🎬 Bot ishga tushdi!\n\nKino nomi yoki kodini kiriting.";

            // /start
            if (str_contains($text, "/start")) {
                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $defaultText
                ]);
            } elseif ($text === '/help') {

                // Admin bo‘lsa
                if (in_array($chatId, $this->adminId)) {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "🛠 Admin panel",
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => '🧩 Mini App',
                                        'web_app' => [
                                            'url' => env('TELEGRAM_WEBHOOK_URL')
                                        ]
                                    ],
                                    [
                                        'text' => 'Change Bot username',
                                        'callback_data' => "change_username"
                                    ]
                                ]
                            ]
                        ])
                    ]);
                }

                // Oddiy user
                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $defaultText
                ]);
            } elseif ($text === '/merge') {

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Botga guruh yoki kanalni biriktirib ijtimoiy tarmoqdan yuklab olgan videolaringizni automatik saqlashingiz mumkin. Buning uchun menga shunchki linkni yuboring!"
                ]);
            } elseif ($text === 'change_username') {
                foreach (Movie::all() as $movie) {
                    $caption = str_replace(env('TELEGRAM_BOT_URL'), env('TELEGRAM_BOT_CHANGE_URL'), $movie->caption);
                    Telegram::editMessageCaption([
                        'chat_id' => $this->channelId,
                        'message_id' => $movie->message_id,
                        'caption' => $caption,
                        'parse_mode' => 'HTML',
                    ]);
                    sleep(2);
                }
            }

            $page = 1;
            $message_id = null;

            // Agar bu callback query (tugma bosilishi) bo'lsa
            if (str_contains($text, "_page_")) {
                $arr = explode('_', $text);
                $text = $arr[0];      // Qidiruv so'zi
                $page = (int)$arr[2]; // Sahifa raqami
                // Callback query obyekti orqali xabar ID sini olamiz
                $message_id = $message->getMessageId();
            }

            $perPage = 10;
            $movies = Movie::where('name', 'LIKE', "%{$text}%")
                ->where('status', 'ready')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($movies->isEmpty()) {
                if ($text === 'change_username') {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'O\'zgartirildi'
                    ]);
                }
                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Kino topilmadi'
                ]);
            }

            $keyboard = [];
            $buttonsRow = [];
            $index = ($movies->currentPage() - 1) * $perPage + 1;
            $messageText = "🎬 <b>Topilgan kinolar (Sahifa: {$movies->currentPage()}):</b>\n\n";

            foreach ($movies as $movie) {
                $messageText .= "{$index}. Nomi: <b>{$movie->name}</b>\n";
                $messageText .= "🆔 Kod: {$movie->code}\n\n";

                $buttonsRow[] = [
                    'text' => (string)$index,
                    'callback_data' => $movie->code
                ];

                if (count($buttonsRow) === 5) {
                    $keyboard[] = $buttonsRow;
                    $buttonsRow = [];
                }
                $index++;
            }

            if (!empty($buttonsRow)) {
                $keyboard[] = $buttonsRow;
            }

            // PAGINATION TUGMALARI
            $paginationButtons = [];
            if (!$movies->onFirstPage()) {
                $paginationButtons[] = [
                    'text' => '⬅️ Orqaga',
                    'callback_data' => $text . "_page_" . ($page - 1)
                ];
            }

            if ($movies->hasMorePages()) {
                $paginationButtons[] = [
                    'text' => 'Keyingi ➡️',
                    'callback_data' => $text . "_page_" . ($page + 1)
                ];
            }

            if (!empty($paginationButtons)) {
                $keyboard[] = $paginationButtons;
            }

            $params = [
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ];

            // ASOSIY MANTIQ: Yangilash yoki Yangi yuborish
            if ($message_id) {
                // Agar message_id bo'lsa, mavjudini tahrirlaymiz
                $params['message_id'] = $message_id;
                try {
                    Telegram::editMessageText($params);
                } catch (\Exception $e) {
                    // Xabar o'zgarmagan bo'lsa Telegram xato qaytaradi, shuni ushlab qolamiz
                }
            } else {
                // Aks holda yangi yuboramiz
                Telegram::sendMessage($params);
            }



            return response('ok');
        }

        return response('ok', 200);
    }
}

function isTelegramLink($text)
{
    return preg_match('/^https?:\/\/t\.me\/.+$/', $text);
}

function isInviteLink($text)
{
    return preg_match('/^https?:\/\/t\.me\/\+.+$/', $text);
}
function isPublicTelegram($text)
{
    return preg_match('/^https?:\/\/t\.me\/(?!\+)[a-zA-Z0-9_]+$/', $text);
}

function checkPublicChat($url)
{
    $username = basename(parse_url($url, PHP_URL_PATH));

    try {
        Telegram::getChat([
            'chat_id' => '@' . $username
        ]);

        return true;
    } catch (\Exception $e) {
        return false;
    }
}
