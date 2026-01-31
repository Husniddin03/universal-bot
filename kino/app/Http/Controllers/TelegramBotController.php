<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    private string $adminId;
    private string $channelId;

    public function __construct()
    {
        $this->adminId = env('TELEGRAM_ADMIN_ID');
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
            if ($message->has('video')) {
                $video = $message->getVideo();

                $fileId = $video->getFileId();
                $duration = $video->getDuration();
                $fileSize = $video->getFileSize();
            }
        } elseif ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            $message = $callbackQuery->getMessage();
            $chatId = $message->getChat()->getId();
            $text = $callbackQuery->getData();
        }




        if ($chatId == $this->adminId) {

            $movie = Movie::latest()->first();

            /* ======================
                1. VIDEO YUBORILDI
            ====================== */
            if ($message->has('video')) {

                if ($movie && $movie->status !== 'ready') {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "⚠️ Avvalgi kino tugallanmagan."
                    ]);
                }

                Movie::create([
                    'message_id' => $message->getMessageId(), // admin video ID
                    'status' => 'waiting_name'
                ]);

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "🎬 Video qabul qilindi.\nKino nomini yuboring."
                ]);
            }


            /* ======================
                2. KINO NOMI
            ====================== */
            if ($movie && $movie->status === 'waiting_name' && $text) {

                // 🔢 AUTO KOD (100 dan)
                $lastCode = Movie::whereNotNull('code')
                    ->orderByRaw('CAST(code AS UNSIGNED) DESC')
                    ->value('code');

                $newCode = $lastCode ? ((int)$lastCode + 1) : 100;

                // 📢 1) Videoni kanalga copy qilamiz
                $sent = Telegram::copyMessage([
                    'chat_id' => $this->channelId,
                    'from_chat_id' => $chatId,
                    'message_id' => $movie->message_id, // admin video ID
                ]);

                // 📝 2) Captionni tahrirlaymiz (yuqoridan yoki pastdan)
                $caption = "🎬 {$text}\n🆔 Kod: {$newCode}";

                Telegram::editMessageCaption([
                    'chat_id' => $this->channelId,
                    'message_id' => $sent->getMessageId(),
                    'caption' => $caption,
                ]);

                // 💾 3) DB yangilaymiz
                $movie->update([
                    'name' => $text,
                    'code' => $newCode,
                    'message_id' => $sent->getMessageId(), // kanal message_id
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
                Telegram::copyMessage([
                    'chat_id' => $chatId,
                    'from_chat_id' => $this->channelId,
                    'message_id' => $movie->message_id,
                ]);

                return response('ok');
            }

            return Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Kino topilmadi'
            ]);
        }



        // if (!$this->isUrl($text)) {
        //     Telegram::sendMessage([
        //         'chat_id' => $chatId,
        //         'text' => "Please, send url"
        //     ]);
        // }

        // $path = trim(shell_exec(
        //     "yt-dlp -f best " .
        //         "-o 'storage/app/videos/%(title)s.%(ext)s' " .
        //         "--print filename '$text'"
        // ));

        // Telegram::sendVideo([
        //     'chat_id' => $chatId,
        //     'video' => InputFile::create($path),
        // ]);

        // unlink($path); // majburiy

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "keldi"
        ]);

        return response('ok', 200);
    }

    public function isUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        } else {
            return false;
        }
    }
}
