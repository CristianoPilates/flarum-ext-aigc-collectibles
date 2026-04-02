import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import BarterProposal from '../models/BarterProposal';
import { collectibleRarityLabel, displayCollectibleName } from '../utils/collectibles';
import { displayUserName } from '../utils/users';
import { gatewayUrl } from '../utils/ipfs';
import { emitBarterThreadUpdated, onBarterThreadUpdated } from '../utils/barterEvents';
import { openBarterComposer } from '../utils/barterComposer';

interface BarterThreadPanelAttrs {
  dialog: any;
}

type AssetSnapshot = Record<string, any> | null | undefined;

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
            {this.proposals.map((proposal) => this.viewProposal(proposal))}
          </div>
        )}
      </section>
    );
  }

  viewProposal(proposal: BarterProposal) {
    const items = proposal.items();
    const currentUserId = app.session.user?.id?.();
    const proposer = proposal.proposer();
    const counterparty = proposal.counterparty();
    const acceptedBy = proposal.acceptedBy?.();
    const proposerId = (proposer as any)?.id?.() || null;
    const counterpartyId = (counterparty as any)?.id?.() || null;
    const fromItems = items.filter((item: any) => String(item.ownerUserId?.()) === String(proposerId));
    const toItems = items.filter((item: any) => String(item.ownerUserId?.()) === String(counterpartyId));
    const isActing = this.actingProposalId === proposal.id();
    const previousRevision = this.findPreviousRevision(proposal);
    const replacementRevision = this.findReplacementRevision(proposal);
    const notes = this.buildProposalNotes(proposal, previousRevision, replacementRevision, acceptedBy);

    return (
      <article
        className={`BarterProposalCard BarterProposalCard--${proposal.status()}`}
        key={proposal.id()}
        data-proposal-id={proposal.id() || ''}
        data-proposal-status={proposal.status() || ''}
      >
        <div className="BarterProposalCard-header">
          <div className="BarterProposalCard-heading">
            <span className={`BarterProposalCard-status BarterProposalCard-status--${proposal.status()}`}>
              {this.statusLabel(proposal.status())}
            </span>
            <span className="BarterProposalCard-revision">
              {this.trans('donk-aigc-collectibles.forum.barter.revision', {
                number: proposal.revisionNumber?.() || 1,
              })}
            </span>
          </div>
          <div className="BarterProposalCard-meta">
            <span>
              {this.trans('donk-aigc-collectibles.forum.barter.proposer', {
                username: displayUserName(proposer),
              })}
            </span>
            <span>
              {proposal.createdAt?.() ? proposal.createdAt()!.toLocaleString() : ''}
            </span>
          </div>
        </div>

        {notes.length > 0 ? <div className="BarterProposalCard-notes">{notes}</div> : null}

        {proposal.message?.() ? <p className="BarterProposalCard-message">{proposal.message()}</p> : null}

        <div className="BarterProposalCard-grid">
          <div className="BarterProposalCard-column">
            <div className="BarterProposalCard-columnTitle">
              {this.trans('donk-aigc-collectibles.forum.barter.offer_from', {
                username: displayUserName(proposer),
              })}
            </div>
            <div className="BarterProposalCard-assets">
              {fromItems.map((item: any) => this.viewItem(item, currentUserId))}
            </div>
          </div>

          <div className="BarterProposalCard-column">
            <div className="BarterProposalCard-columnTitle">
              {this.trans('donk-aigc-collectibles.forum.barter.offer_from', {
                username: displayUserName(counterparty),
              })}
            </div>
            <div className="BarterProposalCard-assets">
              {toItems.map((item: any) => this.viewItem(item, currentUserId))}
            </div>
          </div>
        </div>

        <div className="BarterProposalCard-actions">
          {proposal.status?.() === 'proposed' ? (
            <Button className="Button" onclick={() => void this.openComposer(proposal)} disabled={isActing}>
              {this.trans('donk-aigc-collectibles.forum.barter.counter_button')}
            </Button>
          ) : null}

          {proposal.canAccept?.() ? (
            <Button
              className="Button Button--primary"
              onclick={() => this.performAction(proposal, 'accept')}
              loading={isActing}
              disabled={isActing}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.accept')}
            </Button>
          ) : null}

          {proposal.canReject?.() ? (
            <Button
              className="Button"
              onclick={() => this.performAction(proposal, 'reject')}
              loading={isActing}
              disabled={isActing}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.reject')}
            </Button>
          ) : null}

          {proposal.canCancel?.() ? (
            <Button
              className="Button"
              onclick={() => this.performAction(proposal, 'cancel')}
              loading={isActing}
              disabled={isActing}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.cancel')}
            </Button>
          ) : null}
        </div>
      </article>
    );
  }

  viewItem(item: any, currentUserId?: string | number | null) {
    const snapshot = item.snapshot?.() as AssetSnapshot;
    const kind = item.assetType?.() || snapshot?.kind || 'unknown';
    const ownerUser = item.ownerUser?.();
    const isMine = ownerUser && currentUserId && String(ownerUser.id?.()) === String(currentUserId);

    return (
      <div className={`BarterAssetCard BarterAssetCard--${kind}`} key={`${item.id?.() || item.assetType?.()}-${item.assetId?.()}`}>
        <div className="BarterAssetCard-header">
          <span className="BarterAssetCard-kind">{this.assetTypeLabel(kind)}</span>
          {isMine ? <span className="BarterAssetCard-mine">{this.trans('donk-aigc-collectibles.forum.barter.your_asset')}</span> : null}
        </div>
        {kind === 'collectible' ? this.viewCollectibleSnapshot(snapshot, item) : this.viewBlindBoxSnapshot(snapshot, item)}
      </div>
    );
  }

  viewCollectibleSnapshot(snapshot: AssetSnapshot, item: any) {
    const imageUrl = snapshot?.ipfsCid ? gatewayUrl(snapshot.ipfsCid) : null;
    const ownerUser = item.ownerUser?.();
    const ownerSlug = ownerUser?.slug?.();
    const displayName = displayCollectibleName(snapshot?.name, item.assetId?.());

    return (
      <div className="BarterAssetCard-body">
        {imageUrl ? <img className="BarterAssetCard-image" src={imageUrl} alt={displayName} loading="lazy" /> : null}
        <div className="BarterAssetCard-copy">
          <div className="BarterAssetCard-name">{displayName}</div>
          <div className="BarterAssetCard-meta">
            {snapshot?.rarity ? <span className={`CollectibleRarity CollectibleRarity--${snapshot.rarity}`}>{collectibleRarityLabel(snapshot.rarity)}</span> : null}
            {snapshot?.tokenId ? <span className="BarterAssetCard-token">#{snapshot.tokenId}</span> : null}
          </div>
          {ownerSlug ? (
            <div className="BarterAssetCard-links">
              <Link href={app.route('user.collectibles', { username: ownerSlug })}>
                {this.trans('donk-aigc-collectibles.forum.barter.collectible_link')}
              </Link>
            </div>
          ) : null}
        </div>
      </div>
    );
  }

  viewBlindBoxSnapshot(snapshot: AssetSnapshot, item: any) {
    const status = snapshot?.status || 'unknown';
    const budget = typeof snapshot?.budget === 'number' ? String(snapshot.budget) : this.trans('donk-aigc-collectibles.forum.blind_box.budget_unknown');

    return (
      <div className="BarterAssetCard-body BarterAssetCard-body--blindBox">
        <div className="BarterAssetCard-copy">
          <div className="BarterAssetCard-name">
            {this.trans(`donk-aigc-collectibles.forum.blind_box.type_${snapshot?.type || 'unknown'}`)}
          </div>
          <div className="BarterAssetCard-meta BarterAssetCard-meta--stacked">
            <span>{this.trans(`donk-aigc-collectibles.forum.blind_box.status_${status}`)}</span>
            <span>
              {this.trans('donk-aigc-collectibles.forum.blind_box.budget_label')}: {budget}
            </span>
            <span className="BarterAssetCard-seed">
              {this.trans('donk-aigc-collectibles.forum.blind_box.seed_label')}: {snapshot?.seed || item.assetId?.()}
            </span>
          </div>
        </div>
      </div>
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

  async performAction(proposal: BarterProposal, action: 'accept' | 'reject' | 'cancel') {
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

  statusLabel(status?: string | null) {
    return this.trans(`donk-aigc-collectibles.forum.barter.status_${status || 'proposed'}`);
  }

  assetTypeLabel(assetType?: string | null) {
    return this.trans(`donk-aigc-collectibles.forum.barter.asset_type_${assetType || 'unknown'}`);
  }

  async openComposer(proposal?: BarterProposal | null) {
    await openBarterComposer(this.attrs.dialog, this as any, proposal || null);
  }

  findPreviousRevision(proposal: BarterProposal): BarterProposal | null {
    return proposal.replacesProposal?.() || null;
  }

  findReplacementRevision(proposal: BarterProposal): BarterProposal | null {
    return (
      this.proposals.find((candidate) => {
        const replaced = candidate.replacesProposal?.();

        return replaced && String(replaced.id?.()) === String(proposal.id?.());
      }) || null
    );
  }

  buildProposalNotes(proposal: BarterProposal, previousRevision: BarterProposal | null, replacementRevision: BarterProposal | null, acceptedBy: any) {
    const notes: JSX.Element[] = [];
    const previousRevisionNumber =
      previousRevision?.revisionNumber?.() ||
      ((proposal.revisionNumber?.() || 1) > 1 ? (proposal.revisionNumber?.() || 1) - 1 : null);

    if (previousRevisionNumber) {
      notes.push(
        <span className="BarterProposalCard-note BarterProposalCard-note--lineage">
          {this.trans('donk-aigc-collectibles.forum.barter.replaces_revision', {
            number: previousRevisionNumber,
          })}
        </span>
      );
    }

    if (replacementRevision?.revisionNumber?.()) {
      notes.push(
        <span className="BarterProposalCard-note BarterProposalCard-note--lineage">
          {this.trans('donk-aigc-collectibles.forum.barter.replaced_by_revision', {
            number: replacementRevision.revisionNumber?.(),
          })}
        </span>
      );
    } else if (proposal.status?.() === 'superseded') {
      notes.push(
        <span className="BarterProposalCard-note BarterProposalCard-note--lineage">
          {this.trans('donk-aigc-collectibles.forum.barter.replaced_by_later_revision')}
        </span>
      );
    }

    if (acceptedBy && proposal.status?.() === 'completed') {
      notes.push(
        <span className="BarterProposalCard-note BarterProposalCard-note--resolution">
          {this.trans('donk-aigc-collectibles.forum.barter.accepted_by', {
            username: displayUserName(acceptedBy),
          })}
        </span>
      );
    }

    return notes;
  }
}
