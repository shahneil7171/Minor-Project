<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;

class AddressController extends Controller
{
    /**
     * Show the address management page.
     */
    public function index()
    {
        $addresses = auth()->user()->addresses()->latest()->paginate(10);
        return view('profile.addresses.index', compact('addresses'));
    }

    /**
     * Show the form for creating a new address.
     */
    public function create()
    {
        return view('profile.addresses.create');
    }

    /**
     * Store a newly created address.
     */
    public function store(StoreAddressRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // If this is the first address, make it default
        if (auth()->user()->addresses()->count() == 0) {
            $data['is_default_shipping'] = true;
            $data['is_default_billing'] = true;
        }

        // If user marked as default shipping, unmark previous default
        if ($data['is_default_shipping'] ?? false) {
            auth()->user()->addresses()
                ->where('is_default_shipping', true)
                ->update(['is_default_shipping' => false]);
        }

        // If user marked as default billing, unmark previous default
        if ($data['is_default_billing'] ?? false) {
            auth()->user()->addresses()
                ->where('is_default_billing', true)
                ->update(['is_default_billing' => false]);
        }

        Address::create($data);

        return redirect()->route('profile.addresses.index')
            ->with('success', 'Address added successfully!');
    }

    /**
     * Show the form for editing an address.
     */
    public function edit(Address $address)
    {
        $this->authorize('update', $address);
        return view('profile.addresses.edit', compact('address'));
    }

    /**
     * Update the specified address.
     */
    public function update(UpdateAddressRequest $request, Address $address)
    {
        $this->authorize('update', $address);

        $data = $request->validated();

        // If user marked as default shipping, unmark previous default
        if ($data['is_default_shipping'] ?? false) {
            auth()->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default_shipping' => false]);
        }

        // If user marked as default billing, unmark previous default
        if ($data['is_default_billing'] ?? false) {
            auth()->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default_billing' => false]);
        }

        $address->update($data);

        return redirect()->route('profile.addresses.index')
            ->with('success', 'Address updated successfully!');
    }

    /**
     * Delete the specified address.
     */
    public function destroy(Address $address)
    {
        $this->authorize('delete', $address);

        $wasDefault = $address->is_default_shipping || $address->is_default_billing;
        $address->delete();

        // Set another address as default if the deleted one was default
        if ($wasDefault) {
            $newDefault = auth()->user()->addresses()->first();
            if ($newDefault) {
                $newDefault->update([
                    'is_default_shipping' => true,
                    'is_default_billing' => true,
                ]);
            }
        }

        return redirect()->route('profile.addresses.index')
            ->with('success', 'Address deleted successfully!');
    }

    /**
     * Set an address as default shipping.
     */
    public function setDefaultShipping(Address $address)
    {
        $this->authorize('update', $address);

        auth()->user()->addresses()
            ->update(['is_default_shipping' => false]);

        $address->update(['is_default_shipping' => true]);

        return redirect()->back()
            ->with('success', 'Default shipping address updated!');
    }

    /**
     * Set an address as default billing.
     */
    public function setDefaultBilling(Address $address)
    {
        $this->authorize('update', $address);

        auth()->user()->addresses()
            ->update(['is_default_billing' => false]);

        $address->update(['is_default_billing' => true]);

        return redirect()->back()
            ->with('success', 'Default billing address updated!');
    }
}
