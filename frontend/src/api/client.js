import axios from 'axios'

// Every backend call goes through this one Axios instance — nothing else
// in the frontend should know the API's base URL or talk to it directly.
const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
})

export default client
