<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function getLimit(Event $event, string $userId, UserService $userService): JsonResponse
    {
        return response()->json([
            'user_id' => $userId,
            'allowed_seats' => $userService->getAllowedSeatsCountForUser($event, $userId),
            'limit' => UserService::RESERVATION_LIMIT_PER_USER,
        ]);
    }
}
