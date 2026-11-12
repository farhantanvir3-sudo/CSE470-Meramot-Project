<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R1: Delivery Agent Onboarding & Credential Verification Model
 * ============================================================================
 * Assigned Developer: Md. Nafiul Islam [Student ID: 22299035]
 * Commits by Nafiul:
 *   - c6c0781: Aligned `registration_status`, `status`, `nid_number`, `license_number`
 *   - fdf5e6f: Added `rejection_reason` attribute
 * Merged with Original Meramot RiderProfile Document & Telemetry Fields:
 *   - Farhan Tanvir [Student ID: 22299179]
 * ============================================================================
 */
class DeliveryAgent extends Model
{
    use HasFactory;

    /* ------------------------------------------------------------------------
     * AUTHOR BLOCK: Md. Nafiul Islam [22299035] (Commits c6c0781 & fdf5e6f)
     * Merged with original RiderProfile document URL columns
     * ------------------------------------------------------------------------ */
    protected $fillable = [
        'user_id',
        'applicant_name',
        'applicant_phone',
        'personal_email',
        'company_email',
        'company_password_vault',
        'nid_number',
        'vehicle_type',
        'license_number',
        'nid_document_url',
        'license_document_url',
        'education_document_url',
        'cv_document_url',
        'profile_picture_url',
        'registration_status', // 'PENDING', 'APPROVED', 'REJECTED'
        'status',              // 'OFFLINE', 'AVAILABLE', 'BUSY', 'SUSPENDED'
        'is_active',
        'rejection_reason',    // Added by Md. Nafiul Islam [22299035] in fdf5e6f
        'status_otp_code',
        'status_otp_expires_at',
        'approved_at',
        'current_latitude',
        'current_longitude',
        'total_earnings',
    ];

    protected $hidden = [
        'company_password_vault',
        'status_otp_code',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'total_earnings' => 'decimal:2',
        'status_otp_expires_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryJobs()
    {
        return $this->hasMany(DeliveryJob::class, 'delivery_agent_id');
    }
}
