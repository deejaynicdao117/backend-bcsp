<?php

namespace App\Http\Controllers;

use App\Models\PortalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $latestId = (int) PortalNotification::where('user_id', $user->id)->max('id');
        $afterId = $request->integer('after_id');

        $query = PortalNotification::where('user_id', $user->id);
        if ($request->has('after_id')) {
            $query->where('id', '>', $afterId);
        }

        $notifications = $query->orderBy('id')
            ->limit(100)
            ->get(['id', 'type', 'title', 'message', 'data', 'created_at']);

        return response()->json([
            'data' => $notifications,
            'latest_id' => $latestId,
        ]);
    }
}