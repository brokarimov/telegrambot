<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        if ($inlineKeyboard || $statusUsersbtn) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard ?? $statusUsersbtn
            ]);
        }


        $messageResponse = Http::post($telegramApiUrl, $payload);

        if (!$messageResponse->successful()) {
            Log::error('Telegram message failed to send: ' . $messageResponse->body());
        }

        return $messageResponse->json();
    }

    public function getMessage(Request $request)
    {
        try {
            $data = $request->all();
            Log::info('Received data: ', $data);

            if (isset($data['callback_query'])) {
                Log::info('Callback query received: ', ['callback_data' => $data['callback_query']['data']]);
                return $this->handleCallbackQuery($data['callback_query']);
            }

            if (isset($data['message']['chat']['id'])) {
                $chat_id = $data['message']['chat']['id'];
                $text = $data['message']['text'] ?? null;

                if ($text === '/start') {
                    return $this->handleStartCommand($chat_id);
                }

                $step = Cache::get("registration_step_{$chat_id}");
                if ($step) {
                    return $this->handleRegistrationSteps($chat_id, $text, $data['message']);
                }
            }

            Log::warning('Invalid Telegram Webhook Payload: ', $data);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // public function sendMessage(Request $request)
    // {
    //     try {
    //         $data = $request->all();
    //         Log::info('Received data: ', $data);

    //         if (isset($data['callback_query'])) {
    //             Log::info('Callback query received: ', ['callback_data' => $data['callback_query']['data']]);
    //             return $this->handleCallbackQuery($data['callback_query']);
    //         }

    //         if (isset($data['message']['chat']['id'])) {
    //             $chat_id = $data['message']['chat']['id'];
    //             $text = $data['message']['text'] ?? null;

    //             if ($text === '/start') {
    //                 return $this->handleStartCommand($chat_id);
    //             }

    //             $step = Cache::get("registration_step_{$chat_id}");
    //             if ($step) {
    //                 return $this->handleRegistrationSteps($chat_id, $text, $data['message']);
    //             }
    //         }

    //         Log::warning('Invalid Telegram Webhook Payload: ', $data);
    //         return response()->json(['error' => 'Invalid payload'], 400);
    //     } catch (\Exception $e) {
    //         Log::error('Telegram Webhook Error: ' . $e->getMessage());
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

    private function handleStartCommand(int $chat_id)
    {
        $existingUser = User::where('chat_id', $chat_id)->first();
        $existingCompany = Company::where('chat_id', $chat_id)->first();

        if ($existingUser) {
            $this->store("Welcome back, {$existingUser->name}! You are already registered as a user.", $chat_id);

            if ($existingUser->role == 'admin') {
                $statusUsersbtn = [
                    'keyboard' => [
                        [
                            ['text' => 'Confirmed users'],
                            ['text' => 'Unconfirmed users'],
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
                $this->sendUnconfirmedCompanies($chat_id);
                $this->store('You can manage users and companies below:', $chat_id, null, $statusUsersbtn);
            }
        } elseif ($existingCompany) {
            $this->store("Welcome back, {$existingCompany->name}! You are already registered as a company.", $chat_id);
        } else {
            $inlineKeyboard = [
                [
                    ['text' => 'I am a User', 'callback_data' => 'register_user'],
                    ['text' => 'I am a Company', 'callback_data' => 'register_company'],
                ],
            ];

            $this->store('Welcome! Please select your registration type:', $chat_id, $inlineKeyboard);
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
            $text = "User Name: {$user->name}\n";
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

    private function sendUnconfirmedCompanies(int $chat_id)
    {
        $unconfirmedCompanies = Company::where('status', 0)->get();

        if ($unconfirmedCompanies->isEmpty()) {
            $this->store('There are no more unconfirmed companies.', $chat_id);
            return;
        }

        foreach ($unconfirmedCompanies as $company) {
            $text = "Company Name: {$company->name}\n";
            $text .= "Email: {$company->email}\n";

            $inlineKeyboard = [
                [
                    ['text' => 'Confirm ✅', 'callback_data' => "confirmcompany_{$company->id}"],
                    ['text' => 'Unconfirm ❌', 'callback_data' => "unconfirmcompany_{$company->id}"],
                ],
            ];

            $this->store($text, $chat_id, $inlineKeyboard);
        }
    }

    private function handleCallbackQuery(array $callbackQuery)
    {
        $callbackData = $callbackQuery['data'];
        $chat_id = $callbackQuery['message']['chat']['id'];

        Log::info("Handling callback query: ", ['callback_data' => $callbackData, 'chat_id' => $chat_id]);

        if ($callbackData === 'register_user') {
            Cache::put("registration_type_{$chat_id}", 'user');
            Cache::put("registration_step_{$chat_id}", 'name');
            $this->store('Great! Let\'s register you as a user. What is your name?', $chat_id);
        } elseif ($callbackData === 'register_company') {
            Cache::put("registration_type_{$chat_id}", 'company');
            Cache::put("registration_step_{$chat_id}", 'name');
            $this->store('Great! Let\'s register your company. What is the company name?', $chat_id);
        } elseif ($callbackData === 'skip_company') {
            Cache::put("registration_step_{$chat_id}", 'completed');
            $this->store('User registration complete! Welcome!', $chat_id);

            User::create([
                'name' => Cache::get("user_name_{$chat_id}"),
                'email' => Cache::get("user_email_{$chat_id}"),
                'password' => Cache::get("user_password_{$chat_id}"),
                'chat_id' => $chat_id,
            ]);

            Cache::forget("registration_step_{$chat_id}");
            Cache::forget("registration_type_{$chat_id}");
        } elseif (strpos($callbackData, 'company_') === 0) {
            $companyId = str_replace('company_', '', $callbackData);
            $company = Company::find($companyId);

            Log::info('Company selected: ', ['company_id' => $companyId, 'company_name' => $company->name]);

            Cache::put("company_id_{$chat_id}", $companyId);
            $this->store("You have selected the company: {$company->name}.", $chat_id);

            Cache::put("registration_step_{$chat_id}", 'completed');
            $this->store('User registration complete! Welcome!', $chat_id);

            User::create([
                'name' => Cache::get("user_name_{$chat_id}"),
                'email' => Cache::get("user_email_{$chat_id}"),
                'password' => Cache::get("user_password_{$chat_id}"),
                'chat_id' => $chat_id,
                'company_id' => $companyId,
            ]);

            Cache::forget("registration_step_{$chat_id}");
            Cache::forget("registration_type_{$chat_id}");
        } elseif (str_starts_with($callbackData, 'confirm_')) {
            $userId = str_replace('confirm_', '', $callbackData);
            User::where('id', $userId)->update(['status' => 1]);
            $this->store("User ID {$userId} has been confirmed ✅", $chat_id, null);
        } elseif (str_starts_with($callbackData, 'unconfirm_')) {
            $userId = str_replace('unconfirm_', '', $callbackData);
            User::where('id', $userId)->update(['status' => 2]);
            $this->store("User ID {$userId} has been unconfirmed ❌", $chat_id, null);
        } elseif (str_starts_with($callbackData, 'confirmcompany_')) {
            $companyID = str_replace('confirmcompany_', '', $callbackData);
            Company::where('id', $companyID)->update(['status' => 1]);
            $this->store("Company ID {$companyID} has been confirmed ✅", $chat_id, null);
        } elseif (str_starts_with($callbackData, 'unconfirmcompany_')) {
            $companyID = str_replace('unconfirmcompany_', '', $callbackData);
            Company::where('id', $companyID)->update(['status' => 2]);
            $this->store("Company ID {$companyID} has been unconfirmed ❌", $chat_id, null);
        } else {
            $this->store('Invalid selection. Please choose a company or skip.', $chat_id);
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function handleRegistrationSteps(int $chat_id, ?string $text, $update)
    {
        $type = Cache::get("registration_type_{$chat_id}");
        $step = Cache::get("registration_step_{$chat_id}");

        if ($type === 'user') {
            $this->handleUserRegistration($chat_id, $step, $text, $update);
        } elseif ($type === 'company') {
            $this->handleCompanyRegistration($chat_id, $step, $text, $update);
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function handleUserRegistration(int $chat_id, string $step, ?string $text, $update)
    {
        switch ($step) {
            case 'name':
                Cache::put("user_name_{$chat_id}", $text);
                Cache::put("registration_step_{$chat_id}", 'email');
                $this->store('What is your email?', $chat_id);
                break;

            case 'email':
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    Cache::put("user_email_{$chat_id}", $text);
                    Cache::put("registration_step_{$chat_id}", 'password');
                    $this->store('Please create a password for your account:', $chat_id);
                } else {
                    $this->store('Invalid email format. Please try again.', $chat_id);
                }
                break;

            case 'password':
                if (strlen($text) >= 6) {
                    Cache::put("user_password_{$chat_id}", bcrypt($text));
                    Cache::put("registration_step_{$chat_id}", 'company');
                    $this->store('Please select your company (or skip if none):', $chat_id);

                    $companies = Company::all();
                    $inlineKeyboard = [];

                    if ($companies->count() > 0) {
                        foreach ($companies as $company) {
                            $inlineKeyboard[] = [
                                ['text' => $company->name, 'callback_data' => "company_{$company->id}"]
                            ];
                        }
                    }

                    $inlineKeyboard[] = [['text' => 'Skip company', 'callback_data' => 'skip_company']];
                    $this->store('Please select the company you work for or skip this step:', $chat_id, $inlineKeyboard);
                } else {
                    $this->store('Password must be at least 6 characters long. Please try again.', $chat_id);
                }
                break;
        }
    }

    private function handleCompanyRegistration(int $chat_id, string $step, ?string $text, $update)
    {
        switch ($step) {
            case 'name':
                Cache::put("company_name_{$chat_id}", $text);
                Cache::put("registration_step_{$chat_id}", 'email');
                $this->store('What is the company email?', $chat_id);
                break;

            case 'email':
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    Cache::put("company_email_{$chat_id}", $text);
                    Cache::put("registration_step_{$chat_id}", 'logo');
                    $this->store('Please provide your company logo (as a link or upload):', $chat_id);
                } else {
                    $this->store('Invalid email format. Please try again.', $chat_id);
                }
                break;

            case 'logo':
                if (empty($update['photo'])) {
                    $this->store('No photo detected. Please upload your company logo.', $chat_id);
                    return;
                }

                $photo = end($update['photo']);
                $fileId = $photo['file_id'];
                $token = env('TELEGRAM_BOT_TOKEN');
                $response = Http::get("https://api.telegram.org/bot{$token}/getFile?file_id={$fileId}");

                if (!$response->ok()) {
                    $this->store('Failed to retrieve the file. Please try again later.', $chat_id);
                    Log::error('Telegram API error: ', ['response' => $response->body()]);
                    return;
                }

                $filePath = $response->json()['result']['file_path'];
                $fileUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";
                $fileContents = Http::get($fileUrl)->body();

                if (!$fileContents) {
                    $this->store('Failed to download the file. Please try again later.', $chat_id);
                    Log::error('File download error: ', ['fileUrl' => $fileUrl]);
                    return;
                }

                $fileName = 'telegram_photos/' . basename($filePath);
                Storage::put($fileName, $fileContents);

                        
                $companyName = Cache::get("company_name_{$chat_id}");
                $companyEmail = Cache::get("company_email_{$chat_id}");


                $company = Company::create([
                    'name' => $companyName,
                    'chat_id' => $chat_id,
                    'email' => $companyEmail,
                    'logo' => $fileName,
                ]);

                if ($company) {
                    $this->store('Company registered successfully!', $chat_id);
                    Log::info('Company created: ', ['company' => $company->toArray()]);
                } else {
                    $this->store('Failed to register the company. Please try again later.', $chat_id);
                    Log::error('Company creation failed: ', [
                        'name' => $companyName,
                        'email' => $companyEmail,
                        'logo_path' => $fileName,
                    ]);
                }
                break;
        }
    }
}
