<script setup>
import { useToast } from "vue-toastification"
import { defineExpose, ref, watch, computed, nextTick } from "vue"

const props = defineProps({
  kanbanItem: {
    type: Object,
    required: false,
    default: () => ({
      item: {
        id: null,
        name: 'Board Chat'
      }
    }),
  },
  containerId: {
    type: Number,
    required: false,
    default: null
  },
  availableMembers: {
    type: Array,
    required: false,
    default: () => [],
  },
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
  isSuperAdmin: { type: Boolean, required: false, default: false },
  isOwner: { type: Boolean, required: false, default: false },
  isMember: { type: Boolean, required: false, default: false },
  authId: { type: Number, required: false },
  showBackButton: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'update:isDrawerOpen',
  'update:kanbanItem',
  'deleteKanbanItem',
  'refreshKanbanData',
  'editTimer',
  'back-to-general'
])

const messages = ref([])
const selectedMembers = ref([])
const uploadedFiles = ref([])
const localAvailableMembers = ref([])
const message = ref('')
const currentMessageId = ref(null)
const messageToReply = ref(null)
const showLocalBackButton = ref(props.showBackButton)
const chatLogPS = ref()
const previewDialog = ref(false)
const isEditMode = ref(false)
const selectedAttachment = ref(null)
const containerData = ref(null)
const messageActionsMap = ref(new Map())
const isLoading = ref(false)
const messageEditorRef = ref(null)

const toast = useToast()

const localKanbanItem = ref(props.kanbanItem?.item || {
  id: null,
  name: 'Board Chat'
})

const commentableType = computed(() => {
  return props.containerId ? 'App\\Models\\Container' : 'App\\Models\\Task'
})

const commentableId = computed(() => {
  return props.containerId || props.kanbanItem.item.id
})

const itemTitle = computed(() => {
  if (props.containerId) {
    return 'Board Chat'
  }
  return `${props.kanbanItem.item.sequence_id}) ${props.kanbanItem.item.name}`
})

const handleDrawerModelValueUpdate = val => {
  emit('update:isDrawerOpen', val)
}

watch(() => props, () => {
  localAvailableMembers.value = props.availableMembers
}, { deep: true, immediate: true })

const fetchKanbanItem = async () => {
  if (!commentableId.value) return
  
  isLoading.value = true
  
  try {
    const endpoint = props.containerId ? `/container/${props.containerId}/comments` : `/task/${props.kanbanItem.item.id}`
    const res = await $api(endpoint)
    
    if (res) {
      if (props.containerId) {
        containerData.value = res.data
      } else {
        localKanbanItem.value = res.data
      }
      
      if (res.data.comments) {
        messages.value = res.data.comments
        await nextTick()
        scrollToBottom()
        checkCommentsInView()
      }
    }
  } finally {
    setTimeout(() => {
      isLoading.value = false
    }, 300)
  }
}

watch(() => props.kanbanItem, () => {
  if (props.kanbanItem?.item) {
    localKanbanItem.value = JSON.parse(JSON.stringify(props.kanbanItem.item))
  }
}, { deep: true })

watch(() => props.showBackButton, () => {
  showLocalBackButton.value = props.showBackButton
}, { deep:true, immediate: true })

const scrollToBottom = async () => {
  await nextTick()

  const scrollEl = chatLogPS.value.$el || chatLogPS.value

  scrollEl.scrollTop = scrollEl.scrollHeight
}

const handleAddMessage = async () => {
  const res = await $api('/comment', {
    method: 'POST',
    body: {
      content: message.value,
      commentable_id: commentableId.value,
      commentable_type: commentableType.value,
      temporary_uploads: uploadedFiles.value,
      mentioned_users: selectedMembers.value,
      parent_id: messageToReply.value ? messageToReply.value.id : null,
    },
  })

  if (res) {
    messages.value = res.data
    message.value = ''
    selectedMembers.value = []
    uploadedFiles.value = []
    messageToReply.value = null
    currentMessageId.value = null
    isEditMode.value = false

    await nextTick()
    exitEditMode()
    exitReplyMode()
    scrollToBottom()
  }
}

const markCommentAsRead = async commentId => {
  const comment = messages.value.find(msg => msg.id === commentId)
  if (!comment || comment.is_read) return

  comment.markingAsRead = true

  try {
    await $api('/comment/mark-as-read', {
      method: 'POST',
      body: { type: 'comment', id: commentId },
    })

    comment.is_read = true
  } catch (error) {
    toast.error('Failed to mark comment as read.')
  } finally {
    comment.markingAsRead = false
  }
}

const checkCommentsInView = () => {
  messages.value.forEach(msg => {
    if (!msg.is_read) {
      markCommentAsRead(msg.id)
    }
  })
}

const editMessage = async messageToEdit => {
  if (messageToEdit) {
    exitReplyMode()
    message.value = messageToEdit.content
    uploadedFiles.value = messageToEdit.attachments
    selectedMembers.value = messageToEdit.mentioned_users
    currentMessageId.value = messageToEdit.id
    isEditMode.value = true
  }
}

const exitEditMode = () => {
  currentMessageId.value = null
  isEditMode.value = false
  messageToReply.value = null
  message.value = ''
  uploadedFiles.value = []
  selectedMembers.value = []
}

const exitReplyMode = () => {
  messageToReply.value = null
  if (message.value.includes('data-type="mention"')) {
    message.value = ''
  }
}

const submitEditMessage = async messageId => {
  const res = await $api(`/comment/${messageId}`, {
    method: 'PUT',
    body: {
      content: message.value,
      commentable_id: commentableId.value,
      commentable_type: commentableType.value,
      temporary_uploads: uploadedFiles.value,
      mentioned_users: selectedMembers.value,
    },
  })

  if (res) {
    messages.value = res.data

    await nextTick(() => {
      exitEditMode()
      scrollToBottom()
    })
  }
}

const deleteMessage = async messageId => {
  const res = await $api(`/comment/${messageId}`, {
    method: 'DELETE',
  })

  if (res) {
    messages.value = messages.value.filter(msg => msg.id !== messageId)
    toast.success('Comment deleted successfully')
  }
}

const closeNavigationDrawer = () => {
  emit('update:isDrawerOpen', false)
  messages.value = []
  message.value = ''
  uploadedFiles.value = []
  selectedMembers.value = []
  exitEditMode()
  exitReplyMode()
  messageToReply.value = null
  
  if (messageEditorRef.value && typeof messageEditorRef.value.resetEditor === 'function') {
    messageEditorRef.value.resetEditor()
  }

  emit('refreshKanbanData')
}

const replyMessage = msg => {
  exitEditMode()

  messageToReply.value = msg
  message.value = `<span class="mention" data-type="mention" data-id="${msg.createdBy.id}" data-label="${msg.createdBy.full_name}" contenteditable="false">@${msg.createdBy.full_name}</span> `
}

const isImage = attachment => /\.(jpg|jpeg|png|gif)$/i.test(attachment.name)
const isPDF = attachment => attachment.name.endsWith('.pdf')
const isWord = attachment => attachment.name.endsWith('.docx')
const isExcel = attachment => attachment.name.endsWith('.xlsx')

const openPreview = attachment => {
  selectedAttachment.value = attachment
  previewDialog.value = true
}

const closePreview = () => {
  selectedAttachment.value = null
  previewDialog.value = false
}

const getAttachmentIcon = (attachment) => {
  if (isImage(attachment)) return 'tabler-photo'
  if (isPDF(attachment)) return 'tabler-file-type-pdf'
  if (isWord(attachment)) return 'tabler-file-word'
  if (isExcel(attachment)) return 'tabler-file-excel'
  return 'tabler-file-check'
}

const getAttachmentColor = (attachment) => {
  if (isImage(attachment)) return '#00A5E0'
  if (isPDF(attachment)) return '#E74C3C'
  if (isWord(attachment)) return '#2B579A'
  if (isExcel(attachment)) return '#217346'
  return '#636E72'
}

const getMessageMenuModel = (messageId) => ({
  get: () => messageActionsMap.value.get(messageId) || false,
  set: (value) => messageActionsMap.value.set(messageId, value)
})

watch(() => props.isDrawerOpen, async (newVal) => {
  if (newVal) {
    messageToReply.value = null
    message.value = ''
    uploadedFiles.value = []
    selectedMembers.value = []
    exitEditMode()
    exitReplyMode()
    
    if (messageEditorRef.value && typeof messageEditorRef.value.resetEditor === 'function') {
      messageEditorRef.value.resetEditor()
    }
    
    await fetchKanbanItem()
  } else {
    messageToReply.value = null
    message.value = ''
    uploadedFiles.value = []
    selectedMembers.value = []
    
    if (messageEditorRef.value && typeof messageEditorRef.value.resetEditor === 'function') {
      messageEditorRef.value.resetEditor()
    }
  }
})

const handleBackClick = () => {
  emit('update:isDrawerOpen', false)
  emit('back-to-general')
}
</script>

<template>
  <VNavigationDrawer
    temporary
    persistent
    :width="700"
    location="end"
    class="scrollable-content"
    :model-value="props.isDrawerOpen"
    @update:model-value="handleDrawerModelValueUpdate"
  >
    <AppDrawerHeaderSection
      :title="itemTitle"
      :show-back-button="showLocalBackButton"
      @cancel="closeNavigationDrawer"
      @back="handleBackClick"
    />

    <VDivider />

    <div
      ref="chatLogPS"
      class="flex-grow-1 messenger-content"
      style="height: calc(100% - 64px); overflow: auto;"
    >
      <div 
        v-if="isLoading" 
        class="loading-container pa-6"
      >
        <div class="loading-content">
          <div v-for="n in 4" :key="n" class="loading-item">
            <div class="loading-header">
              <div class="loading-avatar shimmer" />
              <div class="loading-meta">
                <div class="loading-name shimmer" />
                <div class="loading-time shimmer" />
              </div>
            </div>
            <div class="loading-body">
              <div class="loading-text shimmer" />
              <div class="loading-text shimmer" style="width: 85%" />
            </div>
          </div>
        </div>
      </div>

      <div 
        v-if="!isLoading"
        class="messages-container"
      >
        <template v-if="messages.length">
          <div class="chat-log pa-6">
            <div
              v-for="(msg, index) in messages"
              :id="`comment-${msg.id}`"
              :key="msg.createdBy.id + String(index)"
              class="chat-group d-flex align-start"
              :class="[{
                'flex-row-reverse': msg.createdBy.id === authId,
              }]"
            >
              <div
                class="chat-avatar"
                :class="msg.createdBy.id === authId ? 'ms-4' : 'me-4'"
              >
                <VAvatar
                  size="32"
                  variant="flat"
                >
                  <template v-if="msg.createdBy.avatar">
                    <VImg
                      :src="msg.createdBy.avatar"
                      alt="Avatar"
                    />
                  </template>
                  <template v-else>
                    <span>{{ msg.createdBy.avatar_or_initials }}</span>
                  </template>
                </VAvatar>
              </div>
              <div class="chat-body d-inline-flex flex-column w-100" :class="msg.createdBy.id === authId ? 'align-end' : 'align-start'">
                <div
                  class="chat-content"
                  :class="msg.createdBy.id !== authId ? 'chat-left' : 'chat-right'"
                >
                  <div
                    v-if="msg.attachments?.length"
                    class="attachments-section"
                  >
                    <div class="attachments-wrapper">
                      <div
                        v-for="attachment in msg.attachments"
                        :key="attachment.id"
                        class="attachment-item"
                        @click.prevent="openPreview(attachment)"
                      >
                        <div class="attachment-info">
                          <VIcon
                            :icon="getAttachmentIcon(attachment)"
                            :color="getAttachmentColor(attachment)"
                            size="16"
                          />
                          <span class="attachment-name">{{ attachment.name }}</span>
                          <span class="attachment-size">{{ attachment.size }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="message-content-container">
                    <div v-if="msg.createdBy.id !== authId" class="message-actions-container">
                      <div class="message-actions">
                        <VMenu
                          :model-value="messageActionsMap.get(msg.id)"
                          @update:model-value="value => messageActionsMap.set(msg.id, value)"
                          :close-on-content-click="true"
                          location="bottom"
                          :offset="[0, 5]"
                          :open-delay="0"
                          :close-delay="0"
                        >
                          <template #activator="{ props }">
                            <div
                              v-bind="props"
                              class="custom-action-btn"
                            >
                              <VIcon size="12" icon="tabler-dots-vertical" />
                            </div>
                          </template>
                          
                          <VList class="dropdown-menu pa-2" density="compact">
                            <VListItem
                              class="menu-item"
                              @click="replyMessage(msg)"
                            >
                              <template #prepend>
                                <VIcon
                                  size="16"
                                  color="primary"
                                  icon="tabler-message-reply"
                                />
                              </template>
                              <VListItemTitle>Reply</VListItemTitle>
                            </VListItem>

                            <VListItem
                              v-if="msg.createdBy.id === authId"
                              class="menu-item"
                              @click="editMessage(msg)"
                            >
                              <template #prepend>
                                <VIcon
                                  size="16"
                                  color="warning"
                                  icon="tabler-edit"
                                />
                              </template>
                              <VListItemTitle>Edit</VListItemTitle>
                            </VListItem>

                            <VListItem
                              v-if="msg.createdBy.id === authId"
                              class="menu-item"
                              color="error"
                              @click="deleteMessage(msg.id)"
                            >
                              <template #prepend>
                                <VIcon
                                  size="16"
                                  icon="tabler-trash"
                                />
                              </template>
                              <VListItemTitle>Delete</VListItemTitle>
                            </VListItem>
                          </VList>
                        </VMenu>
                      </div>
                    </div>
                    
                    <div class="message-main-content">
                      <div class="chat-parent-message-preview" v-if="msg.parent">
                        <div
                          class="chat-parent-message-text me reply tiptap"
                          v-html="msg.parent.content"
                        />
                      </div>

                      <p
                        class="mb-0 text-message-content tiptap"
                        v-html="msg.content"
                      />
                    </div>
                    
                    <div v-if="msg.createdBy.id === authId" class="message-actions-container">
                      <div class="message-actions">
                        <VMenu
                          :model-value="messageActionsMap.get(msg.id)"
                          @update:model-value="value => messageActionsMap.set(msg.id, value)"
                          :close-on-content-click="true"
                          location="bottom"
                          :offset="[0, 5]"
                          :open-delay="0"
                          :close-delay="0"
                        >
                          <template #activator="{ props }">
                            <div
                              v-bind="props"
                              class="custom-action-btn"
                            >
                              <VIcon size="12" icon="tabler-dots-vertical" />
                            </div>
                          </template>
                          
                          <VList class="dropdown-menu pa-2" density="compact">
                            <VListItem
                              class="menu-item"
                              @click="replyMessage(msg)"
                            >
                              <template #prepend>
                                <VIcon
                                  size="16"
                                  color="primary"
                                  icon="tabler-message-reply"
                                />
                              </template>
                              <VListItemTitle>Reply</VListItemTitle>
                            </VListItem>

                            <VListItem
                              v-if="msg.createdBy.id === authId"
                              class="menu-item"
                              @click="editMessage(msg)"
                            >
                              <template #prepend>
                                <VIcon
                                  size="16"
                                  color="warning"
                                  icon="tabler-edit"
                                />
                              </template>
                              <VListItemTitle>Edit</VListItemTitle>
                            </VListItem>

                            <VListItem
                              v-if="msg.createdBy.id === authId"
                              class="menu-item"
                              color="error"
                              @click="deleteMessage(msg.id)"
                            >
                              <template #prepend>
                                <VIcon
                                  size="16"
                                  icon="tabler-trash"
                                />
                              </template>
                              <VListItemTitle>Delete</VListItemTitle>
                            </VListItem>
                          </VList>
                        </VMenu>
                      </div>
                    </div>
                  </div>
                </div>

                <div :class="[ msg.createdBy.id === authId ? 'text-right' : 'text-left align-self-start' ]">
                  <VIcon
                    v-if="msg.createdBy.id !== authId"
                    id="comment-check"
                    size="14"
                    :class="{'check-animation': msg.markingAsRead, 'opacity-0': !msg.is_read}"
                    color="success"
                  >
                    tabler-check
                  </VIcon>
                  <span class="ms-1">{{ msg.created_at }}</span>
                </div>
              </div>
            </div>
          </div>
        </template>
        <template v-else>
          <div
            class="d-flex flex-column align-center justify-center text-center py-10"
            style="height: 100%;"
          >
            <VIcon
              size="48"
              color="grey"
            >
              tabler-mood-smile-beam
            </VIcon>
            <p class="text-body-1 text-disabled mt-3">
              No messages yet. <br>
              <strong>Be the first to share your thoughts!</strong> <br>
              Type "@" to mention team members, add emojis, or upload files
            </p>
          </div>
        </template>
      </div>
    </div>

    <div class="chat-log-message-form mb-5">
      <MessageEditor
        ref="messageEditorRef"
        v-model="message"
        v-model:pre-uploaded-files="uploadedFiles"
        v-model:is-edit-mode="isEditMode"
        :available-members="localAvailableMembers"
        :pre-selected-members="selectedMembers"
        :message-to-reply="messageToReply"
        @update:selected-members="selectedMembers = $event"
        @update:uploaded-files="uploadedFiles = $event"
        @send="handleAddMessage"
        @edit-message="submitEditMessage(currentMessageId)"
        @exit-edit-mode="exitEditMode"
        @exit-reply-mode="exitReplyMode"
      />
    </div>

    <VDialog
      v-if="previewDialog"
      v-model="previewDialog"
      max-width="800"
    >
      <VCard>
        <VCardTitle
          v-if="selectedAttachment"
          class="d-flex justify-space-between align-items-center"
        >
          <span class="font-weight-bold">Preview Attachment</span>
          <a
            :href="selectedAttachment.url"
            target="_blank"
            class="custom-badge text-primary"
          >Download File</a>
        </VCardTitle>
        <VCardText class="text-center">
          <template v-if="selectedAttachment">
            <VImg
              v-if="isImage(selectedAttachment)"
              :src="selectedAttachment.url"
              max-height="500"
              contain
              class="rounded-lg mx-auto"
            />

            <iframe
              v-else-if="isPDF(selectedAttachment)"
              :src="selectedAttachment.url"
              width="100%"
              height="500px"
            />

            <iframe
              v-else-if="isWord(selectedAttachment) || isExcel(selectedAttachment)"
              :src="`https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(selectedAttachment.url)}`"
              width="100%"
              height="500px"
            />

            <p
              v-else
              class="text-center"
            >
              <VIcon
                v-if="isWord(selectedAttachment)"
                size="48"
                style="color: #2B579A;"
              >
                tabler-file-word
              </VIcon>
              <VIcon
                v-else-if="isExcel(selectedAttachment)"
                size="48"
                style="color: #217346;"
              >
                tabler-file-excel
              </VIcon>
              <br>
              <a
                :href="selectedAttachment.url"
                target="_blank"
                class="custom-badge text-primary"
              >Download File</a>
            </p>
          </template>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn
            color="primary"
            @click="closePreview"
          >
            Close
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VNavigationDrawer>
</template>

<style lang="scss" scoped>
$github-colors: (
  text-primary: #24292f,
  text-secondary: #57606a,
  border: #d0d7de,
  bg-primary: #ffffff,
  bg-secondary: #f6f8fa,
  accent: #0969da,
  hover: #f3f4f6,
  shadow: rgba(31, 35, 40, 0.07),
  reply-border: #ffd33d,
  reply-bg: rgba(255, 211, 61, 0.1)
);

$spacing: (
  xs: 4px,
  sm: 8px,
  md: 12px,
  lg: 16px,
  xl: 20px
);

// Mixins
@mixin github-card {
  background: map-get($github-colors, bg-primary);
  border: 1px solid map-get($github-colors, border);
  border-radius: 6px;
  box-shadow: 0 1px 0 rgba(31, 35, 40, 0.04);
}

.chat-log {
  padding: map-get($spacing, lg);
  display: flex;
  flex-direction: column;
  gap: map-get($spacing, lg);
}

.chat-group {
  display: flex;
  gap: map-get($spacing, md);
  align-items: flex-start;
  position: relative;
  margin-bottom: 24px;
  
  &:last-child {
    margin-bottom: 0;
  }

  &.flex-row-reverse {
    .chat-content {
      background: #f0f6ff;
    }
    
    .chat-avatar {
      margin-left: map-get($spacing, sm);
    }
    
    .chat-timestamp {
      right: 48px;
      text-align: right;
    }
  }
  
  &:not(.flex-row-reverse) {
    .chat-avatar {
      margin-right: map-get($spacing, sm);
    }
    
    .chat-timestamp {
      left: 48px;
      text-align: left;
    }
  }
}

.chat-avatar {
  position: relative;
  
  .v-avatar {
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(31, 35, 40, 0.1);
  }
}

.chat-timestamp {
  position: absolute;
  bottom: -18px;
  font-size: 11px;
  color: map-get($github-colors, text-secondary);
  opacity: 0.8;
  white-space: nowrap;
}

.chat-body {
  flex: 1;
  max-width: 85%;
}

.chat-content {
  @include github-card;
  position: relative;
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(31, 35, 40, 0.08);
  transition: box-shadow 0.2s ease;
  padding: 0;
  overflow: hidden;
  
  .chat-group.flex-row-reverse & {
    background: #f0f6ff;
    border-top-right-radius: 4px;
  }
  
  .chat-group:not(.flex-row-reverse) & {
    background: map-get($github-colors, bg-primary);
    border-top-left-radius: 4px;
  }
  
  &:hover {
    box-shadow: 0 2px 6px rgba(31, 35, 40, 0.12);
    
    .message-actions {
      opacity: 1;
    }
  }
  
  :deep(.tiptap-link) {
    color: map-get($github-colors, accent);
    text-decoration: underline;
    cursor: pointer;
    
    &:hover {
      text-decoration: none;
    }
  }
}

.chat-parent-message-preview {
  margin-bottom: map-get($spacing, sm);
  border-left: 3px solid map-get($github-colors, reply-border);
  background: map-get($github-colors, reply-bg);
  padding: map-get($spacing, xs) map-get($spacing, sm);
  border-radius: 0 3px 3px 0;
  font-size: 13px;
  color: map-get($github-colors, text-secondary);
  position: relative;
  max-height: 60px;
  overflow: hidden;
  
  &::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(to bottom, transparent, map-get($github-colors, reply-bg));
    pointer-events: none;
    opacity: 0.8;
  }
  
  .chat-parent-message-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.text-message-content {
  color: map-get($github-colors, text-primary);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
  word-break: break-word;
  
  :deep(p) {
    margin-bottom: 8px;
    
    &:last-child {
      margin-bottom: 0;
    }
  }
  
  :deep(strong) {
    font-weight: 600;
  }
  
  :deep(em) {
    font-style: italic;
    color: rgba(map-get($github-colors, text-primary), 0.9);
  }
  
  :deep(code) {
    background: rgba(map-get($github-colors, text-secondary), 0.1);
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 85%;
  }
  
  :deep(pre) {
    background: map-get($github-colors, bg-secondary);
    padding: 12px;
    border-radius: 6px;
    overflow-x: auto;
    margin: 8px 0;
    
    code {
      background: transparent;
      padding: 0;
    }
  }
  
  :deep(.tiptap-link) {
    color: map-get($github-colors, accent);
    text-decoration: underline;
    cursor: pointer;
    
    &:hover {
      text-decoration: none;
    }
  }
}

.message-content-container {
  display: flex;
  width: 100%;
  
  &.flex-row-reverse {
    flex-direction: row-reverse;
  }
  
  .message-main-content {
    flex: 1;
    padding: map-get($spacing, md);
    min-width: 0;
  }
  
  .message-actions-container {
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    width: 28px;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
    
    &:hover {
      background-color: #f6f8fa;
      
      .custom-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        width: 100%;
        opacity: 1;
        background-color: #f3f4f6;
      }
    }
    
    .chat-right & {
      border-left: 1px solid #d0d7de;
      border-right: none;
    }
    
    .chat-left & {
      border-right: 1px solid #d0d7de;
      border-left: none;
    }
  }
}

.message-actions {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  
  .custom-action-btn {
    opacity: 0.85;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 6px;
    border-radius: 3px;
    background-color: #f6f8fa;
    
    @media (max-width: 768px) {
      opacity: 0.9;
    }
    
    &:hover {
      opacity: 1;
      background-color: #e9ebef;
    }
    
    .v-icon {
      font-size: 14px;
      color: #24292f;
    }
  }
}

.chat-left {
  align-self: flex-start;
}

.chat-right {
  align-self: flex-end;
}

.attachments-section {
  border-bottom: 1px solid rgba(map-get($github-colors, border), 0.5);
  padding: map-get($spacing, sm) 0;
  margin-bottom: map-get($spacing, sm);
}

.attachments-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: map-get($spacing, sm);
  padding-bottom: map-get($spacing, xs);
}

.attachment-item {
  display: flex;
  align-items: center;
  background: map-get($github-colors, bg-secondary);
  border: 1px solid map-get($github-colors, border);
  border-radius: 8px;
  padding: map-get($spacing, sm);
  min-width: 180px;
  max-width: 180px;
  cursor: pointer;
  transition: all 0.2s ease;
  
  &:hover {
    background: map-get($github-colors, hover);
    transform: translateY(-2px);
    box-shadow: 0 3px 6px rgba(31, 35, 40, 0.1);
  }

  .attachment-info {
    display: flex;
    align-items: center;
    gap: map-get($spacing, sm);
    flex: 1;
    min-width: 0;
    
    .attachment-name {
      font-size: 12px;
      font-weight: 500;
      color: map-get($github-colors, text-primary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      flex: 1;
    }
    
    .attachment-size {
      font-size: 11px;
      color: map-get($github-colors, text-secondary);
      white-space: nowrap;
    }
  }
}

// Empty state styling
.no-activities {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: map-get($github-colors, text-secondary);
  text-align: center;
  padding: map-get($spacing, xl);
  
  .v-icon {
    margin-bottom: map-get($spacing, md);
    opacity: 0.7;
  }
}

// Preview dialog styling
.v-dialog {
  .v-card-title {
    font-size: 16px;
    font-weight: 600;
    padding: map-get($spacing, md) map-get($spacing, lg);
    border-bottom: 1px solid map-get($github-colors, border);
  }
  
  .v-card-text {
    padding: map-get($spacing, lg);
  }
  
  .v-card-actions {
    padding: map-get($spacing, sm) map-get($spacing, lg);
    border-top: 1px solid map-get($github-colors, border);
  }
}

// Custom scrollbar
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: map-get($github-colors, bg-secondary);
}

::-webkit-scrollbar-thumb {
  background: darken(map-get($github-colors, border), 10%);
  border-radius: 4px;
  
  &:hover {
    background: darken(map-get($github-colors, border), 15%);
  }
}

// Timestamp and read status
.text-disabled {
  color: map-get($github-colors, text-secondary);
  font-size: 12px;
}

#comment-check {
  transition: opacity 0.3s ease;
  
  &.check-animation {
    animation: checkmark 0.3s ease-in-out;
  }
}

@keyframes checkmark {
  0% { transform: scale(0.5); opacity: 0; }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); opacity: 1; }
}

.dropdown-menu {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.08);
  min-width: 160px;

  .menu-item {
    border-radius: 4px;
    margin-bottom: 2px;
    min-height: 36px;
    padding: 0 8px;

    &:hover {
      background: rgba(var(--v-theme-surface-variant), 0.06);
    }

    .v-list-item-title {
      font-size: 0.875rem;
      font-weight: 400;
    }

    &.v-list-item--density-compact {
      min-height: 32px;
    }

    .v-icon {
      margin-right: 8px;
    }
  }
}

.action-btn {
  opacity: 0.7;
  transition: all 0.2s ease;

  &:hover {
    opacity: 1;
    background: rgba(var(--v-theme-surface-variant), 0.06);
  }
}

.v-navigation-drawer {
  :deep(.v-btn--icon.v-btn--density-default) {
    width: 34px;
    height: 34px;
    
    .v-icon {
      font-size: 20px;
    }
  }
}

.messenger-content {
  position: relative;
}

.loading-container {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgb(var(--v-theme-surface));
  z-index: 1;
  padding: map-get($spacing, lg);
}

.loading-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 720px;
  margin: 0 auto;
}

.loading-item {
  padding: 16px;
  border: 1px solid rgba(map-get($github-colors, border), 0.7);
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 1px 3px rgba(31, 35, 40, 0.04);
}

.loading-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.loading-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
}

.loading-meta {
  flex: 1;
}

.loading-name {
  height: 14px;
  width: 120px;
  margin-bottom: 4px;
  border-radius: 3px;
}

.loading-time {
  height: 12px;
  width: 80px;
  border-radius: 3px;
}

.loading-body {
  padding-left: 40px;
}

.loading-text {
  height: 12px;
  width: 100%;
  margin-bottom: 8px;
  border-radius: 3px;

  &:last-child {
    margin-bottom: 0;
  }
}

.shimmer {
  background: #f6f8fa;
  background-image: linear-gradient(
    90deg,
    #f6f8fa 0%,
    #f6f8fa 40%,
    #f0f2f4 50%,
    #f6f8fa 60%,
    #f6f8fa 100%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite linear;
  border-radius: 4px;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.messages-container {
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

:deep(.tiptap) {
  .tiptap-link {
    color: map-get($github-colors, accent);
    text-decoration: underline;
    cursor: pointer;
    
    &:hover {
      text-decoration: none;
    }
  }
}
</style>

