<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('paciente');
            $table->boolean('can_prescribe')->default(false);
        });
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('document')->unique();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->text('allergies')->nullable();
            $table->text('history')->nullable();
            $table->timestamps();
        });
        Schema::create('care_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('source');
            $table->text('reason');
            $table->string('priority')->default('normal');
            $table->string('status')->default('pendiente');
            $table->timestamps();
        });
        Schema::create('encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('care_requests');
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->text('vital_signs')->nullable();
            $table->text('diagnosis');
            $table->text('notes')->nullable();
            $table->date('follow_up')->nullable();
            $table->timestamps();
        });
        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('ingredient');
            $table->string('presentation');
            $table->string('unit');
            $table->unsignedInteger('min_stock')->default(10);
            $table->timestamps();
        });
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained();
            $table->string('lot');
            $table->date('expires_at');
            $table->unsignedInteger('warehouse_quantity')->default(0);
            $table->unsignedInteger('clinic_quantity')->default(0);
            $table->timestamps();
            $table->unique(['medicine_id', 'lot']);
        });
        Schema::create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('enviado');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->text('instructions')->nullable();
            $table->boolean('allergy_reviewed');
            $table->timestamps();
        });
        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained();
            $table->foreignId('medicine_id')->constrained();
            $table->string('dose');
            $table->string('frequency');
            $table->string('duration');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('dispensed_quantity')->default(0);
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained();
            $table->foreignId('prescription_item_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('type');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->string('action');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'stock_movements', 'prescription_items', 'prescriptions', 'transfers', 'batches', 'medicines', 'encounters', 'care_requests', 'patients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'can_prescribe']));
    }
};
