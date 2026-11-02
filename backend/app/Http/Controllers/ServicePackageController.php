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
    public function index(Request $request)
    {
        $query = ServicePackage::query()->where('is_active', true);

        // Filter by device category
        if ($request->filled('category')) {
            $query->where('device_category', $request->input('category'));
        }

        // Filter by price range
        if ($request->filled('max_price')) {
            $query->where('fixed_price', '<=', (int) $request->input('max_price'));
        }

        // Search by title or description
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%");
            });
        }

        // Sort options
        $sort = $request->input('sort', 'popular');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('fixed_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('fixed_price', 'desc');
                break;
            case 'popular':
            default:
                $query->orderBy('is_popular', 'desc')
                      ->orderBy('order_count', 'desc');
                break;
        }

        $packages = $query->paginate(12)->withQueryString();

        // Get unique categories for filter
        $categories = ServicePackage::select('device_category')
            ->distinct()
            ->pluck('device_category')
            ->toArray();

        // Get cart count
        $cartCount = count(session()->get('cart', []));

        return view('packages.catalog', compact('packages', 'categories', 'cartCount'));
    }

    /**
     * Add selected package to user session cart.
     * Route: POST /cart/add
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:service_packages,id',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        $packageId = $validated['package_id'];
        $quantity = $validated['quantity'] ?? 1;
        
        $package = ServicePackage::findOrFail($packageId);

        $cart = session()->get('cart', []);
        
        if (isset($cart[$packageId])) {
            // Update quantity if already in cart
            $cart[$packageId]['quantity'] += $quantity;
        } else {
            // Add new item to cart
            $cart[$packageId] = [
                'id' => $package->id,
                'title' => $package->title,
                'price' => $package->fixed_price,
                'category' => $package->device_category,
                'duration' => $package->estimated_duration_hours,
                'quantity' => $quantity,
            ];
        }
        
        session()->put('cart', $cart);

        return response()->json([
            'success' => true,
            'message' => 'Package added to cart',
            'cart_count' => count($cart),
        ]);
    }

    /**
     * View shopping cart.
     * Route: GET /cart
     */
    public function viewCart()
    {
        $cart = session()->get('cart', []);
        $total = array_reduce($cart, function($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']);
        }, 0);

        return view('packages.cart', compact('cart', 'total'));
    }

    /**
     * Remove item from cart.
     * Route: DELETE /cart/remove/{packageId}
     */
    public function removeFromCart($packageId)
    {
        $cart = session()->get('cart', []);
        
        if (isset($cart[$packageId])) {
            unset($cart[$packageId]);
            session()->put('cart', $cart);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'cart_count' => count($cart),
        ]);
    }

    /**
     * Update cart item quantity.
     * Route: PATCH /cart/update/{packageId}
     */
    public function updateCartQuantity(Request $request, $packageId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $cart = session()->get('cart', []);
        
        if (isset($cart[$packageId])) {
            $cart[$packageId]['quantity'] = $validated['quantity'];
            session()->put('cart', $cart);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart updated',
        ]);
    }

    /**
     * Clear entire cart.
     * Route: POST /cart/clear
     */
    public function clearCart()
    {
        session()->forget('cart');

        return redirect()->route('packages.catalog')->with('success', 'Cart cleared');
    }

    /**
     * Proceed to checkout with cart items.
     * Route: GET /cart/checkout
     */
    public function checkout()
    {
        $cart = session()->get('cart', []);
        
        if (empty($cart)) {
            return redirect()->route('packages.catalog')
                ->with('error', 'Your cart is empty');
        }

        $total = array_reduce($cart, function($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']);
        }, 0);

        // Pass cart to checkout view (will integrate with R17 payment gateway)
        return view('packages.checkout', compact('cart', 'total'));
    }
}
