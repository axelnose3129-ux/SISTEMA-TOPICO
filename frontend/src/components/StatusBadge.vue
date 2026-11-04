<template>
  <span class="status-badge" :class="'is-' + appearance.tone">
    <q-icon :name="appearance.icon" aria-hidden="true" />
    <span>{{ label || appearance.label }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from "vue";
const props = defineProps<{ value: string; label?: string }>();
const styles: Record<string, { label: string; icon: string; tone: string }> = {
  pendiente_docente: {
    label: "Pendiente del docente",
    icon: "school",
    tone: "review"
  },
  pendiente: { label: "Pendiente", icon: "schedule", tone: "warning" },
  en_atencion: { label: "En atención", icon: "medical_services", tone: "info" },
  finalizada: { label: "Finalizada", icon: "check_circle", tone: "success" },
  enviado: { label: "Enviado", icon: "local_shipping", tone: "info" },
  recibido: { label: "Recibido", icon: "inventory_2", tone: "success" },
  urgente: { label: "Urgente", icon: "priority_high", tone: "danger" },
  prioritaria: { label: "Prioritaria", icon: "outlined_flag", tone: "warning" },
  normal: { label: "Normal", icon: "remove", tone: "neutral" },
  agotado: { label: "Sin stock", icon: "error_outline", tone: "danger" },
  bajo: { label: "Stock bajo", icon: "warning_amber", tone: "warning" },
  disponible: {
    label: "Disponible",
    icon: "check_circle_outline",
    tone: "success"
  },
  vencido: { label: "Vencido", icon: "event_busy", tone: "danger" },
  por_vencer: { label: "Vence pronto", icon: "event", tone: "warning" },
  ingreso: { label: "Ingreso", icon: "south_west", tone: "success" },
  envio: { label: "Envío a tópico", icon: "local_shipping", tone: "info" },
  recepcion: { label: "Recepción", icon: "inventory_2", tone: "success" },
  entrega: { label: "Entrega al paciente", icon: "north_east", tone: "info" }
};
const appearance = computed(
  () =>
    styles[props.value] || {
      label: props.value,
      icon: "circle",
      tone: "neutral"
    }
);
</script>
