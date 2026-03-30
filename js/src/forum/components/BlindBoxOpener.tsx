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

interface BlindBoxOpenerAttrs {
  blindBox?: any;
  onUpdated?: () => void;
}

export default class BlindBoxOpener extends Modal<BlindBoxOpenerAttrs> {
  blindBox: any = null;
  appraising: boolean = false;
  generating: boolean = false;
  generatedCollectible: any = null;
  error: string | null = null;
  pendingCollectibleId: string | null = null;
  pendingRarity: string | null = null;
  pendingPowZeros: number = 0;
  pendingPowAttempts: number = 0;
  pendingPowSecondsLeft: number = 10;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.blindBox = this.attrs.blindBox || null;
    this.appraising = false;
    this.generating = false;
    this.generatedCollectible = null;
    this.error = null;
    this.pendingCollectibleId = null;
    this.pendingRarity = null;
    this.pendingPowZeros = 0;
    this.pendingPowAttempts = 0;
    this.pendingPowSecondsLeft = 10;
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
    this.stopPolling();
    if (this.wsHandler) {
      unsubscribe('collectible.ready', this.wsHandler);
    }
  }

  private wsHandler: ((data: any) => void) | null = null;

  content() {
    const user = app.session?.user;
    if (!user) return null;

    const blindBoxCount = user.attribute<number>('blindBoxCount') || 0;
    const blindBox = this.blindBox;

    // State: reveal completed collectible
    if (this.generatedCollectible) {
      return this.viewReveal();
    }

    if (this.appraising) {
      return this.viewAppraising();
    }

    // State: generating in progress
    if (this.generating) {
      return this.viewGenerating();
    }

    // State: error
    if (this.error) {
      return this.viewError();
    }

    if (!blindBox) {
      return (
        <div className="Modal-body BlindBoxOpener-body BlindBoxOpener-empty">
          <p>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_empty')}</p>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.close')}
          </Button>
        </div>
      );
    }

    const status = blindBox.status?.() || blindBox.attributes?.status || 'unappraised';
    const budget = blindBox.budget?.() ?? blindBox.attributes?.budget ?? null;
    const type = blindBox.type?.() || blindBox.attributes?.type || 'unknown';
    const drawRules = Array.isArray(blindBox.drawRules?.())
      ? blindBox.drawRules()
      : blindBox.attributes?.drawRules || [];

    return (
      <div className="Modal-body BlindBoxOpener-body">
        <div className={`BlindBoxOpener-summary BlindBoxOpener-summary--${type} BlindBoxOpener-summary--${status}`}>
          <div className="BlindBoxOpener-balance">
            <i className="fas fa-box" />
            <span className="BlindBoxOpener-balanceCount">{blindBoxCount}</span>
            <span className="BlindBoxOpener-balanceLabel">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.balance')}
            </span>
          </div>

          <h3 className="BlindBoxOpener-summaryTitle">
            {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.type_${type}`)}
          </h3>

          <p className="BlindBoxOpener-summaryRow">
            <strong>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.status_label')}</strong>{' '}
            {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.status_${status}`)}
          </p>
          <p className="BlindBoxOpener-summaryRow">
            <strong>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.budget_label')}</strong>{' '}
            {typeof budget === 'number'
              ? app.translator.trans('donk-aigc-collectibles.forum.blind_box.budget_value', { budget })
              : app.translator.trans('donk-aigc-collectibles.forum.blind_box.budget_unknown')}
          </p>
          <p className="BlindBoxOpener-summaryRow BlindBoxOpener-summaryRow--seed">
            <strong>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.seed_label')}</strong>{' '}
            <code>{blindBox.seed?.() || blindBox.attributes?.seed || '-'}</code>
          </p>

          <div className="BlindBoxOpener-summaryRules">
            {drawRules.map((rule: { category: string; required: boolean }) => (
              <span
                className={`BlindBoxCard-category${rule.required ? ' BlindBoxCard-category--required' : ''}`}
                key={`${rule.category}-${rule.required ? 'required' : 'optional'}`}
              >
                {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.category_${rule.category}`)}
              </span>
            ))}
          </div>
        </div>

        <div className="BlindBoxOpener-action">
          {status === 'unappraised' && (
            <Button
              className="Button Button--primary Button--block BlindBoxOpener-openButton"
              onclick={() => this.appraiseCurrentBox()}
              icon="fas fa-hammer"
            >
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.appraise_button')}
            </Button>
          )}

          {status === 'appraised' && (
            <Button
              className="Button Button--primary Button--block BlindBoxOpener-openButton"
              onclick={() => this.openCurrentBox()}
              disabled={blindBoxCount < 1}
              icon="fas fa-box-open"
            >
              {blindBoxCount >= 1
                ? app.translator.trans('donk-aigc-collectibles.forum.blind_box.open_collectible_button')
                : app.translator.trans('donk-aigc-collectibles.forum.blind_box.insufficient')}
            </Button>
          )}

          <Button className="Button Button--block" onclick={() => this.hide()}>
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.close')}
          </Button>
        </div>
      </div>
    );
  }

  viewAppraising() {
    const rarity = this.pendingRarity || 'common';

    return (
      <div className={'Modal-body BlindBoxOpener-body BlindBoxOpener-generating BlindBoxOpener-generating--' + rarity}>
        <div className="BlindBoxOpener-generatingAnimation">
          <LoadingIndicator size="large" />
          <div className="BlindBoxOpener-generatingIcon">
            <i className="fas fa-hammer" />
          </div>
        </div>
        <p className="BlindBoxOpener-generatingText">
          {app.translator.trans('donk-aigc-collectibles.forum.blind_box.appraising')}
        </p>
        <p className="BlindBoxOpener-generatingText">
          {app.translator.trans('donk-aigc-collectibles.forum.blind_box.appraising_progress', {
            seconds: this.pendingPowSecondsLeft,
            attempts: this.pendingPowAttempts,
            zeros: this.pendingPowZeros,
          })}
        </p>
        <span className={'CollectibleRarity CollectibleRarity--' + rarity}>
          {app.translator.trans('donk-aigc-collectibles.forum.blind_box.appraising_quality', {
            rarity: RARITY_LABELS[rarity] || rarity,
          })}
        </span>
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

  appraiseCurrentBox() {
    if (this.appraising || this.generating) return;
    if (!this.blindBox) return;

    this.appraising = true;
    this.generating = false;
    this.error = null;
    this.generatedCollectible = null;
    this.pendingCollectibleId = null;
    this.pendingPowZeros = 0;
    this.pendingPowAttempts = 0;
    this.pendingPowSecondsLeft = 10;
    this.pendingRarity = 'common';
    m.redraw();

    void this.runAppraisalFlow();
  }

  openCurrentBox() {
    if (this.appraising || this.generating) return;
    if (!this.blindBox) return;

    this.appraising = false;
    this.generating = true;
    this.error = null;
    this.generatedCollectible = null;
    this.pendingCollectibleId = null;
    this.pendingPowZeros = 0;
    this.pendingPowAttempts = 0;
    this.pendingPowSecondsLeft = 10;
    m.redraw();

    void this.runOpenFlow();
  }

  async runAppraisalFlow() {
    try {
      const readyBox = await this.appraiseBlindBox(this.blindBox);
      const readyBoxId = this.getBlindBoxId(readyBox) || this.getBlindBoxId(this.blindBox);
      this.blindBox = readyBoxId ? app.store.getById('blindboxes', readyBoxId) || this.blindBox : this.blindBox;
      this.appraising = false;
      this.generating = false;
      this.attrs.onUpdated?.();
      m.redraw();
    } catch (error: any) {
      this.appraising = false;
      this.generating = false;
      this.error =
        error?.response?.errors?.[0]?.detail ||
        error?.message ||
        String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_failed'));
      m.redraw();
    }
  }

  async runOpenFlow() {
    try {
      if (!this.blindBox) {
        throw new Error(String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.insufficient')));
      }

      const readyBox = this.blindBox;
      const readyBoxId = this.getBlindBoxId(readyBox);
      if (!readyBoxId) {
        throw new Error('Blind box id is missing.');
      }

      this.pendingRarity = this.rarityFromBudget(readyBox.budget?.() ?? readyBox.attributes?.budget ?? 0);

      const response: any = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/blindboxes/' + readyBoxId + '/open',
      });

      const collectibleId = response?.data?.relationships?.collectible?.data?.id;
      if (!collectibleId) {
        throw new Error(String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_failed')));
      }

      this.pendingCollectibleId = collectibleId;

      const user = app.session?.user;
      if (user) {
        const currentCount = user.attribute<number>('blindBoxCount') || 0;
        user.pushAttributes({ blindBoxCount: Math.max(0, currentCount - 1) });
      }

      this.attrs.onUpdated?.();
      m.redraw();
      this.startPolling();
    } catch (error: any) {
      this.appraising = false;
      this.generating = false;
      this.error =
        error?.response?.errors?.[0]?.detail ||
        error?.message ||
        String(app.translator.trans('donk-aigc-collectibles.forum.blind_box.generation_failed'));
      m.redraw();
    }
  }

  async appraiseBlindBox(box: any): Promise<any> {
    const boxId = this.getBlindBoxId(box);
    const seed = box.seed?.() || box.attributes?.seed;
    if (!boxId || !seed) {
      throw new Error('Blind box seed is missing.');
    }

    const pow = await this.computePow(seed, 10_000);
    this.pendingRarity = this.rarityFromZeros(pow.zeros);

    const response: any = await app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/blindboxes/' + boxId + '/appraise',
      body: {
        nonce: pow.nonce,
        hash: pow.hash,
      },
    });

    const payload = response?.data ? app.store.pushPayload(response) : null;
    const readyBox = Array.isArray(payload) ? payload[0] : payload || response?.data || response;
    const budget = readyBox?.attributes?.budget;

    if (typeof budget === 'number') {
      this.pendingRarity = this.rarityFromBudget(budget);
    }

    if (box.pushAttributes) {
      box.pushAttributes({
        status: readyBox?.attributes?.status,
        budget: readyBox?.attributes?.budget,
      });
    }

    return readyBox;
  }

  getBlindBoxId(box: any): string | null {
    if (!box) return null;
    if (typeof box.id === 'function') return String(box.id());
    if (box.id !== undefined && box.id !== null) return String(box.id);
    if (box.data?.id !== undefined && box.data?.id !== null) return String(box.data.id);
    return null;
  }

  async computePow(
    seed: string,
    durationMs: number = 10_000
  ): Promise<{ nonce: string; hash: string; zeros: number; attempts: number }> {
    const deadline = performance.now() + durationMs;
    const uiUpdateIntervalMs = 200;
    const yieldEvery = 100;

    let nonce = '0';
    let hash = await this.sha256(seed + nonce);
    let bestZeros = this.leadingZeros(hash);
    let attempts = 1;
    let lastUiUpdate = performance.now();

    this.updatePowProgress(deadline, bestZeros, attempts);

    while (performance.now() < deadline) {
      const candidateNonce = attempts.toString(16);
      const candidateHash = await this.sha256(seed + candidateNonce);
      attempts++;
      const zeros = this.leadingZeros(candidateHash);

      if (zeros > bestZeros) {
        nonce = candidateNonce;
        hash = candidateHash;
        bestZeros = zeros;
      }

      const now = performance.now();
      if (now - lastUiUpdate >= uiUpdateIntervalMs) {
        this.updatePowProgress(deadline, bestZeros, attempts);
        lastUiUpdate = now;
      }

      if (attempts % yieldEvery === 0) {
        await this.yieldToBrowser();
      }
    }

    this.updatePowProgress(deadline, bestZeros, attempts);

    return { nonce, hash, zeros: bestZeros, attempts };
  }

  updatePowProgress(deadline: number, zeros: number, attempts: number) {
    const secondsLeft = Math.max(0, Math.ceil((deadline - performance.now()) / 1000));

    this.pendingPowZeros = zeros;
    this.pendingPowAttempts = attempts;
    this.pendingPowSecondsLeft = secondsLeft;
    this.pendingRarity = this.rarityFromZeros(zeros);
    m.redraw();
  }

  async yieldToBrowser(): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve, 0));
  }

  async sha256(value: string): Promise<string> {
    const bytes = new TextEncoder().encode(value);
    const digest = await crypto.subtle.digest('SHA-256', bytes);

    return Array.from(new Uint8Array(digest))
      .map((byte) => byte.toString(16).padStart(2, '0'))
      .join('');
  }

  leadingZeros(hash: string): number {
    let count = 0;

    while (count < hash.length && hash[count] === '0') {
      count++;
    }

    return count;
  }

  rarityFromZeros(zeros: number): string {
    if (zeros >= 7) return 'legendary';
    if (zeros >= 6) return 'epic';
    if (zeros >= 5) return 'rare';
    return 'common';
  }

  rarityFromBudget(budget: number): string {
    if (budget >= 160) return 'legendary';
    if (budget >= 80) return 'epic';
    if (budget >= 40) return 'rare';
    return 'common';
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
    this.blindBox = this.attrs.blindBox || null;
    this.appraising = false;
    this.generating = false;
    this.generatedCollectible = null;
    this.error = null;
    this.pendingCollectibleId = null;
    this.pendingRarity = null;
    this.pendingPowZeros = 0;
    this.pendingPowAttempts = 0;
    this.pendingPowSecondsLeft = 10;
    this.stopPolling();
  }
}
