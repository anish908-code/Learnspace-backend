<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Student ki notifications dekhna
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'notifications' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    // Single notification dekhna
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'notification' => $notification
        ]);
    }

    // Notification ko read mark karna
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->findOrFail($id);

        $notification->update([
            'is_read' => true,
        ]);

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    }

    // Student ki saari notifications clear karna
    public function clearAll(Request $request)
    {
        $user = $request->user();

        $deleted = Notification::where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'All notifications cleared',
            'deleted' => $deleted,
        ]);
    }
}
