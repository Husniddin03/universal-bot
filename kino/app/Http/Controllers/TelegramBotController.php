<?php

namespace App\Http\Controllers;

use App\Models\Movie;
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
                if ($movie) {
                    try {
                        Telegram::copyMessage([
                            'chat_id' => $chatId,
                            'from_chat_id' => $this->channelId,
                            'message_id' => $movie->message_id,
                        ]);

                        return response('ok');
                    } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
                        // MESSAGE_ID_INVALID yoki boshqa xatolar
                        return Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => '❌ Kino topilmadi'
                        ]);
                    }
                }
            }

            return Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Kino topilmadi'
            ]);
        } 
        else if (filter_var($text, FILTER_VALIDATE_URL)) {
            $escapedUrl = escapeshellarg($text);
            $outputPath = storage_path('app/videos/video_' . time());

            // Container ichidagi to'liq yo'l
            $ytdlpPath = '/usr/local/bin/yt-dlp';

            // Avval yt-dlp mavjudligini tekshirish
            if (!file_exists($ytdlpPath)) {
                Log::error('yt-dlp topilmadi: ' . $ytdlpPath);
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Server xatosi. Iltimos, keyinroq urinib ko\'ring.'
                ]);
                return response('error', 500);
            }

            $command = "{$ytdlpPath} " .
                "-f 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best' " .
                "--merge-output-format mp4 " .
                "-o {$outputPath}.mp4 " .
                "{$escapedUrl} 2>&1";

            Log::info('Command: ' . $command);

            exec($command, $output, $returnCode);

            Log::info('Output: ' . implode("\n", $output));
            Log::info('Return code: ' . $returnCode);

            $videoPath = $outputPath . '.mp4';

            if (!file_exists($videoPath)) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Video yuklab olinmadi.'
                ]);
                return response('error', 400);
            }

            try {
                Telegram::sendVideo([
                    'chat_id' => $chatId,
                    'video' => InputFile::create($videoPath),
                ]);
            } finally {
                if (file_exists($videoPath)) {
                    unlink($videoPath);
                }
            }

            return response('ok', 200);
        }
         else {
            if (strlen($text) < 3) {
                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Kamida 3 ta belgi kiriting!'
                ]);
            }
            $movies = Movie::where('name', 'LIKE', "%{$text}%")
                ->where('status', 'ready')
                ->limit(10)
                ->get();

            if ($movies->isEmpty()) {
                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Kino topilmadi'
                ]);
            }

            $keyboard = [];
            $index = 1;
            $messageText = "🎬 <b>Topilgan kinolar:</b>\n\n";

            foreach ($movies as $movie) {

                // Matn
                $messageText .= "{$index}. Nomi: <b>{$movie->name}</b>\n";
                $messageText .= "🆔 Kod: {$movie->code}\n\n";

                // Tugma
                $buttonsRow[] = [
                    'text' => (string)$index,
                    'callback_data' => $movie->code
                ];

                // Har 2 ta tugmadan keyin yangi qator
                if (count($buttonsRow) === 5) {
                    $keyboard[] = $buttonsRow;
                    $buttonsRow = [];
                }

                $index++;
            }

            // Agar oxirida 1 ta qolib ketsa
            if (!empty($buttonsRow)) {
                $keyboard[] = $buttonsRow;
            }

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $keyboard
                ])
            ]);

            return response('ok');
        }

        return response('ok', 200);
    }
}
