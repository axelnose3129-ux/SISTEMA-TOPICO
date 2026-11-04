<template>
  <div v-if="actions.length" class="record-actions">
    <q-btn
      v-for="action in actions"
      :key="action.type"
      :outline="action.primary"
      :flat="!action.primary"
      no-caps
      :color="action.primary ? 'primary' : 'grey-8'"
      :icon="action.icon"
      :label="action.label"
      :disable="busy"
      @click="$emit('action', { type: action.type, row })"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
const props = defineProps<{
  section: string;
  row: Record<string, unknown>;
  clinical: boolean;
  coordinator: boolean;
  patientRole: boolean;
  teacherRole: boolean;
  canPrescribe: boolean;
  requestOpen: boolean;
  busy: boolean;
}>();
defineEmits<{
  action: [value: { type: string; row: Record<string, unknown> }];
}>();
interface Action {
  type: string;
  label: string;
  icon: string;
  primary?: boolean;
}
const actions = computed<Action[]>(() => {
  const result: Action[] = [];
  if (props.section === "pacientes") {
    result.push({ type: "detail", label: "Ver ficha", icon: "visibility" });
    if (props.clinical || props.patientRole)
      result.push({ type: "edit", label: "Editar", icon: "edit" });
  }
  if (props.section === "pacientes" && (props.clinical || props.patientRole))
    result.push({
      type: "refer",
      label: props.patientRole ? "Enviar al docente" : "Registrar ingreso",
      icon: "send",
      primary: true
    });
  if (
    props.section === "solicitudes" &&
    props.teacherRole &&
    props.row.status === "pendiente_docente"
  )
    result.push({
      type: "refer_existing",
      label: "Derivar al tópico",
      icon: "send",
      primary: true
    });
  if (props.section === "solicitudes" && !props.clinical)
    result.push({
      type: "request_detail",
      label: "Ver envío",
      icon: "visibility"
    });
  if (props.section === "solicitudes" && props.clinical) {
    if (props.row.status === "pendiente")
      result.push({
        type: "attend",
        label: "Atender",
        icon: "medical_services",
        primary: true
      });
    if (props.row.status === "en_atencion")
      result.push({
        type: "close",
        label: "Finalizar",
        icon: "task_alt",
        primary: true
      });
  }
  if (props.section === "atenciones") {
    result.push({ type: "detail", label: "Ver atención", icon: "visibility" });
    if (props.canPrescribe && props.requestOpen)
      result.push({
        type: "prescribe",
        label: "Recetar",
        icon: "description",
        primary: true
      });
  }
  if (
    (props.section === "medicamentos" && props.coordinator) ||
    props.section === "usuarios"
  )
    result.push({ type: "edit", label: "Editar", icon: "edit" });
  const expiry = new Date(String(props.row.expires_at) + "T23:59:59");
  if (
    props.section === "inventario" &&
    props.coordinator &&
    Number(props.row.warehouse_quantity) > 0 &&
    expiry >= new Date()
  )
    result.push({
      type: "transfer",
      label: "Enviar a tópico",
      icon: "local_shipping",
      primary: true
    });
  return result;
});
</script>
