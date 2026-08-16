<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Afișează conversația curentă.
     */
    public function index(Request $request)
    {
        $conversation = $this->getConversation($request);

        $messages = $conversation
            ->messages()
            ->oldest()
            ->get();

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages' => $messages,
        ]);
    }

    /**
     * Trimite un mesaj nou.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $conversation = $this->getConversation($request);

        $message = $conversation->messages()->create([
            'sender_type' => 'customer',
            'message' => $validated['message'],
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Găsește conversația clientului sau creează una nouă.
     */
    private function getConversation(Request $request): ChatConversation
    {
        if ($request->user()) {
            return ChatConversation::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'status' => 'open',
                ],
                [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'last_message_at' => now(),
                ]
            );
        }

        $sessionId = $request->session()->getId();

        return ChatConversation::firstOrCreate(
            [
                'session_id' => $sessionId,
                'status' => 'open',
            ],
            [
                'last_message_at' => now(),
            ]
        );
    }
}