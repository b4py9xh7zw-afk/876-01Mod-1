<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isStudent ? '我的申诉' : '申诉审核' }}
      </h1>
      <div class="flex items-center space-x-2">
        <select v-model="statusFilter" @change="fetchAppeals" class="input-base w-auto text-sm py-2">
          <option value="">全部状态</option>
          <option value="pending">待审核</option>
          <option value="approved">已通过</option>
          <option value="rejected">已驳回</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <div v-else-if="appeals.length === 0" class="card-base p-12 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
      </svg>
      <p class="text-gray-500 text-lg">暂无申诉记录</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="appeal in appeals" :key="appeal.id" class="card-base p-5">
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center space-x-3">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="statusBadgeClass(appeal.status)">
              {{ statusLabel(appeal.status) }}
            </span>
            <span v-if="appeal.proctor_event" class="px-2 py-0.5 rounded text-xs font-semibold" :class="eventTypeBadgeClass(appeal.proctor_event.event_type)">
              {{ eventTypeLabel(appeal.proctor_event.event_type) }}
            </span>
          </div>
          <span class="text-sm text-gray-400">{{ formatDate(appeal.created_at) }}</span>
        </div>

        <div class="mb-3">
          <p class="text-sm text-gray-500 mb-1">
            <span class="font-medium text-gray-700">试卷:</span>
            {{ appeal.exam_record?.exam_paper?.title || '-' }}
          </p>
          <p v-if="!isStudent && appeal.user" class="text-sm text-gray-500">
            <span class="font-medium text-gray-700">申诉人:</span>
            {{ appeal.user.real_name || appeal.user.username }}
          </p>
        </div>

        <div class="bg-gray-50 rounded-lg p-3 mb-3">
          <p class="text-sm text-gray-700 leading-relaxed">{{ appeal.explanation }}</p>
        </div>

        <div v-if="appeal.screenshots && appeal.screenshots.length > 0" class="mb-3">
          <p class="text-xs text-gray-500 mb-2">截图证据:</p>
          <div class="flex flex-wrap gap-2">
            <a v-for="(url, i) in appeal.screenshots" :key="i" :href="url" target="_blank"
              class="inline-flex items-center px-2.5 py-1.5 bg-indigo-50 text-indigo-600 rounded text-xs font-medium hover:bg-indigo-100 transition-colors">
              <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              截图 {{ i + 1 }}
            </a>
          </div>
        </div>

        <div v-if="appeal.review_comment" class="border-t border-gray-100 pt-3 mt-3">
          <p class="text-sm text-gray-500">
            <span class="font-medium text-gray-700">审核意见:</span>
            {{ appeal.review_comment }}
          </p>
          <p class="text-xs text-gray-400 mt-1">
            审核人: {{ appeal.reviewer?.username || '-' }} | {{ formatDate(appeal.reviewed_at) }}
          </p>
        </div>

        <div v-if="!isStudent && appeal.status === 'pending'" class="border-t border-gray-100 pt-3 mt-3 flex items-center space-x-3">
          <textarea
            v-model="reviewComments[appeal.id]"
            rows="2"
            class="input-base text-sm flex-1"
            placeholder="填写审核意见（可选）"
          ></textarea>
          <div class="flex flex-col space-y-2">
            <button
              @click="reviewAppeal(appeal.id, 'approved')"
              :disabled="reviewingId === appeal.id"
              class="px-3 py-1.5 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-500 disabled:opacity-50 transition-all"
            >
              通过
            </button>
            <button
              @click="reviewAppeal(appeal.id, 'rejected')"
              :disabled="reviewingId === appeal.id"
              class="px-3 py-1.5 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-500 disabled:opacity-50 transition-all"
            >
              驳回
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
          <router-link
            :to="'/proctor/replay/' + appeal.exam_record_id"
            class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center space-x-1 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>查看监考回放</span>
          </router-link>
        </div>
      </div>
    </div>

    <div v-if="pagination.last_page > 1" class="flex items-center justify-center space-x-2">
      <button
        @click="goToPage(pagination.current_page - 1)"
        :disabled="pagination.current_page <= 1"
        class="btn-secondary text-sm py-2 px-3 disabled:opacity-50"
      >
        上一页
      </button>
      <span class="text-sm text-gray-500">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button
        @click="goToPage(pagination.current_page + 1)"
        :disabled="pagination.current_page >= pagination.last_page"
        class="btn-secondary text-sm py-2 px-3 disabled:opacity-50"
      >
        下一页
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import api from '../../api'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../composables/useToast'

const authStore = useAuthStore()
const { success: toastSuccess, error: toastError } = useToast()

const appeals = ref([])
const loading = ref(true)
const statusFilter = ref('')
const reviewingId = ref(null)
const reviewComments = reactive({})
const pagination = ref({ current_page: 1, last_page: 1 })

const isStudent = computed(() => authStore.user?.role === 'student')

onMounted(() => {
  fetchAppeals()
})

const fetchAppeals = async (page = 1) => {
  loading.value = true
  try {
    const params = { page, per_page: 10 }
    if (statusFilter.value) params.status = statusFilter.value
    const response = await api.get('/appeals', { params })
    appeals.value = response.data.appeals.data
    pagination.value = {
      current_page: response.data.appeals.current_page,
      last_page: response.data.appeals.last_page
    }
  } catch (e) {
    toastError('获取申诉列表失败')
  } finally {
    loading.value = false
  }
}

const goToPage = (page) => {
  fetchAppeals(page)
}

const reviewAppeal = async (appealId, status) => {
  reviewingId.value = appealId
  try {
    await api.post(`/appeals/${appealId}/review`, {
      status,
      review_comment: reviewComments[appealId] || ''
    })
    toastSuccess(status === 'approved' ? '申诉已通过，异常标记已改判' : '申诉已驳回')
    delete reviewComments[appealId]
    fetchAppeals(pagination.value.current_page)
  } catch (e) {
    toastError(e.response?.data?.message || '审核操作失败')
  } finally {
    reviewingId.value = null
  }
}

const statusBadgeClass = (status) => {
  const map = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

const statusLabel = (status) => {
  const map = { pending: '待审核', approved: '已通过', rejected: '已驳回' }
  return map[status] || status
}

const eventTypeBadgeClass = (type) => {
  const map = {
    screen_switch: 'bg-orange-100 text-orange-700',
    camera_disconnect: 'bg-red-100 text-red-700',
    idle: 'bg-yellow-100 text-yellow-700',
    network_recovery: 'bg-blue-100 text-blue-700'
  }
  return map[type] || 'bg-gray-100 text-gray-700'
}

const eventTypeLabel = (type) => {
  const map = { screen_switch: '切屏', camera_disconnect: '摄像头断开', idle: '长时间未操作', network_recovery: '网络恢复' }
  return map[type] || type
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
