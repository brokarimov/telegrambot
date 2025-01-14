<?php

namespace App\Http\Controllers;

use App\Mail\SendVerifyCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class TelegramBotController extends Controller
{
    public function store(string $text, int $chat_id, ?array $inlineKeyboard = null, ?array $statusUsersbtn = null)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $telegramApiUrl = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = [
            'parse_mode' => 'HTML',
            'chat_id' => $chat_id,
            'text' => $text,
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }
        if ($statusUsersbtn) {
            $payload['reply_markup'] = json_encode($statusUsersbtn);
        }

        $messageResponse = Http::post($telegramApiUrl, $payload);

        if (!$messageResponse->successful()) {
            Log::error('Telegram message failed to send: ' . $messageResponse->body());
        }

        return $messageResponse->json();
    }


    public function sendMessage(Request $request)
    {
        try {
            $data = $request->all();
            Log::info('Telegram Webhook Received: ', $data);

            // Handle callback query to confirm or unconfirm users
            if (isset($data['callback_query'])) {
                return $this->handleCallbackQuery($data['callback_query']);
            }



            if (isset($data['message']['chat']['id'])) {
                $chat_id = $data['message']['chat']['id'];
                $text = $data['message']['text'] ?? null;
                $photo = $data['message']['photo'] ?? null;

                if ($text === '/start') {
                    return $this->handleStartCommand($chat_id);
                } elseif ($text === 'Confirmed users') {
                    $this->filterUsersStatus(1, $chat_id);
                    return response()->json(['status' => 'success'], 200);
                } elseif ($text === 'Unconfirmed users') {
                    $this->filterUsersStatus(2, $chat_id);
                    return response()->json(['status' => 'success'], 200);
                }

                return $this->handleRegistrationSteps($chat_id, $text, $photo);
            }


            Log::warning('Invalid Telegram Webhook Payload: ', $data);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function filterUsersStatus(int $status, int $chat_id)
    {
        $users = User::where('status', $status)->get();
        if ($users->isNotEmpty()) {

            foreach ($users as $user) {
                $text = "Name: {$user->name}\n";
                $text .= "Email: {$user->email}\n";
                $text .= "Role: {$user->role}\n\n";
                if ($status == 1) {
                    $inlineKeyboard = [
                        [
                            ['text' => 'Unconfirm ❌', 'callback_data' => "unconfirm_{$user->id}"],
                        ],
                    ];
                }elseif($status == 2){
                    $inlineKeyboard = [
                        [
                            ['text' => 'Confirm ✅', 'callback_data' => "confirm_{$user->id}"],
                        ],
                    ];
                }
                $this->store($text, $chat_id, $inlineKeyboard);
            }
        } else {
            $this->store('There is not users in this status!', $chat_id);
        }
    }

    private function handleCallbackQuery(array $callbackQuery)
    {
        $callbackData = $callbackQuery['data'];
        $chat_id = $callbackQuery['message']['chat']['id'];

        if (str_starts_with($callbackData, 'confirm_')) {
            $userId = str_replace('confirm_', '', $callbackData);
            User::where('id', $userId)->update(['status' => 1]);

            $this->store("User ID {$userId} has been confirmed ✅", $chat_id, null);
        } elseif (str_starts_with($callbackData, 'unconfirm_')) {
            $userId = str_replace('unconfirm_', '', $callbackData);
            User::where('id', $userId)->update(['status' => 2]);

            $this->store("User ID {$userId} has been unconfirmed ❌", $chat_id, null);
        }





        return response()->json(['status' => 'success'], 200);
    }

    private function sendUnconfirmedUsers(int $chat_id)
    {
        $unconfirmedUsers = User::where('status', 0)->get();

        if ($unconfirmedUsers->isEmpty()) {
            $this->store('There are no more unconfirmed users.', $chat_id);
            return;
        }

        foreach ($unconfirmedUsers as $user) {
            $text = "Name: {$user->name}\n";
            $text .= "Email: {$user->email}\n";
            $text .= "Role: {$user->role}\n\n";

            $inlineKeyboard = [
                [
                    ['text' => 'Confirm ✅', 'callback_data' => "confirm_{$user->id}"],
                    ['text' => 'Unconfirm ❌', 'callback_data' => "unconfirm_{$user->id}"],
                ],
            ];

            $this->store($text, $chat_id, $inlineKeyboard);
        }
    }


    private function handleStartCommand(int $chat_id)
    {
        $existingUser = User::where('chat_id', $chat_id)->first();

        if ($existingUser) {
            $this->store("Welcome back, {$existingUser->name}! You are already registered.", $chat_id);

            if ($existingUser->role == 'admin') {
                $statusUsersbtn = [
                    'keyboard' => [
                        [
                            ['text' => 'Confirmed users', 'callback_data' => "confirmed_users"],
                            ['text' => 'Unconfirmed users', 'callback_data' => "unconfirmed_users"],
                        ]
                    ],
                    'resize_keyboard' => true
                ];

                $inlineKeyboard = [
                    [
                        ['text' => 'Confirm ✅', 'callback_data' => "confirm_{$existingUser->id}"],
                        ['text' => 'Unconfirm ❌', 'callback_data' => "unconfirm_{$existingUser->id}"],
                    ],
                ];

                $this->sendUnconfirmedUsers($chat_id);
                $this->store('You can manage users below:', $chat_id, $inlineKeyboard, $statusUsersbtn);
            }
        } else {
            Cache::put("user_step_{$chat_id}", 'name');
            $this->store('Welcome! Let\'s get started. What is your name?', $chat_id);
        }

        return response()->json(['status' => 'success'], 200);
    }


    private function handleRegistrationSteps(int $chat_id, ?string $text, ?array $photo)
    {
        $step = Cache::get("user_step_{$chat_id}", 'name');

        switch ($step) {
            case 'name':
                Cache::put("user_name_{$chat_id}", $text);
                Cache::put("user_step_{$chat_id}", 'email');
                $this->store('Great! Now, please provide your email:', $chat_id);
                break;

            case 'email':
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    Cache::put("user_email_{$chat_id}", $text);
                    Cache::put("user_step_{$chat_id}", 'password');
                    $this->store('Almost done! Please provide your password:', $chat_id);
                } else {
                    $this->store('Invalid email format. Please try again:', $chat_id);
                }
                break;

            case 'password':
                Cache::put("user_password_{$chat_id}", $text);
                Cache::put("user_step_{$chat_id}", 'image');
                $this->store('Great! Now, please upload your profile picture:', $chat_id);
                break;

            case 'image':
                if ($photo) {
                    $file_id = end($photo)['file_id'];
                    Cache::put("user_image_{$chat_id}", $file_id);

                    $verification_code = rand(100000, 999999);
                    Cache::put("user_verification_code_{$chat_id}", $verification_code);

                    $email = Cache::get("user_email_{$chat_id}");
                    $data['code'] = $verification_code;
                    Mail::to($email)->send(new SendVerifyCode($data));

                    Cache::put("user_step_{$chat_id}", 'verification_code');
                    $this->store('A verification code has been sent to your email. Please enter the code:', $chat_id);
                } else {
                    $this->store('Please upload a valid image to proceed.', $chat_id);
                }
                break;

            case 'verification_code':
                $stored_code = Cache::get("user_verification_code_{$chat_id}");

                if ($text == $stored_code) {
                    $userData = [
                        'name' => Cache::get("user_name_{$chat_id}"),
                        'email' => Cache::get("user_email_{$chat_id}"),
                        'password' => bcrypt(Cache::get("user_password_{$chat_id}")),
                        'chat_id' => $chat_id,
                        'image' => Cache::get("user_image_{$chat_id}"),
                        'email_verified_at' => now(),
                    ];

                    $user = User::create($userData);

                    if ($user) {
                        $this->store("Registration complete! Welcome, {$user->name}!", $chat_id);
                    } else {
                        $this->store('Something went wrong while saving your data. Please try again.', $chat_id);
                    }


                    Cache::forget("user_name_{$chat_id}");
                    Cache::forget("user_email_{$chat_id}");
                    Cache::forget("user_password_{$chat_id}");
                    Cache::forget("user_image_{$chat_id}");
                    Cache::forget("user_verification_code_{$chat_id}");
                    Cache::forget("user_step_{$chat_id}");
                } else {
                    $this->store('Invalid verification code. Please try again:', $chat_id);
                }
                break;

            default:
                $this->store('I didn\'t understand that. Let\'s start again. Please provide your name:', $chat_id);
                Cache::put("user_step_{$chat_id}", 'name');
                break;
        }

        return response()->json(['status' => 'success'], 200);
    }
}
