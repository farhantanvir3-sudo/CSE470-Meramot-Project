<?php

namespace App\Http\Controllers;

use App\Models\VendorProfile;
use Illuminate\Http\Request;

/**
 * Feature R11: Vendor Onboarding & Business Profile Verification Engine
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 1
 * MVC Layer: Controller
 */
class VendorOnboardingController extends Controller
{
    /**
     * Display vendor onboarding status or application view.
     */
    public function show(Request $request)
    {
        $profile = VendorProfile::where('user_id', $request->user()->id ?? 1)->first();
        return response()->json([
            'status' => 'success',
            'data' => $profile,
        ]);
    }

    /**
     * Store new vendor profile application.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'business_license_no' => 'required|string|max:100',
            'nid_number' => 'required|string|max:30',
            'shop_address' => 'required|string',
            'categories' => 'required|array',
        ]);

        $profile = VendorProfile::updateOrCreate(
            ['user_id' => $request->user()->id ?? 1],
            array_merge($validated, [
                'verification_status' => 'pending',
                'commission_rate' => 10.00,
            ])
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor application submitted successfully for administrative audit.',
            'data' => $profile,
        ], 201);
    }

    /**
     * Admin action to verify/approve vendor profile.
     */
    public function verify(Request $request, $id)
    {
        $profile = VendorProfile::findOrFail($id);
        $status = $request->input('action') === 'approve' ? 'approved' : 'rejected';
        $profile->update([
            'verification_status' => $status,
            'rejection_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Vendor profile has been {$status}.",
            'data' => $profile,
        ]);
    }
}
