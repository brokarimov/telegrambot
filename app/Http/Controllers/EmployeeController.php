<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Food;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function index()
    {
        $companies = Company::where('status', 1)->get();
        $foods = Food::all();
        return view('employee-index', ['models' => $companies, 'foods' => $foods]);
    }
    public function store(Request $request)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $telegramApiUrl = "https://api.telegram.org/bot{$token}/";

        $usersChatIds = User::whereIn('company_id', $request->company)->where('status', 1)->pluck('chat_id');

        $foods = Food::whereIn('id', $request->food)->get();

        
        $text = "Today's menu:\n\n";
        foreach ($foods as $food) {
            $text .= " - {$food->name} - {$food->price}$\n";
        }

        
        $inlineKeyboard = [];
        $row = [];
        foreach ($foods as $index => $food) {
            $row[] = [
                'text' => $food->name,
                'callback_data' => "food_{$food->id}"
            ];

           
            if (count($row) === 3 || $index === $foods->count() - 1) {
                $inlineKeyboard[] = $row;
                $row = [];
            }
        }

        
        $inlineKeyboard[] = [
            ['text' => 'Cart 🛒', 'callback_data' => 'cart']
        ];

       
        foreach ($usersChatIds as $chat_id) {
            $response = Http::post($telegramApiUrl . 'sendMessage', [
                'parse_mode' => 'HTML',
                'chat_id' => $chat_id,
                'text' => $text,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $inlineKeyboard
                ]),
            ]);

            
            if ($response->failed()) {
                Log::error("Failed to send Telegram message to chat_id {$chat_id}: " . $response->body());
            }
        }

        return back()->with('success', 'Order details and food list sent successfully!');
    }
}
