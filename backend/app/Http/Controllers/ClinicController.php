<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClinicController extends Controller
{
    private function allow(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'No tiene permiso para esta operación.');
    }

    private function scopeTeacherRequests(Builder $query, int $teacherId, string $prefix = ''): void
    {
        $query->where(function (Builder $visible) use ($teacherId, $prefix): void {
            $visible->where($prefix.'teacher_id', $teacherId)
                ->orWhere(function (Builder $unassigned) use ($prefix): void {
                    $unassigned->whereNull($prefix.'teacher_id')->where($prefix.'status', 'pendiente_docente');
                })
                ->orWhere(function (Builder $legacy) use ($teacherId, $prefix): void {
                    $legacy->where($prefix.'created_by', $teacherId)->where($prefix.'source', 'derivacion');
                });
        });
    }

    private function audit(Request $request, string $action): void
    {
        DB::table('audit_logs')->insert(['created_by' => $request->user()->id, 'action' => $action, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'nullable|required_without:email|string|max:255',
            'email' => 'nullable|required_without:identifier|email|max:255',
            'password' => 'required|string',
        ]);
        $identifier = trim($data['identifier'] ?? $data['email']);
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'dni';
        abort_unless($field === 'email' || preg_match('/^[0-9]{8}$/', $identifier), 422, 'Ingresa un DNI de 8 digitos o un correo valido.');
        abort_unless(Auth::attempt([$field => $identifier, 'password' => $data['password']]), 422, 'DNI, correo o contraseña incorrectos.');
        $request->session()->regenerate();
        $this->audit($request, 'Inicio de sesión');

        return response()->json(['user' => $request->user(), 'csrf' => csrf_token()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['csrf' => csrf_token()]);
    }

    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        $clinical = $user->role === 'encargado';
        $coordinator = $user->role === 'coordinador';
        $patientId = DB::table('patients')->where('user_id', $user->id)->value('id');
        $patients = DB::table('patients')->orderBy('name');
        if ($user->role === 'paciente') {
            $patients->where('id', $patientId ?? 0);
        }
        if (! $clinical && $user->role !== 'paciente') {
            $patients->select(['id', 'name', 'document']);
        }
        $requests = DB::table('care_requests as r')->join('patients as p', 'p.id', '=', 'r.patient_id')
            ->leftJoin('users as teacher', 'teacher.id', '=', 'r.teacher_id')
            ->select('r.*', 'p.name as patient_name', 'teacher.name as teacher_name')->orderByDesc('r.created_at');
        if ($user->role === 'paciente') {
            $requests->where('p.id', $patientId ?? 0);
        } elseif ($user->role === 'docente') {
            $this->scopeTeacherRequests($requests, $user->id, 'r.');
        } elseif ($coordinator) {
            $requests->whereRaw('1 = 0');
        } elseif ($clinical) {
            $requests->where('r.status', '!=', 'pendiente_docente');
        }
        $encounters = DB::table('encounters as e')->join('patients as p', 'p.id', '=', 'e.patient_id')->select('e.*', 'p.name as patient_name')->orderByDesc('e.id');
        $prescriptions = DB::table('prescriptions as rx')->join('encounters as e', 'e.id', '=', 'rx.encounter_id')->join('patients as p', 'p.id', '=', 'e.patient_id')->join('users as u', 'u.id', '=', 'rx.created_by')->select('rx.*', 'p.name as patient_name', 'u.name as prescriber')->orderByDesc('rx.id');
        if (! $clinical) {
            $visibleId = $user->role === 'paciente' ? ($patientId ?? 0) : 0;
            $encounters->where('p.id', $visibleId);
            $prescriptions->where('p.id', $visibleId);
        }
        $rx = $prescriptions->get();
        $items = DB::table('prescription_items as i')->join('medicines as m', 'm.id', '=', 'i.medicine_id')->whereIn('i.prescription_id', $rx->pluck('id'))->select('i.*', 'm.name as medicine_name')->get()->groupBy('prescription_id');
        foreach ($rx as $prescription) {
            $prescription->items = $items->get($prescription->id, collect());
        }
        $stockAccess = $clinical || $coordinator;
        $batches = DB::table('batches as b')->join('medicines as m', 'm.id', '=', 'b.medicine_id')->select('b.*', 'm.name as medicine_name')->orderBy('b.expires_at')->get();
        $medicines = DB::table('medicines')->orderBy('name')->get();
        foreach ($medicines as $medicine) {
            $medicine->stock = $batches->where('medicine_id', $medicine->id)->filter(fn ($batch) => $batch->expires_at >= today()->toDateString())->sum('clinic_quantity');
        }
        $statsQuery = DB::table('care_requests');
        if ($user->role === 'paciente') {
            $statsQuery->where('patient_id', $patientId ?? 0);
        } elseif ($user->role === 'docente') {
            $this->scopeTeacherRequests($statsQuery, $user->id);
        } else {
            $statsQuery->where('status', '!=', 'pendiente_docente');
        }
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->whereIn('status', ['pendiente_docente', 'pendiente'])->count(),
            'pending_teacher' => (clone $statsQuery)->where('status', 'pendiente_docente')->count(),
            'completed' => (clone $statsQuery)->where('status', 'finalizada')->count(),
            'today' => (clone $statsQuery)->whereDate('created_at', today())->count(),
        ];

        return response()->json([
            'user' => $user, 'csrf' => csrf_token(), 'server_today' => today()->toDateString(), 'patients' => $patients->get(), 'requests' => $requests->get(), 'encounters' => $encounters->get(), 'prescriptions' => $rx, 'stats' => $stats,
            'users' => $coordinator ? User::orderBy('name')->get() : [],
            'teachers' => $user->role === 'paciente' ? User::where('role', 'docente')->orderBy('name')->get(['id', 'name']) : [],
            'medicines' => $stockAccess ? $medicines : [], 'batches' => $stockAccess ? $batches : [],
            'transfers' => $stockAccess ? DB::table('transfers as t')->join('batches as b', 'b.id', '=', 't.batch_id')->join('medicines as m', 'm.id', '=', 'b.medicine_id')->select('t.*', 'b.lot', 'm.name as medicine_name')->orderByDesc('t.id')->get() : [],
            'movements' => $stockAccess ? DB::table('stock_movements as s')->join('batches as b', 'b.id', '=', 's.batch_id')->join('medicines as m', 'm.id', '=', 'b.medicine_id')->join('users as u', 'u.id', '=', 's.created_by')->select('s.*', 'b.lot', 'm.name as medicine_name', 'u.name as actor')->orderByDesc('s.id')->limit(200)->get() : [],
            'audits' => $coordinator ? DB::table('audit_logs as a')->join('users as u', 'u.id', '=', 'a.created_by')->select('a.*', 'u.name as actor')->orderByDesc('a.id')->limit(100)->get() : [],
        ]);
    }

    public function savePatient(Request $request, ?int $id = null): JsonResponse
    {
        $this->allow($request, ['encargado', 'paciente']);
        $own = $request->user()->role === 'paciente';
        if ($own) {
            $existingId = DB::table('patients')->where('user_id', $request->user()->id)->value('id');
            abort_if($id !== null && $id !== $existingId, 403);
            $id = $existingId;
        }
        if ($id) {
            abort_unless(DB::table('patients')->where('id', $id)->exists(), 404);
        }
        $data = $request->validate([
            'name' => 'required|string|max:160', 'document' => ['required', 'string', 'max:30', Rule::unique('patients')->ignore($id)],
            'birth_date' => 'nullable|date|before_or_equal:today', 'phone' => 'nullable|string|max:40', 'emergency_contact' => 'nullable|string|max:255',
            'allergies' => 'nullable|string|max:3000', 'history' => 'nullable|string|max:6000',
        ]);
        $data['updated_at'] = now();
        if ($id) {
            DB::table('patients')->where('id', $id)->update($data);
        } else {
            $data['user_id'] = $own ? $request->user()->id : null;
            $id = DB::table('patients')->insertGetId($data + ['created_at' => now()]);
        }
        $this->audit($request, "Ficha de paciente #{$id} guardada");

        return response()->json(['id' => $id]);
    }

    public function saveUser(Request $request, ?int $id = null): JsonResponse
    {
        $this->allow($request, ['coordinador']);
        $data = $request->validate([
            'name' => 'required|string|max:160', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'dni' => ['nullable', 'string', 'regex:/^[0-9]{8}$/', Rule::unique('users', 'dni')->ignore($id)],
            'role' => 'required|in:coordinador,encargado,paciente,docente', 'can_prescribe' => 'required|boolean',
            'password' => ($id ? 'nullable' : 'required').'|string|min:10|max:100', 'patient_id' => 'nullable|integer|exists:patients,id',
        ]);
        abort_if($id === $request->user()->id && $data['role'] !== 'coordinador', 422, 'No puede quitarse su propio rol de coordinador.');
        abort_if($data['can_prescribe'] && $data['role'] !== 'encargado', 422, 'Solo un encargado autorizado puede emitir recetas.');
        DB::transaction(function () use ($request, $data, $id): void {
            $user = $id ? User::findOrFail($id) : new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            if (array_key_exists('dni', $data)) {
                $user->dni = $data['dni'];
            }
            $user->role = $data['role'];
            $user->can_prescribe = $data['can_prescribe'];
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();
            $linked = DB::table('patients')->where('user_id', $user->id)->lockForUpdate()->first();
            $patientId = $data['patient_id'] ?? null;
            if ($data['role'] === 'paciente' && $patientId) {
                $patient = DB::table('patients')->where('id', $patientId)->lockForUpdate()->first();
                abort_if($patient->user_id && $patient->user_id !== $user->id, 422, 'El paciente ya tiene otra cuenta vinculada.');
                abort_if($linked && $linked->id !== $patientId, 422, 'La cuenta ya tiene otra ficha vinculada.');
                DB::table('patients')->where('id', $patientId)->update(['user_id' => $user->id]);
            }
            abort_if($linked && $data['role'] !== 'paciente', 422, 'Una cuenta vinculada a un paciente debe conservar ese rol.');
            $this->audit($request, "Usuario #{$user->id} guardado");
        });

        return response()->json(['message' => 'Usuario guardado']);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:10|max:100|confirmed']);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->session()->regenerate();
        $this->audit($request, 'Contraseña actualizada');

        return response()->json(['csrf' => csrf_token()]);
    }

    public function createPatientRequest(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'paciente') {
            return response()->json([
                'code' => 'session_changed',
                'message' => 'La cuenta activa cambio en otra pestana. Ingresa como paciente para enviar su malestar al docente.',
            ], 409);
        }

        return $this->createRequest($request);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $this->allow($request, ['encargado', 'paciente']);
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'reason' => 'required|string|max:3000',
            'priority' => 'sometimes|required|in:normal,prioritaria,urgente',
            'teacher_id' => [$request->user()->role === 'paciente' ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'docente')],
        ]);
        $data['reason'] = trim($data['reason']);
        abort_if($data['reason'] === '', 422, 'Escribe el malestar que deseas comunicar al docente.');
        $data['priority'] ??= 'normal';
        $data['teacher_id'] = $request->user()->role === 'paciente' ? (int) $data['teacher_id'] : null;

        $result = DB::transaction(function () use ($request, $data): array {
            $patient = DB::table('patients')->where('id', $data['patient_id'])->lockForUpdate()->first();
            abort_unless($patient, 404);

            if ($request->user()->role === 'paciente') {
                abort_unless($patient->user_id === $request->user()->id, 403);
                $data['priority'] = 'normal';
            }

            $duplicate = DB::table('care_requests')
                ->where('patient_id', $patient->id)
                ->where('created_by', $request->user()->id)
                ->where('teacher_id', $data['teacher_id'])
                ->where('reason', $data['reason'])
                ->where('status', $request->user()->role === 'paciente' ? 'pendiente_docente' : 'pendiente')
                ->where('created_at', '>=', now()->subSeconds(30))
                ->first();
            if ($duplicate) {
                return ['id' => $duplicate->id, 'already_sent' => true, 'teacher_id' => $duplicate->teacher_id, 'status' => $duplicate->status];
            }

            $id = DB::table('care_requests')->insertGetId($data + [
                'source' => 'solicitud',
                'status' => $request->user()->role === 'paciente' ? 'pendiente_docente' : 'pendiente',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit($request, "Solicitud #{$id} registrada en estado ".($request->user()->role === 'paciente' ? 'pendiente del docente' : 'pendiente en topico'));

            return ['id' => $id, 'already_sent' => false, 'teacher_id' => $data['teacher_id'], 'status' => $request->user()->role === 'paciente' ? 'pendiente_docente' : 'pendiente'];
        });

        $result['teacher_name'] = $result['teacher_id'] ? DB::table('users')->where('id', $result['teacher_id'])->value('name') : null;

        return response()->json($result);
    }

    public function referRequest(Request $request, int $id): JsonResponse
    {
        $this->allow($request, ['docente']);
        DB::transaction(function () use ($request, $id): void {
            $visit = DB::table('care_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($visit, 404);
            abort_unless($visit->teacher_id === $request->user()->id || ($visit->teacher_id === null && $visit->status === 'pendiente_docente'), 403, 'Esta solicitud pertenece a otro docente.');
            abort_unless($visit->status === 'pendiente_docente', 422, 'La solicitud ya fue derivada al topico.');
            DB::table('care_requests')->where('id', $id)->update([
                'teacher_id' => $request->user()->id,
                'status' => 'pendiente',
                'source' => 'derivacion',
                'forwarded_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit($request, "Solicitud #{$id} derivada al topico por el docente");
        });

        return response()->json(['id' => $id, 'status' => 'pendiente']);
    }

    public function encounter(Request $request): JsonResponse
    {
        $this->allow($request, ['encargado']);
        $data = $request->validate([
            'request_id' => 'required|integer|exists:care_requests,id',
            'diagnosis' => 'required|string|max:5000',
            'vital_signs' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:6000',
            'follow_up' => 'nullable|date|after_or_equal:today',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe contener texto.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'request_id.exists' => 'La solicitud seleccionada ya no esta disponible.',
            'follow_up.date' => 'Introduce una fecha de seguimiento valida o deja el campo vacio.',
            'follow_up.after_or_equal' => 'La fecha de seguimiento debe ser hoy o una fecha posterior. Si no necesitas seguimiento, deja el campo vacio.',
        ], [
            'request_id' => 'solicitud',
            'diagnosis' => 'evaluacion / diagnostico',
            'vital_signs' => 'signos vitales',
            'notes' => 'procedimientos e indicaciones',
            'follow_up' => 'fecha de seguimiento',
        ]);
        $id = DB::transaction(function () use ($request, $data): int {
            $visit = DB::table('care_requests')->where('id', $data['request_id'])->lockForUpdate()->first();
            abort_unless($visit->status === 'pendiente', 422, 'La solicitud ya tiene una atención registrada.');
            $id = DB::table('encounters')->insertGetId($data + ['patient_id' => $visit->patient_id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('care_requests')->where('id', $visit->id)->update(['status' => 'en_atencion', 'updated_at' => now()]);
            $this->audit($request, "Atención #{$id} registrada");

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    public function closeRequest(Request $request, int $id): JsonResponse
    {
        $this->allow($request, ['encargado']);
        $updated = DB::table('care_requests')->where('id', $id)->where('status', 'en_atencion')->update(['status' => 'finalizada', 'updated_at' => now()]);
        abort_unless($updated, 422, 'Solo puede cerrar una solicitud que esté en atención.');
        $this->audit($request, "Solicitud #{$id} finalizada");

        return response()->json(['message' => 'Atención finalizada']);
    }

    public function medicine(Request $request, ?int $id = null): JsonResponse
    {
        $this->allow($request, ['coordinador']);
        $data = $request->validate(['name' => 'required|string|max:160', 'ingredient' => 'required|string|max:160', 'presentation' => 'required|string|max:160', 'unit' => 'required|string|max:60', 'min_stock' => 'required|integer|min:0|max:1000000']);
        $data['updated_at'] = now();
        if ($id) {
            abort_unless(DB::table('medicines')->where('id', $id)->exists(), 404);
            DB::table('medicines')->where('id', $id)->update($data);
        } else {
            $id = DB::table('medicines')->insertGetId($data + ['created_at' => now()]);
        }
        $this->audit($request, "Medicamento #{$id} guardado");

        return response()->json(['id' => $id]);
    }

    public function batch(Request $request): JsonResponse
    {
        $this->allow($request, ['coordinador']);
        $data = $request->validate(['medicine_id' => 'required|integer|exists:medicines,id', 'lot' => ['required', 'string', 'max:100', Rule::unique('batches')->where('medicine_id', $request->input('medicine_id'))], 'expires_at' => 'required|date|after_or_equal:today', 'quantity' => 'required|integer|min:1|max:1000000']);
        DB::transaction(function () use ($request, $data): void {
            $id = DB::table('batches')->insertGetId(['medicine_id' => $data['medicine_id'], 'lot' => $data['lot'], 'expires_at' => $data['expires_at'], 'warehouse_quantity' => $data['quantity'], 'created_at' => now(), 'updated_at' => now()]);
            $this->movement($request, $id, 'ingreso', $data['quantity']);
            $this->audit($request, "Ingreso de lote #{$id}");
        });

        return response()->json(['message' => 'Lote ingresado en almacén']);
    }

    private function movement(Request $request, int $batchId, string $type, int $quantity, ?int $itemId = null): void
    {
        DB::table('stock_movements')->insert(['batch_id' => $batchId, 'prescription_item_id' => $itemId, 'type' => $type, 'quantity' => $quantity, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $this->allow($request, ['coordinador']);
        $data = $request->validate(['batch_id' => 'required|integer|exists:batches,id', 'quantity' => 'required|integer|min:1|max:1000000']);
        DB::transaction(function () use ($request, $data): void {
            $batch = DB::table('batches')->where('id', $data['batch_id'])->lockForUpdate()->first();
            abort_if($batch->expires_at < today()->toDateString(), 422, 'El lote está vencido.');
            abort_if($batch->warehouse_quantity < $data['quantity'], 422, 'Stock insuficiente en almacén.');
            DB::table('batches')->where('id', $batch->id)->decrement('warehouse_quantity', $data['quantity']);
            $id = DB::table('transfers')->insertGetId($data + ['created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->movement($request, $batch->id, 'envio', $data['quantity']);
            $this->audit($request, "Envío #{$id} al tópico");
        });

        return response()->json(['message' => 'Envío pendiente de recepción']);
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $this->allow($request, ['encargado']);
        DB::transaction(function () use ($request, $id): void {
            $transfer = DB::table('transfers')->where('id', $id)->lockForUpdate()->first();
            abort_unless($transfer, 404);
            abort_unless($transfer->status === 'enviado', 422, 'Este envío ya fue recibido.');
            DB::table('batches')->where('id', $transfer->batch_id)->lockForUpdate()->first();
            DB::table('batches')->where('id', $transfer->batch_id)->increment('clinic_quantity', $transfer->quantity);
            DB::table('transfers')->where('id', $id)->update(['status' => 'recibido', 'received_by' => $request->user()->id, 'updated_at' => now()]);
            $this->movement($request, $transfer->batch_id, 'recepcion', $transfer->quantity);
            $this->audit($request, "Envío #{$id} recibido");
        });

        return response()->json(['message' => 'Medicamentos recibidos']);
    }

    public function prescription(Request $request): JsonResponse
    {
        $this->allow($request, ['encargado']);
        abort_unless($request->user()->can_prescribe, 403, 'Su cuenta no tiene autorización para emitir recetas.');
        $data = $request->validate([
            'encounter_id' => 'required|integer|exists:encounters,id', 'instructions' => 'nullable|string|max:5000', 'allergy_reviewed' => 'required|accepted',
            'items' => 'required|array|min:1|max:20', 'items.*' => 'required|array:medicine_id,dose,frequency,duration,quantity', 'items.*.medicine_id' => 'required|integer|distinct|exists:medicines,id',
            'items.*.dose' => 'required|string|max:160', 'items.*.frequency' => 'required|string|max:160', 'items.*.duration' => 'required|string|max:160', 'items.*.quantity' => 'required|integer|min:1|max:10000',
        ]);
        $id = DB::transaction(function () use ($request, $data): int {
            $encounter = DB::table('encounters')->where('id', $data['encounter_id'])->first();
            $visit = DB::table('care_requests')->where('id', $encounter->request_id)->lockForUpdate()->first();
            abort_unless($visit->status === 'en_atencion', 422, 'La atención ya está finalizada.');
            $id = DB::table('prescriptions')->insertGetId(['encounter_id' => $data['encounter_id'], 'instructions' => $data['instructions'] ?? null, 'allergy_reviewed' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $item) {
                DB::table('prescription_items')->insert($item + ['prescription_id' => $id]);
            }
            $this->audit($request, "Receta #{$id} emitida con revisión de alergias");

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    public function dispense(Request $request): JsonResponse
    {
        $this->allow($request, ['encargado']);
        $data = $request->validate(['item_id' => 'required|integer|exists:prescription_items,id', 'batch_id' => 'required|integer|exists:batches,id', 'quantity' => 'required|integer|min:1|max:10000', 'allergy_reviewed' => 'required|accepted']);
        DB::transaction(function () use ($request, $data): void {
            $item = DB::table('prescription_items')->where('id', $data['item_id'])->lockForUpdate()->first();
            $batch = DB::table('batches')->where('id', $data['batch_id'])->lockForUpdate()->first();
            abort_unless($item->medicine_id === $batch->medicine_id, 422, 'El lote no corresponde al medicamento recetado.');
            abort_if($batch->expires_at < today()->toDateString(), 422, 'No puede entregar un lote vencido.');
            abort_if($batch->clinic_quantity < $data['quantity'], 422, 'Stock insuficiente en tópico.');
            abort_if($item->dispensed_quantity + $data['quantity'] > $item->quantity, 422, 'La cantidad excede lo pendiente de la receta.');
            DB::table('batches')->where('id', $batch->id)->decrement('clinic_quantity', $data['quantity']);
            DB::table('prescription_items')->where('id', $item->id)->increment('dispensed_quantity', $data['quantity']);
            $this->movement($request, $batch->id, 'entrega', $data['quantity'], $item->id);
            $this->audit($request, "Entrega de {$data['quantity']} unidades para receta #{$item->prescription_id}");
        });

        return response()->json(['message' => 'Entrega registrada y stock actualizado']);
    }
}
