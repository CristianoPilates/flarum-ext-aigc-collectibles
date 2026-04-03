import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BarterProposal from '../models/BarterProposal';
import { emitBarterThreadUpdated, onBarterThreadUpdated } from '../utils/barterEvents';
import { openBarterComposer } from '../utils/barterComposer';
import BarterProposalCard, { type BarterProposalAction } from './BarterProposalCard';

interface BarterThreadPanelAttrs {
  dialog: any;
}

export default class BarterThreadPanel extends Component<BarterThreadPanelAttrs> {
  loading: boolean = true;
  proposals: BarterProposal[] = [];
  error: string | null = null;
  actingProposalId: string | null = null;
  lastDialogId: string | null = null;
  removeThreadUpdatedListener: (() => void) | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.lastDialogId = vnode.attrs.dialog?.id?.() || null;
    this.removeThreadUpdatedListener = onBarterThreadUpdated((dialogId) => {
      if (String(dialogId) === String(this.attrs.dialog?.id?.())) {
        void this.loadProposals();
      }
    });
    this.loadProposals();
  }

  onremove() {
    this.removeThreadUpdatedListener?.();
    this.removeThreadUpdatedListener = null;
  }

  onbeforeupdate(vnode: any) {
    const dialogId = vnode.attrs.dialog?.id?.() || null;

    if (dialogId !== this.lastDialogId) {
      this.lastDialogId = dialogId;
      this.proposals = [];
      this.error = null;
      this.loadProposals();
    }

    return true;
  }

  view() {
    return (
      <section className="BarterThreadPanel">
        <div className="BarterThreadPanel-header">
          <div>
            <h3 className="BarterThreadPanel-title">
              {this.trans('donk-aigc-collectibles.forum.barter.thread_title')}
            </h3>
            <p className="BarterThreadPanel-subtitle">
              {this.trans('donk-aigc-collectibles.forum.barter.thread_subtitle')}
            </p>
          </div>
          <div className="BarterThreadPanel-headerActions">
            <Button className="Button Button--primary" icon="fas fa-handshake" onclick={() => void this.openComposer()}>
              {this.trans('donk-aigc-collectibles.forum.barter.create_button')}
            </Button>
            <Button className="Button Button--icon" icon="fas fa-sync" onclick={() => this.loadProposals()} disabled={this.loading}>
              {this.trans('donk-aigc-collectibles.forum.barter.refresh')}
            </Button>
          </div>
        </div>

        {this.error ? <div className="BarterThreadPanel-error">{this.error}</div> : null}

        {this.loading ? (
          <div className="BarterThreadPanel-loading">
            <LoadingIndicator />
          </div>
        ) : this.proposals.length === 0 ? (
          <div className="BarterThreadPanel-empty">
            {this.trans('donk-aigc-collectibles.forum.barter.empty')}
          </div>
        ) : (
          <div className="BarterThreadPanel-list">
            {this.proposals.map((proposal) => (
              <BarterProposalCard
                proposal={proposal}
                proposals={this.proposals}
                isActing={this.actingProposalId === proposal.id()}
                onCounter={(currentProposal: BarterProposal) => this.openComposer(currentProposal)}
                onAction={(currentProposal: BarterProposal, action: BarterProposalAction) =>
                  this.performAction(currentProposal, action)
                }
              />
            ))}
          </div>
        )}
      </section>
    );
  }

  async loadProposals() {
    const dialogId = this.attrs.dialog?.id?.();

    if (!dialogId) {
      this.loading = false;
      this.proposals = [];
      return;
    }

    this.loading = true;
    this.error = null;
    m.redraw();

    try {
      const response: any = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/barter-proposals',
        params: {
          include: 'items.ownerUser,proposer,counterparty,acceptedBy,replacesProposal',
          threadType: 'dialog',
          threadId: dialogId,
          sort: '-createdAt',
          'page[limit]': 20,
        },
      });

      const payload = app.store.pushPayload(response) as unknown;
      const proposals = Array.isArray(payload)
        ? (payload as BarterProposal[])
        : payload
          ? [payload as BarterProposal]
          : [];

      this.proposals = proposals.sort((left, right) => {
        const leftCreatedAt = left.createdAt?.()?.getTime?.() || 0;
        const rightCreatedAt = right.createdAt?.()?.getTime?.() || 0;

        if (leftCreatedAt !== rightCreatedAt) {
          return rightCreatedAt - leftCreatedAt;
        }

        const leftRevision = left.revisionNumber?.() || 0;
        const rightRevision = right.revisionNumber?.() || 0;

        if (leftRevision !== rightRevision) {
          return rightRevision - leftRevision;
        }

        return Number(right.id?.() || 0) - Number(left.id?.() || 0);
      });
    } catch (error: any) {
      this.error = error?.response?.errors?.[0]?.detail || this.trans('donk-aigc-collectibles.forum.barter.load_failed');
      this.proposals = [];
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  async performAction(proposal: BarterProposal, action: BarterProposalAction) {
    this.actingProposalId = proposal.id() ? String(proposal.id()) : null;
    this.error = null;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/barter-proposals/${proposal.id()}/${action}`,
      });

      emitBarterThreadUpdated(this.attrs.dialog?.id?.());
      await this.loadProposals();
    } catch (error: any) {
      this.error = error?.response?.errors?.[0]?.detail || this.trans('donk-aigc-collectibles.forum.barter.action_failed');
    } finally {
      this.actingProposalId = null;
      m.redraw();
    }
  }

  trans(key: string, parameters: Record<string, any> = {}) {
    return app.translator.trans(key, parameters);
  }

  async openComposer(proposal?: BarterProposal | null) {
    await openBarterComposer(this.attrs.dialog, this as any, proposal || null);
  }
}
