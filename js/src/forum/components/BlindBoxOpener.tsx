import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Modal from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { subscribe, unsubscribe } from '../utils/notifications';
import { gatewayUrl } from '../utils/ipfs';

const RARITY_LABELS: Record<string, string> = {
  common: 'Common',
  rare: 'Rare',
  epic: 'Epic',
  legendary: 'Legendary',
};

export default class BlindBoxOpener extends Modal {
  generating: boolean = false;
  generatedCollectible: any = null;
  error: string | null = null;
  pendingCollectibleId: string | null = null;
  pendingRarity: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.generating = false;
    this.generatedCollectible = null;
    this.error = null;
    this.pendingCollectibleId = null;
    this.pendingRarity = null;
  }

  className() {
    return 'BlindBoxOpener Modal--large';
  }

  title() {
    return app.translator.trans('donk-aigc-collectibles.forum.blind_box.open_title');
  }

  oncreate(vnode: any) {
    super.oncreate(vnode);
    this.wsHandler = this.onCollectibleReady.bind(this);
    subscribe('collectible.ready', this.wsHandler);
  }

  onremove(vnode: any) {
    super.onremove(vnode);
    if (this.wsHandler) {
      unsubscribe('collectible.ready', this.wsHandler);
    }
  }

  private wsHandler: ((data: any) => void) | null = null;

  content() {
    const user = app.session.user;
    if (!user) return null;

    const blindBoxCount = user.attribute<number>('blindBoxCount') || 0;

    // State: reveal completed collectible
    if (this.generatedCollectible) {
      return this.viewReveal();
    }

    // State: generating in progress
    if (this.generating) {
      return this.viewGenerating();
    }

    // State: error
    if (this.error) {
      return this.viewError();
    }

    // State: idle — ready to open
    return (
      <div className="Modal-body BlindBoxOpener-body">
        <div className="BlindBoxOpener-balance">
          <i className="fas fa-box" />
          <span className="BlindBoxOpener-balanceCount">{blindBoxCount}</span>
          <span className="BlindBoxOpener-balanceLabel">
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.balance')}
          </span>
        </div>

        <div className="BlindBoxOpener-action">
          <Button
            className="Button Button--primary Button--block BlindBoxOpener-openButton"
            onclick={() => this.openBox()}
            disabled={blindBoxCount < 1}
            icon="fas fa-box-open"
          >
            {blindBoxCount >= 1
              ? app.translator.trans('donk-aigc-collectibles.forum.blind_box.open')
              : app.translator.trans('donk-aigc-collectibles.forum.blind_box.insufficient')}
          </Button>
        </div>
      </div>
    );
  }

  viewGenerating() {
    const rarityClass = this.pendingRarity ? ' BlindBoxOpener-generating--' + this.pendingRarity : '';

    return (
      <div className={'Modal-body BlindBoxOpener-body BlindBoxOpener-generating' + rarityClass}>
        <div className="BlindBoxOpener-generatingAnimation">
          <LoadingIndicator size="large" />
          <div className="BlindBoxOpener-generatingIcon">
            <i className="fas fa-magic" />
          </div>
        </div>
        <p className="BlindBoxOpener-generatingText">
          {app.translator.trans('donk-aigc-collectibles.forum.blind_box.generating')}
        </p>
        {this.pendingRarity && (
          <span className={'CollectibleRarity CollectibleRarity--' + this.pendingRarity}>
            {RARITY_LABELS[this.pendingRarity] || this.pendingRarity}
          </span>
        )}
      </div>
    );
  }

  viewReveal() {
    const collectible = this.generatedCollectible;
    const rarity = collectible.rarity();
    const imageUrl = collectible.ipfsCid() ? gatewayUrl(collectible.ipfsCid()) : null;

    return (
      <div className={'Modal-body BlindBoxOpener-body BlindBoxOpener-reveal BlindBoxOpener-reveal--' + rarity}>
        <div className="BlindBoxOpener-revealCard">
          {imageUrl ? (
            <img className="BlindBoxOpener-revealImage" src={imageUrl} alt={collectible.name()} loading="lazy" />
          ) : (
            <div className="BlindBoxOpener-revealPlaceholder">
              <i className="fas fa-image" />
            </div>
          )}
        </div>
        <h3 className="BlindBoxOpener-revealName">{collectible.name()}</h3>
        <span className={'CollectibleRarity CollectibleRarity--' + rarity}>{RARITY_LABELS[rarity] || rarity}</span>
        <div className="BlindBoxOpener-revealActions">
          <Button className="Button Button--primary" onclick={() => this.reset()}>
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.open_another')}
          </Button>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.close')}
          </Button>
        </div>
      </div>
    );
  }

  viewError() {
    return (
      <div className="Modal-body BlindBoxOpener-body BlindBoxOpener-error">
        <div className="BlindBoxOpener-errorIcon">
          <i className="fas fa-exclamation-triangle" />
        </div>
        <p>{this.error}</p>
        <Button className="Button Button--primary" onclick={() => this.reset()}>
          {app.translator.trans('donk-aigc-collectibles.forum.blind_box.try_again')}
        </Button>
      </div>
    );
  }

  openBox() {
    if (this.generating) return;

    this.generating = true;
    this.error = null;
    this.generatedCollectible = null;

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/collectibles/generate',
      })
      .then((response: any) => {
        // The API returns 202 with the collectible in "generating" status
        if (response?.data?.id) {
          this.pendingCollectibleId = response.data.id;
          this.pendingRarity = response.data.attributes?.rarity || null;
        }

        // Update user's blind box count
        const user = app.session.user;
        if (user && response?.data?.attributes) {
          const currentCount = user.attribute<number>('blindBoxCount') || 0;
          user.pushAttributes({ blindBoxCount: Math.max(0, currentCount - 1) });
        }

        m.redraw();

        // Start polling as fallback for WebSocket
        this.startPolling();
      })
      .catch((error: any) => {
        this.generating = false;
        this.error =
          error.response?.errors?.[0]?.detail ||
          String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_failed'));
        m.redraw();
      });
  }

  onCollectibleReady(data: any) {
    if (this.pendingCollectibleId && data.collectibleId === this.pendingCollectibleId) {
      this.fetchCollectible(this.pendingCollectibleId);
    }
  }

  private pollingTimer: ReturnType<typeof setTimeout> | null = null;
  private pollAttempts: number = 0;
  private readonly MAX_POLL_ATTEMPTS = 60;
  private readonly POLL_INTERVAL = 3000;

  startPolling() {
    this.pollAttempts = 0;
    this.poll();
  }

  poll() {
    if (!this.pendingCollectibleId || !this.generating) return;
    if (this.pollAttempts >= this.MAX_POLL_ATTEMPTS) {
      this.generating = false;
      this.error = String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_timeout'));
      m.redraw();
      return;
    }

    this.pollAttempts++;

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/collectibles/' + this.pendingCollectibleId,
      })
      .then((response: any) => {
        const status = response?.data?.attributes?.status;

        if (status === 'completed') {
          this.fetchCollectible(this.pendingCollectibleId!);
        } else if (status === 'failed') {
          this.generating = false;
          this.error = String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_failed'));
          // Refund is done server-side
          m.redraw();
        } else {
          // Still generating, poll again
          this.pollingTimer = setTimeout(() => this.poll(), this.POLL_INTERVAL);
        }
      })
      .catch(() => {
        this.pollingTimer = setTimeout(() => this.poll(), this.POLL_INTERVAL);
      });
  }

  fetchCollectible(id: string) {
    app.store
      .find('collectibles', id)
      .then((collectible: any) => {
        this.generating = false;
        this.generatedCollectible = collectible;
        this.stopPolling();
        m.redraw();
      })
      .catch(() => {
        this.generating = false;
        this.error = String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_failed'));
        this.stopPolling();
        m.redraw();
      });
  }

  stopPolling() {
    if (this.pollingTimer) {
      clearTimeout(this.pollingTimer);
      this.pollingTimer = null;
    }
  }

  reset() {
    this.generating = false;
    this.generatedCollectible = null;
    this.error = null;
    this.pendingCollectibleId = null;
    this.pendingRarity = null;
    this.stopPolling();
  }
}
