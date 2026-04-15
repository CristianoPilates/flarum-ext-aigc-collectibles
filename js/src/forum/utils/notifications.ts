import app from 'flarum/forum/app';

type EventHandler = (data: any) => void;

const listeners: Record<string, EventHandler[]> = {};

let socket: WebSocket | null = null;
let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
let reconnectAttempts = 0;
const MAX_RECONNECT_ATTEMPTS = 5;
const BASE_RECONNECT_DELAY = 1000;

export function subscribe(event: string, handler: EventHandler): void {
  if (!listeners[event]) {
    listeners[event] = [];
  }
  listeners[event].push(handler);
}

export function unsubscribe(event: string, handler: EventHandler): void {
  if (!listeners[event]) return;
  listeners[event] = listeners[event].filter((h) => h !== handler);
}

function dispatch(event: string, data: any): void {
  const handlers = listeners[event];
  if (!handlers) return;
  handlers.forEach((handler) => {
    try {
      handler(data);
    } catch (e) {
      console.error('[aigc-collectibles] WebSocket handler error:', e);
    }
  });
}

export function connect(): void {
  const wsUrl = app.forum.attribute<string>('donk-aigc-collectibles.ws-url');
  if (!wsUrl) return;

  if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
    return;
  }

  try {
    socket = new WebSocket(wsUrl);

    socket.onopen = () => {
      reconnectAttempts = 0;

      // Authenticate with session token if available
      const sessionId = (app.session as any)?.token;
      if (sessionId && socket) {
        socket.send(JSON.stringify({ type: 'auth', token: sessionId }));
      }
    };

    socket.onmessage = (event) => {
      try {
        const message = JSON.parse(event.data);
        if (message.event) {
          dispatch(message.event, message.data || {});
        }
      } catch (e) {
        // Ignore non-JSON messages
      }
    };

    socket.onclose = () => {
      socket = null;
      scheduleReconnect();
    };

    socket.onerror = () => {
      // onclose will be called after onerror
    };
  } catch (e) {
    console.error('[aigc-collectibles] WebSocket connection error:', e);
  }
}

function scheduleReconnect(): void {
  if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) return;
  if (reconnectTimer) return;

  const delay = BASE_RECONNECT_DELAY * Math.pow(2, reconnectAttempts);
  reconnectAttempts++;

  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    connect();
  }, delay);
}

export function disconnect(): void {
  if (reconnectTimer) {
    clearTimeout(reconnectTimer);
    reconnectTimer = null;
  }
  if (socket) {
    socket.close();
    socket = null;
  }
}
