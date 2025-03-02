<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import Link from '@tiptap/extension-link'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import { ImageResize } from 'tiptap-extension-resize-image'
import Highlight from '@tiptap/extension-highlight'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import css from 'highlight.js/lib/languages/css'
import js from 'highlight.js/lib/languages/javascript'
import ts from 'highlight.js/lib/languages/typescript'
import html from 'highlight.js/lib/languages/xml'
import { all, createLowlight } from 'lowlight'
import { FileHandler } from '@tiptap-pro/extension-file-handler'
import { Emoji, gitHubEmojis } from '@tiptap-pro/extension-emoji'
import suggestionEmojis from "@/components/messages/suggestionEmojis"
import { useDebounceFn } from '@vueuse/core'

const props = defineProps({
  isDialogVisible: Boolean,
  containerId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['update:isDialogVisible'])

const lowlight = createLowlight(all)
lowlight.register('html', html)
lowlight.register('css', css)
lowlight.register('js', js)
lowlight.register('ts', ts)

const activeTab = ref(null)
const tabs = ref([])
const isLoading = ref(true)
const isEditingTab = ref(false)
const isSaving = ref(false)
const searchQuery = ref('')
const currentContent = ref('')
const showNewTabDialog = ref(false)
const newTabName = ref('')
const hasUnsavedChanges = ref(false)
const isAutoSaving = ref(false)
const lastSavedAt = ref(null)
const initialContent = ref('')

const editor = useEditor({
  content: '',
  extensions: [
    StarterKit.configure({
      codeBlock: false
    }),
    Placeholder.configure({
      placeholder: 'Write something...'
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph']
    }),
    Underline,
    TaskList,
    TaskItem.configure({
      nested: true
    }),
    Table.configure({
      resizable: true
    }),
    TableRow,
    TableCell,
    TableHeader,
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
    Highlight,
    CodeBlockLowlight.configure({
      lowlight,
      defaultLanguage: 'javascript',
      HTMLAttributes: {
        class: 'code-block',
      }
    }),
    Emoji.configure({
      emojis: gitHubEmojis,
      enableEmoticons: true,
      suggestion: {
        ...suggestionEmojis,
      },
    }),
    Link.configure({
      openOnClick: true,
      autolink: true,
      linkOnPaste: true,
      HTMLAttributes: {
        class: 'editor-link',
        target: '_blank',
        rel: 'noopener noreferrer',
      },
      validate: href => /^https?:\/\//.test(href),
    }),
  ],
  editorProps: {
    attributes: {
      class: 'documentation-editor prose prose-sm max-w-none'
    }
  },
  onUpdate: ({ editor }) => {
    currentContent.value = editor.getHTML()
  }
})

const editorActions = [
  {
    icon: 'tabler-bold',
    action: () => editor.value?.chain().focus().toggleBold().run(),
    isActive: () => editor.value?.isActive('bold'),
    tooltip: 'Bold'
  },
  {
    icon: 'tabler-italic',
    action: () => editor.value?.chain().focus().toggleItalic().run(),
    isActive: () => editor.value?.isActive('italic'),
    tooltip: 'Italic'
  },
  {
    icon: 'tabler-underline',
    action: () => editor.value?.chain().focus().toggleUnderline().run(),
    isActive: () => editor.value?.isActive('underline'),
    tooltip: 'Underline'
  },
  // Heading levels
  {
    icon: 'tabler-h-1',
    action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run(),
    isActive: () => editor.value?.isActive('heading', { level: 1 }),
    tooltip: 'Heading 1'
  },
  {
    icon: 'tabler-h-2',
    action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
    isActive: () => editor.value?.isActive('heading', { level: 2 }),
    tooltip: 'Heading 2'
  },
  // List options
  {
    icon: 'tabler-list',
    action: () => editor.value?.chain().focus().toggleBulletList().run(),
    isActive: () => editor.value?.isActive('bulletList'),
    tooltip: 'Bullet List'
  },
  {
    icon: 'tabler-list-numbers',
    action: () => editor.value?.chain().focus().toggleOrderedList().run(),
    isActive: () => editor.value?.isActive('orderedList'),
    tooltip: 'Numbered List'
  },
  {
    icon: 'tabler-list-check',
    action: () => editor.value?.chain().focus().toggleTaskList().run(),
    isActive: () => editor.value?.isActive('taskList'),
    tooltip: 'Task List'
  },
  // Table controls
  {
    icon: 'tabler-table',
    action: () => insertTable(),
    tooltip: 'Insert Table'
  },
  // Code block
  {
    icon: 'tabler-code',
    action: () => editor.value?.chain().focus().toggleCodeBlock().run(),
    isActive: () => editor.value?.isActive('codeBlock'),
    tooltip: 'Code Block'
  },
  // Link
  {
    icon: 'tabler-link',
    action: () => insertLink(),
    isActive: () => editor.value?.isActive('link'),
    tooltip: 'Insert Link'
  },
]

const insertTable = () => {
  editor.value?.chain()
    .focus()
    .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
    .run()
}

const insertLink = () => {
  const previousUrl = editor.value?.getAttributes('link').href
  const url = window.prompt('URL:', previousUrl)
  
  if (url === null) return

  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const loadTabs = async () => {
  try {
    isLoading.value = true
    const response = await $api(`/container/${props.containerId}/documentation-tabs`, {
      method: 'GET'
    })
    tabs.value = response.data
    if (tabs.value.length > 0) {
      await selectTab(tabs.value[0])
    }
  } catch (error) {
    console.error('Failed to load tabs:', error)
  } finally {
    isLoading.value = false
  }
}

const selectTab = async (tab) => {
  if (hasUnsavedChanges.value) {
    if (await confirm('You have unsaved changes. Do you want to save them before switching tabs?')) {
      await saveChanges()
    }
  }
  
  activeTab.value = tab
  editor.value?.commands.setContent(tab.content || '')
  initialContent.value = tab.content || ''
  hasUnsavedChanges.value = false
  if (window.innerWidth <= 960) {
    isSidebarOpen.value = false
  }
}

const createNewTab = async () => {
  try {
    isSaving.value = true
    const response = await $api(`/container/${props.containerId}/documentation-tab`, {
      method: 'POST',
      body: {
        name: newTabName.value
      }
    })
    tabs.value.push(response.data)
    await selectTab(response.data)
    showNewTabDialog.value = false
    newTabName.value = ''
  } catch (error) {
    console.error('Failed to create tab:', error)
  } finally {
    isSaving.value = false
  }
}

const deleteTab = async (tab) => {
  if (!confirm('Are you sure you want to delete this page?')) return
  
  try {
    await $api(`/container/documentation-tab/${tab.id}`, {
      method: 'DELETE'
    })
    const index = tabs.value.findIndex(t => t.id === tab.id)
    if (index !== -1) {
      tabs.value.splice(index, 1)
      if (activeTab.value?.id === tab.id) {
        activeTab.value = tabs.value[0] || null
      }
    }
  } catch (error) {
    console.error('Failed to delete tab:', error)
  }
}

const saveContent = async () => {
  if (!hasUnsavedChanges.value || !activeTab.value) return
  
  try {
    isAutoSaving.value = true
    await $api(`/container/documentation-tab/${activeTab.value.id}`, {
      method: 'PUT',
      body: {
        content: currentContent.value
      }
    })
    hasUnsavedChanges.value = false
    lastSavedAt.value = new Date()
  } catch (error) {
    console.error('Auto-save failed:', error)
  } finally {
    isAutoSaving.value = false
  }
}

const debouncedSaveContent = useDebounceFn(saveContent, 2000)

watch(() => currentContent.value, (newContent) => {
  if (newContent !== initialContent.value) {
    hasUnsavedChanges.value = true
    debouncedSaveContent()
  }
})

watch(() => props.isDialogVisible, async (newVal) => {
  if (newVal) {
    await loadTabs()
  }
})

const formatLastSaved = (date) => {
  if (!date) return ''
  const now = new Date()
  const diff = Math.floor((now - date) / 1000)
  
  if (diff < 60) return 'Saved just now'
  if (diff < 3600) return `Saved ${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `Saved ${Math.floor(diff / 3600)}h ago`
  return `Saved on ${date.toLocaleDateString()}`
}

const handleClose = async () => {
  if (hasUnsavedChanges.value) {
    if (await confirm('You have unsaved changes. Do you want to save them before closing?')) {
      await saveChanges()
    }
  }
  emit('update:isDialogVisible', false)
}

onMounted(() => {
  if (props.isDialogVisible) {
    loadTabs()
  }
})

onBeforeUnmount(() => {
  if (debouncedSaveContent.cancel) {
    debouncedSaveContent.cancel()
  }
})

const saveChanges = async () => {
  if (!activeTab.value || !hasUnsavedChanges.value) return
  
  try {
    isSaving.value = true
    await saveContent()
  } catch (error) {
    console.error('Failed to save changes:', error)
  } finally {
    isSaving.value = false
  }
}

const isSidebarOpen = ref(false)

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const handleClickOutside = (event) => {
  if (isSidebarOpen.value && !event.target.closest('.sidebar') && !event.target.closest('.v-btn')) {
    isSidebarOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    fullscreen
    class="documentation-dialog"
    @update:model-value="handleClose"
  >
    <VCard class="h-100">
      <!-- Header -->
      <VCardTitle class="header d-flex align-center py-3 px-4">
        <!-- Mobile Sidebar Toggle -->
        <VBtn
          v-show="$vuetify.display.mdAndDown"
          icon
          size="small"
          variant="text"
          class="me-2"
          @click="toggleSidebar"
        >
          <VIcon>tabler-menu-2</VIcon>
        </VBtn>
        
        <span class="text-h6 font-weight-medium">Documentation</span>
        <VSpacer />
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="hasUnsavedChanges"
            :loading="isSaving"
            color="primary"
            size="small"
            variant="tonal"
            class="save-btn"
            @click="saveChanges"
          >
            <VIcon size="18" class="me-1">tabler-device-floppy</VIcon>
            Save changes
          </VBtn>
          <VBtn
            icon
            size="32"
            variant="text"
            @click="$emit('update:isDialogVisible', false)"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0 d-flex fill-height">
        <!-- Sidebar cu clasă dinamică pentru mobile -->
        <div 
          class="sidebar" 
          :class="{ 'sidebar-open': isSidebarOpen }"
        >
          <div class="pa-4">
            <VTextField
              v-model="searchQuery"
              placeholder="Search pages..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              hide-details
              class="search-field mb-4"
            />

            <div class="d-flex align-center justify-space-between mb-3">
              <div class="text-subtitle-2 text-medium-emphasis font-weight-medium">Pages</div>
              <VBtn
                size="small"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="showNewTabDialog = true"
              >
                New
              </VBtn>
            </div>

            <VList
              density="compact"
              nav
              class="pages-list rounded-lg"
              bg-color="transparent"
            >
              <VListItem
                v-for="tab in tabs"
                :key="tab.id"
                :active="activeTab?.id === tab.id"
                :title="tab.name"
                rounded="lg"
                class="mb-1"
                @click="selectTab(tab)"
              >
                <template #prepend>
                  <VIcon size="18" class="me-2">tabler-file-text</VIcon>
                </template>
                <template #append>
                  <VMenu location="bottom end" :close-on-content-click="true">
                    <template #activator="{ props }">
                      <VBtn
                        icon
                        variant="text"
                        size="small"
                        v-bind="props"
                        class="action-btn"
                      >
                        <VIcon size="16">tabler-dots-vertical</VIcon>
                      </VBtn>
                    </template>
                    <VList density="compact" class="menu-list" min-width="120">
                      <VListItem
                        @click="deleteTab(tab)"
                        prepend-icon="tabler-trash"
                        title="Delete"
                        class="text-error"
                      />
                    </VList>
                  </VMenu>
                </template>
              </VListItem>
            </VList>
          </div>
        </div>

        <!-- Overlay pentru mobile când sidebar-ul este deschis -->
        <div
          v-show="isSidebarOpen && $vuetify.display.mdAndDown"
          class="sidebar-overlay"
          @click="toggleSidebar"
        />

        <!-- Editor Area -->
        <div class="editor-wrapper flex-grow-1">
          <!-- Editor Toolbar -->
          <div class="editor-toolbar px-4 py-1">
            <div class="d-flex align-center flex-wrap gap-1">
              <!-- Text Formatting -->
              <VBtnGroup density="compact" variant="text" class="me-2">
                <VBtn
                  v-for="action in editorActions.slice(0, 3)"
                  :key="action.tooltip"
                  :title="action.tooltip"
                  size="x-small"
                  :color="action.isActive?.() ? 'primary' : undefined"
                  @click="action.action"
                >
                  <VIcon size="16">{{ action.icon }}</VIcon>
                </VBtn>
              </VBtnGroup>

              <VDivider vertical class="mx-1" />

              <!-- Headings -->
              <VBtnGroup density="compact" variant="text" class="me-2">
                <VBtn
                  v-for="action in editorActions.slice(3, 5)"
                  :key="action.tooltip"
                  :title="action.tooltip"
                  size="x-small"
                  :color="action.isActive?.() ? 'primary' : undefined"
                  @click="action.action"
                >
                  <VIcon size="16">{{ action.icon }}</VIcon>
                </VBtn>
              </VBtnGroup>

              <VDivider vertical class="mx-1" />

              <!-- Lists -->
              <VBtnGroup density="compact" variant="text" class="me-2">
                <VBtn
                  v-for="action in editorActions.slice(5, 8)"
                  :key="action.tooltip"
                  :title="action.tooltip"
                  size="x-small"
                  :color="action.isActive?.() ? 'primary' : undefined"
                  @click="action.action"
                >
                  <VIcon size="16">{{ action.icon }}</VIcon>
                </VBtn>
              </VBtnGroup>

              <VDivider vertical class="mx-1" />

              <!-- Advanced Features -->
              <VBtnGroup density="compact" variant="text">
                <VBtn
                  v-for="action in editorActions.slice(8)"
                  :key="action.tooltip"
                  :title="action.tooltip"
                  size="x-small"
                  :color="action.isActive?.() ? 'primary' : undefined"
                  @click="action.action"
                >
                  <VIcon size="16">{{ action.icon }}</VIcon>
                </VBtn>
              </VBtnGroup>
            </div>
          </div>

          <VDivider />

          <div v-if="activeTab" class="editor-content pa-6">
            <editor-content :editor="editor" class="documentation-editor" />
          </div>
          <div v-else class="empty-state d-flex align-center justify-center flex-grow-1">
            <div class="text-center">
              <VIcon size="48" color="grey-lighten-1" class="mb-4">tabler-file-text</VIcon>
              <div class="text-h6 text-grey-darken-1">Select or create a page</div>
              <div class="text-body-2 text-grey">Choose an existing page or create a new one to start editing</div>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- New Page Dialog -->
    <VDialog
      v-model="showNewTabDialog"
      max-width="400"
      persistent
      class="new-page-dialog"
    >
      <VCard>
        <VCardTitle class="pa-4">Create new page</VCardTitle>
        <VCardText class="pt-2">
          <VTextField
            v-model="newTabName"
            label="Page name"
            variant="outlined"
            hide-details
            autofocus
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="showNewTabDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :loading="isSaving"
            :disabled="!newTabName"
            @click="createNewTab"
          >
            Create
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VDialog>
</template>

<style lang="scss" scoped>
// GitHub colors
$github-colors: (
  bg: #0d1117,
  text: #c9d1d9,
  border: #30363d,
  link: #58a6ff,
  heading: #d2a8ff,
  code-bg: #161b22,
  code-text: #a5d6ff,
  quote-bg: #1b1f24,
  quote-text: #8b949e,
  quote-border: #3b434b
);

.documentation-dialog {
  .header {
    background: #f6f8fa;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    height: 56px; // Fixed height for consistency

    @media (max-width: 600px) {
      height: 48px;
    }
  }

  .sidebar {
    width: 280px;
    border-right: 1px solid #e1e4e8;
    background: #f6f8fa;
    transition: transform 0.3s ease;
    z-index: 100;
    
    @media (max-width: 960px) {
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      transform: translateX(-100%);
      box-shadow: none;

      &.sidebar-open {
        transform: translateX(0);
        box-shadow: 4px 0 8px rgba(0, 0, 0, 0.1);
      }
    }

    .search-field :deep(.v-field) {
      background: white;
      box-shadow: 0 1px 2px rgba(31, 35, 40, 0.085);
    }

    .pages-list {
      :deep(.v-list-item) {
        min-height: 36px;
      }

      :deep(.v-list-item--active) {
        background: #0969da15;
        color: #0969da;
        font-weight: 500;
        
        .v-icon {
          color: #0969da;
        }
      }

      :deep(.v-list-item) {
        .action-btn {
          opacity: 0;
          transition: opacity 0.2s;
        }

        &:hover .action-btn {
          opacity: 1;
        }
      }
    }
  }

  .editor-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 64px); // înălțimea totală minus header
    background: white;

    .editor-toolbar {
      background: #f6f8fa;
      border-bottom: 1px solid #e1e4e8;
      padding: 8px;
      
      &::-webkit-scrollbar {
        height: 4px;
      }

      &::-webkit-scrollbar-thumb {
        background: #dfe2e5;
        border-radius: 4px;
      }

      :deep(.v-btn) {
        height: 28px;
        min-width: 28px;
        padding: 0 4px;
        
        .v-btn__content {
          font-size: 14px;
        }
        
        &:hover {
          background-color: rgba(9, 105, 218, 0.1);
        }
        
        &.v-btn--active {
          background-color: rgba(9, 105, 218, 0.15);
        }
      }

      @media (max-width: 600px) {
        .v-btn-group {
          margin-right: 4px !important;
        }

        .v-divider {
          margin: 0 4px !important;
        }
      }
    }

    .editor-content {
      height: calc(100% - 44px); // înălțimea totală minus toolbar
      position: relative;
      
      :deep(.documentation-editor) {
        height: 100%;
        
        .ProseMirror {
          height: 100%;
          max-height: 100%;
          padding: 1.5rem;
          overflow-y: auto;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
          font-size: 14px;
          line-height: 1.6;
          color: #24292f;
          
          &:focus {
            outline: none;
          }

          /* Code block styling */
          .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'JetBrains Mono', 'SF Mono', 'Fira Code', monospace;
            font-size: 13px;
            line-height: 1.5;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            position: relative;

            &::before {
              content: attr(data-language);
              position: absolute;
              top: 0;
              right: 0;
              padding: 4px 8px;
              font-size: 12px;
              color: #858585;
              background: #2d2d2d;
              border-radius: 0 6px 0 6px;
              text-transform: uppercase;
            }

            pre {
              background: none;
              color: inherit;
              font-size: inherit;
              font-family: inherit;
              line-height: inherit;
              margin: 0;
              padding: 0;
              
              code {
                color: inherit;
                padding: 0;
                background: none;
                font-size: inherit;
              }
            }

            .hljs-comment,
            .hljs-quote {
              color: #6a9955;
            }

            .hljs-keyword,
            .hljs-selector-tag,
            .hljs-literal,
            .hljs-section {
              color: #569cd6;
            }

            .hljs-string,
            .hljs-title,
            .hljs-name,
            .hljs-type,
            .hljs-attribute,
            .hljs-symbol,
            .hljs-bullet,
            .hljs-built_in,
            .hljs-addition,
            .hljs-variable,
            .hljs-template-tag,
            .hljs-template-variable {
              color: #ce9178;
            }

            .hljs-comment,
            .hljs-quote,
            .hljs-deletion,
            .hljs-meta {
              color: #6a9955;
            }

            .hljs-keyword,
            .hljs-selector-tag,
            .hljs-literal,
            .hljs-title,
            .hljs-section,
            .hljs-doctag,
            .hljs-type,
            .hljs-name,
            .hljs-strong {
              font-weight: normal;
            }

            .hljs-emphasis {
              font-style: italic;
            }
          }

          /* Inline code styling */
          p code {
            background: #f6f8fa;
            color: #24292f;
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-size: 85%;
            font-family: 'JetBrains Mono', 'SF Mono', monospace;
          }
        }
      }
    }
  }

  // Overlay pentru mobile
  .sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99;
    animation: fadeIn 0.2s ease;
  }

  // Animație pentru overlay
  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  // Ajustări pentru header pe mobile
  .header {
    .v-btn--icon {
      margin-left: -8px; // Aliniere mai bună pentru butonul de menu
    }
  }
}

.menu-list :deep(.v-list-item) {
  min-height: 32px;
}

.empty-state {
  height: calc(100vh - 180px);
  background: #f6f8fa15;
}
</style> 