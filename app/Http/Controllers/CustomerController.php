<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    /* =========================================================
     | INDEX — GET /api/customers
     * ========================================================= */
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('lastname', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('whatsapp', 'like', "%{$s}%");
            });
        }

        return $query->latest()->get();
    }

    /* =========================================================
     | STORE — POST /api/customers
     * ========================================================= */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'lastname'      => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255|unique:customers,email',
            'phone'         => 'nullable|string|max:50',
            'whatsapp'      => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')
                ->store('customers', 'public');
        }

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    /* =========================================================
     | SHOW — GET /api/customers/{id}
     * ========================================================= */
    public function show(Customer $customer)
    {
        return $customer;
    }

    /* =========================================================
     | UPDATE — PUT /api/customers/{id}
     * ========================================================= */
    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'lastname'      => 'sometimes|nullable|string|max:255',
            'email'         => 'sometimes|nullable|email|max:255|unique:customers,email,' . $customer->id,
            'phone'         => 'sometimes|nullable|string|max:50',
            'whatsapp'      => 'sometimes|nullable|string|max:50',
            'address'       => 'sometimes|nullable|string|max:255',
            'profile_photo' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            if ($customer->profile_photo
                && Storage::disk('public')->exists($customer->profile_photo)) {
                Storage::disk('public')->delete($customer->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')
                ->store('customers', 'public');
        }

        $customer->update($data);

        return $customer->fresh();
    }

    /* =========================================================
     | DESTROY — DELETE /api/customers/{id}
     * ========================================================= */
    public function destroy(Customer $customer)
    {
        if ($customer->profile_photo
            && Storage::disk('public')->exists($customer->profile_photo)) {
            Storage::disk('public')->delete($customer->profile_photo);
        }

        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}