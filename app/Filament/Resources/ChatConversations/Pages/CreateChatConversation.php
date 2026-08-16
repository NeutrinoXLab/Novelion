<?php

namespace App\Filament\Resources\ChatConversations\Pages;

use App\Filament\Resources\ChatConversations\ChatConversationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChatConversation extends CreateRecord
{
    protected static string $resource = ChatConversationResource::class;
}
