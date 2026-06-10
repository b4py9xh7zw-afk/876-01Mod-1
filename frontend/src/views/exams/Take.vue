<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">{{ examPaper?.title }}</h1>
      <div class="text-lg">
        剩余时间: <span class="font-mono font-bold" :class="{'text-red-600': timeRemaining < 60}">{{ formatTime(timeRemaining) }}</span>
      </div>
    </div>

    <div v-if="proctorWarnings.length > 0" class="space-y-2">
      <div
        v-for="warning in proctorWarnings"
        :key="warning.id"
        class="flex items-center p-3 rounded-lg border"
        :class="warning.type === 'screen_switch' ? 'bg-red-50 border-red-200 text-red-800' : warning.type === 'camera_disconnect' ? 'bg-orange-50 border-orange-200 text-orange-800' : warning.type === 'idle' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' : 'bg-blue-50 border-blue-200 text-blue-800'"
      >
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-sm font-medium">{{ warning.message }}</span>
      </div>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="!loading && questions.length > 0" class="space-y-8">
      <div v-for="(question, index) in questions" :key="question.id" class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start mb-4">
          <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-2.5 py-0.5 rounded mr-3">{{ index + 1 }}</span>
          <div class="flex-1">
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ question.title }}</h3>
            <p class="text-sm text-gray-500 mb-3">分值: {{ question.score }}分 | 题型: {{ questionTypeLabel(question.type) }}</p>
            <div class="space-y-2">
              <template v-if="question.type === 'single_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === key}">
                  <input type="radio" :name="'question_' + question.id" :value="key" v-model="answers[question.id]" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else-if="question.type === 'true_false'">
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'true'}">
                  <input type="radio" :name="'question_' + question.id" value="true" v-model="answers[question.id]" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">正确</span>
                </label>
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'false'}">
                  <input type="radio" :name="'question_' + question.id" value="false" v-model="answers[question.id]" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">错误</span>
                </label>
              </template>
              <template v-else-if="question.type === 'multiple_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': (answers[question.id] || []).includes(key)}">
                  <input type="checkbox" :value="key" @change="toggleMultipleChoice(question.id, key)" :checked="(answers[question.id] || []).includes(key)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else>
                <textarea v-model="answers[question.id]" rows="3" class="w-full border border-gray-300 rounded-md p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="请输入答案"></textarea>
              </template>
            </div>
          </div>
        </div>
      </div>
      <div class="flex justify-between">
        <router-link to="/exams" class="bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400">返回</router-link>
        <button @click="submitExam" :disabled="submitting" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ submitting ? '提交中...' : '提交答卷' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'

const route = useRoute()
const router = useRouter()
const { alert } = useModal()
const examPaper = ref(null)
const examRecord = ref(null)
const questions = ref([])
const answers = ref({})
const loading = ref(true)
const submitting = ref(false)
const timeRemaining = ref(0)
let timer = null
let idleTimer = null
let warningIdCounter = 0
const proctorWarnings = ref([])
const idleThreshold = 60000
let lastActivityTime = Date.now()
let cameraStream = null

const recordProctorEvent = async (eventType, detail = null) => {
  if (!examRecord.value?.id) return
  try {
    await api.post('/proctor/events', {
      exam_record_id: examRecord.value.id,
      event_type: eventType,
      event_time: new Date().toISOString(),
      detail: detail
    })
  } catch (e) {
    console.error('Failed to record proctor event:', e)
  }
}

const addWarning = (type, message) => {
  const id = ++warningIdCounter
  proctorWarnings.value.push({ id, type, message })
  setTimeout(() => {
    const idx = proctorWarnings.value.findIndex(w => w.id === id)
    if (idx > -1) proctorWarnings.value.splice(idx, 1)
  }, 5000)
}

const showActivity = () => {
  lastActivityTime = Date.now()
}

const checkIdle = () => {
  const now = Date.now()
  if (now - lastActivityTime > idleThreshold) {
    const idleSeconds = Math.floor((now - lastActivityTime) / 1000)
    recordProctorEvent('idle', `用户空闲 ${idleSeconds} 秒`)
    addWarning('idle', `检测到您已 ${idleSeconds} 秒未操作`)
    lastActivityTime = now
  }
}

const handleVisibilityChange = () => {
  if (document.hidden) {
    recordProctorEvent('screen_switch', '用户切换到其他标签页或窗口')
    addWarning('screen_switch', '检测到您切换了页面，请勿在考试期间离开当前页面')
  }
}

const handleWindowBlur = () => {
  recordProctorEvent('screen_switch', '窗口失去焦点')
}

const handleOnline = () => {
  recordProctorEvent('network_recover', '网络连接恢复')
  addWarning('network_recover', '网络连接已恢复')
}

const handleOffline = () => {
  addWarning('network_recover', '网络连接断开，请检查网络')
}

const initCameraCheck = async () => {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: true })
    cameraStream.getVideoTracks().forEach(track => {
      track.addEventListener('ended', () => {
        recordProctorEvent('camera_disconnect', '摄像头流被关闭')
        addWarning('camera_disconnect', '检测到摄像头已关闭，请保持摄像头开启')
      })
      track.addEventListener('mute', () => {
        recordProctorEvent('camera_disconnect', '摄像头被静音/禁用')
        addWarning('camera_disconnect', '检测到摄像头被禁用，请保持摄像头开启')
      })
    })
  } catch (e) {
    if (e.name === 'NotAllowedError' || e.name === 'NotFoundError') {
      recordProctorEvent('camera_disconnect', `摄像头无法访问: ${e.name}`)
      addWarning('camera_disconnect', '无法访问摄像头，考试要求摄像头处于开启状态')
    }
  }
}

onMounted(async () => {
  try {
    const response = await api.get(`/exams/${route.params.id}/questions`)
    examPaper.value = response.data.exam_paper
    examRecord.value = response.data.exam_record
    questions.value = response.data.questions
    timeRemaining.value = examPaper.value.total_time * 60
    startTimer()

    lastActivityTime = Date.now()
    idleTimer = setInterval(checkIdle, 30000)
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('blur', handleWindowBlur)
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    document.addEventListener('mousemove', showActivity)
    document.addEventListener('keydown', showActivity)
    document.addEventListener('click', showActivity)
    document.addEventListener('scroll', showActivity)
    document.addEventListener('touchstart', showActivity)

    initCameraCheck()
  } catch (e) {
    alert('获取考试信息失败', '考试加载失败', 'error')
    router.push('/exams')
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (idleTimer) clearInterval(idleTimer)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  window.removeEventListener('blur', handleWindowBlur)
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
  document.removeEventListener('mousemove', showActivity)
  document.removeEventListener('keydown', showActivity)
  document.removeEventListener('click', showActivity)
  document.removeEventListener('scroll', showActivity)
  document.removeEventListener('touchstart', showActivity)
  if (cameraStream) {
    cameraStream.getTracks().forEach(track => track.stop())
  }
})

const startTimer = () => {
  timer = setInterval(() => {
    if (timeRemaining.value > 0) {
      timeRemaining.value--
    } else {
      clearInterval(timer)
      submitExam()
    }
  }, 1000)
}

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const questionTypeLabel = (type) => {
  const labels = {
    single_choice: '单选题',
    multiple_choice: '多选题',
    true_false: '判断题',
    fill_blank: '填空题',
    essay: '问答题'
  }
  return labels[type] || type
}

const toggleMultipleChoice = (questionId, key) => {
  if (!answers.value[questionId]) {
    answers.value[questionId] = []
  }
  const index = answers.value[questionId].indexOf(key)
  if (index === -1) {
    answers.value[questionId].push(key)
  } else {
    answers.value[questionId].splice(index, 1)
  }
}

const submitExam = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    const answerData = Object.entries(answers.value).map(([questionId, answer]) => ({
      question_id: parseInt(questionId),
      answer: Array.isArray(answer) ? answer.join(',') : answer
    }))
    const response = await api.post(`/exams/${route.params.id}/submit`, {
      exam_record_id: examRecord.value.id,
      answers: answerData
    })
    alert(`考试完成！得分: ${response.data.score}`, '考试完成', 'success')
    router.push('/records')
  } catch (e) {
    alert(e.response?.data?.message || '提交失败', '提交失败', 'error')
  } finally {
    submitting.value = false
  }
}
</script>
