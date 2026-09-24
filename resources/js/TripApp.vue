<script setup>
import { ref, onMounted } from 'vue'

// ── State ────────────────────────────────────────────────────────────────────
const trips = ref([])
const loading = ref(false)
const submitting = ref(false)
const errors = ref({})

const form = ref({
  traveler_name: '',
  destination: '',
  start_date: '',
  end_date: '',
  cost: '',
})

// ── Helpers ──────────────────────────────────────────────────────────────────
const today = () => new Date().toISOString().split('T')[0]

async function fetchTrips() {
  loading.value = true
  try {
    const res = await fetch('/api/trips')
    trips.value = await res.json()
  } finally {
    loading.value = false
  }
}

async function createTrip() {
  submitting.value = true
  errors.value = {}

  try {
    const res = await fetch('/api/trips', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(form.value),
    })

    const data = await res.json()

    if (!res.ok) {
      errors.value = data.errors ?? {}
      return
    }

    // Prepend the new trip so it shows up immediately, then re-fetch for order
    await fetchTrips()

    form.value = { traveler_name: '', destination: '', start_date: '', end_date: '', cost: '' }
  } finally {
    submitting.value = false
  }
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(fetchTrips)
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="mx-auto max-w-3xl space-y-10">

      <!-- Heading -->
      <h1 class="text-2xl font-semibold text-gray-900">Trips</h1>

      <!-- ── Create Form ── -->
      <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-6">
        <h2 class="text-base font-medium text-gray-800 mb-5">New trip</h2>

        <form @submit.prevent="createTrip" novalidate class="space-y-4">

          <!-- Traveler name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="traveler_name">
              Traveler name
            </label>
            <input
              id="traveler_name"
              v-model="form.traveler_name"
              type="text"
              maxlength="255"
              placeholder="e.g. Alice Smith"
              class="w-full rounded-md border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="errors.traveler_name ? 'border-red-400' : 'border-gray-300'"
            />
            <p v-if="errors.traveler_name" class="mt-1 text-xs text-red-500">
              {{ errors.traveler_name[0] }}
            </p>
          </div>

          <!-- Destination -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="destination">
              Destination
            </label>
            <input
              id="destination"
              v-model="form.destination"
              type="text"
              maxlength="255"
              placeholder="e.g. Tokyo"
              class="w-full rounded-md border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="errors.destination ? 'border-red-400' : 'border-gray-300'"
            />
            <p v-if="errors.destination" class="mt-1 text-xs text-red-500">
              {{ errors.destination[0] }}
            </p>
          </div>

          <!-- Dates row -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1" for="start_date">
                Start date
              </label>
              <input
                id="start_date"
                v-model="form.start_date"
                type="date"
                :min="today()"
                class="w-full rounded-md border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :class="errors.start_date ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="errors.start_date" class="mt-1 text-xs text-red-500">
                {{ errors.start_date[0] }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1" for="end_date">
                End date
              </label>
              <input
                id="end_date"
                v-model="form.end_date"
                type="date"
                :min="form.start_date || today()"
                class="w-full rounded-md border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :class="errors.end_date ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="errors.end_date" class="mt-1 text-xs text-red-500">
                {{ errors.end_date[0] }}
              </p>
            </div>
          </div>

          <!-- Cost -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="cost">
              Cost (USD)
            </label>
            <input
              id="cost"
              v-model="form.cost"
              type="number"
              min="0"
              max="99999.99"
              step="0.01"
              placeholder="0.00"
              class="w-full rounded-md border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="errors.cost ? 'border-red-400' : 'border-gray-300'"
            />
            <p v-if="errors.cost" class="mt-1 text-xs text-red-500">
              {{ errors.cost[0] }}
            </p>
          </div>

          <button
            type="submit"
            :disabled="submitting"
            class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
          >
            {{ submitting ? 'Saving…' : 'Create trip' }}
          </button>

        </form>
      </section>

      <!-- ── Trip List ── -->
      <section>
        <h2 class="text-base font-medium text-gray-800 mb-3">All trips</h2>

        <p v-if="loading" class="text-sm text-gray-500">Loading…</p>

        <p v-else-if="trips.length === 0" class="text-sm text-gray-500">
          No trips yet. Create one above.
        </p>

        <ul v-else class="space-y-3">
          <li
            v-for="trip in trips"
            :key="trip.id"
            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-5 flex flex-col gap-1"
          >
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-900">{{ trip.traveler_name }}</span>
              <span class="text-sm text-gray-500">${{ trip.cost }}</span>
            </div>
            <div class="text-sm text-gray-600">{{ trip.destination }}</div>
            <div class="text-xs text-gray-400">
              {{ trip.start_date }} → {{ trip.end_date }}
            </div>
          </li>
        </ul>
      </section>

    </div>
  </div>
</template>
