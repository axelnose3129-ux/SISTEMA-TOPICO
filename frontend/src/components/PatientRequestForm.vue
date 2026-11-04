<template>
  <q-form class="patient-request-form" @submit="submit">
    <q-card-section class="patient-request-body">
      <div v-if="!compact" class="patient-request-heading">
        <span class="patient-request-symbol"
          ><q-icon name="add_circle_outline"
        /></span>
        <div
          ><h2>Solicitar atención</h2
          ><p
            >Escribe lo que sientes y envía tu solicitud al docente. Él podrá
            derivarte al tópico.</p
          ></div
        >
      </div>
      <div v-if="!patient" class="error-box"
        ><p>Primero completa tus datos para poder solicitar atención.</p
        ><q-btn
          flat
          no-caps
          color="primary"
          label="Completar mis datos"
          to="/pacientes"
          @click="$emit('profile')"
      /></div>
      <div v-if="error" class="error-box" role="alert">{{ error }}</div>
      <div class="patient-request-fields">
        <q-input
          :model-value="patient?.name || ''"
          outlined
          readonly
          label="Nombre del paciente"
          hint="Se completa con los datos de tu cuenta."
          ><template #prepend><q-icon name="person_outline" /></template
        ></q-input>
        <q-input
          :model-value="patient?.document || ''"
          outlined
          readonly
          label="DNI / Documento"
          hint="No necesitas volver a escribirlo."
          ><template #prepend><q-icon name="badge" /></template
        ></q-input>
        <q-select
          v-model="teacherId"
          class="patient-request-wide"
          outlined
          label="Docente que recibirá la solicitud *"
          :options="teacherOptions"
          emit-value
          map-options
          :rules="[required]"
          lazy-rules
          hide-bottom-space
          hint="Selecciona el docente que te derivará al tópico."
          ><template #prepend><q-icon name="school" /></template
        ></q-select>
        <q-input
          v-model="reason"
          class="patient-request-wide"
          outlined
          type="textarea"
          label="¿Qué sientes o qué malestar tienes? *"
          placeholder="Describe con tus palabras lo que sientes."
          :rules="[
            required,
            (value: string) =>
              value.trim().length > 0 || 'Escribe el motivo de tu solicitud.'
          ]"
          maxlength="3000"
          counter
          autogrow
          lazy-rules
          ><template #prepend><q-icon name="notes" /></template
        ></q-input>
      </div>
      <div class="patient-request-reasons"
        ><small>Puedes empezar con un motivo frecuente:</small
        ><div
          ><q-btn
            v-for="option in reasons"
            :key="option"
            outline
            no-caps
            rounded
            size="sm"
            color="primary"
            :label="option"
            @click="reason = option" /></div
      ></div>
      <div v-if="!teachers.length" class="error-box"
        >No hay docentes disponibles. Coordinación debe registrar uno para
        recibir las solicitudes.</div
      >
      <div v-if="activeRequest" class="patient-request-status"
        ><div
          ><strong>Solicitud anterior en seguimiento</strong
          ><StatusBadge :value="activeRequest.status" /></div
        ><p
          >Puedes escribir y enviar un nuevo malestar sin esperar a que termine
          la solicitud anterior.</p
        ><q-btn
          flat
          no-caps
          color="primary"
          icon="visibility"
          label="Ver solicitud anterior"
          @click="$emit('view', activeRequest)"
      /></div>
      <p class="patient-request-help"
        ><q-icon name="info_outline" /> El envío llega primero al docente, no
        directamente al tópico.</p
      >
    </q-card-section>
    <q-card-actions class="patient-request-footer" align="right">
      <span>Los campos con * son obligatorios.</span>
      <q-btn
        type="submit"
        unelevated
        no-caps
        color="primary"
        icon="send"
        label="Enviar solicitud al docente"
        :loading="submitting"
        :disable="disabled || !patient || !teacherId"
      />
    </q-card-actions>
  </q-form>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { api, type Patient, type Visit } from "@/lib/api";
import StatusBadge from "./StatusBadge.vue";
const props = defineProps<{
  patient: Patient | null;
  teachers: { id: number; name: string }[];
  activeRequest: Visit | undefined;
  disabled?: boolean;
  compact?: boolean;
}>();
const emit = defineEmits<{
  submitted: [
    result: { alreadySent: boolean; requestId: number; teacherName: string }
  ];
  view: [visit: Visit];
  profile: [];
}>();
const teacherId = ref<number | null>(null);
const reason = ref("");
const error = ref("");
const submitting = ref(false);
const teacherOptions = computed(() =>
  props.teachers.map(teacher => ({ label: teacher.name, value: teacher.id }))
);
const required = (value: unknown) =>
  (value !== null && value !== undefined && value !== "") ||
  "Completa este campo.";
const reasons = ["Malestar general", "Dolor de cabeza", "Golpe o caída"];
watch(
  () => props.teachers,
  teachers => {
    if (!teachers.some(teacher => teacher.id === teacherId.value))
      teacherId.value =
        teachers.length === 1 ? (teachers[0]?.id ?? null) : null;
  },
  { immediate: true }
);
async function submit() {
  if (submitting.value || props.disabled || !props.patient || !teacherId.value)
    return;
  submitting.value = true;
  error.value = "";
  try {
    const result = await api<{
      id: number;
      already_sent: boolean;
      teacher_name: string | null;
      status: string;
    }>("patient/requests", "POST", {
      patient_id: props.patient.id,
      teacher_id: teacherId.value,
      reason: reason.value.trim(),
      priority: "normal"
    });
    reason.value = "";
    emit("submitted", {
      alreadySent: Boolean(result.already_sent),
      requestId: result.id,
      teacherName:
        result.teacher_name ||
        teacherOptions.value.find(teacher => teacher.value === teacherId.value)
          ?.label ||
        "tu docente"
    });
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : "No se pudo enviar la solicitud.";
  } finally {
    submitting.value = false;
  }
}
</script>
