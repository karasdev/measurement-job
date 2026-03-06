import Pusher from 'pusher-js'

export interface JobProgressPayload {
  id: number
  status: string
  progress_percent: number
  rows_processed: number
  execution_time_ms: number | null
  memory_used_bytes: number | null
  error_message: string | null
  completed_at: string | null
}

let pusher: Pusher | null = null

function getPusher(): Pusher | null {
  const config = useRuntimeConfig()
  const key = config.public.pusherKey as string
  const cluster = (config.public.pusherCluster as string) || 'mt1'
  if (!key) return null
  if (!pusher) {
    pusher = new Pusher(key, { cluster })
  }
  return pusher
}

/**
 * Subscribe to real-time progress for the given job IDs.
 * Calls onProgress whenever a progress event is received for any of those jobs.
 * Returns an unsubscribe function.
 */
export function useJobProgress(
  jobIds: number[],
  onProgress: (payload: JobProgressPayload) => void
): () => void {
  const client = getPusher()
  const channels: { id: number; channelName: string; channel: ReturnType<Pusher['subscribe']> }[] = []

  if (!client || !jobIds.length) {
    return () => {}
  }

  for (const id of jobIds) {
    const channelName = `measurement_job.${id}`
    const channel = client.subscribe(channelName)
    channel.bind('progress', (data: JobProgressPayload) => {
      if (data && typeof data.id === 'number') onProgress(data)
    })
    channels.push({ id, channelName, channel })
  }

  return () => {
    for (const { channelName, channel } of channels) {
      channel.unbind('progress')
      client.unsubscribe(channelName)
    }
  }
}
