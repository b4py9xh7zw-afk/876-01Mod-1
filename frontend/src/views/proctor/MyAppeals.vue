<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">我的申诉</h1>
        <p class="text-sm text-gray-500 mt-1">查看历史申诉记录及处理进度</p>
      </div>
    </div>

    <div class="flex gap-2 flex-wrap">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="currentTab = tab.value"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="currentTab === tab.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
      >
        {{ tab.label }}
        <span
          v-if="tab.count"
          class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full"
          :class="currentTab === tab.value ? 'bg-indigo-500 text-white' : 'bg-gray-200 text-gray-700'"
        >
          {{ tab.count }}
        </span>
      </button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <div v-else-if="appeals.length === 0" class="text-center py-16 text-gray-500">
      <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
      </svg>
      <p class="font-medium text-lg">暂无申诉记录</p>
      <p class="text-sm mt-1">如对考试结果有疑问，可在监考回放页面提交申诉</p>
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="appeal in appeals"
        :key="appeal.id"
        class="card-base p-5 border border-gray-100 hover:shadow-md transition-shadow duration-200"
      >
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-3">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusClass(appeal.status)"
              >
                {{ getStatusLabel(appeal.status) }}
              </span>
              <span
                v-if="appeal.proctor_event"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getEventTypeClass(appeal.proctor_event.event_type)"
              >
                {{ getEventTypeLabel(appeal.proctor_event.event_type) }}
              </span>
              <span class="text-xs text-gray-400">{{ formatDateTime(appeal.created_at) }}</span>
            </div>

            <div class="mb-2">
              <h4 class="font-medium text-gray-900">
                {{ appeal.exam_record?.exam_paper?.title || '未知试卷' }}
              </h4>
              <p v-if="appeal.proctor_event?.detail" class="text-sm text-gray-500 mt-1">
                异常详情：{{ appeal.proctor_event.detail }}
              </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-3 mb-2">
              <p class="text-xs text-gray-500 mb-1">申诉说明：</p>
              <p class="text-sm text-gray-700">{{ appeal.explanation }}</p>
            </div>

            <div v-if="appeal.screenshots && appeal.screenshots.length > 0" class="mb-3">
              <p class="text-xs text-gray-500 mb-2">上传的截图：</p>
              <div class="flex flex-wrap gap-2">
                <img
                  v-for="(img, idx) in appeal.screenshots"
                  :key="idx"
                  :src="img"
                  class="w-16 h-16 object-cover rounded-md border border-gray-200 cursor-pointer hover:opacity-80 transition-opacity"
                  @click="previewImg(img)"
                />
              </div>
            </div>

            <div
              v-if="appeal.status !== 'pending' && appeal.review_comment"
              class="border-l-4 pl-3 py-1"
              :class="appeal.status === 'approved' ? 'border-green-400 bg-green-50' : 'border-red-400 bg-red-50'"
            >
              <p class="text-xs text-gray-500 mb-1">
                复核人：{{ appeal.reviewer?.real_name || appeal.reviewer?.username || '未知' }}
                <span class="ml-2">{{ formatDateTime(appeal.reviewed_at) }}</span>
              </p>
              <p class="text-sm font-medium" :class="appeal.status === 'approved' ? 'text-green-700' : 'text-red-700'">
                复核意见：{{ appeal.review_comment }}
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-2 md:items-end">
            <router-link
              :to="`/proctor/record/${appeal.exam_record_id}`"
              class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              查看详情
            </router-link>
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
import { ref, computed, onMounted, watch } from 'vue'
import api from '../../api'
import { useToast } from '../../composables/useToast'

const { error: toastError } = useToast()

const loading = ref(true)
const appeals = ref([])
const currentTab = ref('all')
const previewUrl = ref(null)
const allAppeals = ref([])

const statusLabels = {
  pending: '待审核',
  approved: '申诉通过',
  rejected: '申诉驳回'
}

const eventTypeLabels = {
  screen_switch: '切屏',
  camera_disconnect: '摄像头断开',
  idle: '长时间未操作',
  network_recover: '网络恢复'
}

const tabs = computed(() => [
  { label: '全部', value: 'all', count: allAppeals.value.length },
  { label: '待审核', value: 'pending', count: allAppeals.value.filter(a => a.status === 'pending').length },
  { label: '已通过', value: 'approved', count: allAppeals.value.filter(a => a.status === 'approved').length },
  { label: '已驳回', value: 'rejected', count: allAppeals.value.filter(a => a.status === 'rejected').length }
])

const getStatusLabel = (status) => statusLabels[status] || status
const getEventTypeLabel = (type) => eventTypeLabels[type] || type

const getStatusClass = (status) => {
  switch (status) {
    case 'pending': return 'bg-yellow-100 text-yellow-800'
    case 'approved': return 'bg-green-100 text-green-800'
    case 'rejected': return 'bg-red-100 text-red-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

const getEventTypeClass = (type) => {
  switch (type) {
    case 'screen_switch': return 'bg-red-100 text-red-800'
    case 'camera_disconnect': return 'bg-orange-100 text-orange-800'
    case 'idle': return 'bg-yellow-100 text-yellow-800'
    case 'network_recover': return 'bg-blue-100 text-blue-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const previewImg = (url) => {
  previewUrl.value = url
}

const loadAppeals = async () => {
  loading.value = true
  try {
    const response = await api.get('/proctor/appeals/my')
    allAppeals.value = response.data.appeals.data || response.data.appeals || []
    filterAppeals()
  } catch (e) {
    toastError(e.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const filterAppeals = () => {
  if (currentTab.value === 'all') {
    appeals.value = [...allAppeals.value]
  } else {
    appeals.value = allAppeals.value.filter(a => a.status === currentTab.value)
  }
}

watch(currentTab, () => {
  filterAppeals()
})

onMounted(() => {
  loadAppeals()
})
</script>
