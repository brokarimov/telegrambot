<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::all();
        return view('employee-index', ['models' => $employees]);
    }
    public function store(Request $request)
    {
      
        $token = env('TELEGRAM_BOT_TOKEN');
        $telegramApiUrl = "https://api.telegram.org/bot{$token}/";
        $chatId = env('TELEGRAM_CHAT_ID'); 

        
        $data = $request->validate([
            'employees' => 'required|array',
            'file' => 'nullable|file',
        ]);

        $employees = Employee::whereIn('id', $data['employees'])->get();

        if ($employees->isEmpty()) {
            return back()->with('error', 'No employees selected.');
        }

        $message = "<b>Selected Employees:</b>\n\n";
        foreach ($employees as $employee) {
            $message .= "Name: {$employee->name}\n";
            $message .= "Telephone: {$employee->tel}\n";
            $message .= "Address: {$employee->address}\n\n";
        }

        $messageResponse = Http::post($telegramApiUrl . 'sendMessage', [
            'parse_mode' => 'HTML',
            'chat_id' => $chatId,
            'text' => $message,
        ]);

       

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->getPathname();
            $fileMimeType = $file->getMimeType();

            $endpoint = match (true) {
                str_contains($fileMimeType, 'image') => 'sendPhoto',
                str_contains($fileMimeType, 'video') => 'sendVideo',
                default => 'sendDocument',
            };

            $fieldName = match ($endpoint) {
                'sendPhoto' => 'photo',
                'sendVideo' => 'video',
                default => 'document',
            };

            $fileUploadResponse = Http::attach($fieldName, file_get_contents($filePath), $file->getClientOriginalName())
                ->post($telegramApiUrl . $endpoint, [
                    'chat_id' => $chatId,
                ]);

            
        }

        return back()->with('success', 'Employee details and file sent successfully!');
    }
}
