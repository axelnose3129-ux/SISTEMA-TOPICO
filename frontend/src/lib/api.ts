export interface User {
  id: number;
  name: string;
  email: string;
  dni: string | null;
  role: string;
  can_prescribe: boolean;
}
export interface Patient {
  id: number;
  name: string;
  document: string;
  birth_date: string;
  phone: string;
  emergency_contact: string;
  allergies: string;
  history: string;
  user_id: number | null;
}
export interface Visit {
  teacher_id: number | null;
  teacher_name: string | null;
  forwarded_at: string | null;
  id: number;
  patient_id: number;
  patient_name: string;
  reason: string;
  priority: string;
  status: string;
  source: string;
  created_at: string;
}
export interface Encounter {
  id: number;
  request_id: number;
  patient_id: number;
  patient_name: string;
  diagnosis: string;
  vital_signs: string;
  notes: string;
  follow_up: string;
  created_at: string;
}
export interface Medicine {
  id: number;
  name: string;
  ingredient: string;
  presentation: string;
  unit: string;
  min_stock: number;
  stock: number;
}
export interface Batch {
  id: number;
  medicine_id: number;
  medicine_name: string;
  lot: string;
  expires_at: string;
  warehouse_quantity: number;
  clinic_quantity: number;
}
export interface Prescription {
  id: number;
  encounter_id: number;
  patient_name: string;
  prescriber: string;
  instructions: string;
  created_at: string;
  items: PrescriptionItem[];
}
export interface PrescriptionItem {
  id: number;
  medicine_id: number;
  medicine_name: string;
  dose: string;
  frequency: string;
  duration: string;
  quantity: number;
  dispensed_quantity: number;
}
export interface Movement {
  id: number;
  medicine_name: string;
  lot: string;
  type: string;
  quantity: number;
  actor: string;
  created_at: string;
}
export interface Audit {
  id: number;
  actor: string;
  action: string;
  created_at: string;
}
export interface Transfer {
  id: number;
  medicine_name: string;
  lot: string;
  quantity: number;
  status: string;
  created_at: string;
}
export interface State {
  server_today: string;
  user: User;
  users: User[];
  teachers: { id: number; name: string }[];
  patients: Patient[];
  requests: Visit[];
  encounters: Encounter[];
  medicines: Medicine[];
  batches: Batch[];
  prescriptions: Prescription[];
  movements: Movement[];
  audits: Audit[];
  transfers: Transfer[];
  stats: {
    total: number;
    pending: number;
    pending_teacher: number;
    completed: number;
    today: number;
  };
}
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number
  ) {
    super(message);
    this.name = "ApiError";
  }
}
let csrf = "";

async function readResponse(response: Response) {
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 401 || response.status === 419) csrf = "";
    if (response.status === 419) {
      window.dispatchEvent(new Event("topico:session-change"));
      throw new ApiError(
        "La sesión cambió. Revisa la cuenta activa y vuelve a enviar la solicitud.",
        419
      );
    }
    if (response.status === 409 && data.code === "session_changed")
      window.dispatchEvent(new Event("topico:session-change"));
    if (response.status >= 500) {
      throw new Error(
        "No se puede conectar con el servidor o la base de datos. Abre «Iniciar Topico.cmd» y vuelve a intentarlo."
      );
    }
    const errors = data.errors
      ? Object.values(data.errors).flat().join(" ")
      : "";
    throw new ApiError(
      errors || data.message || "No se pudo completar la operación.",
      response.status
    );
  }
  return data;
}

export async function api<T>(
  path: string,
  method = "GET",
  body?: unknown
): Promise<T> {
  if (method !== "GET" && !csrf) {
    const response = await fetch("/api/csrf", {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    });
    const data = await readResponse(response);
    csrf = data.token;
  }
  const response = await fetch(`/api/${path}`, {
    method,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrf
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) })
  });
  const data = await readResponse(response);
  if (data.csrf) csrf = data.csrf;
  return data as T;
}
