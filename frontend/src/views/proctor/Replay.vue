<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <router-link to="/records" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </router-link>
        <h1 class="text-2xl font-bold text-gray-900">监考回放</h1>
      </div>
      <div v-if="record" class="flex items-center space-x-2 text-sm text-gray-500">
        <span>{{ record.exam_paper?.title }}</span>
        <span class="text-gray-300">|</span>
        <span>{{ formatDate(record.start_time) }}</span>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <div v-else-if="events.length === 0" class="card-base p-12 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
      <p class="text-gray-500 text-lg">考试过程无异常事件</p>
      <p class="text-gray-400 text-sm mt-1">该场考试未被记录到任何监考异常</p>
    </div>

    <div v-else class="space-y-6">
      <div class="card-base p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
          <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3 shadow-sm shadow-indigo-300"></span>
          异常事件时间线
          <span class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">{{ events.length }} 条异常</span>
        </h3>

        <div class="relative">
          <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200"></div>
          <div class="space-y-4">
            <div
              v-for="event in events"
              :key="event.id"
              class="relative flex items-start pl-14 group"
            >
              <div class="absolute left-4 top-1.5 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                :class="eventIconClass(event.event_type)"
              >
                <div class="w-2 h-2 rounded-full" :class="eventDotClass(event.event_type)"></div>
              </div>
              <div class="flex-1 card-base p-4 ml-2 cursor-pointer hover:border-indigo-300 transition-colors"
                @click="selectEvent(event)"
                :class="{'ring-2 ring-indigo-500 border-indigo-300': selectedEvent?.id === event.id}"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="eventBadgeClass(event.event_type)">
                      {{ event.event_type_label }}
                    </span>
                    <span v-if="event.has_appeal" class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-700">
                      {{ appealStatusText(event.appeal) }}
                    </span>
                  </div>
                  <span class="text-sm text-gray-500">{{ formatDateTime(event.event_time) }}</span>
                </div>
                <p v-if="event.detail" class="text-sm text-gray-600">{{ event.detail }}</p>
                <div v-if="!event.has_appeal" class="mt-2">
                  <button
                    @click.stop="openAppealModal(event)"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center space-x-1 transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                    <span>申诉此异常</span>
                  </button>
                </div>
                <div v-else class="mt-2">
                  <router-link
                    :to="'/appeals/' + event.appeal?.id"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center space-x-1 transition-colors"
                  >
                    <span>查看申诉</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showAppealModal" class="fixed inset-0 z-50 overflow-y-auto">
          <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm" @click="showAppealModal = false"></div>
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-xl sm:w-full sm:max-w-lg border border-gray-100 z-10">
              <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">提交异常申诉</h3>
                <p class="text-sm text-gray-500 mt-1" v-if="selectedEvent">
                  异常类型: {{ selectedEvent.event_type_label }} | 
                  时间: {{ formatDateTime(selectedEvent.event_time) }}
                </p>
              </div>
              <div class="px-6 py-4 space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">说明理由 <span class="text-red-500">*</span></label>
                  <textarea
                    v-model="appealForm.explanation"
                    rows="4"
                    class="input-base"
                    placeholder="请详细说明该异常产生的原因..."
                    maxlength="2000"
                  ></textarea>
                  <p class="text-xs text-gray-400 mt-1">{{ appealForm.explanation.length }}/2000</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">截图链接</label>
                  <p class="text-xs text-gray-400 mb-2">每行一个图片URL，最多5张</p>
                  <textarea
                    v-model="screenshotsText"
                    rows="3"
                    class="input-base"
                    placeholder="https://example.com/screenshot1.png"
                  ></textarea>
                </div>
              </div>
              <div class="bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row-reverse sm:gap-3">
                <button
                  @click="submitAppeal"
                  :disabled="submitting || !appealForm.explanation.trim()"
                  class="inline-flex justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all sm:w-auto min-w-[80px]"
                >
                  {{ submitting ? '提交中...' : '提交申诉' }}
                </button>
                <button
                  @click="showAppealModal = false"
                  class="mt-3 inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all sm:mt-0 sm:w-auto min-w-[80px]"
                >
                  取消
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api'
import { useToast } from '../../composables/useToast'

const route = useRoute()
const router = useRouter()
const { success: toastSuccess, error: toastError } = useToast()

const record = ref(null)
const events = ref([])
const loading = ref(true)
const selectedEvent = ref(null)
const showAppealModal = ref(false)
const submitting = ref(false)
const screenshotsText = ref('')
const appealForm = ref({
  exam_record_id: null,
  proctor_event_id: null,
  explanation: '',
  screenshots: []
})

onMounted(async () => {
  try {
    const response = await api.get(`/proctor/timeline/${route.params.recordId}`)
    record.value = response.data.record
    events.value = response.data.events
  } catch (e) {
    toastError('获取监考记录失败')
  } finally {
    loading.value = false
  }
})

const selectEvent = (event) => {
  selectedEvent.value = event
}

const openAppealModal = (event) => {
  selectedEvent.value = event
  appealForm.value = {
    exam_record_id: record.value.id,
    proctor_event_id: event.id,
    explanation: '',
    screenshots: []
  }
  screenshotsText.value = ''
  showAppealModal.value = true
}

const submitAppeal = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    const screenshots = screenshotsText.value
      .split('\n')
      .map(s => s.trim())
      .filter(s => s.length > 0)
      .slice(0, 5)

    await api.post('/appeals', {
      exam_record_id: appealForm.value.exam_record_id,
      proctor_event_id: appealForm.value.proctor_event_id,
      explanation: appealForm.value.explanation,
      screenshots: screenshots.length > 0 ? screenshots : undefined
    })
    toastSuccess('申诉已提交，请等待教师审核')
    showAppealModal.value = false
    const response = await api.get(`/proctor/timeline/${route.params.recordId}`)
    events.value = response.data.events
  } catch (e) {
    toastError(e.response?.data?.message || '提交申诉失败')
  } finally {
    submitting.value = false
  }
}

const appealStatusText = (appeal) => {
  if (!appeal) return ''
  const map = { pending: '申诉待审核', approved: '申诉已通过', rejected: '申诉已驳回' }
  return map[appeal.status] || appeal.status
}

const eventBadgeClass = (type) => {
  const map = {
    screen_switch: 'bg-orange-100 text-orange-700',
    camera_disconnect: 'bg-red-100 text-red-700',
    idle: 'bg-yellow-100 text-yellow-700',
    network_recovery: 'bg-blue-100 text-blue-700'
  }
  return map[type] || 'bg-gray-100 text-gray-700'
}

const eventIconClass = (type) => {
  const map = {
    screen_switch: 'border-orange-300 bg-orange-50',
    camera_disconnect: 'border-red-300 bg-red-50',
    idle: 'border-yellow-300 bg-yellow-50',
    network_recovery: 'border-blue-300 bg-blue-50'
  }
  return map[type] || 'border-gray-300 bg-gray-50'
}

const eventDotClass = (type) => {
  const map = {
    screen_switch: 'bg-orange-400',
    camera_disconnect: 'bg-red-400',
    idle: 'bg-yellow-400',
    network_recovery: 'bg-blue-400'
  }
  return map[type] || 'bg-gray-400'
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('zh-CN')
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('zh-CN', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}
</script>
