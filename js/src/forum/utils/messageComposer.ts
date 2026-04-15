import app from 'flarum/forum/app';

export const MESSAGE_COMPOSER_PATH = 'ext:flarum/messages/forum/components/MessageComposer';

function currentComposer(): any {
  return app.composer as any;
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

export function messageComposerMatchesDialog(dialog: any, composer: any = currentComposer()): boolean {
  return Boolean(
    composer?.bodyMatches?.(MESSAGE_COMPOSER_PATH, { replyingTo: dialog }) ||
    composer?.body?.attrs?.replyingTo === dialog ||
    composer?.composingMessageTo?.(dialog)
  );
}

export async function waitForVisibleMessageComposer(dialog: any, timeoutMs: number = 3000): Promise<boolean> {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    const composer = currentComposer();

    if (messageComposerMatchesDialog(dialog, composer) && composer?.isVisible?.()) {
      return true;
    }

    await delay(50);
  }

  return false;
}

export async function waitForComposerEditor(timeoutMs: number = 3000): Promise<boolean> {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    const composer = currentComposer();

    if (composer?.isVisible?.() && composer?.editor) {
      return true;
    }

    await delay(50);
  }

  const composer = currentComposer();

  if (!composer?.isVisible?.() || typeof composer?.editorReady !== 'function') {
    return Boolean(composer?.isVisible?.() && composer?.editor);
  }

  let timedOut = false;
  const timeoutHandle = window.setTimeout(() => {
    timedOut = true;
  }, timeoutMs);

  try {
    while (!timedOut && !composer?.editor) {
      await Promise.race([composer.editorReady(), delay(50)]);
    }
  } finally {
    window.clearTimeout(timeoutHandle);
  }

  return Boolean(composer?.isVisible?.() && composer?.editor);
}

export function applyComposerContent(initialContent: string, composer: any = currentComposer()): void {
  composer.fields.content(initialContent);

  const editor = composer.editor as any;
  if (editor?.el) {
    editor.el.value = initialContent;
    editor.moveCursorTo?.(initialContent.length);
    editor.focus?.();
  }
}
