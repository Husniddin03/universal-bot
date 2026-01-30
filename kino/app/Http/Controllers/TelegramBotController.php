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

            // 1. Video yuborildi
            if ($message->has('video')) {

                // Oldingi kino to‘liq emas
                if ($movie && $movie->status != 'ready') {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "⚠️ Avvalgi kino to‘liq emas!\nIltimos davom ettiring."
                    ]);
                }

                $fileId = $message->getVideo()->getFileId();

                // Yangi kino yaratamiz
                Movie::create([
                    'file_id' => $fileId,
                    'message_id' => $message->getMessageId(),
                    'status' => 'waiting_name'
                ]);

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "🎬 Video qabul qilindi.\nEndi kino nomini yuboring."
                ]);
            }

            // 2. Kino nomi kelyapti
            if ($movie && $movie->status == 'waiting_name' && $text) {

                $movie->update([
                    'name' => $text,
                    'status' => 'waiting_code'
                ]);

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "✅ Nom saqlandi.\nEndi kino kodini yuboring."
                ]);
            }

            // 3. Kino kodi kelyapti
            if ($movie && $movie->status == 'waiting_code' && $text) {

                $avelebl = Movie::where('code', $text)->first();

                if ($avelebl) {
                    return Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Bu kod ishlatilgan"
                    ]);
                }
                // Kanalga post qilamiz
                Telegram::copyMessage([
                    'chat_id' => $this->channelId,
                    'from_chat_id' => $chatId,
                    'message_id' => $movie->message_id,
                ]);

                $movie->update([
                    'code' => $text,
                    'status' => 'ready'
                ]);

                return Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "✅ Kino kanalga joylandi!"
                ]);
            }
        }


        if (is_numeric($text)) {
            $movie = Movie::where('code', $text)->first();
            if ($movie) {
                return Telegram::sendVideo([
                    'chat_id' => $chatId,
                    'video' => $movie->file_id,
                ]);
            }

            return Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Kino topilmadi'
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
            'text' => $text
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
