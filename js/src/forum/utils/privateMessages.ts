import app from 'flarum/forum/app';

interface StartPrivateMessageOptions {
  initialContent?: string;
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

async function waitForComposerReady(timeoutMs: number = 3000): Promise<boolean> {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    if (app.composer?.isVisible?.()) {
      return true;
    }

    await new Promise((resolve) => setTimeout(resolve, 50));
  }

  return false;
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
    const userControlsModule = await import('flarum/forum/utils/UserControls');
    const UserControls = userControlsModule.default ?? userControlsModule;
    const items = UserControls.userControls(targetUser, null);

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
      app.composer.fields.content(options.initialContent);
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
