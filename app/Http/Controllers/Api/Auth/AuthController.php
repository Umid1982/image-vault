<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Resources\UserResource;
use App\Http\Traits\ApiResponseHelper;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponseHelper;

    public function __construct(protected AuthService $authService)
    {
    }

    /**
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->successResponse(
            UserResource::make($this->authService->createUser($request->validated())));
    }

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authResponse(
            $this->authService->login($request->validated()),
            200,
            'Login successful'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->revokeToken($request->user());
        return $this->successResponse([], 'data', 200, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(UserResource::make($request->user()), 'user');
    }
}
