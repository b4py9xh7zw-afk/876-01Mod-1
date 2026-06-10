<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">监考回放</h1>
        <p class="text-sm text-gray-500 mt-1">查看考试过程中的异常事件记录，可针对异常提交申诉</p>
      </div>
      <router-link v-if="backUrl" :to="backUrl" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        返回
      </router-link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else-if="examRecord">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div>
            <p class="text-sm text-gray-500">试卷</p>
            <p class="text-lg font-semibold text-gray-900 mt-1">{{ examPaper?.title || '-' }}</p>
          </div>
          <div v-if="user">
            <p class="text-sm text-gray-500">考生</p>
            <p class="text-lg font-semibold text-gray-900 mt-1">{{ user.real_name || user.username }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">得分</p>
            <p class="text-lg font-semibold mt-1" :class="examRecord.score >= 60 ? 'text-green-600' : 'text-red-600'">
              {{ examRecord.score }} 分
            </p>
          </div>
          <div>
            <p class="text-sm text-gray-500">异常状态</p>
            <span
              v-if="examRecord.has_anomaly"
              class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full mt-1"
              :class="getAnomalyClass(examRecord.anomaly_status)"
            >
              {{ examRecord.anomaly_status_label }}
            </span>
            <span v-else class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 mt-1">
              无异常
            </span>
          </div>
        </div>
      </div>

      <div class="card-base p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
          <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3 shadow-sm shadow-indigo-300"></span>
          监考事件时间线
          <span class="ml-3 text-sm font-normal text-gray-500">(共 {{ events.length }} 条)</span>
        </h3>

        <div v-if="events.length === 0" class="text-center py-12 text-gray-500">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="font-medium">本场考试未检测到任何异常事件</p>
        </div>

        <ol v-else class="relative border-l-2 border-gray-200 ml-3 space-y-8">
          <li v-for="(event, index) in events" :key="event.id" class="ml-6">
            <span
              class="absolute -left-[11px] flex items-center justify-center w-5 h-5 rounded-full ring-4 ring-white"
              :class="getEventIconBg(event.event_type)"
            >
              <component :is="getEventIcon(event.event_type)" class="w-2.5 h-2.5 text-white" />
            </span>
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="getEventBadgeClass(event.event_type)"
                  >
                    {{ event.event_type_label }}
                  </span>
                  <span class="text-sm text-gray-500">{{ formatDateTime(event.event_time) }}</span>
                </div>
                <p class="text-sm text-gray-600 mb-2" v-if="event.detail">{{ event.detail }}</p>

                <div v-if="event.appeal" class="mt-3 p-3 bg-white rounded-lg border border-gray-200">
                  <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span class="font-medium text-sm text-gray-900">已提交申诉</span>
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="getAppealStatusClass(event.appeal.status)"
                    >
                      {{ event.appeal.status_label }}
                    </span>
                  </div>
                  <p class="text-sm text-gray-700 mb-2"><span class="text-gray-500">说明：</span>{{ event.appeal.explanation }}</p>
                  <div v-if="event.appeal.screenshots && event.appeal.screenshots.length > 0" class="mb-2">
                    <p class="text-xs text-gray-500 mb-1">截图：</p>
                    <div class="flex flex-wrap gap-2">
                      <img
                        v-for="(img, idx) in event.appeal.screenshots"
                        :key="idx"
                        :src="img"
                        class="w-20 h-20 object-cover rounded-md border border-gray-200 cursor-pointer hover:opacity-80"
                        @click="previewImage(img)"
                      />
                    </div>
                  </div>
                  <div v-if="event.appeal.review_comment" class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-sm">
                      <span class="text-gray-500">复核意见：</span>
                      <span :class="event.appeal.status === 'approved' ? 'text-green-700' : 'text-red-700'">
                        {{ event.appeal.review_comment }}
                      </span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ formatDateTime(event.appeal.reviewed_at) }}</p>
                  </div>
                </div>
              </div>

              <div class="flex-shrink-0">
                <button
                  v-if="!event.appeal && isStudent"
                  @click="openAppealModal(event)"
                  class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-sm"
                >
                  <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  提交申诉
                </button>
              </div>
            </div>
          </li>
        </ol>
      </div>
    </template>

    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showAppealModal" class="fixed inset-0 z-[90]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
          <div class="fixed inset-0 z-[90] bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="closeAppealModal"></div>
          <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 sm:p-0">
              <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
              >
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100">
                  <div class="px-6 pb-6 pt-6">
                    <h3 class="text-lg font-bold leading-6 text-gray-900 tracking-tight mb-4">提交申诉</h3>

                    <div v-if="selectedEvent" class="mb-4 p-3 bg-gray-50 rounded-lg">
                      <div class="flex items-center gap-2 mb-1">
                        <span
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                          :class="getEventBadgeClass(selectedEvent.event_type)"
                        >
                          {{ selectedEvent.event_type_label }}
                        </span>
                        <span class="text-xs text-gray-500">{{ formatDateTime(selectedEvent.event_time) }}</span>
                      </div>
                      <p v-if="selectedEvent.detail" class="text-sm text-gray-600">{{ selectedEvent.detail }}</p>
                    </div>

                    <div class="space-y-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">申诉说明 <span class="text-red-500">*</span></label>
                        <textarea
                          v-model="appealForm.explanation"
                          rows="4"
                          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                          placeholder="请详细说明情况，至少 5 个字符..."
                        ></textarea>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">上传截图 (可选)</label>
                        <div class="space-y-2">
                          <div
                            @click="triggerFileUpload"
                            class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-indigo-500 hover:bg-indigo-50/50 transition-colors"
                          >
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="mt-2 text-xs text-gray-500">点击上传截图</p>
                            <p class="text-xs text-gray-400">支持 JPG, PNG, GIF，最大 5MB</p>
                          </div>
                          <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            multiple
                            class="hidden"
                            @change="handleFileUpload"
                          />
                          <div v-if="appealForm.screenshots.length > 0" class="flex flex-wrap gap-2">
                            <div v-for="(img, idx) in appealForm.screenshots" :key="idx" class="relative">
                              <img :src="img" class="w-20 h-20 object-cover rounded-md border border-gray-200" />
                              <button
                                type="button"
                                @click="removeScreenshot(idx)"
                                class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600"
                              >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                              </button>
                            </div>
                          </div>
                        </div>
                        <p v-if="uploading" class="text-xs text-indigo-600 mt-1">上传中...</p>
                      </div>
                    </div>
                  </div>
                  <div class="bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row-reverse sm:gap-3">
                    <button
                      type="button"
                      :disabled="submittingAppeal || uploading"
                      class="inline-flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all focus-visible:outline sm:w-auto min-w-[80px] bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
                      @click="submitAppeal"
                    >
                      {{ submittingAppeal ? '提交中...' : '提交申诉' }}
                    </button>
                    <button
                      type="button"
                      :disabled="submittingAppeal"
                      class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all sm:mt-0 sm:w-auto min-w-[80px] disabled:opacity-50"
                      @click="closeAppealModal"
                    >
                      取消
                    </button>
                  </div>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="previewUrl" class="fixed inset-0 z-[110] bg-black/80 flex items-center justify-center p-4" @click.self="previewUrl = null">
          <img :src="previewUrl" class="max-w-full max-h-full rounded-lg" />
          <button @click="previewUrl = null" class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import api from '../../api'
import { useToast } from '../../composables/useToast'

const route = useRoute()
const authStore = useAuthStore()
const { success: toastSuccess, error: toastError } = useToast()

const loading = ref(true)
const examRecord = ref(null)
const examPaper = ref(null)
const user = ref(null)
const events = ref([])
const showAppealModal = ref(false)
const selectedEvent = ref(null)
const submittingAppeal = ref(false)
const uploading = ref(false)
const previewUrl = ref(null)
const fileInput = ref(null)

const appealForm = ref({
  explanation: '',
  screenshots: []
})

const isStudent = computed(() => authStore.user?.role === 'student')
const backUrl = computed(() => {
  if (authStore.isAdmin || authStore.isTeacher) {
    return '/appeals/review'
  }
  return '/records'
})

const getAnomalyClass = (status) => {
  switch (status) {
    case 'flagged': return 'bg-red-100 text-red-800'
    case 'appealed': return 'bg-yellow-100 text-yellow-800'
    case 'resolved': return 'bg-blue-100 text-blue-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

const getEventIconBg = (type) => {
  switch (type) {
    case 'screen_switch': return 'bg-red-500'
    case 'camera_disconnect': return 'bg-orange-500'
    case 'idle': return 'bg-yellow-500'
    case 'network_recover': return 'bg-blue-500'
    default: return 'bg-gray-500'
  }
}

const getEventBadgeClass = (type) => {
  switch (type) {
    case 'screen_switch': return 'bg-red-100 text-red-800'
    case 'camera_disconnect': return 'bg-orange-100 text-orange-800'
    case 'idle': return 'bg-yellow-100 text-yellow-800'
    case 'network_recover': return 'bg-blue-100 text-blue-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

const getAppealStatusClass = (status) => {
  switch (status) {
    case 'pending': return 'bg-yellow-100 text-yellow-800'
    case 'approved': return 'bg-green-100 text-green-800'
    case 'rejected': return 'bg-red-100 text-red-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

const getEventIcon = (type) => {
  const icons = {
    screen_switch: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
          h('path', { d: 'M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4' })
        ])
      }
    },
    camera_disconnect: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
          h('path', { d: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2zM3 3l18 18' })
        ])
      }
    },
    idle: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
          h('path', { d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' })
        ])
      }
    },
    network_recover: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
          h('path', { d: 'M13 10V3L4 14h7v7l9-11h-7z' })
        ])
      }
    }
  }
  return icons[type] || icons.screen_switch
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

const previewImage = (url) => {
  previewUrl.value = url
}

const openAppealModal = (event) => {
  selectedEvent.value = event
  appealForm.value = {
    explanation: '',
    screenshots: []
  }
  showAppealModal.value = true
}

const closeAppealModal = () => {
  if (submittingAppeal.value || uploading.value) return
  showAppealModal.value = false
  selectedEvent.value = null
}

const triggerFileUpload = () => {
  fileInput.value?.click()
}

const handleFileUpload = async (e) => {
  const files = Array.from(e.target.files || [])
  if (files.length === 0) return

  uploading.value = true
  try {
    for (const file of files) {
      const formData = new FormData()
      formData.append('file', file)
      const response = await api.post('/proctor/upload-screenshot', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      if (response.data.url) {
        appealForm.value.screenshots.push(response.data.url)
      }
    }
  } catch (err) {
    toastError('图片上传失败')
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

const removeScreenshot = (idx) => {
  appealForm.value.screenshots.splice(idx, 1)
}

const submitAppeal = async () => {
  if (!appealForm.value.explanation || appealForm.value.explanation.trim().length < 5) {
    toastError('请填写申诉说明（至少5个字符）')
    return
  }
  if (!selectedEvent.value) return

  submittingAppeal.value = true
  try {
    await api.post('/proctor/appeals', {
      exam_record_id: examRecord.value.id,
      proctor_event_id: selectedEvent.value.id,
      explanation: appealForm.value.explanation,
      screenshots: appealForm.value.screenshots
    })
    toastSuccess('申诉已提交，等待老师复核')
    showAppealModal.value = false
    loadData()
  } catch (e) {
    toastError(e.response?.data?.message || '提交失败')
  } finally {
    submittingAppeal.value = false
  }
}

const loadData = async () => {
  try {
    const response = await api.get(`/proctor/events/${route.params.id}`)
    examRecord.value = response.data.exam_record
    examPaper.value = response.data.exam_paper
    user.value = response.data.user
    events.value = response.data.events
  } catch (e) {
    toastError(e.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>
