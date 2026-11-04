<template>
  <StatusBadge
    v-if="field === 'status' || field === 'priority'"
    :value="String(row[field])"
  />
  <StatusBadge v-else-if="field === 'type'" :value="String(row.type)" />
  <span v-else-if="field === 'stock'" class="stock-value"
    ><strong>{{ value }}</strong
    ><StatusBadge
      :value="
        Number(row.stock) === 0
          ? 'agotado'
          : Number(row.stock) <= Number(row.min_stock)
            ? 'bajo'
            : 'disponible'
      "
  /></span>
  <span v-else-if="field === 'expires_at'" class="expiry-value"
    ><span>{{ value }}</span
    ><StatusBadge v-if="expiryState" :value="expiryState"
  /></span>
  <span v-else-if="field === 'can_prescribe'" class="permission-value"
    ><q-icon
      :name="row.can_prescribe ? 'verified_user' : 'remove_circle_outline'"
      :color="row.can_prescribe ? 'positive' : 'grey-6'"
      aria-hidden="true"
    />{{ value }}</span
  >
  <details v-else-if="text.length > 120" class="expandable-value"
    ><summary>{{ text.slice(0, 110) }}… <span>Ver más</span></summary
    ><p>{{ text }}</p></details
  >
  <span
    v-else
    class="record-value"
    :class="{
      'record-value--primary': [
        'name',
        'patient_name',
        'medicine_name',
        'actor'
      ].includes(field)
    }"
    >{{ text }}</span
  >
</template>

<script setup lang="ts">
import { computed } from "vue";
import StatusBadge from "./StatusBadge.vue";
const props = defineProps<{
  field: string;
  value: unknown;
  row: Record<string, unknown>;
}>();
const text = computed(() => String(props.value ?? "—"));
const expiryState = computed(() => {
  if (props.field !== "expires_at" || !props.row.expires_at) return "";
  const [year, month, day] = String(props.row.expires_at)
    .split("-")
    .map(Number);
  if (!year || !month || !day) return "";
  const expiry = new Date(year, month - 1, day);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const limit = new Date(today);
  limit.setDate(limit.getDate() + 60);
  return expiry < today ? "vencido" : expiry <= limit ? "por_vencer" : "";
});
</script>
