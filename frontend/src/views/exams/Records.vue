<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">我的成绩</h1>
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="records.length === 0" class="text-center py-8 text-gray-500">
      暂无考试记录
    </div>
    <div v-else class="bg-white shadow overflow-hidden sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">试卷</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">得分</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">异常</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">考试时间</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="record in records" :key="record.id">
            <td class="px-6 py-4 whitespace-nowrap">{{ record.exam_paper?.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap font-bold" :class="{'text-green-600': record.score >= 60, 'text-red-600': record.score < 60}">{{ record.score }} 分</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {{ record.status === 'graded' ? '已评分' : record.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span v-if="record.has_anomaly" class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full"
                :class="anomalyBadgeClass(record.anomaly_status)">
                {{ anomalyLabel(record.anomaly_status) }}
              </span>
              <span v-else class="text-xs text-gray-400">正常</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(record.created_at).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm space-x-3">
              <router-link
                v-if="record.has_anomaly"
                :to="'/proctor/replay/' + record.id"
                class="text-indigo-600 hover:text-indigo-800 font-medium transition-colors"
              >
                监考回放
              </router-link>
              <router-link
                v-if="record.has_anomaly && record.anomaly_status === 'flagged'"
                :to="'/proctor/replay/' + record.id"
                class="text-orange-600 hover:text-orange-800 font-medium transition-colors"
              >
                申诉
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const records = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/exams/records')
    records.value = response.data.records.data
  } catch (e) {
    console.error('Failed to fetch records:', e)
  } finally {
    loading.value = false
  }
})

const anomalyBadgeClass = (status) => {
  const map = {
    flagged: 'bg-red-100 text-red-700',
    overridden: 'bg-blue-100 text-blue-700',
    none: 'bg-green-100 text-green-700'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

const anomalyLabel = (status) => {
  const map = {
    flagged: '有异常',
    overridden: '已改判',
    none: '正常'
  }
  return map[status] || status
}
</script>
