const BARTER_THREAD_UPDATED_EVENT = 'donk-aigc-collectibles:barter-thread-updated';

export function emitBarterThreadUpdated(dialogId: string | number): void {
  if (typeof window === 'undefined') {
    return;
  }

  window.dispatchEvent(
    new CustomEvent(BARTER_THREAD_UPDATED_EVENT, {
      detail: { dialogId: String(dialogId) },
    })
  );
}

export function onBarterThreadUpdated(listener: (dialogId: string) => void): () => void {
  if (typeof window === 'undefined') {
    return () => {};
  }

  const handler = (event: Event) => {
    const dialogId = (event as CustomEvent<{ dialogId?: string }>).detail?.dialogId;

    if (dialogId) {
      listener(dialogId);
    }
  };

  window.addEventListener(BARTER_THREAD_UPDATED_EVENT, handler);

  return () => {
    window.removeEventListener(BARTER_THREAD_UPDATED_EVENT, handler);
  };
}
