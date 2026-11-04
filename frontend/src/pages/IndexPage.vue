<template>
  <div v-if="booting" class="boot-screen"
    ><q-spinner size="42px" color="primary" /><p>Abriendo Tópico…</p></div
  >
  <div v-else-if="!state" class="login-page">
    <section class="login-story"
      ><div class="brand"
        ><span class="brand-mark"><q-icon name="add" /></span> tópico<span
          class="brand-dot"
          >.</span
        ></div
      ><span class="eyebrow">BIENESTAR EN CADA ENCUENTRO</span
      ><h1>Un espacio para<br />cuidar mejor.</h1
      ><p
        >Atención, seguimiento y medicamentos.<br />Todo conectado, para estar
        más cerca.</p
      ><div class="care-art"
        ><img
          class="login-illustration"
          src="/images/cuidado-topico.png"
          alt="Profesional de salud acompañando a un estudiante en el tópico"
          width="1536"
          height="1024"
          fetchpriority="high"
        /><span class="art-tag"
          ><q-icon name="favorite" /> La salud, primero</span
        ></div
      ><small
        >Sistema de gestión del tópico · Acceso institucional</small
      ></section
    >
    <section class="login-form"
      ><div class="login-box"
        ><div class="login-card-badge" aria-hidden="true"
          ><q-icon name="medical_services" /></div
        ><span class="eyebrow">TE DAMOS LA BIENVENIDA</span
        ><h2>Ingresa a tu espacio</h2
        ><p class="muted">Ingresa con tu DNI o tu correo y contraseña.</p
        ><q-form @submit="login" class="form-stack"
          ><q-input
            v-model="credentials.identifier"
            outlined
            type="text"
            label="DNI o correo electrónico"
            placeholder="Ej. 10000001"
            maxlength="255"
            autocomplete="username"
            :rules="[required]"
            ><template #prepend><q-icon name="badge" /></template></q-input
          ><q-input
            v-model="credentials.password"
            outlined
            type="password"
            label="Contraseña"
            autocomplete="current-password"
            :rules="[required]"
            ><template #prepend
              ><q-icon name="lock_outline" /></template></q-input
          ><div v-if="error" class="error-box" role="alert">{{ error }}</div
          ><q-btn
            type="submit"
            unelevated
            color="primary"
            label="Ingresar al sistema"
            icon-right="arrow_forward"
            no-caps
            :loading="busy" /></q-form
        ><div class="login-note"
          ><q-icon name="lock_outline" /> Tu acceso protege la información de
          los pacientes.</div
        ></div
      ></section
    >
  </div>
  <div
    v-else
    class="workspace"
    :data-section="section === 'solicitar' ? 'solicitudes' : section"
    @keydown.esc="menuOpen = false"
  >
    <aside
      id="app-navigation"
      :inert="!menuOpen && $q.screen.width <= 900 ? true : undefined"
      :aria-hidden="!menuOpen && $q.screen.width <= 900 ? true : undefined"
      class="sidebar"
      :class="{ 'mobile-open': menuOpen }"
      ><div class="brand"
        ><span class="brand-mark"><q-icon name="add" /></span> tópico<span
          class="brand-dot"
          >.</span
        ></div
      ><div class="workspace-label">GESTIÓN DE BIENESTAR</div
      ><nav aria-label="Módulos del sistema"
        ><router-link
          v-for="item in navigation"
          :key="item.id"
          :data-module="item.id"
          :to="item.id === 'inicio' ? '/' : '/' + item.id"
          :class="{ active: section === item.id }"
          :aria-current="section === item.id ? 'page' : undefined"
          @click="menuOpen = false"
          ><q-icon :name="item.icon" /><span>{{ item.label }}</span
          ><span
            v-if="
              item.id === 'solicitudes' &&
              (teacherRole ? state.stats.pending_teacher : state.stats.pending)
            "
            class="nav-count"
            >{{
              teacherRole ? state.stats.pending_teacher : state.stats.pending
            }}</span
          ></router-link
        ></nav
      ><div class="sidebar-bottom"
        ><div class="care-note"
          ><q-icon name="favorite_border" /><strong
            >Cuidar también es prevenir.</strong
          ><span>Cada registro hace la diferencia.</span></div
        ><button class="user-card" @click="openForm('password')"
          ><span class="avatar">{{ initials(state.user.name) }}</span
          ><span
            ><strong>{{ state.user.name }}</strong
            ><small>{{ roleLabel(state.user.role) }}</small></span
          ><q-icon name="settings" /></button
        ><q-btn
          flat
          no-caps
          icon="logout"
          label="Cerrar sesión"
          size="sm"
          @click="logout"
          :disable="busy" /></div
    ></aside>
    <div v-if="menuOpen" class="sidebar-scrim" @click="menuOpen = false"></div>
    <main class="main-content"
      ><header class="topbar"
        ><div class="row items-center q-gutter-sm"
          ><q-btn
            class="mobile-toggle"
            aria-label="Abrir o cerrar menú"
            aria-controls="app-navigation"
            :aria-expanded="menuOpen"
            flat
            round
            icon="menu"
            @click="menuOpen = !menuOpen"
          /><span
            >Mi espacio <q-icon name="chevron_right" />
            <strong>{{ currentTitle }}</strong></span
          ></div
        ><div class="topbar-right"
          ><span class="date-label">{{ currentDate }}</span
          ><span class="access-badge"
            ><i></i> {{ roleLabel(state.user.role) }}</span
          ><q-btn
            flat
            round
            icon="refresh"
            size="sm"
            aria-label="Actualizar registros"
            :loading="busy"
            @click="refresh" /></div
      ></header>
      <div class="page-body"
        ><div v-if="error" class="error-box q-mb-md" role="alert"
          >{{ error }} <button @click="error = ''">Cerrar</button></div
        ><div v-if="success" class="success-box q-mb-md" role="status">{{
          success
        }}</div
        ><div
          class="page-heading"
          :class="{ 'has-module-image': section !== 'inicio' }"
          ><img
            v-if="
              section !== 'inicio' &&
              navigation.some(item => item.id === section)
            "
            class="module-heading-image"
            :key="section"
            :src="
              '/images/modulo-' +
              (section === 'solicitar' ? 'solicitudes' : section) +
              '.png'
            "
            :alt="'Ilustración de ' + currentTitle"
            width="1254"
            height="1254"
            decoding="async" /><div
            ><span class="eyebrow">{{
              section === "inicio"
                ? "TU CENTRO DE ATENCIÓN"
                : "SISTEMA DE TÓPICO"
            }}</span
            ><h1>{{ section === "inicio" ? greeting : currentTitle }}</h1
            ><p class="muted">{{
              descriptions[section] ||
              "Información disponible según los permisos de tu cuenta."
            }}</p></div
          ><q-btn
            v-if="primaryAction"
            unelevated
            color="primary"
            :icon="primaryAction.kind === 'request' ? 'send' : 'add'"
            :disable="busy"
            :label="primaryAction.label"
            no-caps
            @click="openForm(primaryAction.kind)"
        /></div>
        <template v-if="section === 'solicitar' && patientRole"
          ><section class="panel patient-request-panel"
            ><PatientRequestForm
              :patient="state.patients[0] || null"
              :teachers="state.teachers || []"
              :active-request="patientActiveRequest"
              :disabled="busy"
              @submitted="patientRequestSubmitted"
              @view="viewPatientRequest"
              @profile="dialog = false" /></section></template
        ><template v-else-if="section === 'inicio' || section === 'reportes'">
          <div class="stats-grid"
            ><article
              v-for="card in statCards"
              :key="card.label"
              class="stat-card"
              :class="'stat-card--' + card.tone"
              ><div
                ><span>{{ card.label }}</span
                ><strong>{{ card.value }}</strong
                ><small>{{ card.hint }}</small></div
              ><span class="stat-icon" :class="card.tone"
                ><q-icon :name="card.icon" /></span></article
          ></div>
          <div v-if="section === 'inicio'" class="welcome-banner"
            ><div
              ><span class="eyebrow">CONECTADOS PARA CUIDAR</span
              ><h2>{{
                clinical
                  ? "Cada atención comienza escuchando."
                  : coordinator
                    ? "Todo listo para una mejor atención."
                    : "Tu bienestar tiene un lugar aquí."
              }}</h2
              ><p>{{
                clinical
                  ? "Consulta la lista de espera, registra una atención y acompaña el seguimiento."
                  : coordinator
                    ? "Organiza los recursos y mantén el tópico abastecido."
                    : "Consulta tus registros y mantén al día la información que compartes con el tópico."
              }}</p
              ><q-btn
                outline
                no-caps
                color="primary"
                :label="
                  patientRole
                    ? 'Solicitar atención'
                    : coordinator
                      ? 'Ver inventario'
                      : 'Ver solicitudes'
                "
                icon-right="arrow_forward"
                :to="
                  patientRole
                    ? '/solicitar'
                    : coordinator
                      ? '/inventario'
                      : '/solicitudes'
                " /></div
            ><div class="banner-illustration"
              ><img
                src="/images/modulo-inicio.png"
                alt="Bienvenida al centro de atención del tópico"
                width="1254"
                height="1254"
                loading="lazy" /></div
          ></div>
          <div class="dashboard-grid"
            ><section class="panel"
              ><div class="panel-heading"
                ><div
                  ><h3>{{
                    section === "reportes"
                      ? "Resumen de actividad"
                      : teacherRole
                        ? "Solicitudes recibidas"
                        : patientRole
                          ? "Mis solicitudes recientes"
                          : "Atenciones recientes"
                  }}</h3
                  ><span class="muted">{{
                    coordinator
                      ? "Estado general del servicio"
                      : "El seguimiento empieza aquí"
                  }}</span></div
                ><q-btn
                  v-if="section === 'reportes'"
                  flat
                  no-caps
                  icon="download"
                  label="Exportar"
                  @click="exportReport" /><q-btn
                  v-else-if="!coordinator"
                  flat
                  no-caps
                  color="primary"
                  label="Ver todas"
                  to="/solicitudes" /></div
              ><div
                v-if="coordinator || section === 'reportes'"
                class="activity-summary"
                ><div v-for="entry in statusSummary" :key="entry.label"
                  ><div class="row justify-between"
                    ><span>{{ entry.label }}</span
                    ><strong>{{ entry.value }}</strong></div
                  ><div class="progress-track"
                    ><i
                      :style="{
                        width:
                          (state.stats.total
                            ? (entry.value / state.stats.total) * 100
                            : 0) + '%',
                        background: entry.color
                      }"
                    ></i></div></div
                ><p class="muted"
                  >{{ state.stats.total }} solicitudes registradas en total.</p
                ></div
              ><template v-else
                ><div
                  v-for="visit in state.requests.slice(0, 5)"
                  :key="visit.id"
                  class="recent-row"
                  ><span class="avatar light">{{
                    initials(visit.patient_name)
                  }}</span
                  ><div
                    ><strong>{{ visit.patient_name }}</strong
                    ><small>{{ visit.reason }}</small></div
                  ><StatusBadge :value="visit.status" /><q-btn
                    v-if="teacherRole && visit.status === 'pendiente_docente'"
                    outline
                    no-caps
                    color="primary"
                    icon="send"
                    label="Derivar al tópico"
                    :loading="busy"
                    @click="referToClinic(visit.id)" /></div
                ><EmptyState
                  v-if="!state.requests.length"
                  icon="event_available"
                  title="La lista está al día"
                  text="Las nuevas solicitudes de atención aparecerán aquí." /></template
            ></section>
            <section class="panel"
              ><div class="panel-heading"
                ><div
                  ><h3>{{
                    stockAccess ? "Para tener en cuenta" : "Accesos rápidos"
                  }}</h3
                  ><span class="muted">{{
                    stockAccess
                      ? "Alertas de inventario y seguimiento"
                      : "Tus gestiones, a un paso"
                  }}</span></div
                ><q-icon
                  name="notifications_none"
                  color="primary"
                  size="24px" /></div
              ><template v-if="stockAccess"
                ><router-link
                  v-for="alert in alerts.slice(0, 6)"
                  :key="alert.title"
                  :to="alert.to"
                  class="alert-row"
                  :class="'alert-row--' + alert.tone"
                  ><span class="alert-icon"><q-icon :name="alert.icon" /></span
                  ><div
                    ><strong>{{ alert.title }}</strong
                    ><small>{{ alert.text }}</small></div
                  ><q-icon name="chevron_right" /></router-link
                ><EmptyState
                  v-if="!alerts.length"
                  icon="verified"
                  title="Sin alertas pendientes"
                  text="Aquí verás avisos de stock, vencimientos y seguimientos." /></template
              ><template v-else
                ><router-link
                  v-for="link in navigation.filter(n => n.id !== 'inicio')"
                  :key="link.id"
                  :to="'/' + link.id"
                  class="alert-row"
                  ><q-icon
                    :name="link.icon"
                    color="primary"
                    size="24px" /><strong>{{ link.label }}</strong
                  ><q-icon
                    name="arrow_forward" /></router-link></template></section
          ></div>
          <section
            v-if="section === 'reportes' && stockAccess"
            class="panel q-mt-lg"
            ><div class="panel-heading"
              ><h3>Consumo de medicamentos</h3
              ><span class="muted"
                >Entregas registradas · últimos 200 movimientos</span
              ></div
            ><div
              v-for="medicine in consumption"
              :key="medicine.name"
              class="consumption-row"
              ><span>{{ medicine.name }}</span
              ><strong>{{ medicine.quantity }} unidades</strong></div
            ><EmptyState
              v-if="!consumption.length"
              icon="bar_chart"
              title="Aún no hay entregas"
              text="El consumo se calcula al confirmar la entrega al paciente."
          /></section>
        </template>
        <template v-else-if="section === 'recetas'"
          ><div class="search-toolbar"
            ><q-input
              v-model="search"
              dense
              outlined
              placeholder="Buscar por paciente o medicamento"
              clearable
              ><template #prepend><q-icon name="search" /></template></q-input
            ><span class="muted"
              >{{ filteredPrescriptions.length }} recetas</span
            ></div
          ><div class="prescription-grid"
            ><article
              v-for="rx in filteredPrescriptions"
              :key="rx.id"
              class="panel prescription-card"
              ><div class="panel-heading"
                ><div
                  ><span class="eyebrow"
                    >RECETA #{{ String(rx.id).padStart(4, "0") }}</span
                  ><h3>{{ rx.patient_name }}</h3></div
                ><q-btn
                  flat
                  round
                  icon="print"
                  aria-label="Imprimir receta"
                  @click="printPrescription(rx)" /></div
              ><p class="muted"
                >{{ formatDate(rx.created_at) }} · {{ rx.prescriber }}</p
              ><div v-for="item in rx.items" :key="item.id" class="rx-item"
                ><strong>{{ item.medicine_name }}</strong
                ><p
                  >{{ item.dose }} · {{ item.frequency }} ·
                  {{ item.duration }}</p
                ><div class="row items-center justify-between"
                  ><StatusBadge
                    :value="
                      item.dispensed_quantity === item.quantity
                        ? 'finalizada'
                        : 'pendiente'
                    "
                    :label="
                      item.dispensed_quantity +
                      ' / ' +
                      item.quantity +
                      ' entregadas'
                    " /><q-btn
                    v-if="clinical && item.dispensed_quantity < item.quantity"
                    flat
                    color="primary"
                    no-caps
                    icon="inventory_2"
                    label="Entregar"
                    @click="openDispense(item, rx)" /></div></div
              ><p v-if="rx.instructions" class="rx-instructions">{{
                rx.instructions
              }}</p></article
            ></div
          ><EmptyState
            v-if="!filteredPrescriptions.length"
            icon="description"
            title="Sin recetas para mostrar"
            text="Las recetas emitidas durante una atención aparecerán aquí."
        /></template>
        <template v-else-if="navigation.some(n => n.id === section)"
          ><div v-if="section === 'solicitudes'" class="request-flow"
            ><span><q-icon name="person" /> 1. Paciente solicita</span
            ><q-icon name="arrow_forward" class="request-flow-arrow" /><span
              ><q-icon name="school" /> 2. Docente deriva</span
            ><q-icon name="arrow_forward" class="request-flow-arrow" /><span
              ><q-icon name="medical_services" /> 3. Tópico atiende</span
            ></div
          ><div
            v-if="section === 'solicitudes' && teacherRole"
            class="teacher-inbox-notice"
            ><q-icon name="school" /><div
              ><strong
                >Bandeja del docente · {{ state.stats.pending_teacher }} por
                derivar</strong
              ><p
                >Las solicitudes de tus pacientes se actualizan automáticamente.
                Pulsa «Derivar al tópico» para enviar cada una.</p
              ></div
            ></div
          ><section
            class="panel table-panel"
            :class="{
              'has-row-actions': tableColumns.some(
                col => col.name === 'actions'
              )
            }"
            ><div class="panel-heading"
              ><div class="table-title"
                ><h3>{{ currentTitle }}</h3
                ><span class="record-count"
                  >{{ tableRows.length }} registros</span
                ></div
              ><q-input
                v-model="search"
                outlined
                dense
                clearable
                placeholder="Buscar en los registros"
                aria-label="Buscar en los registros"
                debounce="200"
                ><template #prepend
                  ><q-icon name="search" /></template></q-input></div
            ><q-table
              flat
              :rows="tableRows"
              :grid="$q.screen.lt.md"
              :loading="busy"
              wrap-cells
              :columns="tableColumns"
              row-key="id"
              :filter="search || ''"
              :rows-per-page-options="[10, 25, 50]"
              no-data-label="Todavía no hay registros"
              no-results-label="No se encontraron coincidencias"
              rows-per-page-label="Registros por página"
            >
              <template #body-cell="props"
                ><q-td :props="props"
                  ><RecordValue
                    :field="props.col.name"
                    :value="props.value"
                    :row="props.row" /></q-td
              ></template>
              <template #body-cell-actions="props"
                ><q-td :props="props" class="table-actions-cell"
                  ><RecordActions
                    :section="section"
                    :row="props.row"
                    :clinical="clinical"
                    :coordinator="coordinator"
                    :patient-role="patientRole"
                    :teacher-role="teacherRole"
                    :can-prescribe="Boolean(canPrescribe)"
                    :request-open="
                      state.requests.some(
                        r =>
                          r.id === props.row.request_id &&
                          r.status === 'en_atencion'
                      )
                    "
                    :busy="busy"
                    @action="handleRecordAction" /></q-td
              ></template>
              <template #item="props"
                ><div class="record-card-column col-12 col-sm-6"
                  ><article class="record-card">
                    <header class="record-card-heading"
                      ><span class="record-card-icon"
                        ><q-icon
                          :name="
                            navigation.find(item => item.id === section)
                              ?.icon || 'description'
                          " /></span
                      ><div
                        ><small>{{ props.cols[0]?.label }}</small
                        ><h3>{{ props.cols[0]?.value }}</h3></div
                      ></header
                    >
                    <dl class="record-card-values"
                      ><template
                        v-for="col in props.cols
                          .filter((col: QTableColumn) => col.name !== 'actions')
                          .slice(1)"
                        :key="col.name"
                        ><dt>{{ col.label }}</dt
                        ><dd
                          ><RecordValue
                            :field="col.name"
                            :value="col.value"
                            :row="props.row" /></dd></template
                    ></dl>
                    <footer
                      v-if="tableColumns.some(col => col.name === 'actions')"
                      class="record-card-actions"
                      ><RecordActions
                        :section="section"
                        :row="props.row"
                        :clinical="clinical"
                        :coordinator="coordinator"
                        :patient-role="patientRole"
                        :teacher-role="teacherRole"
                        :can-prescribe="Boolean(canPrescribe)"
                        :request-open="
                          state.requests.some(
                            r =>
                              r.id === props.row.request_id &&
                              r.status === 'en_atencion'
                          )
                        "
                        :busy="busy"
                        @action="handleRecordAction"
                    /></footer> </article></div
              ></template>
              <template #no-data
                ><EmptyState
                  :icon="search ? 'search_off' : 'folder_open'"
                  :title="
                    search ? 'Sin coincidencias' : 'Todavía no hay registros'
                  "
                  :text="
                    search
                      ? 'Prueba con otro nombre, documento o palabra.'
                      : 'Los registros de este módulo aparecerán aquí.'
                  " /><q-btn
                  v-if="search"
                  flat
                  no-caps
                  color="primary"
                  label="Limpiar búsqueda"
                  @click="search = ''"
              /></template> </q-table
          ></section>
          <section v-if="section === 'inventario'" class="panel q-mt-lg"
            ><div class="panel-heading"
              ><div
                ><h3>Envíos al tópico</h3
                ><span class="muted"
                  >El stock estará disponible al confirmar la recepción.</span
                ></div
              ><q-icon name="local_shipping" size="26px" color="primary" /></div
            ><div
              v-for="transfer in state.transfers"
              :key="transfer.id"
              class="recent-row"
              ><span class="avatar light"><q-icon name="local_shipping" /></span
              ><div
                ><strong
                  >{{ transfer.medicine_name }} ·
                  {{ transfer.quantity }} unidades</strong
                ><small
                  >Lote {{ transfer.lot }} ·
                  {{ formatDate(transfer.created_at) }}</small
                ></div
              ><StatusBadge :value="transfer.status" /><q-btn
                v-if="clinical && transfer.status === 'enviado'"
                flat
                no-caps
                color="primary"
                label="Recibir"
                @click="
                  openConfirm(
                    'transfers/' + transfer.id + '/receive',
                    'Confirmar recepción',
                    'Confirma que recibiste físicamente las ' +
                      transfer.quantity +
                      ' unidades del lote ' +
                      transfer.lot +
                      '.'
                  )
                " /></div
            ><EmptyState
              v-if="!state.transfers.length"
              icon="local_shipping"
              title="Sin envíos registrados"
              text="Los envíos de coordinación aparecerán aquí para su recepción." /></section></template
        ><EmptyState
          v-else
          icon="lock"
          title="Módulo no disponible"
          text="Usa el menú para acceder a los módulos de tu cuenta."
        />
        <footer class="page-footer"
          ><span>Tópico · Gestión de bienestar</span
          ><span>Un mejor cuidado empieza con buena información.</span></footer
        ></div
      ></main
    >
    <q-dialog v-model="dialog" persistent :maximized="$q.screen.lt.sm"
      ><q-card class="editor-dialog"
        ><q-card-section class="dialog-heading"
          ><div
            ><span class="eyebrow">{{
              editingId ? "ACTUALIZAR REGISTRO" : "NUEVO REGISTRO"
            }}</span
            ><h2>{{ formTitle }}</h2></div
          ><q-btn
            flat
            round
            icon="close"
            aria-label="Cerrar"
            :disable="busy"
            @click="dialog = false" /></q-card-section
        ><PatientRequestForm
          v-if="kind === 'request' && patientRole"
          compact
          :patient="state.patients[0] || null"
          :teachers="state.teachers || []"
          :active-request="patientActiveRequest"
          :disabled="busy"
          @submitted="patientRequestSubmitted"
          @view="viewPatientRequest"
          @profile="dialog = false" /><q-form v-else @submit="save"
          ><q-card-section class="form-stack"
            ><div v-if="dialogError" class="error-box" role="alert">{{
              dialogError
            }}</div
            ><div
              v-if="kind === 'prescription' || kind === 'dispense'"
              class="allergy-box"
              ><q-icon name="health_and_safety" size="24px" /><div
                ><strong>Alergias registradas</strong
                ><p>{{
                  selectedPatient?.allergies ||
                  "Sin información de alergias registrada. Confirma con el paciente."
                }}</p
                ><small
                  >La revisión debe realizarla el personal autorizado antes de
                  recetar o entregar.</small
                ></div
              ></div
            ><div
              v-if="kind === 'encounter' && selectedPatient"
              class="allergy-box"
              ><div
                ><strong>{{ selectedPatient.name }}</strong
                ><p
                  >Alergias:
                  {{
                    selectedPatient.allergies || "Sin información registrada"
                  }}</p
                ><small
                  >Antecedentes:
                  {{
                    selectedPatient.history || "Sin antecedentes registrados"
                  }}</small
                ></div
              ></div
            ><div v-if="kind === 'request'" class="referral-intro"
              ><span class="referral-intro-icon"><q-icon name="send" /></span
              ><div
                ><strong>{{
                  patientRole
                    ? "Primero lo recibe tu docente"
                    : "Ingreso en la lista de atención"
                }}</strong
                ><p>{{
                  patientRole
                    ? "Tu docente recibirá la solicitud y podrá derivarte al tópico con un clic."
                    : "Indica el motivo para registrar el ingreso al tópico."
                }}</p></div
              ></div
            >
            <div
              v-if="
                kind === 'request' && requestPatientLocked && requestPatient
              "
              class="referral-patient"
              ><q-avatar
                color="blue-1"
                text-color="primary"
                icon="person" /><div
                ><strong>{{ requestPatient.name }}</strong
                ><small>Documento: {{ requestPatient.document }}</small></div
              ><q-btn
                v-if="!patientRole"
                flat
                no-caps
                color="primary"
                label="Cambiar"
                @click="requestPatientLocked = false"
            /></div>
            <div
              v-if="kind === 'request' && patientRole && !requestPatient"
              class="error-box"
              ><p>Completa tu ficha para poder enviar la solicitud.</p
              ><q-btn
                flat
                no-caps
                color="primary"
                label="Completar mi ficha"
                to="/pacientes"
                @click="dialog = false"
            /></div>
            <div
              v-if="kind === 'request' && activeRequest"
              class="referral-existing"
              role="status"
              ><q-icon name="task_alt" /><div
                ><strong>{{
                  activeRequest.status === "pendiente_docente"
                    ? "Ya está enviada al docente"
                    : "Ya está enviada al tópico"
                }}</strong
                ><p>{{
                  activeRequest.status === "pendiente_docente"
                    ? "Espera a que tu docente la derive al tópico. No hace falta enviarla otra vez."
                    : activeRequest.status === "en_atencion"
                      ? "El paciente ya está siendo atendido."
                      : "La solicitud ya fue derivada al tópico y está pendiente de atención."
                }}</p
                ><q-btn
                  flat
                  no-caps
                  color="primary"
                  label="Ver solicitud"
                  @click="
                    showRequest(activeRequest);
                    dialog = false;
                  " /></div
            ></div>
            <div
              v-if="
                kind === 'request' && patientRole && teacherOptions.length === 1
              "
              class="referral-patient"
              ><q-avatar
                color="purple-1"
                text-color="purple-8"
                icon="school"
              /><div
                ><strong>{{ selectedTeacherName }}</strong
                ><small>Este docente recibirá tu solicitud.</small></div
              ></div
            >
            <div
              v-if="kind === 'request' && patientRole && !teacherOptions.length"
              class="error-box"
              >No hay docentes registrados. Coordinación debe crear una cuenta
              de docente antes de enviar solicitudes.</div
            ><p class="form-guidance"
              >Los campos con <strong>*</strong> son obligatorios.</p
            ><div class="editor-fields"
              ><div
                v-for="field in fields"
                :key="field.key"
                class="editor-field"
                :class="{
                  'editor-field--wide':
                    field.type === 'textarea' ||
                    field.type === 'check' ||
                    field.type === 'select' ||
                    field.type === 'password' ||
                    ['name', 'email', 'emergency_contact'].includes(field.key)
                }"
                ><q-select
                  v-if="field.type === 'select'"
                  v-model="form[field.key]"
                  outlined
                  hide-bottom-space
                  lazy-rules
                  :label="field.label + (field.required ? ' *' : '')"
                  :options="
                    kind === 'request' && field.key === 'patient_id'
                      ? requestPatientOptions
                      : field.options || []
                  "
                  :use-input="kind === 'request' && field.key === 'patient_id'"
                  :fill-input="kind === 'request' && field.key === 'patient_id'"
                  :hide-selected="
                    kind === 'request' && field.key === 'patient_id'
                  "
                  input-debounce="0"
                  @filter="filterRequestPatients"
                  emit-value
                  map-options
                  :clearable="!field.required"
                  :rules="fieldRules(field)" /><q-checkbox
                  v-else-if="field.type === 'check'"
                  v-model="form[field.key]"
                  :label="field.label + (field.required ? ' *' : '')"
                  color="primary" /><q-input
                  v-else
                  :model-value="inputValue(field.key)"
                  @update:model-value="form[field.key] = $event"
                  outlined
                  hide-bottom-space
                  lazy-rules
                  :label="field.label + (field.required ? ' *' : '')"
                  :type="field.type || 'text'"
                  :hint="
                    field.key === 'follow_up'
                      ? 'Opcional: hoy o una fecha posterior. Déjala vacía si no hay seguimiento.'
                      : field.hint
                  "
                  :clearable="field.key === 'follow_up'"
                  :rules="fieldRules(field)"
                  :min="
                    field.key === 'follow_up'
                      ? minimumFollowUpDate
                      : field.type === 'number'
                        ? 0
                        : undefined
                  " /></div
            ></div>
            <div v-if="kind === 'request'" class="referral-extras"
              ><div class="referral-reasons"
                ><small>Motivos frecuentes</small
                ><div
                  ><q-btn
                    v-for="reason in frequentReasons"
                    :key="reason"
                    outline
                    rounded
                    no-caps
                    size="sm"
                    color="primary"
                    :label="reason"
                    @click="form.reason = reason" /></div></div
              ><q-expansion-item
                v-if="!patientRole"
                dense
                icon="outlined_flag"
                :label="
                  'Prioridad: ' +
                  requestPriorityOptions.find(
                    option => option.value === form.priority
                  )?.label
                "
                ><q-btn-toggle
                  v-model="form.priority"
                  :options="requestPriorityOptions"
                  no-caps
                  unelevated
                  spread
                  toggle-color="primary"
                  color="grey-2"
                  text-color="grey-8"
                  class="referral-priority" /></q-expansion-item
            ></div>
            <template v-if="kind === 'prescription'"
              ><div class="row justify-between items-center"
                ><h3>Medicamentos</h3
                ><q-btn
                  flat
                  no-caps
                  color="primary"
                  icon="add"
                  label="Añadir"
                  @click="rxItems.push(newRxItem())" /></div
              ><div
                v-for="(item, index) in rxItems"
                :key="index"
                class="rx-editor"
                ><div class="row justify-between items-center"
                  ><strong>Medicamento {{ index + 1 }}</strong
                  ><q-btn
                    v-if="rxItems.length > 1"
                    flat
                    round
                    icon="delete_outline"
                    aria-label="Quitar medicamento"
                    @click="rxItems.splice(index, 1)" /></div
                ><q-select
                  v-model="item.medicine_id"
                  outlined
                  label="Medicamento y stock en tópico"
                  :options="medicineOptions"
                  emit-value
                  map-options
                  :rules="[required]" /><div class="field-grid"
                  ><q-input
                    v-model="item.dose"
                    outlined
                    label="Dosis"
                    :rules="[required]" /><q-input
                    v-model="item.frequency"
                    outlined
                    label="Frecuencia"
                    :rules="[required]" /><q-input
                    v-model="item.duration"
                    outlined
                    label="Duración"
                    :rules="[required]" /><q-input
                    v-model.number="item.quantity"
                    outlined
                    type="number"
                    min="1"
                    label="Cantidad total"
                    :rules="[required]" /></div></div
              ><q-checkbox
                v-model="form.allergy_reviewed"
                label="He revisado las alergias y la pertinencia de la receta."
                color="primary"
            /></template> </q-card-section
          ><q-card-actions align="right" class="dialog-actions"
            ><q-btn
              flat
              no-caps
              label="Cancelar"
              :disable="busy"
              @click="dialog = false" /><q-btn
              type="submit"
              unelevated
              color="primary"
              no-caps
              :label="
                kind === 'request'
                  ? patientRole
                    ? 'Enviar al docente'
                    : 'Registrar ingreso'
                  : kind === 'prescription'
                    ? 'Emitir receta'
                    : 'Guardar registro'
              "
              :loading="busy"
              :disable="
                kind === 'request' &&
                (!requestPatient ||
                  Boolean(activeRequest) ||
                  (patientRole && !form.teacher_id))
              "
              :icon="
                kind === 'request' ? 'send' : undefined
              " /></q-card-actions></q-form></q-card
    ></q-dialog>
    <q-dialog v-model="detailOpen"
      ><q-card class="editor-dialog"
        ><q-card-section class="dialog-heading"
          ><h2>{{ detailTitle }}</h2
          ><q-btn flat round icon="close" v-close-popup /></q-card-section
        ><q-card-section
          ><dl class="detail-list"
            ><template v-for="entry in detailEntries" :key="entry.label"
              ><dt>{{ entry.label }}</dt
              ><dd>{{
                entry.value || "Sin información registrada"
              }}</dd></template
            ></dl
          ></q-card-section
        ><q-card-actions
          v-if="detailPatientId && (clinical || patientRole)"
          align="right"
          class="dialog-actions"
          ><q-btn
            unelevated
            color="primary"
            icon="send"
            no-caps
            :label="patientRole ? 'Solicitar atención' : 'Registrar ingreso'"
            @click="
              openForm('request', { patient_id: detailPatientId });
              detailOpen = false;
            " /></q-card-actions></q-card
    ></q-dialog>
    <q-dialog v-model="confirmOpen" persistent
      ><q-card class="confirm-dialog"
        ><q-card-section
          ><h2>{{ confirmTitle }}</h2
          ><p>{{ confirmText }}</p
          ><div v-if="dialogError" class="error-box">{{
            dialogError
          }}</div></q-card-section
        ><q-card-actions align="right"
          ><q-btn
            flat
            no-caps
            label="Cancelar"
            :disable="busy"
            @click="confirmOpen = false" /><q-btn
            unelevated
            color="primary"
            no-caps
            label="Confirmar"
            :loading="busy"
            @click="confirmAction" /></q-card-actions></q-card
    ></q-dialog>
  </div>
  <section v-if="printRx" class="print-sheet"
    ><h1>Tópico · Receta médica</h1
    ><p>Receta #{{ printRx.id }} · {{ formatDate(printRx.created_at) }}</p
    ><h2>{{ printRx.patient_name }}</h2
    ><p>Profesional: {{ printRx.prescriber }}</p
    ><table
      ><thead
        ><tr
          ><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Duración</th
          ><th>Cantidad</th></tr
        ></thead
      ><tbody
        ><tr v-for="item in printRx.items" :key="item.id"
          ><td>{{ item.medicine_name }}</td
          ><td>{{ item.dose }}</td
          ><td>{{ item.frequency }}</td
          ><td>{{ item.duration }}</td
          ><td>{{ item.quantity }}</td></tr
        ></tbody
      ></table
    ><h3>Indicaciones</h3
    ><p>{{ printRx.instructions || "Sin indicaciones adicionales." }}</p
    ><div class="signature">Firma y sello del profesional</div></section
  >
</template>

<script setup lang="ts">
import {
  computed,
  defineComponent,
  h,
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  watch
} from "vue";
import { useRoute, useRouter } from "vue-router";
import type { QTableColumn } from "quasar";
import StatusBadge from "@/components/StatusBadge.vue";
import PatientRequestForm from "@/components/PatientRequestForm.vue";
import RecordValue from "@/components/RecordValue.vue";
import RecordActions from "@/components/RecordActions.vue";
import {
  api,
  ApiError,
  type State,
  type Patient,
  type Visit,
  type Encounter,
  type Prescription,
  type PrescriptionItem
} from "@/lib/api";
const EmptyState = defineComponent({
  props: ["icon", "title", "text"],
  setup: p => () =>
    h("div", { class: "empty-state" }, [
      h("span", { class: "material-icons" }, p.icon),
      h("strong", p.title),
      h("p", p.text)
    ])
});
const route = useRoute();
const router = useRouter();
const state = ref<State | null>(null);
const booting = ref(true);
const busy = ref(false);
const error = ref("");
const success = ref("");
const search = ref("");
const menuOpen = ref(false);
const credentials = ref({ identifier: "", password: "" });
const section = computed(() => String(route.params.section || "inicio"));
const clinical = computed(() => state.value?.user.role === "encargado");
const teacherRole = computed(() => state.value?.user.role === "docente");
const coordinator = computed(() => state.value?.user.role === "coordinador");
const patientRole = computed(() => state.value?.user.role === "paciente");
const stockAccess = computed(() => clinical.value || coordinator.value);
const patientActiveRequest = computed(() =>
  patientRole.value
    ? state.value?.requests.find(visit =>
        ["pendiente_docente", "pendiente", "en_atencion"].includes(visit.status)
      )
    : undefined
);
async function patientRequestSubmitted(result: {
  alreadySent: boolean;
  requestId: number;
  teacherName: string;
}) {
  dialog.value = false;
  busy.value = true;
  await router.push("/solicitudes");
  success.value = result.alreadySent
    ? `Este malestar ya estaba registrado en la solicitud #${result.requestId}. Puedes consultar su estado abajo.`
    : `Solicitud #${result.requestId} enviada a ${result.teacherName}. Está pendiente de que el docente la derive al tópico.`;
  await refresh();
}
function viewPatientRequest(visit: Visit) {
  dialog.value = false;
  showRequest(visit);
}
const canPrescribe = computed(
  () => clinical.value && state.value?.user.can_prescribe
);
const allNav = [
  {
    id: "inicio",
    label: "Inicio",
    icon: "space_dashboard",
    roles: ["coordinador", "encargado", "paciente", "docente"]
  },
  {
    id: "solicitar",
    label: "Solicitar atención",
    icon: "add_circle_outline",
    roles: ["paciente"]
  },
  {
    id: "pacientes",
    label: "Pacientes",
    icon: "people_outline",
    roles: ["encargado", "paciente"]
  },
  {
    id: "solicitudes",
    label: "Solicitudes y derivaciones",
    icon: "assignment_outlined",
    roles: ["encargado", "paciente", "docente"]
  },
  {
    id: "atenciones",
    label: "Atenciones",
    icon: "favorite_border",
    roles: ["encargado", "paciente"]
  },
  {
    id: "recetas",
    label: "Recetas",
    icon: "description",
    roles: ["encargado", "paciente"]
  },
  {
    id: "medicamentos",
    label: "Medicamentos",
    icon: "medication",
    roles: ["coordinador", "encargado"]
  },
  {
    id: "inventario",
    label: "Inventario y envíos",
    icon: "inventory_2",
    roles: ["coordinador", "encargado"]
  },
  {
    id: "movimientos",
    label: "Movimientos",
    icon: "swap_horiz",
    roles: ["coordinador", "encargado"]
  },
  {
    id: "reportes",
    label: "Reportes",
    icon: "bar_chart",
    roles: ["coordinador", "encargado"]
  },
  {
    id: "usuarios",
    label: "Usuarios y permisos",
    icon: "admin_panel_settings",
    roles: ["coordinador"]
  },
  {
    id: "auditoria",
    label: "Actividad del sistema",
    icon: "history",
    roles: ["coordinador"]
  }
];
const navigation = computed(() =>
  allNav
    .filter(n => n.roles.includes(state.value?.user.role || ""))
    .map(item => ({
      ...item,
      label: patientRole.value
        ? (
            {
              pacientes: "Mis datos",
              solicitudes: "Mis solicitudes",
              atenciones: "Mis atenciones",
              recetas: "Mis recetas"
            } as Record<string, string>
          )[item.id] || item.label
        : item.label
    }))
);
const currentTitle = computed(
  () => navigation.value.find(n => n.id === section.value)?.label || "Módulo"
);
function handleRecordAction(action: {
  type: string;
  row: Record<string, unknown>;
}) {
  const row = action.row;
  if (action.type === "detail") {
    if (section.value === "pacientes") showPatient(row as unknown as Patient);
    else showEncounter(row as unknown as Encounter);
  } else if (action.type === "edit") {
    openForm(
      (
        {
          pacientes: "patient",
          medicamentos: "medicine",
          usuarios: "user"
        } as Record<string, string>
      )[section.value] || "",
      row
    );
  } else if (action.type === "attend")
    openForm("encounter", { request_id: row.id });
  else if (action.type === "prescribe")
    openForm("prescription", { encounter_id: row.id });
  else if (action.type === "refer") openForm("request", { patient_id: row.id });
  else if (action.type === "refer_existing") void referToClinic(Number(row.id));
  else if (action.type === "request_detail")
    showRequest(row as unknown as Visit);
  else if (action.type === "transfer")
    openForm("transfer", { batch_id: row.id });
  else if (action.type === "close")
    openConfirm(
      "requests/" + row.id + "/close",
      "Finalizar atención",
      "Se cerrará esta atención y ya no se podrán emitir nuevas recetas en ella."
    );
}
const descriptions: Record<string, string> = {
  inicio: "Un vistazo a lo que importa para seguir cuidando.",
  solicitar:
    "Envía tu solicitud al docente para que pueda derivarte al tópico.",
  pacientes: "Fichas, contactos de emergencia, antecedentes y alergias.",
  solicitudes: "El paciente solicita, el docente deriva y el tópico atiende.",
  atenciones: "Evaluación, historia clínica y seguimiento de los pacientes.",
  recetas: "Indicaciones y entregas vinculadas a cada atención.",
  medicamentos: "El catálogo que acompaña el cuidado diario.",
  inventario: "Controla los lotes, vencimientos y envíos al tópico.",
  movimientos:
    "La trazabilidad de cada unidad, desde su ingreso hasta la entrega.",
  usuarios: "Asigna accesos y autoriza las funciones de cada persona.",
  reportes: "Información para organizar los recursos y tomar decisiones.",
  auditoria: "Registro de accesos y operaciones realizadas por el equipo."
};
const currentDate = new Intl.DateTimeFormat("es", {
  day: "numeric",
  month: "long",
  year: "numeric"
}).format(new Date());
const greeting = computed(
  () => "Hola, " + (state.value?.user.name.split(" ")[0] || "") + " 👋"
);
const required = (v: unknown) =>
  (v !== null && v !== undefined && v !== "" && v !== false) ||
  "Completa este campo";
const initials = (name: string) =>
  name
    .split(" ")
    .slice(0, 2)
    .map(n => n[0])
    .join("")
    .toUpperCase();
const roleLabel = (role: string) =>
  ({
    coordinador: "Coordinador",
    encargado: "Encargado de tópico",
    paciente: "Paciente",
    docente: "Docente"
  })[role] || role;
const formatDate = (value: unknown) => {
  if (!value) return "—";
  const d = new Date(String(value).replace(" ", "T"));
  return Number.isNaN(d.getTime())
    ? String(value)
    : new Intl.DateTimeFormat("es", {
        dateStyle: "medium",
        ...(String(value).includes(":") ? { timeStyle: "short" as const } : {})
      }).format(d);
};
const localDay = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};
const isExpired = (date: string) => date < localDay();
const lowStock = computed(
  () => state.value?.medicines.filter(m => m.stock <= m.min_stock) || []
);
const alerts = computed(() => {
  if (!state.value) return [];
  const list = lowStock.value.map(m => ({
    title: m.name,
    text: `${m.stock} ${m.unit} en tópico · mínimo ${m.min_stock}`,
    icon: "inventory_2",
    tone: m.stock === 0 ? "danger" : "warning",
    to: "/inventario"
  }));
  const limit = new Date();
  limit.setDate(limit.getDate() + 60);
  for (const b of state.value.batches) {
    if (
      b.clinic_quantity + b.warehouse_quantity > 0 &&
      new Date(b.expires_at) <= limit
    )
      list.push({
        title: `${isExpired(b.expires_at) ? "Lote vencido" : "Próximo a vencer"}: ${b.lot}`,
        text: `${b.medicine_name} · ${formatDate(b.expires_at)}`,
        icon: "event_busy",
        tone: isExpired(b.expires_at) ? "danger" : "warning",
        to: "/inventario"
      });
  }
  for (const e of state.value.encounters) {
    if (e.follow_up && e.follow_up >= localDay())
      list.push({
        title: "Seguimiento: " + e.patient_name,
        text: formatDate(e.follow_up),
        icon: "event",
        tone: "info",
        to: "/atenciones"
      });
  }
  const order: Record<string, number> = { danger: 0, warning: 1, info: 2 };
  return list.sort((a, b) => (order[a.tone] ?? 3) - (order[b.tone] ?? 3));
});
const statCards = computed(() => {
  const s = state.value?.stats;
  return [
    {
      label: "Solicitudes de hoy",
      value: s?.today || 0,
      hint: "Registradas hoy",
      icon: "today",
      tone: "cobalt"
    },
    {
      label: teacherRole.value ? "Por derivar" : "En espera",
      value: teacherRole.value ? s?.pending_teacher || 0 : s?.pending || 0,
      hint: teacherRole.value
        ? "Solicitudes para enviar al tópico"
        : patientRole.value
          ? "Esperando al docente o al tópico"
          : "Pendientes de atención",
      icon: "schedule",
      tone: "amber"
    },
    {
      label: "Atenciones finalizadas",
      value: s?.completed || 0,
      hint: "Total acumulado",
      icon: "task_alt",
      tone: "blue"
    },
    {
      label: stockAccess.value ? "Alertas de stock" : "Mis recetas",
      value: stockAccess.value
        ? lowStock.value.length
        : state.value?.prescriptions.length || 0,
      hint: stockAccess.value
        ? "Medicamentos bajo el mínimo"
        : "Disponibles para consultar",
      icon: stockAccess.value ? "medication" : "description",
      tone: "rose"
    }
  ];
});
const statusSummary = computed(() => {
  const s = state.value?.stats;
  return [
    { label: "Pendientes", value: s?.pending || 0, color: "#d49c3b" },
    {
      label: "En atención",
      value: (s?.total || 0) - (s?.pending || 0) - (s?.completed || 0),
      color: "#5c94b3"
    },
    { label: "Finalizadas", value: s?.completed || 0, color: "#188065" }
  ];
});
const consumption = computed(() => {
  const counts: Record<string, number> = {};
  for (const m of state.value?.movements || [])
    if (m.type === "entrega")
      counts[m.medicine_name] = (counts[m.medicine_name] || 0) + m.quantity;
  return Object.entries(counts)
    .map(([name, quantity]) => ({ name, quantity }))
    .sort((a, b) => b.quantity - a.quantity);
});
const primaryAction = computed(() => {
  if (
    section.value === "pacientes" &&
    (clinical.value || (patientRole.value && !state.value?.patients.length))
  )
    return {
      label: patientRole.value ? "Completar mi ficha" : "Nuevo paciente",
      kind: "patient"
    };
  if (
    ["inicio", "solicitudes"].includes(section.value) &&
    ["encargado", "paciente"].includes(state.value?.user.role || "")
  )
    return {
      label: patientRole.value ? "Solicitar atención" : "Registrar ingreso",
      kind: "request"
    };
  if (section.value === "medicamentos" && coordinator.value)
    return { label: "Nuevo medicamento", kind: "medicine" };
  if (section.value === "inventario" && coordinator.value)
    return { label: "Ingresar lote", kind: "batch" };
  if (section.value === "usuarios" && coordinator.value)
    return { label: "Nuevo usuario", kind: "user" };
  return null;
});
const tableRows = computed(() => {
  const s = state.value;
  if (!s) return [];
  return (
    (
      {
        pacientes: s.patients,
        solicitudes: s.requests,
        atenciones: s.encounters,
        medicamentos: s.medicines,
        inventario: s.batches,
        movimientos: s.movements,
        usuarios: s.users,
        auditoria: s.audits
      } as Record<string, unknown[]>
    )[section.value] || []
  );
});
const columnMap: Record<string, [string, string][]> = {
  pacientes: [
    ["name", "Paciente"],
    ["document", "Documento"],
    ["phone", "Teléfono"],
    ["allergies", "Alergias"]
  ],
  solicitudes: [
    ["patient_name", "Paciente"],
    ["reason", "Motivo"],
    ["teacher_name", "Docente"],
    ["source", "Origen"],
    ["priority", "Prioridad"],
    ["status", "Estado"],
    ["created_at", "Fecha"]
  ],
  atenciones: [
    ["patient_name", "Paciente"],
    ["diagnosis", "Evaluación / diagnóstico"],
    ["follow_up", "Seguimiento"],
    ["created_at", "Fecha"]
  ],
  medicamentos: [
    ["name", "Medicamento"],
    ["ingredient", "Principio activo"],
    ["presentation", "Presentación"],
    ["unit", "Unidad"],
    ["stock", "Stock tópico"],
    ["min_stock", "Mínimo"]
  ],
  inventario: [
    ["medicine_name", "Medicamento"],
    ["lot", "Lote"],
    ["expires_at", "Vencimiento"],
    ["warehouse_quantity", "Almacén"],
    ["clinic_quantity", "Tópico"]
  ],
  movimientos: [
    ["medicine_name", "Medicamento"],
    ["lot", "Lote"],
    ["type", "Movimiento"],
    ["quantity", "Cantidad"],
    ["actor", "Responsable"],
    ["created_at", "Fecha"]
  ],
  usuarios: [
    ["name", "Nombre"],
    ["dni", "DNI"],
    ["email", "Correo"],
    ["role", "Rol"],
    ["can_prescribe", "Puede recetar"]
  ],
  auditoria: [
    ["actor", "Usuario"],
    ["action", "Acción"],
    ["created_at", "Fecha"]
  ]
};
const tableColumns = computed<QTableColumn[]>(() => [
  ...(columnMap[section.value] || []).map(([key, label]) => ({
    name: key,
    label,
    field: key,
    classes: ["name", "patient_name", "medicine_name", "actor"].includes(key)
      ? "primary-value-cell"
      : "",
    align: "left" as const,
    sortable: true,
    format: (value: unknown) =>
      key.endsWith("_at") || key === "follow_up"
        ? formatDate(value)
        : key === "can_prescribe"
          ? value
            ? "Sí"
            : "No"
          : key === "role"
            ? roleLabel(String(value))
            : String(value ?? "—")
  })),
  ...([
    "pacientes",
    "solicitudes",
    "atenciones",
    "medicamentos",
    "inventario",
    "usuarios"
  ].includes(section.value)
    ? [
        {
          name: "actions",
          label: "Acciones",
          field: "id",
          align: "right" as const,
          sortable: false
        }
      ]
    : [])
]);
const filteredPrescriptions = computed(
  () =>
    state.value?.prescriptions.filter(rx =>
      `${rx.patient_name} ${rx.items.map(i => i.medicine_name).join(" ")}`
        .toLowerCase()
        .includes((search.value || "").toLowerCase())
    ) || []
);
async function refresh() {
  busy.value = true;
  error.value = "";
  try {
    applyState(await api<State>("state"));
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
const message = (e: unknown) =>
  e instanceof Error ? e.message : "No se pudo completar la operación.";
async function login() {
  authVersion += 1;
  busy.value = true;
  error.value = "";
  try {
    await api("login", "POST", credentials.value);
    applyState(await api<State>("state"));
    credentials.value.password = "";
    announceSessionChange();
    await router.push("/");
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
async function logout() {
  authVersion += 1;
  busy.value = true;
  try {
    await api("logout", "POST");
    state.value = null;
    announceSessionChange();
    printRx.value = null;
    search.value = "";
    success.value = "";
    await router.push("/");
  } catch (e) {
    error.value = message(e);
  } finally {
    busy.value = false;
  }
}
let inboxTimer: ReturnType<typeof setInterval> | undefined;
let syncInProgress = false;
let authVersion = 0;
let needsSessionSync = false;
function resetOpenViews() {
  dialog.value = false;
  detailOpen.value = false;
  confirmOpen.value = false;
  menuOpen.value = false;
  printRx.value = null;
  search.value = "";
}
function applyState(next: State) {
  const changed = Boolean(
    state.value &&
    (state.value.user.id !== next.user.id ||
      state.value.user.role !== next.user.role)
  );
  state.value = next;
  if (changed) {
    resetOpenViews();
    success.value = "";
    error.value =
      "La cuenta activa cambió en otra pestaña. Ahora estás como " +
      roleLabel(next.user.role) +
      ".";
    void router.replace("/");
  }
}
function announceSessionChange() {
  try {
    localStorage.setItem("topico:session-change", String(Date.now()));
  } catch {
    /* Storage can be disabled by the browser. */
  }
}
async function synchronizeInbox() {
  if (
    !state.value ||
    busy.value ||
    syncInProgress ||
    document.visibilityState !== "visible"
  )
    return;
  syncInProgress = true;
  const version = authVersion;
  try {
    const next = await api<State>("state");
    if (authVersion !== version || busy.value) return;
    applyState(next);
    needsSessionSync = false;
  } catch (exception) {
    if (exception instanceof ApiError && exception.status === 401) {
      state.value = null;
      resetOpenViews();
      needsSessionSync = false;
      error.value = "La sesión terminó en otra pestaña. Ingresa nuevamente.";
    }
  } finally {
    syncInProgress = false;
  }
}
function onSessionChanged() {
  needsSessionSync = true;
  void synchronizeInbox();
}
function onSessionStorage(event: StorageEvent) {
  if (event.key === "topico:session-change") onSessionChanged();
}
function onWindowFocus() {
  needsSessionSync = true;
  void synchronizeInbox();
}
onMounted(async () => {
  try {
    applyState(await api<State>("state"));
  } catch (exception) {
    const text = message(exception);
    if (!text.includes("Unauthenticated")) error.value = text;
  } finally {
    booting.value = false;
  }
  inboxTimer = setInterval(() => {
    if (teacherRole.value || patientRole.value || needsSessionSync)
      void synchronizeInbox();
  }, 5000);
  window.addEventListener("storage", onSessionStorage);
  window.addEventListener("focus", onWindowFocus);
  window.addEventListener("topico:session-change", onSessionChanged);
  document.addEventListener("visibilitychange", onWindowFocus);
});
onUnmounted(() => {
  if (inboxTimer) clearInterval(inboxTimer);
  window.removeEventListener("storage", onSessionStorage);
  window.removeEventListener("focus", onWindowFocus);
  window.removeEventListener("topico:session-change", onSessionChanged);
  document.removeEventListener("visibilitychange", onWindowFocus);
});
watch(section, () => {
  search.value = "";
  success.value = "";
});
const dialog = ref(false);
const kind = ref("");
const editingId = ref<number | null>(null);
const dialogError = ref("");
const form = ref<Record<string, string | number | boolean | null>>({});
interface Field {
  key: string;
  label: string;
  type?:
    | "text"
    | "textarea"
    | "email"
    | "password"
    | "date"
    | "number"
    | "select"
    | "check";
  required?: boolean;
  hint?: string;
  options?: { label: string; value: number | string }[];
}
const patientOptions = computed(
  () =>
    state.value?.patients.map(p => ({
      label: `${p.name} · ${p.document}`,
      value: p.id
    })) || []
);
const requestPatientLocked = ref(false);
const requestPatientSearch = ref("");
const requestPatientOptions = computed(() =>
  patientOptions.value.filter(option =>
    option.label
      .toLocaleLowerCase()
      .includes(requestPatientSearch.value.toLocaleLowerCase())
  )
);
function filterRequestPatients(
  value: string,
  update: (callback: () => void) => void
) {
  update(() => {
    requestPatientSearch.value = value;
  });
}
const requestPatient = computed(() =>
  state.value?.patients.find(
    patient => patient.id === Number(form.value.patient_id)
  )
);
const activeRequest = computed(() =>
  kind.value === "request"
    ? state.value?.requests.find(
        visit =>
          visit.patient_id === Number(form.value.patient_id) &&
          ["pendiente_docente", "pendiente", "en_atencion"].includes(
            visit.status
          )
      )
    : undefined
);
const teacherOptions = computed(() =>
  (state.value?.teachers || []).map(teacher => ({
    label: teacher.name,
    value: teacher.id
  }))
);
const selectedTeacherName = computed(
  () =>
    teacherOptions.value.find(
      teacher => teacher.value === Number(form.value.teacher_id)
    )?.label || ""
);
const requestPriorityOptions = [
  { label: "Normal", value: "normal" },
  { label: "Prioritaria", value: "prioritaria" },
  { label: "Urgente", value: "urgente" }
];
const frequentReasons = [
  "Malestar general",
  "Dolor de cabeza",
  "Golpe o caída"
];
const medicineOptions = computed(
  () =>
    state.value?.medicines.map(m => ({
      label: `${m.name} · ${m.presentation} · stock: ${m.stock}`,
      value: m.id
    })) || []
);
const selectedPatient = computed(() => {
  if (!state.value) return undefined;
  if (kind.value === "encounter") {
    const r = state.value.requests.find(
      r => r.id === Number(form.value.request_id)
    );
    return state.value.patients.find(p => p.id === r?.patient_id);
  }
  const e = state.value.encounters.find(
    e => e.id === Number(form.value.encounter_id)
  );
  return state.value.patients.find(p => p.id === e?.patient_id);
});
const titles: Record<string, string> = {
  patient: "Ficha del paciente",
  user: "Usuario y permisos",
  request: "Registrar ingreso al tópico",
  encounter: "Registrar atención",
  medicine: "Medicamento",
  batch: "Ingreso de lote",
  transfer: "Enviar medicamentos al tópico",
  prescription: "Emitir receta",
  dispense: "Entrega al paciente",
  password: "Cambiar contraseña"
};
const formTitle = computed(() =>
  kind.value === "request" && patientRole.value
    ? "Solicitar atención"
    : titles[kind.value] || "Registro"
);
const fields = computed<Field[]>(() => {
  const f = (
    key: string,
    label: string,
    type: Field["type"] = "text",
    required = true
  ): Field => ({ key, label, type, required });
  const select = (
    key: string,
    label: string,
    options: NonNullable<Field["options"]>,
    required = true
  ): Field => ({ key, label, type: "select", required, options });
  switch (kind.value) {
    case "patient":
      return [
        f("name", "Nombres y apellidos"),
        f("document", "Documento de identidad"),
        f("birth_date", "Fecha de nacimiento", "date", false),
        f("phone", "Teléfono", "text", false),
        f(
          "emergency_contact",
          "Contacto de emergencia y teléfono",
          "text",
          false
        ),
        f("allergies", "Alergias a medicamentos", "textarea", false),
        f("history", "Antecedentes relevantes", "textarea", false)
      ];
    case "user":
      return [
        f("name", "Nombre completo"),
        f("email", "Correo electrónico", "email"),
        f("dni", "DNI de acceso (8 dígitos, opcional)", "text", false),
        select(
          "role",
          "Rol",
          ["coordinador", "encargado", "paciente", "docente"].map(r => ({
            label: roleLabel(r),
            value: r
          }))
        ),
        f(
          "password",
          editingId.value
            ? "Nueva contraseña (opcional)"
            : "Contraseña (mínimo 10 caracteres)",
          "password",
          !editingId.value
        ),
        ...(form.value.role === "paciente"
          ? [
              select(
                "patient_id",
                "Vincular ficha de paciente (opcional)",
                patientOptions.value,
                false
              )
            ]
          : []),
        ...(form.value.role === "encargado"
          ? [
              f(
                "can_prescribe",
                "Autorizar emisión de recetas según su cargo profesional",
                "check",
                false
              )
            ]
          : [])
      ];
    case "request":
      return [
        ...(patientRole.value && teacherOptions.value.length > 1
          ? [
              select(
                "teacher_id",
                "¿A qué docente envías la solicitud?",
                teacherOptions.value
              )
            ]
          : []),
        ...(!patientRole.value && !requestPatientLocked.value
          ? [
              select(
                "patient_id",
                "Buscar paciente por nombre o documento",
                patientOptions.value
              )
            ]
          : []),
        f("reason", "¿Por qué necesita acudir al tópico?", "textarea")
      ];
    case "encounter":
      return [
        f("vital_signs", "Signos vitales", "textarea", false),
        f("diagnosis", "Evaluación / diagnóstico", "textarea"),
        f("notes", "Procedimientos e indicaciones", "textarea", false),
        f("follow_up", "Fecha de seguimiento (opcional)", "date", false)
      ];
    case "medicine":
      return [
        f("name", "Nombre comercial o genérico"),
        f("ingredient", "Principio activo"),
        f("presentation", "Presentación / concentración"),
        f("unit", "Unidad de control"),
        f("min_stock", "Stock mínimo en tópico", "number")
      ];
    case "batch":
      return [
        select("medicine_id", "Medicamento", medicineOptions.value),
        f("lot", "Número de lote"),
        f("expires_at", "Fecha de vencimiento", "date"),
        f("quantity", "Cantidad recibida en almacén", "number")
      ];
    case "transfer":
      return [
        select(
          "batch_id",
          "Lote de almacén",
          (state.value?.batches || [])
            .filter(b => b.warehouse_quantity > 0 && !isExpired(b.expires_at))
            .map(b => ({
              label: `${b.medicine_name} · ${b.lot} · disponible: ${b.warehouse_quantity}`,
              value: b.id
            }))
        ),
        f("quantity", "Cantidad a enviar", "number")
      ];
    case "prescription":
      return [f("instructions", "Indicaciones adicionales", "textarea", false)];
    case "dispense":
      return [
        select(
          "batch_id",
          "Lote disponible en tópico",
          (state.value?.batches || [])
            .filter(
              b =>
                b.medicine_id === Number(form.value.medicine_id) &&
                b.clinic_quantity > 0 &&
                !isExpired(b.expires_at)
            )
            .map(b => ({
              label: `${b.lot} · vence ${formatDate(b.expires_at)} · ${b.clinic_quantity} disponibles`,
              value: b.id
            }))
        ),
        f("quantity", "Cantidad a entregar", "number"),
        f(
          "allergy_reviewed",
          "He revisado las alergias antes de entregar.",
          "check",
          false
        )
      ];
    case "password":
      return [
        f("current_password", "Contraseña actual", "password"),
        f("password", "Nueva contraseña (mínimo 10 caracteres)", "password"),
        f("password_confirmation", "Repetir nueva contraseña", "password")
      ];
    default:
      return [];
  }
});
const minimumFollowUpDate = computed(
  () => state.value?.server_today || localDay()
);
function fieldRules(field: Field): ((value: unknown) => boolean | string)[] {
  const rules: ((value: unknown) => boolean | string)[] = field.required
    ? [required]
    : [];
  if (field.key === "follow_up")
    rules.push(
      value =>
        !value ||
        (/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(String(value)) &&
          String(value) >= minimumFollowUpDate.value) ||
        "Elige hoy o una fecha posterior, o deja el seguimiento vacío."
    );
  return rules;
}
const inputValue = (key: string): string | number | null => {
  const value = form.value[key];
  return typeof value === "boolean" ? "" : (value ?? null);
};
const newRxItem = () => ({
  medicine_id: null as number | null,
  dose: "",
  frequency: "",
  duration: "",
  quantity: 1
});
const rxItems = ref([newRxItem()]);
function openForm(type: string, data: object = {}) {
  const values = data as Record<string, unknown>;
  kind.value = type;
  requestPatientSearch.value = "";
  requestPatientLocked.value =
    type === "request" && (patientRole.value || Boolean(values.patient_id));
  editingId.value = ["patient", "user", "medicine"].includes(type)
    ? Number(values.id) || null
    : null;
  form.value = {
    priority: "normal",
    role: "paciente",
    can_prescribe: false,
    min_stock: 10,
    quantity: 1,
    allergy_reviewed: false
  };
  for (const [key, value] of Object.entries(values))
    if (
      typeof value === "string" ||
      typeof value === "number" ||
      typeof value === "boolean" ||
      value === null
    )
      form.value[key] = value;
  if (type === "request" && patientRole.value)
    form.value.patient_id = state.value?.patients[0]?.id ?? null;
  if (type === "request" && patientRole.value)
    form.value.teacher_id =
      teacherOptions.value.length === 1
        ? (teacherOptions.value[0]?.value ?? null)
        : null;
  if (type === "user") {
    form.value.password = "";
    form.value.patient_id =
      state.value?.patients.find(p => p.user_id === Number(values.id))?.id ??
      null;
  }
  rxItems.value = [newRxItem()];
  dialogError.value = "";
  dialog.value = true;
}
function openDispense(item: PrescriptionItem, rx: Prescription) {
  openForm("dispense", {
    item_id: item.id,
    medicine_id: item.medicine_id,
    encounter_id: rx.encounter_id,
    quantity: item.quantity - item.dispensed_quantity
  });
}
async function save() {
  busy.value = true;
  dialogError.value = "";
  try {
    const endpoint = (
      {
        patient: "patients",
        user: "users",
        request: "requests",
        encounter: "encounters",
        medicine: "medicines",
        batch: "batches",
        transfer: "transfers",
        prescription: "prescriptions",
        dispense: "dispense",
        password: "password"
      } as Record<string, string>
    )[kind.value];
    const payload: Record<string, unknown> = { ...form.value };
    for (const key of [
      "min_stock",
      "quantity",
      "patient_id",
      "teacher_id",
      "medicine_id",
      "batch_id",
      "request_id",
      "encounter_id",
      "item_id"
    ])
      if (
        payload[key] !== undefined &&
        payload[key] !== null &&
        payload[key] !== ""
      )
        payload[key] = Number(payload[key]);
    if (kind.value === "user" && payload.role !== "encargado")
      payload.can_prescribe = false;
    if (kind.value === "prescription") payload.items = rxItems.value;
    if (kind.value === "encounter" && !payload.follow_up)
      payload.follow_up = null;
    await api(
      endpoint + (editingId.value ? "/" + editingId.value : ""),
      editingId.value ? "PUT" : "POST",
      payload
    );
    dialog.value = false;
    if (kind.value === "request") {
      await router.push("/solicitudes");
      success.value = patientRole.value
        ? "Solicitud enviada al docente. Él podrá derivarla al tópico."
        : "Ingreso registrado en la lista de atención del tópico.";
    } else success.value = "Registro guardado correctamente.";
    await refresh();
  } catch (e) {
    dialogError.value = message(e);
  } finally {
    busy.value = false;
  }
}
const detailOpen = ref(false);
const detailTitle = ref("");
const detailPatientId = ref<number | null>(null);
const detailEntries = ref<{ label: string; value: unknown }[]>([]);
function showPatient(p: Patient) {
  detailPatientId.value = p.id;
  detailTitle.value = p.name;
  detailEntries.value = [
    { label: "Documento", value: p.document },
    { label: "Nacimiento", value: formatDate(p.birth_date) },
    { label: "Teléfono", value: p.phone },
    { label: "Contacto de emergencia", value: p.emergency_contact },
    { label: "Alergias", value: p.allergies },
    { label: "Antecedentes", value: p.history }
  ];
  detailOpen.value = true;
}
function showEncounter(e: Encounter) {
  detailPatientId.value = null;
  detailTitle.value = "Atención · " + e.patient_name;
  detailEntries.value = [
    { label: "Fecha", value: formatDate(e.created_at) },
    { label: "Signos vitales", value: e.vital_signs },
    { label: "Evaluación / diagnóstico", value: e.diagnosis },
    { label: "Procedimientos e indicaciones", value: e.notes },
    { label: "Seguimiento", value: formatDate(e.follow_up) }
  ];
  detailOpen.value = true;
}
async function referToClinic(id: number) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    await api("requests/" + id + "/refer", "POST");
    success.value =
      "Derivada al tópico. El encargado ya puede ver la solicitud y atender al paciente.";
    await refresh();
  } catch (exception) {
    error.value = message(exception);
  } finally {
    busy.value = false;
  }
}
function showRequest(visit: Visit) {
  detailPatientId.value = null;
  detailTitle.value = "Solicitud #" + visit.id;
  detailEntries.value = [
    { label: "Paciente", value: visit.patient_name },
    { label: "Motivo", value: visit.reason },
    { label: "Docente", value: visit.teacher_name || "Sin docente asignado" },
    {
      label: "Fecha de derivación al tópico",
      value: visit.forwarded_at
        ? formatDate(visit.forwarded_at)
        : "Aún no derivada"
    },
    {
      label: "Estado",
      value:
        (
          {
            pendiente_docente:
              "Pendiente del docente · aún no enviada al tópico",
            pendiente: "Derivada al tópico · pendiente de atención",
            en_atencion: "En atención",
            finalizada: "Atención finalizada"
          } as Record<string, string>
        )[visit.status] || visit.status
    },
    {
      label: "Prioridad",
      value:
        requestPriorityOptions.find(option => option.value === visit.priority)
          ?.label || visit.priority
    },
    { label: "Fecha de envío", value: formatDate(visit.created_at) }
  ];
  detailOpen.value = true;
}
const confirmOpen = ref(false);
const confirmTitle = ref("");
const confirmText = ref("");
const confirmEndpoint = ref("");
function openConfirm(endpoint: string, title: string, text: string) {
  confirmEndpoint.value = endpoint;
  confirmTitle.value = title;
  confirmText.value = text;
  dialogError.value = "";
  confirmOpen.value = true;
}
async function confirmAction() {
  busy.value = true;
  try {
    await api(confirmEndpoint.value, "POST");
    confirmOpen.value = false;
    success.value = "Operación completada.";
    await refresh();
  } catch (e) {
    dialogError.value = message(e);
  } finally {
    busy.value = false;
  }
}
const printRx = ref<Prescription | null>(null);
async function printPrescription(rx: Prescription) {
  printRx.value = rx;
  await nextTick();
  window.print();
  printRx.value = null;
}
function exportReport() {
  const rows = [
    ["Reporte de tópico", "Cantidad"],
    ...statusSummary.value.map(s => [s.label, String(s.value)]),
    ["Solicitudes de hoy", String(state.value?.stats.today || 0)],
    ["Medicamento entregado", "Unidades (últimos 200 movimientos)"],
    ...consumption.value.map(m => [m.name, String(m.quantity)])
  ];
  const csv =
    "\uFEFF" +
    rows
      .map(row =>
        row
          .map(
            c =>
              '"' +
              String(c)
                .replace(/"/g, '""')
                .replace(/^[=+@-]/, "'") +
              '"'
          )
          .join(";")
      )
      .join("\r\n");
  const url = URL.createObjectURL(
    new Blob([csv], { type: "text/csv;charset=utf-8" })
  );
  const a = document.createElement("a");
  a.href = url;
  a.download = `topico-reporte-${localDay()}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}
</script>
