import app from 'flarum/forum/app';
import { extend as flarumExtend, override } from 'flarum/common/extend';
import BarterThreadPanel from '../components/BarterThreadPanel';
import BarterComposerPanel from '../components/BarterComposerPanel';
import BarterConfigOverlay from '../components/BarterConfigOverlay';
import {
  createBarterProposalFromComposer,
  ensureBarterComposerFields,
  hasBarterDraft,
  loadBarterAssets,
  validateBarterComposer,
} from '../utils/barterComposer';
import { transText } from '../utils/i18n';
import listItems from 'flarum/common/helpers/listItems';

export function installBarterMessaging(): void {
  installDialogSectionPanel();
  installMessageComposerHooks();
}

export function openBarterConfigOverlay(composer: any, dialog: any): void {
  app.modal.show(BarterConfigOverlay, { composer, dialog });
}

function installDialogSectionPanel(): void {
  override('ext:flarum/messages/forum/components/DialogSection', 'view', function (this: any, original: () => any) {
    const vnode = original();
    const dialog = this?.attrs?.dialog;

    if (!vnode || !dialog) {
      return vnode;
    }

    const stream = vnode.children?.[1];

    if (!stream) {
      return vnode;
    }

    vnode.children[1] = (
      <div className="DialogSection-streamWrap">
        <BarterThreadPanel dialog={dialog} />
        {stream}
      </div>
    );

    return vnode;
  });
}

function installMessageComposerHooks(): void {
  // Override view() to inject BarterComposerPanel as a proper block element
  // between the header row and the TextEditor — not as a <li> inside headerItems.
  override('ext:flarum/messages/forum/components/MessageComposer', 'view', function (this: any, original: () => any) {
    const vnode = original();
    if (!vnode) return vnode;

    const dialog = this.attrs?.replyingTo;
    const showBarter = dialog?.id?.();

    if (!showBarter) return vnode;

    // Find ComposerBody-content div: vnode.children[0] is ConfirmDocumentUnload,
    // its children[0] is the ComposerBody div, its children[1] is ComposerBody-content.
    const confirmUnload = vnode.children as any;
    const composerBody = confirmUnload?.[0]?.children as any;
    const contentDiv = composerBody?.[1] as any;

    if (!contentDiv || contentDiv.tag !== 'div' || !contentDiv.children) {
      return vnode;
    }

    // Build header <ul> the same way ComposerBody does
    const headerVnode = (
      <ul className="ComposerBody-header">{listItems(this.headerItems().toArray())}</ul>
    );

    // Replace the static [headerList, editor] pair with [header, barter, editor]
    contentDiv.children = [
      headerVnode,
      <div className="MessageComposer-barter">
        <BarterComposerPanel composer={this.composer} dialog={dialog} />
      </div>,
      contentDiv.children[contentDiv.children.length - 1], // TextEditor div
    ];

    return vnode;
  });

  flarumExtend('ext:flarum/messages/forum/components/MessageComposer', 'oninit', function (this: any, _value: unknown, vnode: any) {
    ensureBarterComposerFields(this.composer);

    const dialog = vnode.attrs?.replyingTo;

    if (dialog?.id?.()) {
      void loadBarterAssets(this.composer, dialog);
    }
  });

  override('ext:flarum/messages/forum/components/MessageComposer', 'hasChanges', function (this: any, original: () => boolean) {
    return original() || hasBarterDraft(this.composer);
  });

  override('ext:flarum/messages/forum/components/MessageComposer', 'onsubmit', function (this: any, original: () => void) {
    const fields = ensureBarterComposerFields(this.composer);

    if (!fields.barterEnabled()) {
      return original();
    }

    const validationError = validateBarterComposer(this.composer);

    if (validationError) {
      m.redraw();
      return;
    }

    this.loading = true;
    fields.barterError(null);
    fields.barterValidationError(null);

    const data = this.data();

    app.store
      .createRecord('dialog-messages')
      .save(data, {
        params: {
          include: ['dialog'],
        },
      })
      .then(async (message: any) => {
        const dialog = this.attrs.replyingTo || message.dialog?.() || this.attrs.replyingTo;

        try {
          await createBarterProposalFromComposer(dialog, this.composer);
        } catch (error: any) {
          app.alerts.show(
            { type: 'error' },
            error?.response?.errors?.[0]?.detail ||
              transText('donk-aigc-collectibles.forum.barter.action_failed')
          );
        }

        this.composer.hide();
        m.route.set(app.route('dialog', { id: message.data.relationships!.dialog.data.id }));
        this.attrs.onsubmit?.(message);
      })
      .catch((error: any) => {
        fields.barterError(
          error?.response?.errors?.[0]?.detail ||
            transText('donk-aigc-collectibles.forum.barter.action_failed')
        );
      })
      .finally(() => {
        this.loaded();
      });
  });
}
