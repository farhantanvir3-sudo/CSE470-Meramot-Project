<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RepairShop;
use App\Models\VendorProfile;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherManagementController extends Controller
{
    public function __construct(
        private readonly VoucherService $voucherService
    ) {
    }

    /**
     * Admin Featured Vouchers & First-Order Free Courier Governance Console.
     * Route: GET /admin/vouchers
     */
    public function adminIndex(Request $request)
    {
        if (RepairShop::count() > 0 && !Voucher::where('is_system_default', true)->exists()) {
            $this->voucherService->ensureDefaultFreeCourierVoucher($request->user()?->id);
        }

        $adminVouchers = Voucher::with(['excludedShops:id,name,branch_name,is_branch,area,city'])
            ->withCount('redemptions')
            ->where('issuer_type', Voucher::ISSUER_ADMIN)
            ->orderByDesc('is_system_default')
            ->orderByDesc('is_featured')
            ->orderBy('featured_order', 'asc')
            ->orderByDesc('id')
            ->get();

        $vendorVouchers = Voucher::with(['vendorProfile:id,shop_name,brand_name', 'includedShops:id,name,branch_name,is_branch,area,city'])
            ->withCount('redemptions')
            ->where('issuer_type', Voucher::ISSUER_VENDOR)
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $stackHealth = $this->voucherService->getAdminFeaturedStackHealth();

        $selectableShops = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->orderBy('city')
            ->orderBy('area')
            ->get(['id', 'name', 'is_branch', 'branch_name', 'area', 'city', 'supports_pickup', 'categories']);

        return view('admin.vouchers', compact(
            'adminVouchers',
            'vendorVouchers',
            'stackHealth',
            'selectableShops'
        ));
    }

    /**
     * Create a new Admin Featured Voucher (manual code required).
     * Route: POST /admin/vouchers
     */
    public function adminStore(Request $request)
    {
        $payload = $this->validateVoucherPayload($request, null, true);

        $voucher = DB::transaction(function () use ($request, $payload) {
            $nextOrder = $payload['featured_order'] ?? ((int) Voucher::where('issuer_type', Voucher::ISSUER_ADMIN)
                ->where('is_system_default', false)
                ->max('featured_order') + 10);

            $voucher = Voucher::create([
                'code' => $payload['code'],
                'title' => $payload['title'],
                'banner_subtitle' => $payload['banner_subtitle'],
                'description' => $payload['description'],
                'cta_href' => $payload['cta_href'] ?: '/shops?voucher=true',
                'voucher_type' => $payload['voucher_type'],
                'issuer_type' => Voucher::ISSUER_ADMIN,
                'branch_scope' => Voucher::SCOPE_UNIVERSAL,
                'created_by_user_id' => $request->user()?->id,
                'vendor_profile_id' => null,
                'discount_type' => $payload['discount_type'],
                'discount_value' => $payload['discount_value'],
                'min_order_amount' => $payload['min_order_amount'],
                'max_discount_amount' => $payload['max_discount_amount'],
                'required_payment_method' => $payload['required_payment_method'],
                'is_first_order_only' => $payload['is_first_order_only'],
                'is_system_default' => false,
                'is_featured' => $payload['is_featured'],
                'featured_order' => max(1, (int) $nextOrder),
                'starts_at' => $payload['starts_at'],
                'expires_at' => $payload['expires_at'],
                'valid_days_of_week' => $payload['valid_days_of_week'],
                'daily_start_time' => $payload['daily_start_time'],
                'daily_end_time' => $payload['daily_end_time'],
                'is_active' => true,
            ]);

            $voucher->excludedShops()->sync($payload['excluded_shop_ids']);

            AuditLog::create([
                'actor_id' => $request->user()?->id,
                'action' => 'admin_featured_voucher_created',
                'target_resource' => "vouchers:{$voucher->id}",
                'payload_before' => null,
                'payload_after' => [
                    'code' => $voucher->code,
                    'title' => $voucher->title,
                    'voucher_type' => $voucher->voucher_type,
                    'discount_type' => $voucher->discount_type,
                    'excluded_count' => count($payload['excluded_shop_ids']),
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
            ]);

            return $voucher;
        });

        $this->voucherService->syncShopVoucherFlags();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "Featured Voucher {$voucher->code} ({$voucher->advertisedBadgeText()}) created.",
                'redirect' => route('admin.vouchers.index'),
                'voucher' => [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'title' => $voucher->title,
                ],
            ]);
        }

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Featured Voucher {$voucher->code} ({$voucher->advertisedBadgeText()}) created.");
    }

    /**
     * Update an existing Admin Featured Voucher (including its time period & excluded shop branches).
     * Route: PUT /admin/vouchers/{voucher}
     */
    public function adminUpdate(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->issuer_type === Voucher::ISSUER_ADMIN, 403);

        if ($voucher->is_system_default) {
            $validated = $request->validate([
                'title' => 'required|string|max:140',
                'banner_subtitle' => 'nullable|string|max:240',
                'excluded_shop_ids' => 'nullable|array',
                'excluded_shop_ids.*' => 'integer|exists:repair_shops,id',
                'is_active' => 'nullable|boolean',
            ]);

            DB::transaction(function () use ($voucher, $validated) {
                $voucher->update([
                    'title' => trim($validated['title']),
                    'banner_subtitle' => isset($validated['banner_subtitle']) ? trim((string) $validated['banner_subtitle']) : $voucher->banner_subtitle,
                    'is_active' => $validated['is_active'] ?? $voucher->is_active,
                ]);
                $voucher->excludedShops()->sync($validated['excluded_shop_ids'] ?? []);
            });

            $this->voucherService->syncShopVoucherFlags();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => "Default First-Order Free Courier voucher ({$voucher->code}) updated.",
                    'redirect' => route('admin.vouchers.index'),
                ]);
            }

            return redirect()
                ->route('admin.vouchers.index')
                ->with('success', "Default First-Order Free Courier voucher ({$voucher->code}) updated.");
        }

        $payload = $this->validateVoucherPayload($request, $voucher, true);

        DB::transaction(function () use ($voucher, $payload) {
            $voucher->update([
                'code' => $payload['code'],
                'title' => $payload['title'],
                'banner_subtitle' => $payload['banner_subtitle'],
                'description' => $payload['description'],
                'cta_href' => $payload['cta_href'] ?: '/shops?voucher=true',
                'voucher_type' => $payload['voucher_type'],
                'discount_type' => $payload['discount_type'],
                'discount_value' => $payload['discount_value'],
                'min_order_amount' => $payload['min_order_amount'],
                'max_discount_amount' => $payload['max_discount_amount'],
                'required_payment_method' => $payload['required_payment_method'],
                'is_first_order_only' => $payload['is_first_order_only'],
                'is_featured' => $payload['is_featured'],
                'featured_order' => $payload['featured_order'] ?? $voucher->featured_order,
                'starts_at' => $payload['starts_at'],
                'expires_at' => $payload['expires_at'],
                'valid_days_of_week' => $payload['valid_days_of_week'],
                'daily_start_time' => $payload['daily_start_time'],
                'daily_end_time' => $payload['daily_end_time'],
                'is_active' => $payload['is_active'] ?? $voucher->is_active,
            ]);

            $voucher->excludedShops()->sync($payload['excluded_shop_ids']);
        });

        $this->voucherService->syncShopVoucherFlags();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "Featured Voucher {$voucher->code} updated.",
                'redirect' => route('admin.vouchers.index'),
            ]);
        }

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Featured Voucher {$voucher->code} updated.");
    }

    /**
     * Promote a Featured Voucher to #1 Top-Most slot on the Homepage banner stack.
     * Route: POST /admin/vouchers/{voucher}/promote-top
     */
    public function adminPromoteTop(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->issuer_type === Voucher::ISSUER_ADMIN && !$voucher->is_system_default, 403);

        DB::transaction(function () use ($voucher) {
            Voucher::where('issuer_type', Voucher::ISSUER_ADMIN)
                ->where('is_system_default', false)
                ->where('id', '!=', $voucher->id)
                ->increment('featured_order', 10);

            $voucher->update([
                'is_featured' => true,
                'is_active' => true,
                'featured_order' => 1,
            ]);
        });

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Featured Voucher {$voucher->code} promoted to #1 on the Homepage banner.");
    }

    /**
     * Toggle active/paused state of an Admin Featured Voucher.
     * Route: POST /admin/vouchers/{voucher}/toggle
     */
    public function adminToggle(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->issuer_type === Voucher::ISSUER_ADMIN, 403);

        $voucher->update(['is_active' => !$voucher->is_active]);
        $this->voucherService->syncShopVoucherFlags();

        $stateLabel = $voucher->is_active ? 'activated' : 'paused';
        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Featured Voucher {$voucher->code} {$stateLabel}.");
    }

    /**
     * Delete a non-default Admin Featured Voucher.
     * Route: DELETE /admin/vouchers/{voucher}
     */
    public function adminDestroy(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->issuer_type === Voucher::ISSUER_ADMIN, 403);

        if ($voucher->is_system_default) {
            return redirect()
                ->route('admin.vouchers.index')
                ->with('error', "The default First-Order Free Courier voucher ({$voucher->code}) cannot be deleted.");
        }

        $code = $voucher->code;
        $voucher->delete();
        $this->voucherService->syncShopVoucherFlags();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Featured Voucher {$code} removed.");
    }

    /**
     * Vendor Owner creates a branch-specific or all-branches Voucher (manual code required).
     * Route: POST /vendor/vouchers
     */
    public function vendorStore(Request $request)
    {
        $vendorProfile = $this->resolveApprovedVendorProfile($request);
        $ownedBranchIds = $vendorProfile->shops()->pluck('id')->map(fn ($v) => (int) $v)->all();

        $payload = $this->validateVoucherPayload($request, null, false, $ownedBranchIds);

        $voucher = DB::transaction(function () use ($request, $vendorProfile, $payload) {
            $voucher = Voucher::create([
                'code' => $payload['code'],
                'title' => $payload['title'],
                'banner_subtitle' => null,
                'description' => $payload['description'],
                'cta_href' => null,
                'voucher_type' => $payload['voucher_type'],
                'issuer_type' => Voucher::ISSUER_VENDOR,
                'branch_scope' => $payload['branch_scope'],
                'created_by_user_id' => $request->user()?->id,
                'vendor_profile_id' => $vendorProfile->id,
                'discount_type' => $payload['discount_type'],
                'discount_value' => $payload['discount_value'],
                'min_order_amount' => $payload['min_order_amount'],
                'max_discount_amount' => $payload['max_discount_amount'],
                'required_payment_method' => $payload['required_payment_method'],
                'is_first_order_only' => false,
                'is_system_default' => false,
                'is_featured' => false,
                'featured_order' => 100,
                'starts_at' => $payload['starts_at'],
                'expires_at' => $payload['expires_at'],
                'valid_days_of_week' => $payload['valid_days_of_week'],
                'daily_start_time' => $payload['daily_start_time'],
                'daily_end_time' => $payload['daily_end_time'],
                'is_active' => true,
            ]);

            if ($payload['branch_scope'] === Voucher::SCOPE_SPECIFIC_BRANCHES) {
                $voucher->includedShops()->sync($payload['shop_ids']);
            } else {
                $voucher->includedShops()->detach();
            }

            return $voucher;
        });

        $this->voucherService->syncShopVoucherFlags($vendorProfile->id);

        return redirect('/vendor/dashboard?tab=vouchers')
            ->with('success', "Voucher {$voucher->code} ({$voucher->advertisedBadgeText()}) published.");
    }

    /**
     * Vendor Owner updates their own Voucher (including its time period & branch scope).
     * Route: PUT /vendor/vouchers/{voucher}
     */
    public function vendorUpdate(Request $request, Voucher $voucher)
    {
        $vendorProfile = $this->resolveApprovedVendorProfile($request);
        abort_unless(
            $voucher->issuer_type === Voucher::ISSUER_VENDOR
            && (int) $voucher->vendor_profile_id === (int) $vendorProfile->id,
            403
        );

        $ownedBranchIds = $vendorProfile->shops()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $payload = $this->validateVoucherPayload($request, $voucher, false, $ownedBranchIds);

        DB::transaction(function () use ($voucher, $payload) {
            $voucher->update([
                'code' => $payload['code'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'voucher_type' => $payload['voucher_type'],
                'branch_scope' => $payload['branch_scope'],
                'discount_type' => $payload['discount_type'],
                'discount_value' => $payload['discount_value'],
                'min_order_amount' => $payload['min_order_amount'],
                'max_discount_amount' => $payload['max_discount_amount'],
                'required_payment_method' => $payload['required_payment_method'],
                'starts_at' => $payload['starts_at'],
                'expires_at' => $payload['expires_at'],
                'valid_days_of_week' => $payload['valid_days_of_week'],
                'daily_start_time' => $payload['daily_start_time'],
                'daily_end_time' => $payload['daily_end_time'],
                'is_active' => $payload['is_active'] ?? $voucher->is_active,
            ]);

            if ($payload['branch_scope'] === Voucher::SCOPE_SPECIFIC_BRANCHES) {
                $voucher->includedShops()->sync($payload['shop_ids']);
            } else {
                $voucher->includedShops()->detach();
            }
        });

        $this->voucherService->syncShopVoucherFlags($vendorProfile->id);

        return redirect('/vendor/dashboard?tab=vouchers')
            ->with('success', "Voucher {$voucher->code} updated.");
    }

    /**
     * Toggle active/paused state of a Vendor Voucher.
     * Route: POST /vendor/vouchers/{voucher}/toggle
     */
    public function vendorToggle(Request $request, Voucher $voucher)
    {
        $vendorProfile = $this->resolveApprovedVendorProfile($request);
        abort_unless(
            $voucher->issuer_type === Voucher::ISSUER_VENDOR
            && (int) $voucher->vendor_profile_id === (int) $vendorProfile->id,
            403
        );

        $voucher->update(['is_active' => !$voucher->is_active]);
        $this->voucherService->syncShopVoucherFlags($vendorProfile->id);

        $stateLabel = $voucher->is_active ? 'activated' : 'paused';
        return redirect('/vendor/dashboard?tab=vouchers')
            ->with('success', "Voucher {$voucher->code} {$stateLabel}.");
    }

    /**
     * Delete a Vendor Voucher.
     * Route: DELETE /vendor/vouchers/{voucher}
     */
    public function vendorDestroy(Request $request, Voucher $voucher)
    {
        $vendorProfile = $this->resolveApprovedVendorProfile($request);
        abort_unless(
            $voucher->issuer_type === Voucher::ISSUER_VENDOR
            && (int) $voucher->vendor_profile_id === (int) $vendorProfile->id,
            403
        );

        $code = $voucher->code;
        $voucher->delete();
        $this->voucherService->syncShopVoucherFlags($vendorProfile->id);

        return redirect('/vendor/dashboard?tab=vouchers')
            ->with('success', "Voucher {$code} deleted.");
    }

    private function resolveApprovedVendorProfile(Request $request): VendorProfile
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'vendor', 403);

        $profile = VendorProfile::where('user_id', $user->id)->first();
        abort_unless($profile && $profile->verification_status === 'approved', 403);

        return $profile;
    }

    private function validateVoucherPayload(
        Request $request,
        ?Voucher $existing,
        bool $isAdmin,
        array $ownedBranchIds = []
    ): array {
        $rawCode = strtoupper(trim((string) $request->input('code', '')));
        $cleanCode = preg_replace('/[^A-Z0-9_-]/', '', $rawCode) ?? '';
        $request->merge(['code' => $cleanCode]);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'min:3',
                'max:32',
                Rule::unique('vouchers', 'code')->ignore($existing?->id),
            ],
            'title' => 'required|string|max:140',
            'banner_subtitle' => 'nullable|string|max:240',
            'description' => 'nullable|string|max:1000',
            'cta_href' => 'nullable|string|max:240',
            'voucher_type' => 'required|in:LIMITED_TIME,USE_ONCE',
            'discount_type' => 'required|in:PERCENTAGE,FIXED,FREE_COURIER',
            'discount_value' => 'nullable|numeric|min:0|max:100000',
            'min_order_amount' => 'nullable|numeric|min:0|max:500000',
            'max_discount_amount' => 'nullable|numeric|min:0|max:100000',
            'required_payment_method' => 'nullable|in:BKASH,NAGAD,ROCKET,CARD,SSLCOMMERZ,CASH',
            'is_first_order_only' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'featured_order' => 'nullable|integer|min:1|max:9999',
            'branch_scope' => $isAdmin ? 'nullable|string' : 'required|in:ALL_BRANCHES,SPECIFIC_BRANCHES',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer|exists:repair_shops,id',
            'excluded_shop_ids' => 'nullable|array',
            'excluded_shop_ids.*' => 'integer|exists:repair_shops,id',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'valid_days_of_week' => 'nullable|array',
            'valid_days_of_week.*' => 'integer|min:0|max:6',
            'daily_start_time' => 'nullable|date_format:H:i',
            'daily_end_time' => 'nullable|date_format:H:i',
            'is_active' => 'nullable|boolean',
        ]);

        $discountType = $validated['discount_type'];
        $discountVal = (float) ($validated['discount_value'] ?? 0);

        if ($discountType === Voucher::DISCOUNT_PERCENTAGE && ($discountVal <= 0 || $discountVal > 95)) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discount must be between 1% and 95%.',
            ]);
        }
        if ($discountType === Voucher::DISCOUNT_FIXED && $discountVal <= 0) {
            throw ValidationException::withMessages([
                'discount_value' => 'Fixed discount amount must be greater than ৳0.',
            ]);
        }
        if ($discountType === Voucher::DISCOUNT_FREE_COURIER) {
            $discountVal = 0.0;
        }

        $branchScope = $isAdmin
            ? Voucher::SCOPE_UNIVERSAL
            : ($validated['branch_scope'] ?? Voucher::SCOPE_ALL_BRANCHES);

        $selectedShopIds = [];
        if (!$isAdmin && $branchScope === Voucher::SCOPE_SPECIFIC_BRANCHES) {
            $selectedShopIds = array_values(array_intersect(
                array_map('intval', $validated['shop_ids'] ?? []),
                $ownedBranchIds
            ));
            if (empty($selectedShopIds)) {
                throw ValidationException::withMessages([
                    'shop_ids' => 'Select at least one of your branches for a branch-specific voucher.',
                ]);
            }
        }

        $days = array_values(array_unique(array_map('intval', $validated['valid_days_of_week'] ?? [])));
        sort($days);

        return [
            'code' => $cleanCode,
            'title' => trim($validated['title']),
            'banner_subtitle' => isset($validated['banner_subtitle']) && trim($validated['banner_subtitle']) !== ''
                ? trim($validated['banner_subtitle'])
                : null,
            'description' => isset($validated['description']) && trim($validated['description']) !== ''
                ? trim($validated['description'])
                : null,
            'cta_href' => isset($validated['cta_href']) && trim($validated['cta_href']) !== ''
                ? trim($validated['cta_href'])
                : null,
            'voucher_type' => $validated['voucher_type'],
            'branch_scope' => $branchScope,
            'discount_type' => $discountType,
            'discount_value' => round($discountVal, 2),
            'min_order_amount' => round((float) ($validated['min_order_amount'] ?? 0), 2),
            'max_discount_amount' => isset($validated['max_discount_amount']) && (float) $validated['max_discount_amount'] > 0
                ? round((float) $validated['max_discount_amount'], 2)
                : null,
            'required_payment_method' => !empty($validated['required_payment_method'])
                ? strtoupper(trim($validated['required_payment_method']))
                : null,
            'is_first_order_only' => $isAdmin && $validated['voucher_type'] === Voucher::TYPE_USE_ONCE
                ? $request->boolean('is_first_order_only')
                : false,
            'is_featured' => $isAdmin ? $request->boolean('is_featured', true) : false,
            'featured_order' => isset($validated['featured_order']) ? (int) $validated['featured_order'] : null,
            'shop_ids' => $selectedShopIds,
            'excluded_shop_ids' => $isAdmin
                ? array_values(array_unique(array_map('intval', $validated['excluded_shop_ids'] ?? [])))
                : [],
            'starts_at' => !empty($validated['starts_at']) ? \Carbon\Carbon::parse($validated['starts_at']) : null,
            'expires_at' => !empty($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : null,
            'valid_days_of_week' => !empty($days) ? $days : null,
            'daily_start_time' => $validated['daily_start_time'] ?? null,
            'daily_end_time' => $validated['daily_end_time'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : ($existing?->is_active ?? true),
        ];
    }
}
