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

function getBroadcastClient(): Pusher | null {
  try {
    const config = useRuntimeConfig()
    const apiBase = (config.public.apiBase as string).replace(/\/$/, '')
    const token = useCookie<string | null>('auth_token')
    if (!token?.value) return null

    const auth = {
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
    }
    const authEndpoint = `${apiBase}/api/broadcasting/auth`

    // Optional: use Pusher when explicitly enabled and key is set
    const usePusher = config.public.usePusher as boolean
    const pusherKey = (config.public.pusherKey as string) || ''
    if (usePusher && pusherKey) {
      const cluster = (config.public.pusherCluster as string) || 'mt1'
      return new Pusher(pusherKey, {
        cluster,
        authEndpoint,
        auth,
      })
    }

    // Main: use Reverb (Pusher protocol, self-hosted)
    const reverbKey = (config.public.reverbKey as string) || ''
    if (!reverbKey) return null

    const reverbHost = config.public.reverbHost as string
    const reverbPort = config.public.reverbPort as number
    const reverbScheme = (config.public.reverbScheme as string) || 'http'
    const forceTLS = reverbScheme === 'https'

    return new Pusher(reverbKey, {
      cluster: 'reverb', // required by pusher-js; ignored when wsHost is set
      wsHost: reverbHost,
      wsPort: reverbPort,
      wssPort: reverbPort,
      forceTLS,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint,
      auth,
    })
  } catch {
    return null
  }
}

/**
 * Subscribe to real-time progress for the given job IDs (Reverb by default, or Pusher if enabled).
 * Calls onProgress whenever a progress event is received for any of those jobs.
 * Returns an unsubscribe function.
 */
export function useJobProgress(
  jobIds: number[],
  onProgress: (payload: JobProgressPayload) => void,
  onSync?: () => void
): () => void {
  const client = getBroadcastClient()
  const channels: {
    id: number
    channelName: string
    channel: ReturnType<Pusher['subscribe']>
    onProgressEvent: (data: JobProgressPayload) => void
    onSubscribed: () => void
  }[] = []

  if (!client || !jobIds.length) {
    return () => {}
  }

  const onConnected = () => {
    if (onSync) onSync()
  }
  client.connection.bind('connected', onConnected)

  for (const id of jobIds) {
    const channelName = `private-measurement_job.${id}`
    const channel = client.subscribe(channelName)
    const onProgressEvent = (data: JobProgressPayload) => {
      if (data && typeof data.id === 'number') onProgress(data)
    }
    const onSubscribed = () => {
      if (onSync) onSync()
    }
    channel.bind('progress', onProgressEvent)
    channel.bind('pusher:subscription_succeeded', onSubscribed)
    channels.push({ id, channelName, channel, onProgressEvent, onSubscribed })
  }

  return () => {
    for (const { channelName, channel, onProgressEvent, onSubscribed } of channels) {
      channel.unbind('progress', onProgressEvent)
      channel.unbind('pusher:subscription_succeeded', onSubscribed)
      client!.unsubscribe(channelName)
    }
    client.connection.unbind('connected', onConnected)
    client.disconnect()
  }
}
