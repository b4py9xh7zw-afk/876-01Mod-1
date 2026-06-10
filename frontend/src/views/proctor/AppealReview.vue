<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">申诉复核</h1>
        <p class="text-sm text-gray-500 mt-1">审核学生提交的申诉，可改判分数和清除异常标记</p>
      </div>
      <div class="flex gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="currentTab = tab.value"
          class="px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
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
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <div v-else-if="appeals.length === 0" class="text-center py-16 text-gray-500">
      <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="font-medium text-lg">
        {{ currentTab === 'pending' ? '暂无待复核的申诉' : currentTab === 'approved' ? '暂无通过的申诉' : currentTab === 'rejected' ? '暂无驳回的申诉' : '暂无申诉记录' }}
      </p>
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="appeal in appeals"
        :key="appeal.id"
        class="card-base p-5 border border-gray-100 hover:shadow-md transition-shadow duration-200"
      >
        <div class="flex flex-col lg:flex-row lg:items-start gap-5">
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
              <div class="bg-indigo-50/50 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-1">考生</p>
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                    <span class="text-indigo-600 font-bold text-sm">
                      {{ (appeal.user?.real_name || appeal.user?.username || 'U').charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900 text-sm">{{ appeal.user?.real_name || '-' }}</p>
                    <p class="text-xs text-gray-500">{{ appeal.user?.username || '-' }}</p>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500 mb-1">试卷 / 得分</p>
                <p class="font-medium text-gray-900 text-sm">{{ appeal.exam_record?.exam_paper?.title || '-' }}</p>
                <p class="text-sm">
                  当前得分：
                  <span class="font-bold" :class="appeal.exam_record?.score >= 60 ? 'text-green-600' : 'text-red-600'">
                    {{ appeal.exam_record?.score ?? '-' }} 分
                  </span>
                </p>
              </div>
            </div>

            <div v-if="appeal.proctor_event" class="mb-3">
              <p class="text-xs text-gray-500 mb-1">异常事件详情</p>
              <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                <p class="text-sm text-gray-600">
                  <span class="text-gray-400 mr-2">[{{ formatDateTime(appeal.proctor_event.event_time) }}]</span>
                  {{ appeal.proctor_event.detail || getEventTypeLabel(appeal.proctor_event.event_type) }}
                </p>
              </div>
            </div>

            <div class="mb-3">
              <p class="text-xs text-gray-500 mb-1">学生申诉说明</p>
              <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                <p class="text-sm text-gray-700">{{ appeal.explanation }}</p>
              </div>
            </div>

            <div v-if="appeal.screenshots && appeal.screenshots.length > 0" class="mb-3">
              <p class="text-xs text-gray-500 mb-2">学生上传的截图</p>
              <div class="flex flex-wrap gap-2">
                <img
                  v-for="(img, idx) in appeal.screenshots"
                  :key="idx"
                  :src="img"
                  class="w-20 h-20 object-cover rounded-md border border-gray-200 cursor-pointer hover:opacity-80 transition-opacity"
                  @click="previewImg(img)"
                />
              </div>
            </div>

            <div
              v-if="appeal.status !== 'pending' && appeal.review_comment"
              class="mt-3 pt-3 border-t border-gray-100"
            >
              <p class="text-xs text-gray-500 mb-1">
                复核人：{{ appeal.reviewer?.real_name || appeal.reviewer?.username || '未知' }}
                <span class="ml-2">{{ formatDateTime(appeal.reviewed_at) }}</span>
              </p>
              <div
                class="rounded-lg p-3 border-l-4"
                :class="appeal.status === 'approved' ? 'bg-green-50 border-green-400' : 'bg-red-50 border-red-400'"
              >
                <p class="text-sm font-medium" :class="appeal.status === 'approved' ? 'text-green-700' : 'text-red-700'">
                  复核意见：{{ appeal.review_comment }}
                </p>
              </div>
            </div>
          </div>

          <div class="w-full lg:w-80 flex-shrink-0 space-y-3">
            <router-link
              :to="`/proctor/record/${appeal.exam_record_id}`"
              class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium rounded-lg text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              查看监考回放
            </router-link>

            <div v-if="appeal.status === 'pending'">
              <button
                @click="openReviewModal(appeal)"
                class="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-sm"
              >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                处理申诉
              </button>
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
        <div v-if="showReviewModal" class="fixed inset-0 z-[90]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
          <div class="fixed inset-0 z-[90] bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="closeReviewModal"></div>
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
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl border border-gray-100">
                  <div class="px-6 pb-6 pt-6">
                    <h3 class="text-lg font-bold leading-6 text-gray-900 tracking-tight mb-4">处理申诉</h3>

                    <div v-if="currentAppeal" class="space-y-4">
                      <div class="bg-gray-50 rounded-lg p-3 text-sm">
                        <div class="flex justify-between items-center mb-1">
                          <span class="text-gray-500">考生</span>
                          <span class="font-medium">{{ currentAppeal.user?.real_name || currentAppeal.user?.username }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-1">
                          <span class="text-gray-500">试卷</span>
                          <span class="font-medium">{{ currentAppeal.exam_record?.exam_paper?.title }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                          <span class="text-gray-500">当前分数</span>
                          <span class="font-bold" :class="currentAppeal.exam_record?.score >= 60 ? 'text-green-600' : 'text-red-600'">
                            {{ currentAppeal.exam_record?.score }} 分
                          </span>
                        </div>
                      </div>

                      <div class="space-y-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">复核结果 <span class="text-red-500">*</span></label>
                          <div class="flex gap-3">
                            <label
                              class="flex-1 flex items-center justify-center p-3 border-2 rounded-lg cursor-pointer transition-colors"
                              :class="reviewForm.status === 'approved' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-green-300'"
                            >
                              <input type="radio" v-model="reviewForm.status" value="approved" class="sr-only">
                              <svg class="w-5 h-5 mr-2" :class="reviewForm.status === 'approved' ? 'text-green-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                              </svg>
                              <span class="font-medium" :class="reviewForm.status === 'approved' ? 'text-green-700' : 'text-gray-600'">申诉通过</span>
                            </label>
                            <label
                              class="flex-1 flex items-center justify-center p-3 border-2 rounded-lg cursor-pointer transition-colors"
                              :class="reviewForm.status === 'rejected' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-red-300'"
                            >
                              <input type="radio" v-model="reviewForm.status" value="rejected" class="sr-only">
                              <svg class="w-5 h-5 mr-2" :class="reviewForm.status === 'rejected' ? 'text-red-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                              <span class="font-medium" :class="reviewForm.status === 'rejected' ? 'text-red-700' : 'text-gray-600'">驳回申诉</span>
                            </label>
                          </div>
                        </div>

                        <div v-if="reviewForm.status === 'approved'">
                          <label class="block text-sm font-medium text-gray-700 mb-1">新分数 (申诉通过时可改判)</label>
                          <div class="flex items-center gap-2">
                            <input
                              type="number"
                              v-model.number="reviewForm.new_score"
                              min="0"
                              max="100"
                              step="0.5"
                              class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                              :placeholder="'原分数: ' + currentAppeal.exam_record?.score"
                            />
                            <span class="text-gray-500 text-sm">分 (0-100)</span>
                          </div>
                          <p class="text-xs text-gray-400 mt-1">留空则不修改分数</p>
                        </div>

                        <div v-if="reviewForm.status === 'approved'" class="flex items-center">
                          <input
                            type="checkbox"
                            id="clear_anomaly"
                            v-model="reviewForm.clear_anomaly"
                            class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                          />
                          <label for="clear_anomaly" class="ml-2 text-sm text-gray-700">
                            同时清除该考试的异常标记
                          </label>
                        </div>

                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">复核意见 <span class="text-red-500">*</span></label>
                          <textarea
                            v-model="reviewForm.review_comment"
                            rows="3"
                            class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="请填写复核意见，学生将看到此内容..."
                          ></textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row-reverse sm:gap-3">
                    <button
                      type="button"
                      :disabled="submittingReview"
                      class="inline-flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all sm:w-auto min-w-[100px] bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50"
                      @click="submitReview"
                    >
                      {{ submittingReview ? '提交中...' : '确认提交' }}
                    </button>
                    <button
                      type="button"
                      :disabled="submittingReview"
                      class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all sm:mt-0 sm:w-auto min-w-[100px] disabled:opacity-50"
                      @click="closeReviewModal"
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
import { ref, computed, onMounted, watch } from 'vue'
import api from '../../api'
import { useToast } from '../../composables/useToast'

const { success: toastSuccess, error: toastError } = useToast()

const loading = ref(true)
const appeals = ref([])
const allAppeals = ref([])
const currentTab = ref('pending')
const previewUrl = ref(null)
const showReviewModal = ref(false)
const currentAppeal = ref(null)
const submittingReview = ref(false)
const counts = ref({ pending: 0, approved: 0, rejected: 0, all: 0 })

const reviewForm = ref({
  status: '',
  new_score: null,
  clear_anomaly: false,
  review_comment: ''
})

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
  { label: '待审核', value: 'pending', count: counts.value.pending },
  { label: '已通过', value: 'approved', count: counts.value.approved },
  { label: '已驳回', value: 'rejected', count: counts.value.rejected },
  { label: '全部', value: 'all', count: counts.value.all }
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

const openReviewModal = (appeal) => {
  currentAppeal.value = appeal
  reviewForm.value = {
    status: '',
    new_score: null,
    clear_anomaly: false,
    review_comment: ''
  }
  showReviewModal.value = true
}

const closeReviewModal = () => {
  if (submittingReview.value) return
  showReviewModal.value = false
  currentAppeal.value = null
}

const submitReview = async () => {
  if (!reviewForm.value.status) {
    toastError('请选择复核结果')
    return
  }
  if (!reviewForm.value.review_comment || reviewForm.value.review_comment.trim().length < 2) {
    toastError('请填写复核意见')
    return
  }
  if (!currentAppeal.value) return

  submittingReview.value = true
  try {
    const payload = {
      status: reviewForm.value.status,
      review_comment: reviewForm.value.review_comment
    }
    if (reviewForm.value.status === 'approved') {
      if (reviewForm.value.new_score !== null && reviewForm.value.new_score !== '') {
        const score = Number(reviewForm.value.new_score)
        if (score < 0 || score > 100) {
          toastError('分数必须在 0-100 之间')
          return
        }
        payload.new_score = score
      }
      payload.clear_anomaly = reviewForm.value.clear_anomaly
    }

    await api.post(`/proctor/appeals/${currentAppeal.value.id}/review`, payload)
    toastSuccess('复核结果已提交')
    showReviewModal.value = false
    loadAppeals()
  } catch (e) {
    toastError(e.response?.data?.message || '提交失败')
  } finally {
    submittingReview.value = false
  }
}

const loadAppeals = async () => {
  loading.value = true
  try {
    const pendingRes = await api.get('/proctor/appeals/pending')
    const pendingList = pendingRes.data.appeals.data || pendingRes.data.appeals || []
    counts.value.pending = pendingList.length

    const allRes = await api.get('/proctor/appeals/all')
    const list = allRes.data.appeals.data || allRes.data.appeals || []
    allAppeals.value = list
    counts.value.all = list.length
    counts.value.approved = list.filter(a => a.status === 'approved').length
    counts.value.rejected = list.filter(a => a.status === 'rejected').length

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
