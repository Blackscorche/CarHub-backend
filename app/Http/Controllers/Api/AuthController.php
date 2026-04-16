<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'phone' => ['required', 'regex:/^(\+55)?\d{10,11}$/'],
            'role' => 'required|in:customer,supplier',
            'cpf' => ['nullable', 'string', new Cpf],
            'lgpd_consent' => 'required|accepted',
            'terms_version' => 'nullable|string|max:20',
        ];

        // Supplier-specific rules
        if ($request->input('role') === 'supplier') {
            $rules['business_name'] = 'required|string|max:255';
            $rules['cnpj'] = ['nullable', 'string', new Cnpj];
            $rules['category'] = 'required|in:mecanica,eletrica,funilaria,pneus,estetica,pecas,outros';
            $rules['address'] = 'nullable|array';
            $rules['address.street'] = 'required_with:address|string';
            $rules['address.number'] = 'required_with:address|string';
            $rules['address.neighborhood'] = 'required_with:address|string';
            $rules['address.city'] = 'required_with:address|string';
            $rules['address.state'] = 'required_with:address|string|max:50';
            $rules['address.zip_code'] = 'required_with:address|string';
        }

        $validated = $request->validate($rules);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'cpf' => $validated['cpf'] ?? null,
            'status' => $validated['role'] === 'supplier' ? 'pending_approval' : 'active',
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
            'terms_version' => $validated['terms_version'] ?? '1.0',
            'lgpd_consent_ip' => $request->ip(),
            'lgpd_consent_device' => substr((string) $request->userAgent(), 0, 500),
        ]);

        if ($validated['role'] === 'supplier') {
            $supplier = Supplier::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'cnpj' => $validated['cnpj'] ?? null,
                'category' => $validated['category'],
                'approval_status' => 'pending',
            ]);

            // Create address if provided
            if (!empty($validated['address'])) {
                $address = $user->addresses()->create($validated['address']);
                $supplier->update([
                    'address_id' => $address->id,
                    'latitude' => $request->latitude ?? null,
                    'longitude' => $request->longitude ?? null,
                ]);
            } elseif ($request->latitude && $request->longitude) {
                $supplier->update([
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => $user->load('supplier'),
            'token' => $token,
        ], 'Cadastro realizado com sucesso.', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->error('Credenciais inválidas.', 401);
        }

        if ($user->status === 'suspended') {
            return $this->error('Sua conta foi suspensa. Entre em contato com o suporte.', 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => $user->load('supplier'),
            'token' => $token,
        ], 'Login realizado com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout realizado com sucesso.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return $this->success(null, 'Link de redefinição de senha enviado.');
        }

        return $this->error('Não foi possível enviar o link de redefinição.', 400);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => $password]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Senha redefinida com sucesso.');
        }

        return $this->error('Não foi possível redefinir a senha.', 400);
    }
}
