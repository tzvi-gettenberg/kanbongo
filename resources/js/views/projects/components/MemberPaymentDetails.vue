<script setup>
import { defineProps, ref, watch, computed } from "vue"
import { format } from "date-fns"

const props = defineProps({
  member: { type: Object, required: true },
  dateRange: { type: String, required: false, default: null },
  boardId: { type: Number, required: true, default: 0 },
  isOwner: { type: Boolean, required: false, default: false },
  isSuperAdmin: { type: Boolean, required: false, default: false },
})

const emit = defineEmits(["update:selectedPayment", "update:selectedEntries"])

const paymentDetails = ref(null)
const loading = ref(false)
const selectedTimeEntries = ref([])
const expandedTask = ref(null) // Track expanded task for logs

const fetchMemberPaymentDetails = async () => {
  if (!props.member) return

  loading.value = true
  try {
    const res = await $api(`/container/${props.boardId}/member-payment-details/${props.member.user.id}`, {
      method: "GET",
      params: { date_range: props.dateRange },
    })

    selectedTimeEntries.value = []
    paymentDetails.value = res.data
  } catch (error) {
    console.error("Error fetching member payment details:", error)
  } finally {
    loading.value = false
  }
}

const formatDate = dateString => format(new Date(dateString), "MMM d, yyyy h:mm:ss a")

const parseDurationToSeconds = duration => {
  const [hours, minutes, seconds] = duration.split(":").map(Number)
  
  return hours * 3600 + minutes * 60 + seconds
}

const totalSelectedPayment = computed(() => {
  if (!paymentDetails.value) return 0

  return Object.values(paymentDetails.value).reduce((total, taskDetail) => {
    return (
      total +
      taskDetail.timeEntries
        .filter(entry => selectedTimeEntries.value.includes(entry.id))
        .reduce((sum, entry) => sum + (parseDurationToSeconds(entry.duration) / 3600) * props.member.billable_rate, 0)
    )
  }, 0)
})

const toggleTaskEntries = taskDetail => {
  const entryIds = taskDetail.timeEntries.map(entry => entry.id)
  const allSelected = entryIds.every(id => selectedTimeEntries.value.includes(id))

  selectedTimeEntries.value = allSelected
    ? selectedTimeEntries.value.filter(id => !entryIds.includes(id))
    : [...new Set([...selectedTimeEntries.value, ...entryIds])]
}

const generateLogMessage = log => {
  const formatIfNeeded = date => {
    // Check if it's an ISO string (if it contains 'T' and 'Z', it's likely raw)
    return typeof date === "string" && date.includes("T") ? formatDate(date) : date
  }

  if (log.action === "update") {
    if (log.old_data?.is_paid === false && log.new_data?.is_paid === true) {
      return `
        <div class="log-message log-paid">
          <strong>💰 Payment Recorded!</strong><br>
          ${log.user.full_name} just marked this time entry as paid.<br>
          <strong>Paid Rate:</strong> ${log.new_data.paid_rate ? `$${log.new_data.paid_rate}` : "N/A"}<br>
          <strong>Amount Paid:</strong> ${log.new_data.amount_paid ? `$${log.new_data.amount_paid.toFixed(2)}` : "N/A"}<br>
          A job well done! 🎉
        </div>
      `
    }

    // Time Tracker Stopped
    if (log.old_data?.end === null && log.new_data?.end) {
      return `
        <div class="log-message log-stopped">
          <strong>⏱️ Time Tracker Stopped!</strong><br>
          ${log.user.full_name} stopped the time tracker at <strong>${formatDate(log.new_data.end)}</strong>.<br>
          <strong>Session Duration:</strong> From <strong>${formatIfNeeded(log.old_data.start)}</strong> to <strong>${formatDate(log.new_data.end)}</strong>.
        </div>
      `
    }

    // General update (excluding 'end' updates from stopped tracking)
    let changes = Object.entries(log.new_data)
      .filter(([key]) => key !== "updated_at" && key !== "end") // Ignore 'end' because we already handled it above
      .map(([key, value]) => {
        let oldValue = formatIfNeeded(log.old_data[key]) || "N/A"
        let newValue = formatIfNeeded(value)

        return `<strong>${key}</strong>: <span class="old-value">${oldValue}</span> → <span class="new-value">${newValue}</span>`
      })
      .join("<br>")

    return `
      <div class="log-message log-manual">
        <strong>🔄 Time Entries Modified Manually</strong><br>
        ${log.user.full_name} updated this time entry.<br>
        ${changes ? changes : "Some details have been updated."}
      </div>
    `
  }

  if (log.action === "delete") {
    return `
      <div class="log-message log-deleted">
        <strong>❌ Entry Deleted!</strong><br>
        ${log.user.full_name} decided to remove this time entry.<br>
        <strong>Start:</strong> ${formatIfNeeded(log.old_data.start)}<br>
        <strong>End:</strong> ${log.old_data.end ? formatDate(log.old_data.end) : "N/A"}<br>
        It's gone forever! 🚀
      </div>
    `
  }

  return `<div class="log-message log-default"><strong>📝 Action:</strong> ${log.action}</div>`
}


watch(() => selectedTimeEntries, () => {
  emit("update:selectedPayment", totalSelectedPayment.value)
  emit("update:selectedEntries", selectedTimeEntries.value)
}, { deep: true, immediate: true })

watch(() => props.dateRange, fetchMemberPaymentDetails, { deep: true, immediate: true })
watch(() => props.member, fetchMemberPaymentDetails, { deep: true, immediate: true })
</script>

<template>
  <div class="member-payment-details">
    <div v-if="paymentDetails">
      <div
        v-for="(taskDetail, key) in paymentDetails"
        :key="key"
        class="task-card"
      >
        <div class="task-header">
          <h4 class="task-title">
            {{ taskDetail.task?.name }}
          </h4>
          <span class="custom-badge custom-badge-add">
            <VIcon>
              tabler-clock-play
            </VIcon>
            <span>{{ taskDetail.trackedTimeDisplay }}</span>
          </span>
        </div>

        <VExpansionPanels
          v-if="taskDetail.task && taskDetail.entries_logs.length"
          v-model="expandedTask"
          variant="accordion"
          class="expansion-panels-width-border"
        >
          <VExpansionPanel :value="taskDetail.task.id">
            <VExpansionPanelTitle>
              <div class="custom-badge">
                <VIcon>
                  tabler-history
                </VIcon>
                <span>Task Logs</span>
              </div>
            </VExpansionPanelTitle>
            <VExpansionPanelText>
              <VTimeline
                side="end"
                density="comfortable"
              >
                <VTimelineItem
                  v-for="log in taskDetail.entries_logs"
                  :key="log.id"
                  class="timeline-item"
                  :dot-color="log.action === 'delete' ? 'error' : 'success'"
                >
                  <div class="log-entry">
                    <div class="log-header">
                      <strong>{{ log.action.toUpperCase() }}</strong>
                      <span class="log-date">{{ formatDate(log.created_at) }}</span>
                    </div>
                    <p
                      class="log-message"
                      v-html="generateLogMessage(log)"
                    />
                  </div>
                </VTimelineItem>
              </VTimeline>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>

        <table class="time-entries-table">
          <thead>
            <tr>
              <th
                v-if="props.isOwner || props.isSuperAdmin"
                class="center"
              >
                ✔
              </th>
              <th>Start</th>
              <th>End</th>
              <th>Duration</th>
              <th>Paid</th>
              <th>Amount</th>
              <th>Rate</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="entry in taskDetail.timeEntries"
              :key="entry.id"
            >
              <td
                v-if="props.isOwner || props.isSuperAdmin"
                class="center"
              >
                <VCheckbox
                  v-model="selectedTimeEntries"
                  :value="entry.id"
                  :disabled="entry.is_paid"
                />
              </td>
              <td>{{ formatDate(entry.start) }}</td>
              <td>{{ formatDate(entry.end) }}</td>
              <td>{{ entry.duration }}</td>
              <td>
                <span :class="entry.is_paid ? 'badge-paid' : 'badge-unpaid'">
                  {{ entry.is_paid ? 'Yes' : 'No' }}
                </span>
              </td>
              <td>{{ entry.amount_paid ? `$${entry.amount_paid}` : '--' }}</td>
              <td>{{ entry.paid_rate ? `$${entry.paid_rate}` : '--' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style>
.member-payment-details {
  background: linear-gradient(to bottom, #ffffff, #f4f6f9);
}

.task-card {
  background: #ffffff;
  padding: 14px;
  margin-bottom: 12px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.task-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.task-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.task-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #333;
}

.badge {
  background: #0056b3;
  color: white;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
}

.time-entries-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 5px;
  font-size: 0.95rem;
}

table th, table td {
  padding: 8px 12px;
  border-bottom: 1px solid #ddd;
  text-align: left;
}

.badge-paid {
  background: #28a745;
  color: white;
  padding: 5px 8px;
  border-radius: 5px;
  font-weight: bold;
}

.badge-unpaid {
  background: #dc3545;
  color: white;
  padding: 5px 8px;
  border-radius: 5px;
  font-weight: bold;
}

.icon-title {
  margin-right: 8px;
  color: #007bff;
}

.timeline-item {
  padding-left: 16px;
}

.log-entry {
  padding: 12px;
  border-radius: 8px;
  font-size: 0.9rem;
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.log-header {
  display: flex;
  justify-content: space-between;
  font-weight: bold;
  color: #333;
}

.log-date {
  font-size: 0.85rem;
  color: #666;
}

.log-message {
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 8px;
  font-size: 0.9rem;
  line-height: 1.4;
  font-weight: 500;
}

.log-paid {
  background-color: #d4edda;
  color: #155724;
  border-left: 5px solid #28a745;
}

.log-stopped {
  background-color: #fff3cd;
  color: #856404;
  border-left: 5px solid #ffc107;
}

.log-manual {
  background-color: #e2e3e5;
  color: #383d41;
  border-left: 5px solid #6c757d;
}

.log-deleted {
  background-color: #f8d7da;
  color: #721c24;
  border-left: 5px solid #dc3545;
}

.log-default {
  background-color: #d1ecf1;
  color: #0c5460;
  border-left: 5px solid #17a2b8;
}
</style>
