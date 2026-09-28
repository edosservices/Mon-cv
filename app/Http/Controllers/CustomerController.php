<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->latest()
            ->paginate(20);

        return view('customers.index', ['customers' => $customers]);
    }

    public function show(Customer $customer)
    {
        $customer->load(['vouchers.plan', 'sales']);

        return view('customers.show', ['customer' => $customer]);
    }
}
