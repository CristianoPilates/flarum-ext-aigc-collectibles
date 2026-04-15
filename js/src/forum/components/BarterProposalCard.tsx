import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import BarterProposal from '../models/BarterProposal';
import { collectibleRarityLabel, displayCollectibleName } from '../utils/collectibles';
import { displayUserName } from '../utils/users';
import { gatewayUrl } from '../utils/ipfs';

type AssetSnapshot = Record<string, any> | null | undefined;

export type BarterProposalAction = 'accept' | 'reject' | 'cancel';

interface BarterProposalCardAttrs {
  proposal: BarterProposal;
  proposals: BarterProposal[];
  isActing: boolean;
  onCounter: (proposal: BarterProposal) => void | Promise<void>;
  onAction: (proposal: BarterProposal, action: BarterProposalAction) => void | Promise<void>;
}

export default class BarterProposalCard extends Component<BarterProposalCardAttrs> {
  view() {
    const proposal = this.attrs.proposal;
    const status = proposal.status?.() || 'proposed';
    const proposer = proposal.proposer();
    const counterparty = proposal.counterparty();
    const acceptedBy = proposal.acceptedBy?.();
    const proposerItems = this.itemsForOwner(proposal, proposer);
    const counterpartyItems = this.itemsForOwner(proposal, counterparty);
    const notes = this.buildProposalNotes(
      proposal,
      proposal.replacesProposal?.() || null,
      this.findReplacementRevision(proposal),
      acceptedBy
    );

    return (
      <article
        className={`BarterProposalCard BarterProposalCard--${status}`}
        key={proposal.id()}
        data-proposal-id={proposal.id() || ''}
        data-proposal-status={status}
      >
        <div className="BarterProposalCard-header">
          <div className="BarterProposalCard-heading">
            <span className={`BarterProposalCard-status BarterProposalCard-status--${status}`}>
              {this.statusLabel(status)}
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
            <span>{proposal.createdAt?.() ? proposal.createdAt()!.toLocaleString() : ''}</span>
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
              {proposerItems.map((item: any) => this.viewItem(item))}
            </div>
          </div>

          <div className="BarterProposalCard-column">
            <div className="BarterProposalCard-columnTitle">
              {this.trans('donk-aigc-collectibles.forum.barter.offer_from', {
                username: displayUserName(counterparty),
              })}
            </div>
            <div className="BarterProposalCard-assets">
              {counterpartyItems.map((item: any) => this.viewItem(item))}
            </div>
          </div>
        </div>

        <div className="BarterProposalCard-actions">
          {status === 'proposed' ? (
            <Button className="Button" onclick={() => void this.attrs.onCounter(proposal)} disabled={this.attrs.isActing}>
              {this.trans('donk-aigc-collectibles.forum.barter.counter_button')}
            </Button>
          ) : null}

          {proposal.canAccept?.() ? (
            <Button
              className="Button Button--primary"
              onclick={() => void this.attrs.onAction(proposal, 'accept')}
              loading={this.attrs.isActing}
              disabled={this.attrs.isActing}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.accept')}
            </Button>
          ) : null}

          {proposal.canReject?.() ? (
            <Button
              className="Button Button--text"
              onclick={() => void this.attrs.onAction(proposal, 'reject')}
              loading={this.attrs.isActing}
              disabled={this.attrs.isActing}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.reject')}
            </Button>
          ) : null}

          {proposal.canCancel?.() ? (
            <Button
              className="Button Button--text"
              onclick={() => void this.attrs.onAction(proposal, 'cancel')}
              loading={this.attrs.isActing}
              disabled={this.attrs.isActing}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.cancel')}
            </Button>
          ) : null}
        </div>
      </article>
    );
  }

  itemsForOwner(proposal: BarterProposal, user: any): any[] {
    const ownerId = user?.id?.();

    return proposal.items().filter((item: any) => String(item.ownerUserId?.()) === String(ownerId || ''));
  }

  viewItem(item: any) {
    const snapshot = item.snapshot?.() as AssetSnapshot;
    const kind = item.assetType?.() || snapshot?.kind || 'unknown';
    const ownerUser = item.ownerUser?.();
    const currentUserId = app.session.user?.id?.();
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
    const ownerSlug = item.ownerUser?.()?.slug?.();
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
    const budget =
      typeof snapshot?.budget === 'number'
        ? String(snapshot.budget)
        : this.trans('donk-aigc-collectibles.forum.blind_box.budget_unknown');

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

  findReplacementRevision(proposal: BarterProposal): BarterProposal | null {
    return (
      this.attrs.proposals.find((candidate) => {
        const replaced = candidate.replacesProposal?.();

        return replaced && String(replaced.id?.()) === String(proposal.id?.());
      }) || null
    );
  }

  buildProposalNotes(
    proposal: BarterProposal,
    previousRevision: BarterProposal | null,
    replacementRevision: BarterProposal | null,
    acceptedBy: any
  ) {
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

  trans(key: string, parameters: Record<string, unknown> = {}) {
    return app.translator.trans(key, parameters);
  }

  statusLabel(status?: string | null) {
    return this.trans(`donk-aigc-collectibles.forum.barter.status_${status || 'proposed'}`);
  }

  assetTypeLabel(assetType?: string | null) {
    return this.trans(`donk-aigc-collectibles.forum.barter.asset_type_${assetType || 'unknown'}`);
  }
}
