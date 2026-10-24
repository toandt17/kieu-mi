<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->latest();
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate((int) $request->query('per_page', 20));
        $users->getCollection()->transform(fn (User $user) => $user->toApiArray());

        return ApiResponse::success($users);
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success($user->toApiArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create($data);

        return ApiResponse::success($user->toApiArray(), 'Đã tạo tài khoản', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['sometimes', Rule::in(['admin', 'staff'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return ApiResponse::success($user->fresh()->toApiArray(), 'Đã cập nhật tài khoản');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return ApiResponse::error('Không thể xóa chính tài khoản đang đăng nhập.', 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'Đã xóa tài khoản');
    }
}
