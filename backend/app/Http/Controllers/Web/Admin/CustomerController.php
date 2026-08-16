<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Concerns\ServesBothSections;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreCustomerRequest;
use App\Http\Requests\Web\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use Inertia\Inertia;

class CustomerController extends Controller
{
    use ServesBothSections;

    /**
     * List customers. Rows live-sync via the `customers` shape; work-order
     * counts come as a prop.
     */
    public function index()
    {
        $counts = Customer::withCount('workOrders')
            ->get(['id'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->work_orders_count]);

        return Inertia::render('admin/customers/Index', [
            'counts' => $counts,
            'basePath' => $this->basePath('/customers'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/customers/Create', [
            'basePath' => $this->basePath('/customers'),
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['payment_score'] = $validated['payment_score'] ?? 0;

        Customer::create($validated);

        // The list's New-customer modal posts `stay` so the user keeps their page
        // — filters, paging and scroll — while the new row live-syncs in.
        if ($request->boolean('stay')) {
            return back()->with('success', __('Customer created successfully.'));
        }

        return redirect()->to($this->sectionRoute('customers.index'))
            ->with('success', __('Customer created successfully.'));
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('admin/customers/Edit', [
            'basePath' => $this->basePath('/customers'),
            'customer' => $customer->only(
                'id', 'name', 'code', 'tier', 'payment_score',
                'total_orders', 'total_revenue', 'notes', 'is_active'
            ),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $validated['payment_score'] = $validated['payment_score'] ?? 0;

        $customer->update($validated);

        return redirect()->to($this->sectionRoute('customers.index'))
            ->with('success', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer)
    {
        // Work orders keep their history — the FK nulls out on delete — so a
        // customer can always be removed. Soft delete preserves the record.
        $customer->delete();

        return redirect()->to($this->sectionRoute('customers.index'))
            ->with('success', __('Customer deleted successfully.'));
    }

    public function toggleActive(Customer $customer)
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        $status = $customer->is_active ? __('activated') : __('deactivated');

        return redirect()->to($this->sectionRoute('customers.index'))
            ->with('success', __('Customer :status successfully.', ['status' => $status]));
    }
}
