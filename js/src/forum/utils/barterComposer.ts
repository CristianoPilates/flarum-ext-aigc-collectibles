import app from 'flarum/forum/app';
import Stream from 'flarum/common/utils/Stream';
import type BarterProposal from '../models/BarterProposal';
import { emitBarterThreadUpdated } from './barterEvents';
import { transText } from './i18n';

const MESSAGE_COMPOSER_PATH = 'ext:flarum/messages/forum/components/MessageComposer';

export interface BarterAsset {
  id: number;
  assetType: 'collectible' | 'blind_box';
  kind?: 'collectible' | 'blind_box';
  name?: string | null;
  rarity?: string | null;
  status?: string | null;
  tokenId?: number | null;
  ipfsCid?: string | null;
  metadataCid?: string | null;
  type?: string | null;
  seed?: string | null;
  budget?: number | null;
  ownerUserId: number;
}

export interface BarterAssetBucket {
  collectibles: BarterAsset[];
  blindBoxes: BarterAsset[];
}

export interface BarterAssetsPayload {
  threadType: string;
  threadId: number;
  actorUserId: number;
  counterpartyUserId: number;
  yours: BarterAssetBucket;
  theirs: BarterAssetBucket;
}

interface BarterComposerFields {
  barterEnabled: Stream<boolean>;
  barterExpanded: Stream<boolean>;
  barterLoading: Stream<boolean>;
  barterAssets: Stream<BarterAssetsPayload | null>;
  barterError: Stream<string | null>;
  barterValidationError: Stream<string | null>;
  barterMySelections: Stream<string[]>;
  barterTheirSelections: Stream<string[]>;
  barterReplacesProposalId: Stream<number | null>;
  barterLoadedDialogId: Stream<string | null>;
}

export function optionToken(asset: Pick<BarterAsset, 'assetType' | 'id'>): string {
  return `${asset.assetType}:${asset.id}`;
}

export function ensureBarterComposerFields(composer: any): BarterComposerFields {
  composer.fields.barterEnabled = composer.fields.barterEnabled || Stream(false);
  composer.fields.barterExpanded = composer.fields.barterExpanded || Stream(false);
  composer.fields.barterLoading = composer.fields.barterLoading || Stream(false);
  composer.fields.barterAssets = composer.fields.barterAssets || Stream<BarterAssetsPayload | null>(null);
  composer.fields.barterError = composer.fields.barterError || Stream<string | null>(null);
  composer.fields.barterValidationError = composer.fields.barterValidationError || Stream<string | null>(null);
  composer.fields.barterMySelections = composer.fields.barterMySelections || Stream<string[]>([]);
  composer.fields.barterTheirSelections = composer.fields.barterTheirSelections || Stream<string[]>([]);
  composer.fields.barterReplacesProposalId = composer.fields.barterReplacesProposalId || Stream<number | null>(null);
  composer.fields.barterLoadedDialogId = composer.fields.barterLoadedDialogId || Stream<string | null>(null);

  return composer.fields as BarterComposerFields;
}

export function clearBarterComposer(composer: any, keepAssets: boolean = false): void {
  const fields = ensureBarterComposerFields(composer);

  fields.barterEnabled(false);
  fields.barterExpanded(false);
  fields.barterError(null);
  fields.barterValidationError(null);
  fields.barterMySelections([]);
  fields.barterTheirSelections([]);
  fields.barterReplacesProposalId(null);

  if (!keepAssets) {
    fields.barterAssets(null);
    fields.barterLoadedDialogId(null);
  }
}

export function hasBarterDraft(composer: any): boolean {
  const fields = ensureBarterComposerFields(composer);

  return (
    fields.barterEnabled() ||
    fields.barterMySelections().length > 0 ||
    fields.barterTheirSelections().length > 0 ||
    fields.barterReplacesProposalId() !== null
  );
}

export function barterSelectionCounts(composer: any): { yours: number; theirs: number } {
  const fields = ensureBarterComposerFields(composer);

  return {
    yours: fields.barterMySelections().length,
    theirs: fields.barterTheirSelections().length,
  };
}

export async function loadBarterAssets(composer: any, dialog: any, force: boolean = false): Promise<BarterAssetsPayload | null> {
  const fields = ensureBarterComposerFields(composer);
  const dialogId = dialog?.id?.();
  const recipient = dialog?.recipient?.();
  const recipientId = recipient?.id?.();

  if (!dialogId || !recipientId) {
    fields.barterAssets(null);
    fields.barterLoadedDialogId(null);
    return null;
  }

  if (!force && fields.barterLoadedDialogId() === String(dialogId) && fields.barterAssets()) {
    return fields.barterAssets();
  }

  fields.barterLoading(true);
  fields.barterError(null);
  fields.barterValidationError(null);
  m.redraw();

  try {
    const response: any = await app.request({
      method: 'GET',
      url: `${app.forum.attribute('apiUrl')}/barter-assets`,
      params: {
        filter: {
          threadType: 'dialog',
          threadId: dialogId,
          counterpartyUserId: recipientId,
        },
      },
    });

    const payload = response?.data || null;
    fields.barterAssets(payload);
    fields.barterLoadedDialogId(String(dialogId));

    return payload;
  } catch (error: any) {
    fields.barterError(error?.response?.errors?.[0]?.detail || transText('donk-aigc-collectibles.forum.barter.load_failed'));
    fields.barterAssets(null);
    fields.barterLoadedDialogId(null);
    return null;
  } finally {
    fields.barterLoading(false);
    m.redraw();
  }
}

export function primeBarterComposerFromProposal(composer: any, proposal: BarterProposal): void {
  const fields = ensureBarterComposerFields(composer);
  const actorId = String(app.session.user?.id?.() || '');

  fields.barterEnabled(true);
  fields.barterExpanded(true);
  fields.barterError(null);
  fields.barterValidationError(null);
  fields.barterReplacesProposalId(Number(proposal.id()));

  const mySelections: string[] = [];
  const theirSelections: string[] = [];

  const proposalItems = (proposal.items?.() || []).filter((item: any): item is NonNullable<typeof item> => Boolean(item));

  for (const item of proposalItems) {
    const token = `${item.assetType?.()}:${item.assetId?.()}`;

    if (String(item.ownerUserId?.()) === actorId) {
      mySelections.push(token);
    } else {
      theirSelections.push(token);
    }
  }

  fields.barterMySelections(mySelections);
  fields.barterTheirSelections(theirSelections);
}

export function validateBarterComposer(composer: any): string | null {
  const fields = ensureBarterComposerFields(composer);

  if (!fields.barterEnabled()) {
    fields.barterValidationError(null);
    return null;
  }

  if (!fields.barterAssets()) {
    const message = transText('donk-aigc-collectibles.forum.barter.load_failed');
    fields.barterValidationError(message);
    return message;
  }

  if (fields.barterMySelections().length === 0 && fields.barterTheirSelections().length === 0) {
    const message = transText('donk-aigc-collectibles.forum.barter.validation_assets_required');
    fields.barterValidationError(message);
    return message;
  }

  if (fields.barterMySelections().length === 0 || fields.barterTheirSelections().length === 0) {
    const message = transText('donk-aigc-collectibles.forum.barter.validation_both_sides_required');
    fields.barterValidationError(message);
    return message;
  }

  const content = String(composer.fields.content?.() || '').trim();

  if (content.length === 0) {
    const message = transText('donk-aigc-collectibles.forum.barter.validation_message_required');
    fields.barterValidationError(message);
    return message;
  }

  fields.barterValidationError(null);
  return null;
}

function findAsset(payload: BarterAssetsPayload, side: 'yours' | 'theirs', token: string): BarterAsset | null {
  const assets = [
    ...(payload[side].collectibles || []),
    ...(payload[side].blindBoxes || []),
  ];

  return assets.find((asset) => optionToken(asset) === token) || null;
}

export function buildBarterItems(composer: any): Array<{ ownerUserId: number; assetType: string; assetId: number }> {
  const fields = ensureBarterComposerFields(composer);
  const payload = fields.barterAssets();

  if (!payload) {
    return [];
  }

  const mySelections: string[] = fields.barterMySelections();
  const theirSelections: string[] = fields.barterTheirSelections();

  const items = [
    ...mySelections
      .map((token: string) => findAsset(payload, 'yours', token))
      .filter((asset: BarterAsset | null): asset is BarterAsset => asset !== null)
      .map((asset: BarterAsset) => ({
        ownerUserId: asset.ownerUserId,
        assetType: asset.assetType,
        assetId: Number(asset.id),
      })),
    ...theirSelections
      .map((token: string) => findAsset(payload, 'theirs', token))
      .filter((asset: BarterAsset | null): asset is BarterAsset => asset !== null)
      .map((asset: BarterAsset) => ({
        ownerUserId: asset.ownerUserId,
        assetType: asset.assetType,
        assetId: Number(asset.id),
      })),
  ];

  return items;
}

export async function createBarterProposalFromComposer(dialog: any, composer: any): Promise<void> {
  const fields = ensureBarterComposerFields(composer);
  const payload = fields.barterAssets();
  const dialogId = dialog?.id?.();

  if (!payload || !dialogId) {
    return;
  }

  const items = buildBarterItems(composer);

  await app.request({
    method: 'POST',
    url: `${app.forum.attribute('apiUrl')}/barter-proposals`,
    body: {
      data: {
        type: 'barter-proposals',
        attributes: {
          threadType: 'dialog',
          threadId: Number(dialogId),
          counterpartyUserId: payload.counterpartyUserId,
          replacesProposalId: fields.barterReplacesProposalId(),
          items,
        },
      },
    },
  });

  clearBarterComposer(composer, true);
  emitBarterThreadUpdated(dialogId);
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

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

async function waitForMessageComposer(dialog: any, timeoutMs: number = 3000): Promise<boolean> {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    const composer: any = app.composer;
    const sameDialog =
      composer?.bodyMatches?.(MESSAGE_COMPOSER_PATH, { replyingTo: dialog }) ||
      composer?.body?.attrs?.replyingTo === dialog ||
      composer?.composingMessageTo?.(dialog);

    if (sameDialog && composer?.isVisible?.()) {
      return true;
    }

    await delay(50);
  }

  return false;
}

export async function openBarterComposer(dialog: any, dialogSection?: any, proposal?: BarterProposal | null): Promise<void> {
  const composer: any = app.composer;
  const sameDialog =
    composer?.bodyMatches?.(MESSAGE_COMPOSER_PATH, { replyingTo: dialog }) ||
    composer?.body?.attrs?.replyingTo === dialog;

  if (!sameDialog) {
    const replyTrigger =
      typeof document !== 'undefined'
        ? (document.querySelector('.ReplyPlaceholder') as HTMLElement | null)
        : null;

    if (replyTrigger) {
      replyTrigger.click();
      await waitForMessageComposer(dialog);
    }
  }

  const openedComposer =
    composer?.bodyMatches?.(MESSAGE_COMPOSER_PATH, { replyingTo: dialog }) ||
    composer?.body?.attrs?.replyingTo === dialog ||
    composer?.composingMessageTo?.(dialog);

  if (!openedComposer) {
    await flarum.reg.asyncModuleImport('flarum/forum/components/ComposerBody');

    const MessageComposerModule: any = await flarum.reg.asyncModuleImport(MESSAGE_COMPOSER_PATH);
    const MessageComposerClass = MessageComposerModule?.default || MessageComposerModule;

    await composer.load(MessageComposerClass, {
      user: app.session.user,
      replyingTo: dialog,
      onsubmit: () => {
        const state = dialogSection?.messages;

        if (state?.refresh) {
          Promise.resolve(state.refresh()).finally(() => emitBarterThreadUpdated(dialog.id()));
          return;
        }

        emitBarterThreadUpdated(dialog.id());
      },
    });
  }

  if (!composer?.isVisible?.()) {
    await composer.show();
  }

  const fields = ensureBarterComposerFields(composer);
  fields.barterEnabled(true);
  fields.barterExpanded(true);
  fields.barterError(null);
  fields.barterValidationError(null);

  if (!sameDialog) {
    fields.barterMySelections([]);
    fields.barterTheirSelections([]);
    fields.barterReplacesProposalId(null);
  }

  if (proposal) {
    primeBarterComposerFromProposal(composer, proposal);
  }

  await loadBarterAssets(composer, dialog, true);
  applyInitialContent(String(composer.fields.content?.() || ''));
}
