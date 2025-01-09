<?php

namespace App\Http\Controllers;

use App\Mail\SendVerifyCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; // You can use Cache or a database to track progress.
use Illuminate\Support\Facades\Mail;

class TelegramBotController extends Controller
{
    public function store(string $text, int $chat_id)
    {
        $token = '7580679401:AAF3MDWNK5wEIq_jZKWBrbpsSP6YTNPmUDs';
        $telegramApiUrl = "https://api.telegram.org/bot{$token}/";

        $messageResponse = Http::post($telegramApiUrl . 'sendMessage', [
            'parse_mode' => 'HTML',
            'chat_id' => $chat_id,
            'text' => $text,
        ]);

        

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

            if (isset($data['message']['chat']['id'])) {
                $chat_id = $data['message']['chat']['id'];
                $text = $data['message']['text'] ?? null;
                $photo = $data['message']['photo'] ?? null;

                if ($text === '/start') {
                    Cache::put("user_step_{$chat_id}", 'name');
                    $this->store('Welcome! Let\'s get started. What is your name?', $chat_id);
                    return response()->json(['status' => 'success'], 200);
                }

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

            Log::warning('Invalid Telegram Webhook Payload: ', $data);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
