import app from 'flarum/forum/app';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CollectibleProofModal from './CollectibleProofModal';
import { collectibleRarityLabel, displayCollectibleName } from '../utils/collectibles';
import { gatewayUrl } from '../utils/ipfs';
import {
  buildCollectibleMessageContent,
  currentDiscussionTitle,
  shouldShowPrivateMessageButton,
  startPrivateMessage,
} from '../utils/privateMessages';

interface CollectibleDetailModalAttrs extends IInternalModalAttrs {
  collectible: any;
  isOwnProfile: boolean;
  onUpdated?: () => void;
}

const STATUS_KEYS: Record<string, string> = {
  generating: 'status_generating',
  completed: 'status_completed',
  failed: 'status_failed',
  burned: 'status_burned',
};

export default class CollectibleDetailModal extends Modal<CollectibleDetailModalAttrs> {
  loadingAction: boolean = false;
  error: string | null = null;
  renaming: boolean = false;
  renameValue: string = '';
  renameError: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadingAction = false;
    this.error = null;
    this.renaming = false;
    this.renameValue = '';
    this.renameError = null;
  }

  className() {
    return 'CollectibleDetailModal Modal--large';
  }

  title() {
    return app.translator.trans('donk-aigc-collectibles.forum.collectible.detail_title');
  }

  content() {
    const collectible = this.attrs.collectible;
    const isOwnProfile = this.attrs.isOwnProfile;

    if (!collectible) return null;

    const rarity = collectible.rarity();
    const status = collectible.status();
    const tokenId = collectible.tokenId();
    const imageUrl = collectible.ipfsCid() ? gatewayUrl(collectible.ipfsCid()) : null;
    const owner = collectible.owner?.() || collectible.user?.();
    const canMintAttribute = collectible.canMint?.();
    const canMint = canMintAttribute ?? (status === 'completed' && !tokenId);
    const isShowcase = Boolean(collectible.isShowcase?.());
    const canMessageOwner = Boolean(owner && shouldShowPrivateMessageButton(owner));
    const statusKey = STATUS_KEYS[status];
    const displayName = displayCollectibleName(collectible.name?.(), collectible.id?.());

    return (
      <div className="Modal-body CollectibleDetailModal-body">
        {this.error && <div className="CollectibleDetailModal-error">{this.error}</div>}

        <div className="CollectibleDetailModal-layout">
          <div className="CollectibleDetailModal-media">
            {imageUrl ? (
              <img className="CollectibleDetailModal-image" src={imageUrl} alt={displayName} loading="lazy" />
            ) : (
              <div className="CollectibleDetailModal-placeholder">
                {status === 'generating' ? <LoadingIndicator size="large" /> : <i className="fas fa-image" />}
              </div>
            )}
          </div>

          <div className="CollectibleDetailModal-content">
            <div className="CollectibleDetailModal-nameRow">
              {isOwnProfile && !this.renaming && (
                <button
                  className="CollectibleDetailModal-renameBtn"
                  onclick={() => {
                    this.renaming = true;
                    this.renameValue = collectible.name?.() || '';
                    this.renameError = null;
                    m.redraw();
                  }}
                  title={app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_button')}
                >
                  <i className="fas fa-pencil-alt" />
                </button>
              )}
              <h3 className="CollectibleDetailModal-name">{displayName}</h3>
            </div>

            {this.renaming && (
              <div className="CollectibleDetailModal-renameRow">
                <input
                  className="CollectibleDetailModal-renameInput"
                  type="text"
                  value={this.renameValue}
                  oninput={(e: InputEvent) => {
                    this.renameValue = (e.target as HTMLInputElement).value;
                  }}
                  onkeydown={(e: KeyboardEvent) => {
                    if (e.key === 'Enter') void this.saveRename();
                    if (e.key === 'Escape') { this.renaming = false; m.redraw(); }
                  }}
                  disabled={this.loadingAction}
                  maxLength={100}
                  placeholder={app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_button')}
                />
                {this.renameError && (
                  <div className="CollectibleDetailModal-renameError">{this.renameError}</div>
                )}
                <div className="CollectibleDetailModal-renameActions">
                  <Button
                    className="Button Button--primary"
                    onclick={() => void this.saveRename()}
                    loading={this.loadingAction}
                    disabled={this.loadingAction}
                  >
                    {app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_save')}
                  </Button>
                  <Button
                    className="Button"
                    onclick={() => { this.renaming = false; this.renameError = null; m.redraw(); }}
                    disabled={this.loadingAction}
                  >
                    {app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_cancel')}
                  </Button>
                </div>
              </div>
            )}

            <div className="CollectibleDetailModal-meta">
              <span className={'CollectibleRarity CollectibleRarity--' + rarity}>
                {collectibleRarityLabel(rarity)}
              </span>
              {statusKey && (
                <span className="CollectibleDetailModal-status">
                  {app.translator.trans('donk-aigc-collectibles.forum.collectible.' + statusKey)}
                </span>
              )}
              {tokenId && (
                <span className="CollectibleDetailModal-token">
                  {app.translator.trans('donk-aigc-collectibles.forum.collectible.token_id_label', { id: tokenId })}
                </span>
              )}
            </div>

            {owner && (
              <p className="CollectibleDetailModal-line">
                {app.translator.trans('donk-aigc-collectibles.forum.collectible.owner_label')}{' '}
                <Link href={app.route.user(owner)}>
                  {owner.displayName?.() || owner.username?.() || owner.id?.()}
                </Link>
              </p>
            )}

            <p className="CollectibleDetailModal-line">
              {app.translator.trans('donk-aigc-collectibles.forum.collectible.times_traded', {
                count: collectible.timesTraded?.() || 0,
              })}
            </p>

            {imageUrl && (
              <p className="CollectibleDetailModal-linkRow">
                <a href={imageUrl} target="_blank" rel="noreferrer">
                  {app.translator.trans('donk-aigc-collectibles.forum.collectible.ipfs_label')}
                </a>
              </p>
            )}

            <div className="CollectibleDetailModal-actions">
              {isOwnProfile && status === 'completed' && (
                <Button
                  className="Button Button--primary"
                  onclick={() => this.toggleShowcase()}
                  loading={this.loadingAction}
                  disabled={this.loadingAction}
                >
                  {app.translator.trans(
                    'donk-aigc-collectibles.forum.collectible.' + (isShowcase ? 'showcase_remove' : 'showcase_button')
                  )}
                </Button>
              )}

              {isOwnProfile && canMint && (
                <Button
                  className="Button"
                  onclick={() => this.mintCollectible()}
                  loading={this.loadingAction}
                  disabled={this.loadingAction}
                >
                  {app.translator.trans('donk-aigc-collectibles.forum.collectible.mint_nft_button')}
                </Button>
              )}

              {(collectible.metadataCid?.() || collectible.tokenId?.()) && (
                <Button className="Button" onclick={() => this.openProof()} disabled={this.loadingAction}>
                  {app.translator.trans('donk-aigc-collectibles.forum.collectible.proof_button')}
                </Button>
              )}

              {canMessageOwner && (
                <Button
                  className="Button Button--primary CollectibleDetailModal-messageButton"
                  onclick={() => void this.openConversation()}
                  disabled={this.loadingAction}
                >
                  {app.translator.trans('donk-aigc-collectibles.forum.messages.detail_button')}
                </Button>
              )}

              <Button className="Button" onclick={() => this.hide()} disabled={this.loadingAction}>
                {app.translator.trans('donk-aigc-collectibles.forum.blind_box.close')}
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  async saveRename() {
    const collectible = this.attrs.collectible;
    if (!collectible || this.loadingAction) return;

    const trimmed = this.renameValue.trim();
    if (!trimmed) {
      this.renameError = app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_error');
      m.redraw();
      return;
    }

    this.loadingAction = true;
    this.renameError = null;
    m.redraw();

    try {
      await app.request({
        method: 'PATCH',
        url: app.forum.attribute('apiUrl') + '/collectibles/' + collectible.id(),
        body: {
          data: {
            type: 'collectibles',
            id: collectible.id(),
            attributes: { name: trimmed },
          },
        },
      });

      collectible.pushAttributes({ name: trimmed });
      this.renaming = false;
      app.alerts.show(
        { type: 'success' },
        app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_success')
      );
      this.attrs.onUpdated?.();
    } catch (error: any) {
      this.renameError = error.response?.errors?.[0]?.detail ||
        app.translator.trans('donk-aigc-collectibles.forum.collectible.rename_error');
    } finally {
      this.loadingAction = false;
      m.redraw();
    }
  }

  async toggleShowcase() {
    const collectible = this.attrs.collectible;
    if (!collectible || this.loadingAction) return;

    this.loadingAction = true;
    this.error = null;
    m.redraw();

    const nextShowcase = !Boolean(collectible.isShowcase?.());

    try {
      await app.request({
        method: 'PATCH',
        url: app.forum.attribute('apiUrl') + '/collectibles/' + collectible.id(),
        body: {
          data: {
            type: 'collectibles',
            id: collectible.id(),
            attributes: {
              isShowcase: nextShowcase,
            },
          },
        },
      });

      collectible.pushAttributes({ isShowcase: nextShowcase });

      const currentUser = app.session?.user;
      if (currentUser) {
        currentUser.pushAttributes({
          showcaseCollectibleId: nextShowcase ? Number(collectible.id()) : null,
          showcaseCollectibleName: nextShowcase ? collectible.name() : null,
          showcaseCollectibleCid: nextShowcase ? collectible.ipfsCid() : null,
          showcaseCollectibleRarity: nextShowcase ? collectible.rarity() : null,
          showcaseCollectibleTokenId: nextShowcase ? collectible.tokenId() : null,
        });
      }

      app.alerts.show(
        { type: 'success' },
        app.translator.trans(
          'donk-aigc-collectibles.forum.collectible.' + (nextShowcase ? 'showcase_set' : 'showcase_removed')
        )
      );

      this.attrs.onUpdated?.();
    } catch (error: any) {
      this.error = error.response?.errors?.[0]?.detail || 'Failed to update showcase.';
    } finally {
      this.loadingAction = false;
      m.redraw();
    }
  }

  async mintCollectible() {
    const collectible = this.attrs.collectible;
    if (!collectible || this.loadingAction) return;

    this.loadingAction = true;
    this.error = null;
    m.redraw();

    try {
      const response: any = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/collectibles/' + collectible.id() + '/mint',
      });

      const attributes = response?.data?.attributes || {};
      collectible.pushAttributes({
        tokenId: attributes.tokenId ?? collectible.tokenId?.(),
        canMint: false,
      });

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('donk-aigc-collectibles.forum.notification.nft_minted_body', {
          tokenId: attributes.tokenId,
        })
      );

      this.attrs.onUpdated?.();
    } catch (error: any) {
      this.error = error.response?.errors?.[0]?.detail || 'Failed to mint collectible.';
    } finally {
      this.loadingAction = false;
      m.redraw();
    }
  }

  async openConversation() {
    const collectible = this.attrs.collectible;
    const owner = collectible?.owner?.() || collectible?.user?.();

    if (!owner) return;

    const initialContent = buildCollectibleMessageContent({
      collectible,
      sourceDiscussionTitle: currentDiscussionTitle(),
      sourcePostUrl: typeof window !== 'undefined' ? window.location.href : null,
    });

    this.hide();
    await startPrivateMessage(owner, { initialContent });
  }

  openProof() {
    const collectible = this.attrs.collectible;
    if (!collectible) return;

    app.modal.show(CollectibleProofModal, {
      collectible,
    });
  }
}
