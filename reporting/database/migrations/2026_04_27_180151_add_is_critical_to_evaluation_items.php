<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Critical item slugs — items with direct patient-safety implications
     * (emergency management, life-threatening complications, urgent hospitalization).
     * Clinical team should verify this list before go-live.
     *
     * @var array<string>
     */
    private const CRITICAL_SLUGS = [
        // Diabetes — SAFETY
        'diabetes-D17', // Symptomatic hypoglycemia
        'diabetes-D20', // Hyperglycemic emergencies + stabilization
        'diabetes-D21', // Hospitalization for hyperglycemic status

        // Cardiac — decompensation + life-threatening arrhythmia
        'cardiac-C2',   // Clinical decompensation recognition
        'cardiac-C12',  // HF decompensation + hospitalization referral
        'cardiac-C19',  // Atrial fibrillation + anticoagulation

        // Sickle cell — acute life-threatening complications
        'sickle_cell-S17', // Vaso-occlusive crisis + hospitalization
        'sickle_cell-S18', // Acute chest syndrome
        'sickle_cell-S19', // Stroke recognition
        'sickle_cell-S20', // Acute splenic sequestration / severe hemolytic anemia
        'sickle_cell-S21', // Severe anemia requiring urgent transfusion
        'sickle_cell-S23', // Infection/sepsis + empiric antibiotics

        // Respiratory — danger signs requiring urgent referral
        'respiratory-R19',

        // Hypertension — emergency and urgent care
        'hypertension-H14', // Urgency vs emergency differentiation
        'hypertension-H15', // Acute organ damage recognition
        'hypertension-H17', // Hypertensive emergency management

        // CKD — life-threatening complications
        'ckd-H11', // Hyperkalaemia, encephalopathy, urgent dialysis

        // Epilepsy — stepwise protocol (most common treatment failure cause)
        'epilepsy-E10',
    ];

    public function up(): void
    {
        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->boolean('is_critical')->default(false)->after('is_advanced');
        });

        DB::table('evaluation_items')
            ->whereIn('slug', self::CRITICAL_SLUGS)
            ->update(['is_critical' => true]);
    }

    public function down(): void
    {
        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->dropColumn('is_critical');
        });
    }
};
