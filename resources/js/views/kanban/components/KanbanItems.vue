<script setup>
import {
  animations,
  handleEnd,
  performTransfer, remapNodes,
} from '@formkit/drag-and-drop'
import { dragAndDrop } from '@formkit/drag-and-drop/vue'
import { VForm } from 'vuetify/components/VForm'
import KanbanCard from './KanbanCard.vue'
import { ref, watch } from "vue"

const props = defineProps({
  kanbanIds: {
    type: Array,
    required: true,
  },
  colors: {
    type: Array,
    required: false,
  },
  groupName: {
    type: String,
    required: true,
  },
  boardName: {
    type: String,
    required: true,
  },
  boardColor: {
    type: String,
    required: false,
  },
  boardId: {
    type: Number,
    required: true,
  },
  kanbanData: {
    type: null,
    required: true,
  },
  availableMembers: {
    type: Array,
    required: false,
    default: () => [],
  },
  activeUsers: {
    type: Array,
    required: false,
    default: () => [],
  },
  isSuperAdmin: { type: Boolean, required: false, default: false },
  isMobile: { type: Boolean, required: false, default: false },
  hasActiveTimer: { type: Boolean, required: false, default: false },
  isOwner: { type: Boolean, required: false, default: false },
  isMember: { type: Boolean, required: false, default: false },
  auth: {
    type: Object,
    required: false,
    default: () => ({}),
  },
})

const emit = defineEmits([
  'renameBoard',
  'deleteBoard',
  'addNewItem',
  'editItem',
  'updateItemsState',
  'deleteItem',
  'toggleTimer',
  'editTimer',
  'refreshKanbanData',
])

const refKanbanBoard = ref()
const localBoardName = ref(props.boardName)
const localBoardColor = ref(props.boardColor)
const hasLocalActiveTimer = ref(false)
const localAvailableMembers = ref(props.availableMembers)
const localActiveUsers = ref(props.activeUsers)
const localAuthDetails = ref(props.auth)
const localIds = ref(props.kanbanIds)
const isAddNewFormVisible = ref(false)
const isBoardNameEditing = ref(false)
const refForm = ref()
const newTaskTitle = ref('')
const refKanbanBoardTitle = ref()

const itemsContainer = ref()

const scrollToBottomInChatLog = () => {
  const scrollEl = itemsContainer.value.$el || itemsContainer.value

  scrollEl.scrollTop = scrollEl.scrollHeight
}

const renameBoard = () => {
  refKanbanBoardTitle.value?.validate().then(valid => {
    if (valid.valid) {
      emit('renameBoard', {
        oldName: props.boardName,
        name: localBoardName.value,
        oldColor: props.boardColor,
        color: localBoardColor.value,
        boardId: props.boardId,
      })
      isBoardNameEditing.value = false
    }
  })
}

const addNewItem = () => {
  refForm.value?.validate().then(valid => {
    if (valid.valid) {
      const item = {
        name: newTaskTitle.value,
        boardId: props.boardId,
      }

      emit('addNewItem', item)

      newTaskTitle.value = ''
      isAddNewFormVisible.value = false
    }
  })
}

// 👉 watch kanbanIds its is useful when you add new task
watch(() => props, () => {
  localIds.value = props.kanbanIds
}, {
  immediate: true,
  deep: true,
})

watch(() => props.availableMembers, (newValue, oldValue) => {
  if (newValue !== oldValue) {
    localAvailableMembers.value = [...props.availableMembers]
  }
}, { deep: true, immediate: true })

watch(() => props.activeUsers, (newValue, oldValue) => {
  if (newValue !== oldValue) {
    localActiveUsers.value = [...props.activeUsers]
  }
}, { deep: true, immediate: true })

watch(() => props.auth, (newValue, oldValue) => {
  if (newValue !== oldValue) {
    localAuthDetails.value = props.auth
  }
}, { deep: true, immediate: true })

dragAndDrop({
  parent: refKanbanBoard,
  values: localIds,
  group: props.groupName,
  draggable: child => child.classList.contains('kanban-card'),
  dragHandle: '.card-handler',
  disabled: props.isMobile,
  plugins: [animations()],
  performTransfer: (state, data) => {
    performTransfer(state, data)

    emit('updateItemsState', {
      boardId: props.boardId,
      ids: localIds.value,
    })
  },
  handleEnd: data => {
    handleEnd(data)

    emit('updateItemsState', {
      boardId: props.boardId,
      ids: localIds.value,
    })
  },
})

const resolveItemUsingId = id => props.kanbanData.tasks.find(item => item.id === id)

const deleteItem = item => {
  emit('deleteItem', item)
}

const toggleTimer = (member, taskId) => {
  emit('toggleTimer', member, taskId)
}

const editTimerFn = (member, taskId, taskName) => {
  emit('editTimer', member, taskId, taskName)
}

// 👉 reset add new item form when esc or close
const hideAddNewForm = () => {
  isAddNewFormVisible.value = false
  refForm.value?.reset()
}

// close add new item form when you loose focus from the form
onClickOutside(refForm, hideAddNewForm)

// close board name form when you loose focus from the form
onClickOutside(refKanbanBoardTitle, () => {
  isBoardNameEditing.value = false
})

// 👉 reset board rename form when esc or close
const hideResetBoardNameForm = () => {
  isBoardNameEditing.value = false
  localBoardName.value = props.boardName
  localBoardColor.value = props.boardColor
}

const handleEnterKeydown = event => {
  if (event.key === 'Enter' && !event.shiftKey)
    addNewItem()
}

const refreshData = () => {
  emit('refreshKanbanData')
}
</script>

<template>
  <div class="kanban-board d-flex flex-column h-100">
    <div
      class="kanban-board-header"
      :style="{
        backgroundColor: props.boardColor && !isBoardNameEditing
          ? `${props.boardColor}93`
          : '#fff'
      }"
    >
      <VForm
        v-if="isBoardNameEditing"
        ref="refKanbanBoardTitle"
        @submit.prevent="renameBoard"
      >
        <div class="mb-2">
          <VTextField
            v-model="localBoardName"
            autofocus
            variant="underlined"
            :rules="[requiredValidator]"
            hide-details
            class="border-0"
            @keydown.esc="hideResetBoardNameForm"
          >
            <template #append-inner>
              <VIcon
                size="20"
                color="success"
                icon="tabler-check"
                class="me-1"
                @click="renameBoard"
              />

              <VIcon
                size="20"
                color="error"
                icon="tabler-x"
                @click="hideResetBoardNameForm"
              />
            </template>
          </VTextField>
        </div>
        <div class="d-flex">
          <VRadioGroup
            v-model="localBoardColor"
            class="d-flex gap-3"
            inline
          >
            <VRadio
              v-for="color in props.colors"
              :key="color.name"
              :value="color.value"
              :color="color.value"
              class="custom-radio-checkbox"
              :class="[boardColor === color.value ? 'selected' : '']"
              :style="{ color: color.value }"
              ripple
            />
          </VRadioGroup>
        </div>
      </VForm>

      <div
        v-else
        class="d-flex align-center justify-space-between"
      >
        <div class="d-flex align-center">
          <VIcon
            class="drag-handler"
            size="20"
            icon="tabler-grip-vertical"
          />
          <VChip
            class="text-md font-weight-medium text-truncate text-center"
            :color="props.boardColor"
            size="small"
            variant="elevated"
            pill
            prepend-icon="tabler-layout-kanban"
          >
            {{ boardName }}
          </VChip>
          <VChip
            v-if="localIds.length"
            class="text-md font-weight-medium text-truncate text-center ml-1"
            :color="props.boardColor"
            rounded
            size="small"
            variant="elevated"
          >
            {{ localIds.length }}
          </VChip>
        </div>
        <div class="d-flex align-center gap-2">
          <VIcon
            v-tooltip="'Edit Column'"
            class="text-high-emphasis"
            size="20"
            icon="tabler-edit"
            @click="isBoardNameEditing = true"
          />
          <VIcon
            v-tooltip="'Add New Task'"
            class="text-high-emphasis"
            size="20"
            icon="tabler-square-rounded-plus"
            @click="isAddNewFormVisible = !isAddNewFormVisible"
          />
        </div>
      </div>

      <div class="add-new-form mt-2" v-if="isAddNewFormVisible">
        <VForm
          ref="refForm"
          class="mt-4"
          validate-on="submit"
          @submit.prevent="addNewItem"
        >
          <div class="mb-4">
            <VTextarea
              v-model="newTaskTitle"
              :rules="[requiredValidator]"
              placeholder="Add Content"
              autofocus
              rows="2"
              @keydown.enter="handleEnterKeydown"
              @keydown.esc="hideAddNewForm"
            />
          </div>
          <div class="d-flex gap-4 flex-wrap">
            <VBtn
              size="small"
              type="submit"
            >
              Add
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              color="secondary"
              @click="hideAddNewForm"
            >
              Cancel
            </VBtn>
          </div>
        </VForm>
      </div>
    </div>
    <div
      ref="itemsContainer"
      style="height: 100%; overflow-y: auto;"
      class="flex-grow-1"
    >
      <div
        v-if="localIds"
        ref="refKanbanBoard"
        class="kanban-board-drop-zone d-flex flex-column gap-2"
        :style="{
          backgroundColor: props.boardColor
            ? `${props.boardColor}33`
            : '#f1f1f3'
        }"
        :class="localIds.length ? 'mb-4' : ''"
      >
        <template
          v-for="id in localIds.filter(id => resolveItemUsingId(id))"
          :key="id"
        >
          <KanbanCard
            :item="resolveItemUsingId(id)"
            :board-id="props.boardId"
            :board-name="props.boardName"
            :is-super-admin="props.isSuperAdmin"
            :has-active-timer="props.hasActiveTimer"
            :is-owner="props.isOwner"
            :is-member="props.isMember"
            :auth="localAuthDetails"
            :available-members="localAvailableMembers"
            :active-users="localActiveUsers"
            @delete-kanban-item="deleteItem"
            @toggle-timer="toggleTimer"
            @edit-timer="editTimerFn"
            @refresh-kanban-data="refreshData"
            @edit-kanban-item="emit('editItem', { item: resolveItemUsingId(id), boardId: props.boardId, boardName: props.boardName })"
          />
        </template>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.kanban-board-header {
  .drag-handler {
    cursor: grab;
    opacity: 0.6;
    transition: opacity 0.2s ease;

    &:active {
      cursor: grabbing;
    }

    &:hover {
      opacity: 1;
    }
  }
}

.dragging {
  transform: scale(1.05);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border-radius: 8px;
}

.drop-zone:hover,
.drop-zone.over {
  background-color: rgba(0, 128, 255, 0.15);
  border: 2px dashed rgba(0, 128, 255, 0.4);
  transition: background-color 0.3s ease, border 0.3s ease;
}

.kanban-board .flex-grow-1 {
  scrollbar-width: thin;
  scrollbar-color: rgba(191, 191, 191, 0.5) rgba(240, 240, 240, 0.5);
}

.kanban-board .flex-grow-1::-webkit-scrollbar {
  width: 8px;
  border-radius: 10px;
}

.kanban-board .flex-grow-1::-webkit-scrollbar-track {
  background: rgba(191, 191, 191, 0.5);
  border-radius: 10px;
}

.kanban-board .flex-grow-1::-webkit-scrollbar-thumb {
  background: rgba(191, 191, 191, 0.5);
  border-radius: 10px;
  transition: background 0.3s ease;
}

.kanban-board .flex-grow-1::-webkit-scrollbar-thumb:hover {
  background: rgba(191, 191, 191, 0.5);
}

</style>
