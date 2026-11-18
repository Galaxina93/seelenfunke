<?php

namespace App\Models\Support;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupportExperienceReview extends Model implements HasSystemBlueprint
{
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public const AREA_CONFIGURATOR = 'configurator';
    public const AREA_FUNKENREICH = 'funkenreich';
    public const AREA_CALCULATOR = 'calculator';

    public const AREAS = [
        self::AREA_CONFIGURATOR,
        self::AREA_FUNKENREICH,
        self::AREA_CALCULATOR,
    ];

    public const AREA_LABELS = [
        self::AREA_CONFIGURATOR => 'Produktkonfigurator',
        self::AREA_FUNKENREICH => '3D-Funkenreich',
        self::AREA_CALCULATOR => 'Angebots-Kalkulator',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    protected $table = 'support_experience_reviews';

    protected $fillable = [
        'customer_id',
        'area',
        'rating',
        'comment',
        'status',
        'device_type',
        'product_id',
        'product_name',
        'resolved_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kunden-Erlebnis-Feedback (5-Sterne Qualitätsradar)',
            description: 'Sterne-Bewertungen und Verbesserungshinweise verifizierter Kunden zu Produktkonfigurator, 3D-Funkenreich und Angebots-Kalkulator.',
            department: SystemDepartment::Support,
            navigationNodes: ['admin.support.reviews', 'customer.reviews'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: true,
            customerForeignKey: 'customer_id',
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public static function areaLabel(string $area): string
    {
        return self::AREA_LABELS[$area] ?? $area;
    }

    public function getAreaLabelAttribute(): string
    {
        return self::areaLabel((string) $this->area);
    }

    public static function isCustomerEligible(?Customer $customer): bool
    {
        if (!$customer) {
            return false;
        }

        if (method_exists($customer, 'trashed') && $customer->trashed()) {
            return false;
        }

        if (method_exists($customer, 'hasVerifiedEmail') && !$customer->hasVerifiedEmail()) {
            return false;
        }

        if (!empty($customer->needs_password_change)) {
            return false;
        }

        return true;
    }

    public static function findForAuthenticatedCustomer(string $area): ?self
    {
        if (!in_array($area, self::AREAS, true)) {
            return null;
        }

        if (!Schema::hasTable('support_experience_reviews')) {
            return null;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (!self::isCustomerEligible($customer)) {
            return null;
        }

        return static::query()
            ->where('customer_id', $customer->id)
            ->where('area', $area)
            ->first();
    }

    public static function detectDeviceType(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        if ($ua !== '' && preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public static function sanitizeComment(?string $comment): ?string
    {
        $cleaned = trim(strip_tags((string) $comment));
        if ($cleaned === '') {
            return null;
        }

        return mb_substr($cleaned, 0, 500);
    }

    public static function upsertForCustomer(
        Customer $customer,
        string $area,
        int $rating,
        ?string $comment = null,
        bool $commentProvided = false,
        bool $keepExistingComment = false,
        string $deviceType = 'desktop',
        ?string $productId = null
    ): self {
        $rating = max(1, min(5, $rating));
        $normalizedDevice = in_array($deviceType, ['mobile', 'desktop'], true) ? $deviceType : 'desktop';

        $resolvedProductId = null;
        $resolvedProductName = null;
        $trimmedProductId = trim((string) $productId);
        if ($trimmedProductId !== '' && Str::isUuid($trimmedProductId)) {
            $productName = Product::where('id', $trimmedProductId)->value('name');
            if (is_string($productName) && $productName !== '') {
                $resolvedProductId = $trimmedProductId;
                $resolvedProductName = mb_substr($productName, 0, 190);
            }
        }

        return DB::transaction(function () use (
            $customer,
            $area,
            $rating,
            $comment,
            $commentProvided,
            $keepExistingComment,
            $normalizedDevice,
            $resolvedProductId,
            $resolvedProductName
        ): self {
            /** @var self|null $existing */
            $existing = static::query()
                ->where('customer_id', $customer->id)
                ->where('area', $area)
                ->lockForUpdate()
                ->first();

            if ($rating >= 4) {
                $finalComment = null;
                $status = self::STATUS_DONE;
                $resolvedAt = now();
            } else {
                $status = self::STATUS_OPEN;
                $resolvedAt = null;

                if ($commentProvided) {
                    $sanitized = self::sanitizeComment($comment);
                    $finalComment = ($sanitized === null && $keepExistingComment)
                        ? $existing?->comment
                        : $sanitized;
                } else {
                    $finalComment = $existing?->comment;
                }
            }

            $finalProductId = $resolvedProductId ?? ($area === self::AREA_CONFIGURATOR ? $existing?->product_id : null);
            $finalProductName = $resolvedProductName ?? ($area === self::AREA_CONFIGURATOR ? $existing?->product_name : null);

            /** @var self $review */
            $review = static::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'area' => $area,
                ],
                [
                    'rating' => $rating,
                    'comment' => $finalComment,
                    'status' => $status,
                    'device_type' => $normalizedDevice,
                    'product_id' => $finalProductId,
                    'product_name' => $finalProductName,
                    'resolved_at' => $resolvedAt,
                ]
            );

            return $review;
        });
    }
}
