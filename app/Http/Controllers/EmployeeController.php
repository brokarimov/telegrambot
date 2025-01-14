<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
        $employees = User::where('status', 1)->where('role', '!=', 'admin')->get();
        $foods = Food::all();
        return view('employee-index', ['models' => $employees, 'foods' => $foods]);
    }
    public function store(Request $request)
    {
        $user = User::where('id', $request->user_id)->first();
        $token = env('TELEGRAM_BOT_TOKEN');
        $telegramApiUrl = "https://api.telegram.org/bot{$token}/";
        $chatId = $user->chat_id;

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'food' => 'required|array',
            'longtitude' => 'required',
            'latitude' => 'required',
            'time' => 'required',
        ]);

        $order = Order::create($data);

        $foods = $request->food;

        foreach ($foods as $foodId) {
            OrderItems::create([
                'order_id' => $order->id,
                'food_id' => $foodId
            ]);
        }

        // Fetch the food names
        $foodNames = Food::whereIn('id', $foods)->pluck('name')->toArray();

        // Prepare the message
        $message = "<b>Order №{$order->id}:</b>\n\n";
        $message .= "Delivery Time: {$request->time}\n\n";
        $message .= "<b>Ordered Foods:</b>\n";

        foreach ($foodNames as $foodName) {
            $message .= "- {$foodName}\n";
        }

        // Inline keyboard for Telegram
        $replyMarkup = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'Confirm ✅', 'callback_data' => "confirm_{$order->id}"],
                    ['text' => 'Unconfirm ❌', 'callback_data' => "unconfirm_{$order->id}"]
                ]
            ]
        ]);

        // Send the message via Telegram
        Http::post($telegramApiUrl . 'sendMessage', [
            'parse_mode' => 'HTML',
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => $replyMarkup,
        ]);

        return back()->with('success', 'Order details and food list sent successfully!');
    }



    public function handleCallbackQuery(Request $request)
    {
        $callbackQuery = $request->input('callback_query');

        if (!$callbackQuery) {
            return response()->json(['error' => 'Invalid callback query'], 400);
        }

        $callbackData = $callbackQuery['data'];
        $chatId = $callbackQuery['message']['chat']['id'];

        try {
            if (str_starts_with($callbackData, 'confirm_')) {
                $orderId = str_replace('confirm_', '', $callbackData);
                $order = Order::where('id', $orderId)->update(['status' => 1]);


                if ($order->status == 1) {
                    $this->sendTelegramMessage("Order №{$orderId} has been confirmed ✅", $chatId);
                }
            } elseif (str_starts_with($callbackData, 'unconfirm_')) {
                $orderId = str_replace('unconfirm_', '', $callbackData);
                Order::where('id', $orderId)->update(['status' => 2]);


                $this->sendTelegramMessage("Order №{$orderId} has been unconfirmed ❌", $chatId);
            }
        } catch (\Exception $e) {
            \Log::error("CallbackQuery Error: " . $e->getMessage());

            $this->sendTelegramMessage("An error occurred while processing your request ❌", $chatId);
        }

        return response()->json(['status' => 'success'], 200);
    }




    private function sendTelegramMessage(string $message, $chatId)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $telegramApiUrl = "https://api.telegram.org/bot{$token}/";

        Http::post($telegramApiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}
