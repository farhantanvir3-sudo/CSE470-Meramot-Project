<?php

namespace App\Http\Controllers;

use App\Models\ServicePackage;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R8: Direct Service Package Catalog & Shopping Cart Flow
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Controller (ServicePackageController)
 * Models Touched: ServicePackage, Cart
 * View Rendered: resources/views/packages/catalog.blade.php
 *
 * INSTRUCTIONS FOR SHANID:
 * 1. Implement index(): Display fixed-price standard repair packages
 *    (e.g., iPhone Screen Replacement, Laptop Thermal Repasting, Battery Swap).
 * 2. Implement addToCart(Request $request): Store selected package in session cart.
 * 3. Implement checkout(): Forward cart items to payment gateway controller (R17).
 * ============================================================================
 */
class ServicePackageController extends Controller
{
    /**
     * Display service package catalog.
     * Route: GET /packages/catalog
     */
    public function index()
    {
        // TODO (Shanid): Load packages grouped by device category
        $packages = ServicePackage::all();

        return view('packages.catalog', compact('packages'));
    }

    /**
     * Add selected package to user session cart.
     * Route: POST /cart/add
     */
    public function addToCart(Request $request)
    {
        $packageId = $request->input('package_id');
        $package = ServicePackage::findOrFail($packageId);

        $cart = session()->get('cart', []);
        $cart[$packageId] = [
            'title' => $package->title,
            'price' => $package->fixed_price,
            'category' => $package->device_category,
        ];
        session()->put('cart', $cart);

        return redirect()->back()->with('success', 'Package added to repair cart.');
    }
}
