<?php

namespace App\Http\Controllers;

use App\Models\InboxNotification;
use App\Models\OutboxMessage;
use App\Services\NotificationOutbox;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request, NotificationOutbox $outbox)
    {
        $recipient = $request->user();
        $outbox->deliver($recipient);
        $notifications = InboxNotification::where('recipient_id', $recipient->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $unread = InboxNotification::where('recipient_id', $recipient->id)->whereNull('read_at')->count();
        $pending = OutboxMessage::where('recipient_id', $recipient->id)->whereNull('delivered_at')->count();
        return view('notifications.index', compact('notifications', 'unread', 'pending'));
    }

    public function read(Request $request, InboxNotification $notification)
    {
        abort_unless($notification->recipient_id === $request->user()->id, 404);
        InboxNotification::whereKey($notification->id)->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now()]);
        return redirect()->route('notifications.index');
    }
}
