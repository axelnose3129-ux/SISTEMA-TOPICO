<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table): void {
            $table->foreignId('teacher_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('forwarded_at')->nullable();
        });

        $teachers = DB::table('users')->where('role', 'docente')->pluck('id');
        DB::table('care_requests')
            ->where('status', 'pendiente')
            ->where('source', 'solicitud')
            ->whereIn('created_by', DB::table('users')->select('id')->where('role', 'paciente'))
            ->update([
                'status' => 'pendiente_docente',
                'teacher_id' => $teachers->count() === 1 ? $teachers->first() : null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('care_requests')->where('status', 'pendiente_docente')->update(['status' => 'pendiente']);
        Schema::table('care_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn('forwarded_at');
        });
    }
};
