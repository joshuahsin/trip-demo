import { createApp } from 'vue'
import TripApp from './TripApp.vue'

const el = document.getElementById('app')
if (el) createApp(TripApp).mount(el)
