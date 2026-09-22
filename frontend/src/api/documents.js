import client from './client'

export function listDocuments() {
  return client.get('/documents').then((res) => res.data.data)
}

export function pollDocumentStatus() {
  return client.get('/documents/status').then((res) => res.data.data)
}

export function uploadDocument({ title, category, file }, onUploadProgress) {
  const formData = new FormData()
  formData.append('title', title)
  formData.append('category', category)
  formData.append('file', file)

  return client.post('/documents', formData, { onUploadProgress }).then((res) => res.data.data)
}

export function retryDocument(id) {
  return client.post(`/documents/${id}/retry`).then((res) => res.data.data)
}
