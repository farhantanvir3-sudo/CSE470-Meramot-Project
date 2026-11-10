<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R8: Direct Service Package Catalog & Unified Direct-Order Cart Flow
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Merged Architecture: Farhan Tanvir [22299179] (Direct-Order + Quickmatch)
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Models Touched: ServicePackage, Cart, CartItem, RepairShop, RepairRequest, RepairJob
 * Views Rendered (from root `frontend/views/`):
 *   - packages/catalog.blade.php
 *   - packages/cart.blade.php
 *   - packages/checkout.blade.php
 * ============================================================================
 */
class ServicePackageController extends Controller
{
    public const REGULAR_DELIVERY_FEE = 80;
    public const EXPRESS_DELIVERY_FEE = 150;
    public const SERVICE_CHARGE_RATE = 0.05;
    public const MIN_SERVICE_CHARGE = 30;

    public static function calculateServiceCharge(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0;
        }
        return (float) max(self::MIN_SERVICE_CHARGE, round($subtotal * self::SERVICE_CHARGE_RATE));
    }

    /**
     * Resolve target workshop for Direct Order (locked shop or Quickmatch top-rated Dhaka shop).
     */
    private function resolveTargetShop(?int $requestedShopId = null): ?RepairShop
    {
        if ($requestedShopId) {
            $shop = RepairShop::find($requestedShopId);
            if ($shop) {
                return $shop;
            }
        }

        $sessionShopId = session('cart_shop_id');
        if ($sessionShopId) {
            $shop = RepairShop::find($sessionShopId);
            if ($shop) {
                return $shop;
            }
        }

        return RepairShop::where('is_active', true)
            ->orderBy('rating', 'desc')
            ->orderBy('review_count', 'desc')
            ->first() ?? RepairShop::first();
    }

    /**
     * Persist session cart into PostgreSQL `carts` & `cart_items` when user & shop exist.
     */
    private function syncDatabaseCart(?User $user, ?RepairShop $shop, array $sessionCart): void
    {
        if (!$user || !$shop) {
            return;
        }

        $dbCart = Cart::firstOrCreate(
            [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'status' => 'ACTIVE',
            ]
        );

        $dbCart->items()->delete();

        if (empty($sessionCart)) {
            $dbCart->update(['status' => 'ABANDONED']);
            return;
        }

        foreach ($sessionCart as $key => $item) {
            CartItem::create([
                'cart_id' => $dbCart->id,
                'service_name' => $item['title'],
                'description' => $item['description'] ?? ($item['category'] ?? 'Direct Repair Service'),
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'metadata' => [
                    'item_key' => (string) $key,
                    'source' => $item['source'] ?? 'PACKAGE',
                    'duration' => $item['duration'] ?? 24,
                ],
            ]);
        }
    }

    /**
     * Display service package catalog with Quickmatch shop selector.
     * Route: GET /packages/catalog
     */
    public function index(Request $request)
    {
        $query = ServicePackage::query()->where('is_active', true);

        if ($request->filled('category')) {
            $query->where('device_category', $request->input('category'));
        }

        if ($request->filled('max_price')) {
            $query->where('fixed_price', '<=', (float) $request->input('max_price'));
        }

        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('device_category', 'ILIKE', "%{$searchTerm}%");
            });
        }

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

        $categories = ServicePackage::select('device_category')
            ->distinct()
            ->pluck('device_category')
            ->toArray();

        $verifiedShops = RepairShop::orderBy('rating', 'desc')->get(['id', 'name', 'area', 'rating']);
        $cartCount = count(session()->get('cart', []));

        return view('packages.catalog', compact('packages', 'categories', 'verifiedShops', 'cartCount'));
    }

    /**
     * Add either a Fixed Package (`package_id`) or a Shop Service (`service_name` + `shop_id`) to Cart.
     * Route: POST /cart/add
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'nullable|exists:service_packages,id',
            'shop_id' => 'nullable|exists:repair_shops,id',
            'service_name' => 'required_without:package_id|nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required_without:package_id|nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);
        $shop = $this->resolveTargetShop(isset($validated['shop_id']) ? (int) $validated['shop_id'] : null);
        $cart = session()->get('cart', []);

        // If user explicitly added from a different shop profile, scope cart to that shop
        if (!empty($validated['shop_id']) && session('cart_shop_id') && (int) session('cart_shop_id') !== (int) $validated['shop_id'] && empty($validated['package_id'])) {
            $cart = [];
        }

        if ($shop) {
            session()->put('cart_shop_id', $shop->id);
            session()->put('cart_shop_locked', !empty($validated['service_name']) && empty($validated['package_id']));
        }

        if (!empty($validated['package_id'])) {
            $package = ServicePackage::findOrFail($validated['package_id']);
            $itemKey = $package->id;

            if (isset($cart[$itemKey])) {
                $cart[$itemKey]['quantity'] = min(10, $cart[$itemKey]['quantity'] + $quantity);
            } else {
                $cart[$itemKey] = [
                    'id' => $package->id,
                    'title' => $package->title,
                    'description' => $package->description ?? implode(', ', array_slice($package->inclusions ?? [], 0, 2)),
                    'price' => (float) $package->fixed_price,
                    'category' => $package->device_category,
                    'duration' => $package->estimated_turnaround_hours,
                    'warranty_months' => $package->warranty_months,
                    'quantity' => $quantity,
                    'source' => 'QUICKMATCH_PACKAGE',
                ];
            }
        } else {
            $serviceTitle = trim($validated['service_name']);
            $itemKey = 'svc_' . md5(($shop?->id ?? 0) . '_' . strtolower($serviceTitle));

            if (isset($cart[$itemKey])) {
                $cart[$itemKey]['quantity'] = min(10, $cart[$itemKey]['quantity'] + $quantity);
            } else {
                $cart[$itemKey] = [
                    'id' => $itemKey,
                    'title' => $serviceTitle,
                    'description' => $validated['description'] ?? 'Verified workshop repair service',
                    'price' => (float) $validated['price'],
                    'category' => 'Shop Direct Service',
                    'duration' => $shop ? max(2, (int) round(($shop->eta_minutes ?: 120) / 60)) : 4,
                    'warranty_months' => 3,
                    'quantity' => $quantity,
                    'source' => 'SHOP_SERVICE',
                ];
            }
        }

        session()->put('cart', $cart);
        $this->syncDatabaseCart($request->user(), $shop, $cart);

        $message = $shop
            ? "Service added to cart • {$shop->name}"
            : 'Service added to cart';

        if (!$request->expectsJson()) {
            return redirect()->route('cart.view')->with('success', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'cart_count' => count($cart),
            'shop_name' => $shop?->name,
        ]);
    }

    /**
     * Switch target shop for Quickmatch Direct-Order Cart.
     * Route: POST /cart/shop
     */
    public function selectCartShop(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:repair_shops,id',
        ]);

        $shop = RepairShop::findOrFail($validated['shop_id']);
        session()->put('cart_shop_id', $shop->id);
        $this->syncDatabaseCart($request->user(), $shop, session()->get('cart', []));

        return redirect()->route('cart.view')->with('success', "Direct Order workshop set to {$shop->name} ({$shop->area}).");
    }

    /**
     * View single-page Direct-Order & Quickmatch Cart Wizard.
     * Route: GET /cart
     */
    public function viewCart(Request $request)
    {
        $cart = session()->get('cart', []);
        $total = array_reduce($cart, function ($sum, $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0);

        $serviceCharge = self::calculateServiceCharge($total);
        $activeShop = !empty($cart) ? $this->resolveTargetShop() : null;
        $shopLocked = (bool) session('cart_shop_locked', false);
        $verifiedShops = RepairShop::orderBy('rating', 'desc')->get(['id', 'name', 'area', 'rating', 'address']);

        return view('packages.cart', compact(
            'cart',
            'total',
            'serviceCharge',
            'activeShop',
            'shopLocked',
            'verifiedShops'
        ));
    }

    /**
     * Remove item from cart.
     * Route: DELETE /cart/remove/{packageId}
     */
    public function removeFromCart(Request $request, $packageId)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$packageId])) {
            unset($cart[$packageId]);
            session()->put('cart', $cart);
        }

        if (empty($cart)) {
            session()->forget(['cart_shop_id', 'cart_shop_locked']);
        }

        $this->syncDatabaseCart($request->user(), $this->resolveTargetShop(), $cart);

        return response()->json([
            'success' => true,
            'message' => 'Service removed from cart',
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
            $cart[$packageId]['quantity'] = (int) $validated['quantity'];
            session()->put('cart', $cart);
            $this->syncDatabaseCart($request->user(), $this->resolveTargetShop(), $cart);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart quantity updated',
        ]);
    }

    /**
     * Clear entire cart.
     * Route: POST /cart/clear
     */
    public function clearCart(Request $request)
    {
        session()->forget(['cart', 'cart_shop_id', 'cart_shop_locked']);
        $this->syncDatabaseCart($request->user(), $this->resolveTargetShop(), []);

        return redirect()->route('packages.catalog')->with('success', 'Cart cleared.');
    }

    /**
     * Proceed to checkout screen (kept compatible with Feature R8 routes & tests).
     * Route: GET /cart/checkout
     */
    public function checkout()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('packages.catalog')
                ->with('error', 'Your cart is empty');
        }

        $total = array_reduce($cart, function ($sum, $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0);

        $serviceCharge = self::calculateServiceCharge($total);
        $activeShop = $this->resolveTargetShop();
        $verifiedShops = RepairShop::orderBy('rating', 'desc')->get(['id', 'name', 'area', 'rating']);

        return view('packages.checkout', compact('cart', 'total', 'serviceCharge', 'activeShop', 'verifiedShops'));
    }

    /**
     * Place Direct Order from Cart (Unified Direct-Order Architecture in Laravel MVC):
     * Creates RepairRequest (`status = PENDING`, `source = DIRECT_SERVICE`) + pre-linked
     * RepairJob (`status = CREATED`) + PaymentTransaction, routing into Vendor "Direct Orders".
     * Route: POST /cart/checkout/place
     */
    public function placeDirectOrder(Request $request)
    {
        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('packages.catalog')->with('error', 'Your cart is empty');
        }

        $validated = $request->validate([
            'shop_id' => 'nullable|exists:repair_shops,id',
            'schedule_type' => 'required|in:NOW,LATER',
            'scheduled_at' => 'nullable|date',
            'delivery_type' => 'required|in:REGULAR,EXPRESS',
            'payment_method' => 'required|in:CASH,SSLCOMMERZ',
            'area' => 'nullable|string|max:100',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:30',
            'problem_note' => 'nullable|string|max:1000',
        ]);

        if ($validated['schedule_type'] === 'LATER' && empty($validated['scheduled_at'])) {
            return back()->withErrors(['scheduled_at' => 'Please choose a pickup date and time for scheduled bookings.'])->withInput();
        }

        $shop = $this->resolveTargetShop(isset($validated['shop_id']) ? (int) $validated['shop_id'] : null);
        if (!$shop) {
            return back()->with('error', 'No active verified repair shop available for dispatch.');
        }

        $user = $request->user() ?? User::where('role', 'customer')->first() ?? User::first();
        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0);
        $serviceCharge = self::calculateServiceCharge($subtotal);
        $deliveryFee = $validated['delivery_type'] === 'EXPRESS' ? self::EXPRESS_DELIVERY_FEE : self::REGULAR_DELIVERY_FEE;
        $totalAmount = $subtotal + $serviceCharge + $deliveryFee;

        $serviceLines = collect($cart)->values()->map(function ($item, $idx) {
            return ($idx + 1) . '. ' . $item['title'] . ' × ' . $item['quantity'] . ' — ৳' . number_format($item['price'] * $item['quantity'], 2);
        })->implode("\n");

        $firstItem = reset($cart);
        $title = count($cart) === 1
            ? $firstItem['title']
            : count($cart) . ' Direct Services • ' . $shop->name;

        $scheduleLabel = $validated['schedule_type'] === 'NOW'
            ? 'Immediate Courier Pickup (NOW)'
            : 'Scheduled Pickup (' . $validated['scheduled_at'] . ')';

        $fullAddress = trim($validated['address']);
        if (!empty($validated['area']) && !str_contains($fullAddress, $validated['area'])) {
            $fullAddress .= ', ' . $validated['area'] . ', Dhaka';
        }

        $repairRequest = DB::transaction(function () use (
            $user, $shop, $cart, $validated, $title, $serviceLines, $scheduleLabel,
            $subtotal, $serviceCharge, $deliveryFee, $totalAmount, $fullAddress
        ) {
            $req = RepairRequest::create([
                'customer_id' => $user->id,
                'source' => 'DIRECT_SERVICE',
                'requested_shop_id' => $shop->id,
                'title' => $title,
                'description' => "Direct Order Cart Checkout ({$shop->name})\n"
                    . "Schedule: {$scheduleLabel}\n"
                    . "Logistics: {$validated['delivery_type']} (৳{$deliveryFee})\n"
                    . "Payment: {$validated['payment_method']}\n"
                    . "Subtotal: ৳" . number_format($subtotal, 2) . " | Escrow Fee: ৳" . number_format($serviceCharge, 2) . " | Total: ৳" . number_format($totalAmount, 2) . "\n\n"
                    . "Selected Services:\n{$serviceLines}",
                'problem' => !empty($validated['problem_note'])
                    ? trim($validated['problem_note']) . "\n\nBooked Services:\n" . $serviceLines
                    : "Booked Direct Services:\n" . $serviceLines,
                'device_type' => $firstItem['category'] ?? 'Service Order',
                'brand' => $shop->name,
                'model_name' => 'Direct Service Booking',
                'issue_category' => 'Direct Order',
                'mode' => 'DIRECT_REPAIR',
                // Starts as PENDING in Vendor Dashboard "Direct Orders" queue
                'status' => RepairRequest::STATUS_PENDING,
                'preferred_pickup' => true,
                'preferred_service_type' => 'pickup_dropoff',
                'delivery_type' => $validated['delivery_type'],
                'contact_phone' => $validated['phone'],
                'pickup_address' => $fullAddress,
                'dropoff_address' => $shop->address ?? ($shop->area . ', Dhaka'),
                'checkup_fee' => $serviceCharge,
                'quoted_final_amount' => $totalAmount,
            ]);

            RepairJob::create([
                'repair_request_id' => $req->id,
                'shop_id' => $shop->id,
                'status' => 'CREATED',
                'final_quoted_amount' => $totalAmount,
                'final_quote_items' => array_values($cart),
            ]);

            PaymentTransaction::create([
                'user_id' => $user->id,
                'repair_request_id' => $req->id,
                'amount' => $totalAmount,
                'currency' => 'BDT',
                'payment_method' => $validated['payment_method'],
                'status' => 'PENDING',
                'transaction_reference' => 'MRM-DO-' . strtoupper(Str::random(8)),
            ]);

            Cart::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'CHECKED_OUT']);

            foreach ($cart as $key => $item) {
                if (is_numeric($key)) {
                    ServicePackage::where('id', (int) $key)->increment('order_count', (int) $item['quantity']);
                }
            }

            return $req;
        });

        session()->forget(['cart', 'cart_shop_id', 'cart_shop_locked']);

        if ($validated['payment_method'] === 'SSLCOMMERZ') {
            return redirect("/checkout/payment/{$repairRequest->id}")
                ->with('success', "Direct Order #{$repairRequest->id} created for {$shop->name}! Complete SSLCommerz escrow authorization below.");
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', "Direct Order #{$repairRequest->id} sent to {$shop->name}! Status is PENDING vendor acceptance in Direct Orders.");
    }
}
