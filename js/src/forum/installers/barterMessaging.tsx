import app from 'flarum/forum/app';
import { extend as flarumExtend, override } from 'flarum/common/extend';
import BarterThreadPanel from '../components/BarterThreadPanel';
import BarterComposerPanel from '../components/BarterComposerPanel';
import {
  createBarterProposalFromComposer,
  ensureBarterComposerFields,
  hasBarterDraft,
  loadBarterAssets,
  validateBarterComposer,
} from '../utils/barterComposer';
import { transText } from '../utils/i18n';

export function installBarterMessaging(): void {
  installDialogSectionPanel();
  installMessageComposerHooks();
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
  flarumExtend('ext:flarum/messages/forum/components/MessageComposer', 'oninit', function (this: any, _value: unknown, vnode: any) {
    ensureBarterComposerFields(this.composer);

    const dialog = vnode.attrs?.replyingTo;

    if (dialog?.id?.()) {
      void loadBarterAssets(this.composer, dialog);
    }
  });

  flarumExtend('ext:flarum/messages/forum/components/MessageComposer', 'headerItems', function (this: any, items: any) {
    const dialog = this.attrs?.replyingTo;

    if (!dialog?.id?.()) {
      return;
    }

    items.add(
      'donk-aigc-collectibles-barter-composer',
      <BarterComposerPanel composer={this.composer} dialog={dialog} />,
      90
    );
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
