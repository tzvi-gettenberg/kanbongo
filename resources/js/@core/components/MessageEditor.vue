<script setup>
import { Placeholder } from '@tiptap/extension-placeholder'
import { TextAlign } from '@tiptap/extension-text-align'
import { Underline } from '@tiptap/extension-underline'
import { StarterKit } from '@tiptap/starter-kit'
import { ImageResize } from 'tiptap-extension-resize-image'
import { Document } from '@tiptap/extension-document'
import { Mention } from '@tiptap/extension-mention'
import { Text } from '@tiptap/extension-text'
import { TaskItem } from '@tiptap/extension-task-item'
import { TaskList } from '@tiptap/extension-task-list'
import { Emoji, gitHubEmojis } from '@tiptap-pro/extension-emoji'
import {
  EditorContent,
  useEditor,
} from '@tiptap/vue-3'
import { FileHandler } from '@tiptap-pro/extension-file-handler'
import { useToast } from 'vue-toastification'
import suggestion from "@/components/messages/suggestion"
import suggestionEmojis from "@/components/messages/suggestionEmojis"
import { watch } from "vue"

const props = defineProps({
  modelValue: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    required: false,
  },
  isEditMode: {
    type: Boolean,
    required: false,
    default: false,
  },
  availableMembers: {
    type: Array,
    required: false,
    default: () => [],
  },
  preSelectedMembers: {
    type: Array,
    required: false,
    default: () => [],
  },
  preUploadedFiles: {
    type: Array,
    required: false,
    default: () => [],
  },
  messageToReply: {
    type: Object,
    required: false,
    default: () => null,
  },
})

const emit = defineEmits(['update:modelValue', 'send', 'update:selectedMembers', 'update:uploadedFiles', 'exitEditMode', 'editMessage', 'exitReplyMode'])
const toast = useToast()
const editorRef = ref()
const uploadedFiles = ref([])
const uploadingFiles = ref([])
const selectedMembers = ref([])
const isEditing = ref(props.isEditMode)
const localAvailableMembers = ref(props.availableMembers)
const localMessageToReply = ref(props.messageToReply)

watch(() => props, () => {
  localAvailableMembers.value = props.availableMembers

  if (props.preSelectedMembers.length) {
    selectedMembers.value = props.preSelectedMembers
  }

  if (props.preUploadedFiles.length) {
    uploadedFiles.value = props.preUploadedFiles
  }

  if (props.isEditMode) {
    isEditing.value = true
  }

  if (props.messageToReply) {
    localMessageToReply.value = props.messageToReply
  }
}, { deep: true, immediate: true })

const remapMentions = content => {
  if (!content) return content

  const parser = new DOMParser()
  const doc = parser.parseFromString(content, "text/html")

  doc.querySelectorAll('.mention').forEach(mention => {
    const userId = mention.getAttribute('data-id')
    const foundUser = localAvailableMembers.value.find(member => member.user.id == userId)

    if (foundUser) {
      mention.innerText = `@${foundUser.user.full_name}`
      mention.setAttribute('data-label', foundUser.user.full_name)
    } else {
      mention.innerText = `@Unknown`
      mention.setAttribute('data-label', 'Unknown')
    }
  })

  return doc.body.innerHTML
}

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    Document,
    StarterKit,
    Placeholder.configure({
      includeChildren: true,
      placeholder: ({ node }) => {
        if (node.type.name === 'detailsSummary') {
          return 'Summary'
        }

        return null
      },
    }),
    TextAlign.configure({
      types: [
        'heading',
        'paragraph',
      ],
    }),
    Placeholder.configure({ placeholder: props.placeholder ?? 'Write something here...' }),
    Underline,
    Text,
    TaskList,
    TaskItem.configure({
      nested: true,
    }),
    ImageResize.configure({
      allowBase64: true,
    }),
    FileHandler.configure({
      allowedMimeTypes: ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
      onDrop: (currentEditor, files, pos) => {
        files.forEach(file => {
          const fileReader = new FileReader()

          fileReader.readAsDataURL(file)
          fileReader.onload = () => {
            currentEditor.chain().insertContentAt(pos, {
              type: 'image',
              attrs: {
                src: fileReader.result,
              },
            }).focus().run()
          }
        })
      },
      onPaste: (currentEditor, files) => {
        files.forEach(file => {
          const fileReader = new FileReader()

          fileReader.readAsDataURL(file)
          fileReader.onload = () => {
            currentEditor.chain().insertContentAt(currentEditor.state.selection.anchor, {
              type: 'image',
              attrs: {
                src: fileReader.result,
              },
            }).focus().run()
          }
        })
      },
    }),
    Mention.configure({
      HTMLAttributes: {
        class: 'mention',
      },
      suggestion: {
        ...suggestion,
        items: ({ query }) => {
          return localAvailableMembers.value
            .map(member => ({
              id: member.user.id,
              name: member.user.full_name,
            }))
            .filter(user => user.name.toLowerCase().includes(query.toLowerCase()))
            .slice(0, 5)
        },
      },
      renderHTML: ({ node }) => {
        return [
          'span',
          {
            class: 'mention',
            'data-type': 'mention',
            'data-id': node.attrs.id,
            'data-label': node.attrs.label || 'Unknown',
          },
          `@${node.attrs.label || 'Unknown'}`,
        ]
      },
      parseHTML: [
        {
          tag: 'span[data-type="mention"]',
          getAttrs: dom => ({
            id: dom.getAttribute('data-id'),
            label: dom.innerText.replace('@', ''),
          }),
        },
      ],
    }),
    Emoji.configure({
      emojis: gitHubEmojis,
      enableEmoticons: true,
      suggestion: {
        ...suggestionEmojis,
      },
    }),

  ],
  onUpdate() {
    if (!editor.value)
      return
    emit('update:modelValue', editor.value.getHTML())
  },
})

const simulateProgress = fileData => {
  fileData.progress = 0

  const interval = setInterval(() => {
    if (fileData.progress >= 90) {
      clearInterval(interval)
    } else {
      fileData.progress += Math.random() * 10
    }
  }, 300)

  
  return interval
}

const uploadFile = async event => {
  const file = event.target.files[0]
  if (!file) return

  const fileData = { name: file.name, progress: 0, error: false }

  uploadingFiles.value.push(fileData)

  const formData = new FormData()

  formData.append('file', file)

  const progressInterval = simulateProgress(fileData)

  try {
    const response = await $api('/upload-temp-spatie', {
      method: 'POST',
      body: formData,
      onResponseError({ response }) {
        throw response._data.errors
      },
    })

    clearInterval(progressInterval)
    fileData.progress = 100

    setTimeout(() => {
      uploadingFiles.value = []
      uploadedFiles.value.push(response)
      toast.success('File uploaded successfully!')
      emit('update:uploadedFiles', uploadedFiles.value)
    }, 500)
  } catch (error) {
    clearInterval(progressInterval)
    fileData.progress = 0
    fileData.error = true
    toast.error('Error uploading file')
  }
}

const deleteFile = async (fileId, index) => {
  try {
    await $api(`/delete-temp-spatie/${fileId}`, {
      method: 'DELETE',
      onResponseError({ response }) {
        throw response._data.errors
      },
    })
    uploadedFiles.value.splice(index, 1)
    toast.success('File deleted!')
    emit('update:uploadedFiles', uploadedFiles.value)
  } catch (error) {
    toast.error('Error deleting file')
  }
}

watch(() => props.modelValue, () => {
  const isSame = editor.value?.getHTML() === props.modelValue

  const mentions = []
  const parser = new DOMParser()
  const doc = parser.parseFromString(editor.value.getHTML(), "text/html")

  doc.querySelectorAll('.mention').forEach(mention => {
    mentions.push({
      id: mention.getAttribute('data-id'),
      name: mention.innerText.replace('@', ''),
    })
  })

  selectedMembers.value = mentions
  emit('update:selectedMembers', mentions)
  if (isSame)
    return

  const fixedContent = remapMentions(props.modelValue)

  if (fixedContent) {
    setTimeout(() => {
      editor.value.commands.setContent(fixedContent, true)
      editor.value.commands.focus('end')
    }, 0)
  }
})

const sendMessage = () => {
  if(isEditing.value) {
    emit('editMessage')
  }else{
    emit('send')
  }

  nextTick(() => {
    exitEditMode()
    exitReplyMode()
  })
}

const exitEditMode = () => {
  isEditing.value = false
  emit('exitEditMode')
  editor.value.commands.setContent('', true)
  uploadingFiles.value = []
  uploadedFiles.value = []
  selectedMembers.value = []
}

const exitReplyMode = () => {
  localMessageToReply.value = null
  emit('exitReplyMode')
}

onUnmounted(() => {
  uploadingFiles.value.forEach(file => {
    clearInterval(file.progress)
  })

  uploadingFiles.value = []
  uploadedFiles.value = []

  if (editor.value) {
    editor.value.destroy()
  }
})
</script>

<template>
  <div>
    <div
      v-if="isEditing"
      class="edit-mode-indicator"
    >
      <span class="text-sm">Editing message...</span>
      <VIcon
        icon="tabler-circle-x"
        size="16"
        color="error"
        class="close-reply-btn"
        @click="exitEditMode"
      />
    </div>
    <div class="reply-area" v-if="localMessageToReply">
      <div class="chat-message-box-reply">
        <div class="reply-content">
          <div class="text tiptap" v-html="localMessageToReply.content" />
        </div>
      </div>
      <VIcon
        icon="tabler-circle-x"
        size="16"
        color="error"
        class="close-reply-btn"
        @click="exitReplyMode"
      />
    </div>
    <div class="editor-container mx-5">
      <div
        v-if="uploadedFiles.length || uploadingFiles.length"
        class="uploaded-files"
      >
        <div
          v-for="file in uploadingFiles"
          :key="file.name"
          class="file-item uploading"
        >
          <div class="file-info">
            <VIcon
              icon="tabler-file"
              class="file-icon"
            />
            <span class="file-name">{{ file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name }}</span>
          </div>
          <VProgressLinear
            :value="file.progress"
            color="primary"
            height="6"
            class="progress-bar"
          />
          <VIcon
            v-if="file.error"
            icon="tabler-alert-circle"
            color="red"
            class="error-icon"
          />
        </div>

        <div
          v-for="(file, index) in uploadedFiles"
          :key="file.id"
          class="file-item uploaded"
        >
          <div class="file-info">
            <VIcon
              icon="tabler-file-check"
              class="file-icon success"
            />
            <a
              :href="file.url"
              target="_blank"
              rel="noopener noreferrer"
              class="file-name"
            >{{ file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name }}</a>
          </div>
          <div
            class="custom-badge delete-btn"
            @click="deleteFile(file.id, index)"
          >
            <VIcon icon="tabler-trash" />
          </div>
        </div>
      </div>
      <EditorContent
        ref="editorRef"
        class="this-editor"
        :editor="editor"
      />
      <div
        v-if="editor"
        class="d-flex justify-space-between align-center editor"
      >
        <div class="d-flex gap-2 py-1 px-2 flex-wrap align-center editor">
          <IconBtn
            size="x-small"
            rounded
            class="custom-badge-toolbar"
            @click="$refs.fileInput.click()"
          >
            <VIcon icon="tabler-paperclip" />
          </IconBtn>
          <input
            ref="fileInput"
            type="file"
            class="hidden"
            @change="uploadFile"
          >
          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive('bold') ? 'tonal' : 'text'"
            :color="editor.isActive('bold') ? 'primary' : 'default'"
            @click="editor.chain().focus().toggleBold().run()"
          >
            <VIcon icon="tabler-bold" />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive('underline') ? 'tonal' : 'text'"
            :color="editor.isActive('underline') ? 'primary' : 'default'"
            @click="editor.commands.toggleUnderline()"
          >
            <VIcon icon="tabler-underline" />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive('italic') ? 'tonal' : 'text'"
            :color="editor.isActive('italic') ? 'primary' : 'default'"
            @click="editor.chain().focus().toggleItalic().run()"
          >
            <VIcon
              icon="tabler-italic"
              class="font-weight-medium"
            />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive('strike') ? 'tonal' : 'text'"
            :color="editor.isActive('strike') ? 'primary' : 'default'"
            @click="editor.chain().focus().toggleStrike().run()"
          >
            <VIcon icon="tabler-strikethrough" />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive({ textAlign: 'left' }) ? 'tonal' : 'text'"
            :color="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'default'"
            @click="editor.chain().focus().setTextAlign('left').run()"
          >
            <VIcon icon="tabler-align-left" />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :color="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'default'"
            :variant="editor.isActive({ textAlign: 'center' }) ? 'tonal' : 'text'"
            @click="editor.chain().focus().setTextAlign('center').run()"
          >
            <VIcon icon="tabler-align-center" />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive({ textAlign: 'right' }) ? 'tonal' : 'text'"
            :color="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'default'"
            @click="editor.chain().focus().setTextAlign('right').run()"
          >
            <VIcon icon="tabler-align-right" />
          </IconBtn>

          <IconBtn
            class="custom-badge-toolbar"
            size="x-small"
            rounded
            :variant="editor.isActive({ textAlign: 'justify' }) ? 'tonal' : 'text'"
            :color="editor.isActive({ textAlign: 'justify' }) ? 'primary' : 'default'"
            @click="editor.chain().focus().setTextAlign('justify').run()"
          >
            <VIcon icon="tabler-align-justified" />
          </IconBtn>
        </div>
        <VBtn
          color="#5062ff"
          size="small"
          rounded-full
          :disabled="!editor || editor.isEmpty"
          @click="sendMessage"
        >
          <VIcon icon="tabler-arrow-up" />
        </VBtn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.reply-area{
  background-color: #f2f3fa;
  border-bottom: 1px solid #e2e7eb;
  border-radius: 0;
  align-items: center;
  margin-top: 1px;
  padding-left: 1px;
  padding-right: 5px;
  display: flex;
  justify-content: space-between;
}
.chat-message-box-reply {
  max-width: none;
  min-height: auto;
  max-height: 48px;
  margin-top: 0;
  margin-bottom: 0;
  padding: 4px 8px;
  font-size: 13.7px;
  line-height: 19px;
  overflow: auto;
  border-left: 4px solid #FFC107;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.reply-content {
  display: flex;
  align-items: center;
  width: 100%;
  justify-content: space-between;
}

.close-reply-btn:hover {
  color: #d32f2f;
}

.edit-mode-indicator {
  background: rgba(255, 193, 7, 0.15);
  color: #856404;
  padding: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  font-weight: 500;
  width: 100%;
}
</style>
