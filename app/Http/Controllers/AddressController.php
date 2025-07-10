<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Address;

class AddressController extends Controller
{
    /**
     * Display user's addresses.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $addresses = $user->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses->map(function ($address) {
                return $this->formatAddressResponse($address);
            })
        ]);
    }

    /**
     * Display the specified address.
     */
    public function show(Request $request, Address $address): JsonResponse
    {
        // Check if user owns this address
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatAddressResponse($address)
        ]);
    }

    /**
     * Store a newly created address.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'postal_code' => 'required|string|size:8',
            'reference' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'boolean',
        ]);

        $user = $request->user();

        // Check if user already has 5 addresses (limit)
        if ($user->addresses()->count() >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Você pode ter no máximo 5 endereços cadastrados'
            ], 422);
        }

        // Check if label is unique for this user
        if ($user->addresses()->where('label', $validated['label'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um endereço com este nome'
            ], 422);
        }

        $validated['user_id'] = $user->id;

        // If this is the first address or is_default is true, make it default
        if ($user->addresses()->count() === 0 || ($validated['is_default'] ?? false)) {
            $validated['is_default'] = true;
        }

        $address = Address::create($validated);

        // If this address is set as default, unset others
        if ($address->is_default) {
            $address->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Endereço criado com sucesso',
            'data' => $this->formatAddressResponse($address)
        ], 201);
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        // Check if user owns this address
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'postal_code' => 'required|string|size:8',
            'reference' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'boolean',
        ]);

        // Check if label is unique for this user (excluding current address)
        if ($request->user()->addresses()
            ->where('label', $validated['label'])
            ->where('id', '!=', $address->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um endereço com este nome'
            ], 422);
        }

        $address->update($validated);

        // If this address is set as default, unset others
        if ($address->is_default) {
            $address->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Endereço atualizado com sucesso',
            'data' => $this->formatAddressResponse($address->fresh())
        ]);
    }

    /**
     * Remove the specified address.
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        // Check if user owns this address
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        // Check if address has active subscriptions
        if ($address->subscriptions()->whereIn('status', ['active', 'pending_payment'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir um endereço com assinaturas ativas'
            ], 422);
        }

        // If this is the default address, set another as default
        if ($address->is_default) {
            $newDefault = $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->first();
            
            if ($newDefault) {
                $newDefault->setAsDefault();
            }
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Endereço excluído com sucesso'
        ]);
    }

    /**
     * Set address as default.
     */
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        // Check if user owns this address
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        $address->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Endereço definido como padrão',
            'data' => $this->formatAddressResponse($address->fresh())
        ]);
    }

    /**
     * Search for postal code information.
     */
    public function searchPostalCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => 'required|string|size:8'
        ]);

        $postalCode = $validated['postal_code'];

        // Here you would integrate with a postal code API like ViaCEP
        // For now, we'll return a mock response
        $mockData = [
            'postal_code' => $postalCode,
            'street' => 'Rua das Flores',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
        ];

        return response()->json([
            'success' => true,
            'data' => $mockData,
            'message' => 'CEP encontrado (dados simulados)'
        ]);
    }

    /**
     * Calculate distance between two addresses.
     */
    public function calculateDistance(Request $request, Address $address): JsonResponse
    {
        // Check if user owns this address
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'target_address_id' => 'required|exists:addresses,id'
        ]);

        $targetAddress = Address::findOrFail($validated['target_address_id']);

        // Check if user owns the target address
        if ($targetAddress->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço de destino não encontrado'
            ], 404);
        }

        $distance = $address->distanceTo($targetAddress);

        if ($distance === null) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível calcular a distância. Coordenadas não disponíveis.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'distance_km' => round($distance, 2),
                'distance_formatted' => round($distance, 2) . ' km',
                'from_address' => $this->formatAddressResponse($address),
                'to_address' => $this->formatAddressResponse($targetAddress),
            ]
        ]);
    }

    /**
     * Format address response.
     */
    private function formatAddressResponse(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'street' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'formatted_postal_code' => $address->formatted_postal_code,
            'reference' => $address->reference,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'is_default' => $address->is_default,
            'full_address' => $address->full_address,
            'has_coordinates' => $address->hasCoordinates(),
            'created_at' => $address->created_at,
            'updated_at' => $address->updated_at,
        ];
    }
}
