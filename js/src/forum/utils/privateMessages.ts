import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';
import { displayCollectibleName } from './collectibles';

interface StartPrivateMessageOptions {
  initialContent?: string;
}

export interface PrivateMessageCollectibleContext {
  collectible?: any;
  collectibleId?: string | number | null;
  collectibleName?: string | null;
  rarity?: string | null;
  tokenId?: string | number | null;
  sourceDiscussionTitle?: string | null;
  sourcePostUrl?: string | null;
}

export function currentDiscussionTitle(): string | null {
  if (typeof document === 'undefined') {
    return null;
  }

  const selectors = [
    '.DiscussionHero-title',
    '.DiscussionPage-title',
    '.DiscussionPage h1',
    'main h1',
  ];

  for (const selector of selectors) {
    const value = document.querySelector(selector)?.textContent?.trim();

    if (value) {
      return value;
    }
  }

  return null;
}

function resolveCanSendAnyMessage(user: any): boolean | null {
  if (!user) {
    return null;
  }

  if (typeof user.canSendAnyMessage === 'function') {
    const value = user.canSendAnyMessage();

    if (typeof value === 'boolean') {
      return value;
    }
  }

  if (typeof user.attribute === 'function') {
    const value = user.attribute('canSendAnyMessage');

    if (typeof value === 'boolean') {
      return value;
    }
  }

  return null;
}

export function isPrivateMessagingAvailable(): boolean {
  if (typeof flarum === 'undefined') {
    return false;
  }

  return Boolean(flarum.extensions?.['flarum-messages']);
}

export function shouldShowPrivateMessageButton(targetUser: any): boolean {
  if (!targetUser || !isPrivateMessagingAvailable()) {
    return false;
  }

  const currentUser = app.session?.user;

  return !currentUser || currentUser.id() !== targetUser.id();
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForComposerReady(timeoutMs: number = 3000): Promise<boolean> {
  const composer = app.composer as any;
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    if (composer?.isVisible?.() && composer?.editor) {
      return true;
    }

    await delay(50);
  }

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

async function loadMessageComposer() {
  const userControlsModule = await import('flarum/forum/utils/UserControls');
  return userControlsModule.default ?? userControlsModule;
}

function resolveCollectibleName(context: PrivateMessageCollectibleContext): string | null {
  return context.collectible?.name?.() || context.collectibleName || null;
}

function resolveCollectibleRarity(context: PrivateMessageCollectibleContext): string | null {
  return context.collectible?.rarity?.() || context.rarity || null;
}

function resolveCollectibleTokenId(context: PrivateMessageCollectibleContext): string | number | null {
  return context.collectible?.tokenId?.() || context.tokenId || null;
}

function resolveCollectibleId(context: PrivateMessageCollectibleContext): string | number | null {
  return context.collectible?.id?.() || context.collectibleId || null;
}

export function buildCollectibleMessageContent(context: PrivateMessageCollectibleContext): string {
  const collectibleName = displayCollectibleName(resolveCollectibleName(context), resolveCollectibleId(context));
  const rarity = resolveCollectibleRarity(context);
  const tokenId = resolveCollectibleTokenId(context);
  const lines = [
    extractText(app.translator.trans('donk-aigc-collectibles.forum.messages.context_intro')),
    '',
    extractText(app.translator.trans('donk-aigc-collectibles.forum.messages.context_collectible', { name: collectibleName })),
  ];

  if (rarity) {
    const rarityLabel = extractText(
      app.translator.trans('donk-aigc-collectibles.forum.collectible.rarity_' + rarity)
    );
    lines.push(extractText(app.translator.trans('donk-aigc-collectibles.forum.messages.context_rarity', { rarity: rarityLabel })));
  }

  if (tokenId) {
    lines.push(extractText(app.translator.trans('donk-aigc-collectibles.forum.messages.context_token_id', { id: tokenId })));
  }

  if (context.sourceDiscussionTitle) {
    lines.push(
      extractText(
        app.translator.trans('donk-aigc-collectibles.forum.messages.context_source_discussion', {
          title: context.sourceDiscussionTitle,
        })
      )
    );
  }

  if (context.sourcePostUrl) {
    lines.push(
      extractText(app.translator.trans('donk-aigc-collectibles.forum.messages.context_source_post', { url: context.sourcePostUrl }))
    );
  }

  return lines.join('\n');
}

function applyInitialContent(initialContent: string) {
  app.composer.fields.content(initialContent);

  const editor = app.composer.editor as any;
  if (editor?.el) {
    editor.el.value = initialContent;
    editor.moveCursorTo?.(initialContent.length);
    editor.focus?.();
  }
}

export async function startPrivateMessage(targetUser: any, options: StartPrivateMessageOptions = {}): Promise<boolean> {
  if (!targetUser) {
    return false;
  }

  if (!isPrivateMessagingAvailable()) {
    app.alerts.show(
      { type: 'error' },
      app.translator.trans('donk-aigc-collectibles.forum.messages.unavailable')
    );

    return false;
  }

  const currentUser = app.session?.user;

  if (!currentUser) {
    app.modal.show(() => import('flarum/forum/components/LogInModal'));

    return false;
  }

  if (currentUser.id() === targetUser.id()) {
    app.alerts.show(
      { type: 'warning' },
      app.translator.trans('donk-aigc-collectibles.forum.messages.self_not_allowed')
    );

    return false;
  }

  if (resolveCanSendAnyMessage(currentUser) === false) {
    app.alerts.show(
      { type: 'error' },
      app.translator.trans('donk-aigc-collectibles.forum.messages.permission_denied')
    );

    return false;
  }

  try {
    const UserControls = await loadMessageComposer();
    const items = (UserControls as any).userControls(targetUser, null);

    if (!items?.has?.('sendMessage')) {
      throw new Error('flarum-messages sendMessage control is unavailable.');
    }

    const sendMessageControl = items.get('sendMessage');
    const onClick = sendMessageControl?.attrs?.onclick;

    if (typeof onClick !== 'function') {
      throw new Error('flarum-messages sendMessage control has no onclick handler.');
    }

    await Promise.resolve(onClick());

    if (options.initialContent && (await waitForComposerReady())) {
      applyInitialContent(options.initialContent);
    }

    return true;
  } catch (error) {
    if (typeof console !== 'undefined') {
      console.error('[donk-aigc-collectibles] failed to start private message composer', error);
    }

    app.alerts.show(
      { type: 'error' },
      app.translator.trans('donk-aigc-collectibles.forum.messages.unavailable')
    );

    return false;
  }
}
