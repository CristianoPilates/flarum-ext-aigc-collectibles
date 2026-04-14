import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import { collectibleRarityLabel, displayCollectibleName, sortAssetsByRarity } from '../utils/collectibles';
import { gatewayUrl } from '../utils/ipfs';
import { transText } from '../utils/i18n';
import { displayUserName } from '../utils/users';
import { blindBoxSvgUrl } from '../utils/blindBoxes';
import type { BarterAsset, BarterSelectionSide } from '../utils/barterComposer';
import {
  barterSelections,
  barterSelectionCounts,
  enableBarterComposer,
  ensureBarterComposerFields,
  loadBarterAssets,
  optionToken,
  resetBarterSelections,
  toggleBarterSelection,
} from '../utils/barterComposer';

interface BarterComposerPanelAttrs {
  composer: any;
  dialog: any;
}

let debounceTimers: Record<string, ReturnType<typeof setTimeout>> = {};

export default class BarterComposerPanel extends Component<BarterComposerPanelAttrs> {
  private liveRegion: HTMLElement | null = null;
  private panelRef: HTMLElement | null = null;

  view() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const payload = fields.barterAssets();
    const recipient = this.attrs.dialog?.recipient?.();
    const enabled = fields.barterEnabled();
    const expanded = enabled || fields.barterExpanded();
    const counts = barterSelectionCounts(this.attrs.composer);

    return (
      <section
        className={`BarterComposerPanel ${expanded ? 'BarterComposerPanel--expanded' : ''}`}
        ref={(el: HTMLElement) => (this.panelRef = el)}
      >
        <div className="BarterComposerPanel-header">
          <div>
            <div className="BarterComposerPanel-kicker">
              {this.trans('donk-aigc-collectibles.forum.barter.composer_kicker')}
            </div>
            <div className="BarterComposerPanel-title">
              {this.trans('donk-aigc-collectibles.forum.barter.composer_title', {
                username: displayUserName(recipient),
              })}
            </div>
          </div>

          <div className="BarterComposerPanel-headerActions">
            {enabled && (counts.yours > 0 || counts.theirs > 0) ? (
              <Button
                className="Button Button--text"
                onclick={() => void this.clearSelections()}
              >
                {this.trans('donk-aigc-collectibles.forum.barter.clear_selections')}
              </Button>
            ) : null}
            <Button
              className={`Button ${enabled ? '' : 'Button--primary'}`}
              onclick={() => void this.toggleEnabled()}
            >
              {enabled
                ? this.trans('donk-aigc-collectibles.forum.barter.disable_button')
                : this.trans('donk-aigc-collectibles.forum.barter.enable_button')}
            </Button>
            {enabled ? (
              <Button
                className="Button Button--icon"
                icon="fas fa-sync"
                onclick={() => void loadBarterAssets(this.attrs.composer, this.attrs.dialog, true)}
              >
                {this.trans('donk-aigc-collectibles.forum.barter.refresh')}
              </Button>
            ) : null}
          </div>
        </div>

        {this.renderCounterDiff()}

        {this.renderPreSendSummary()}

        {enabled ? this.viewBody(payload) : null}

        {/* aria-live region for selection count announcements */}
        <div
          ref={(el: HTMLElement) => (this.liveRegion = el)}
          aria-live="polite"
          aria-atomic="true"
          className="BarterComposerPanel-liveRegion"
        />
      </section>
    );
  }

  renderCounterDiff() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const replacesId = fields.barterReplacesProposalId();

    if (!fields.barterEnabled() || !replacesId) return null;

    const payload = fields.barterAssets();
    if (!payload) return null;

    const prevItems = this.getOriginalProposalItems();
    if (!prevItems || prevItems.length === 0) return null;

    const prevCounts = this.countItems(prevItems);
    const currCounts = {
      collectibles: barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('collectible:')).length +
        barterSelections(this.attrs.composer, 'theirs').filter((t) => t.startsWith('collectible:')).length,
      blindBoxes: barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('blind_box:')).length +
        barterSelections(this.attrs.composer, 'theirs').filter((t) => t.startsWith('blind_box:')).length,
    };

    if (prevCounts.collectibles === currCounts.collectibles && prevCounts.blindBoxes === currCounts.blindBoxes) {
      return null;
    }

    return (
      <div className="BarterComposerPanel-counterDiff">
        <span className="BarterComposerPanel-counterDiff-arrow">
          {this.trans('donk-aigc-collectibles.forum.barter.counter_diff', {
            prev: `${prevCounts.collectibles}C / ${prevCounts.blindBoxes}B`,
            curr: `${currCounts.collectibles}C / ${currCounts.blindBoxes}B`,
          })}
        </span>
      </div>
    );
  }

  getOriginalProposalItems(): any[] {
    const dialogId = this.attrs.dialog?.id?.();
    if (!dialogId) return [];

    const proposals = app.store.all('barter-proposals').filter((p: any) => {
      return String(p.threadType?.()) === 'dialog' && String(p.threadId?.()) === String(dialogId);
    });

    const target = proposals.find((p: any) => String(p.id?.()) === String(fields.barterReplacesProposalId()));
    if (!target) return [];

    return target.items?.() || [];
  }

  countItems(items: any[]): { collectibles: number; blindBoxes: number } {
    const collectibles = items.filter((i) => i.assetType?.() === 'collectible').length;
    const blindBoxes = items.filter((i) => i.assetType?.() === 'blind_box').length;
    return { collectibles, blindBoxes };
  }

  renderPreSendSummary() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const enabled = fields.barterEnabled();
    if (!enabled) return null;

    const yours = barterSelections(this.attrs.composer, 'yours');
    const theirs = barterSelections(this.attrs.composer, 'theirs');
    if (yours.length === 0 && theirs.length === 0) return null;

    const payload = fields.barterAssets();
    const yourCollectibles = yours.filter((t) => t.startsWith('collectible:')).length;
    const yourBlindBoxes = yours.filter((t) => t.startsWith('blind_box:')).length;
    const theirCollectibles = theirs.filter((t) => t.startsWith('collectible:')).length;
    const theirBlindBoxes = theirs.filter((t) => t.startsWith('blind_box:')).length;

    return (
      <div className="BarterComposerPanel-preSend" role="status">
        {this.trans('donk-aigc-collectibles.forum.barter.pre_send_summary', {
          collectibles: String(yourCollectibles),
          blindBoxes: String(theirBlindBoxes),
        })}
      </div>
    );
  }

  viewBody(payload: any) {
    const fields = ensureBarterComposerFields(this.attrs.composer);

    if (fields.barterError()) {
      return (
        <div className="BarterComposerPanel-error" role="alert">
          {fields.barterError()}
        </div>
      );
    }

    if (fields.barterValidationError()) {
      return (
        <div className="BarterComposerPanel-error" role="alert">
          {fields.barterValidationError()}
        </div>
      );
    }

    if (fields.barterLoading()) {
      return <div className="BarterComposerPanel-loading">{this.trans('donk-aigc-collectibles.forum.barter.loading')}</div>;
    }

    if (!payload) {
      return null;
    }

    const counts = barterSelectionCounts(this.attrs.composer);

    return (
      <>
        {/* Running selection summary */}
        {counts.yours > 0 || counts.theirs > 0
          ? this.renderSelectionSummary(counts)
          : null}

        <div className="BarterComposerPanel-grid">
          <div className="BarterComposerPanel-column">
            <div className="BarterComposerPanel-columnTitle">
              {this.trans('donk-aigc-collectibles.forum.barter.your_offer')}
            </div>
            {this.assetGroup(
              sortAssetsByRarity(payload.yours?.collectibles || []),
              barterSelections(this.attrs.composer, 'yours'),
              'yours',
              'collectibles_label'
            )}
            {this.assetGroup(
              sortAssetsByRarity(payload.yours?.blindBoxes || []),
              barterSelections(this.attrs.composer, 'yours'),
              'yours',
              'blind_boxes_label'
            )}
          </div>

          <div className="BarterComposerPanel-column">
            <div className="BarterComposerPanel-columnTitle">
              {this.trans('donk-aigc-collectibles.forum.barter.their_offer')}
            </div>
            {this.assetGroup(
              sortAssetsByRarity(payload.theirs?.collectibles || []),
              barterSelections(this.attrs.composer, 'theirs'),
              'theirs',
              'collectibles_label'
            )}
            {this.assetGroup(
              sortAssetsByRarity(payload.theirs?.blindBoxes || []),
              barterSelections(this.attrs.composer, 'theirs'),
              'theirs',
              'blind_boxes_label'
            )}
          </div>
        </div>
      </>
    );
  }

  renderSelectionSummary(counts: { yours: number; theirs: number }) {
    const yoursCollectibles = barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('collectible:')).length;
    const yoursBlindBoxes = barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('blind_box:')).length;
    const theirsCollectibles = barterSelections(this.attrs.composer, 'theirs').filter((t) => t.startsWith('collectible:')).length;
    const theirsBlindBoxes = barterSelections(this.attrs.composer, 'theirs').filter((t) => t.startsWith('blind_box:')).length;

    return (
      <div className="BarterComposerPanel-selectionSummary" aria-live="polite">
        <span>
          {this.trans('donk-aigc-collectibles.forum.barter.selection_summary', {
            collectibles: String(yoursCollectibles),
            blindBoxes: String(yoursBlindBoxes),
          })}
        </span>
      </div>
    );
  }

  assetCard(asset: BarterAsset, checked: boolean, side: BarterSelectionSide) {
    const token = optionToken(asset);
    const isCollectible = asset.assetType === 'collectible';
    const svgUrl = blindBoxSvgUrl(app.forum.attribute('baseUrl'), asset.status || 'unappraised');
    const imageUrl = isCollectible && asset.ipfsCid ? gatewayUrl(asset.ipfsCid) : '';
    const label = this.assetLabel(asset);
    const meta = this.assetMeta(asset);
    const ariaLabel = `${label}${meta ? ', ' + meta : ''}${checked ? ', selected' : ''}`;

    return (
      <div
        className={`BarterAssetCard ${checked ? 'BarterAssetCard--selected' : ''}`}
        key={token}
        onclick={() => this.debouncedToggle(side, token, !checked)}
        role="checkbox"
        aria-checked={checked}
        aria-label={ariaLabel}
        tabIndex={0}
        onkeydown={(e: KeyboardEvent) => {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            this.debouncedToggle(side, token, !checked);
          }
        }}
        title={label}
      >
        <div className="BarterAssetCard-thumb">
          {imageUrl ? (
            <img src={imageUrl} alt={label} className="BarterAssetCard-img" loading="lazy" />
          ) : (
            <img src={svgUrl} alt={label} className="BarterAssetCard-img" loading="lazy" />
          )}
          {checked && (
            <div className="BarterAssetCard-check">
              <i className="fas fa-check" />
            </div>
          )}
        </div>
        <div className="BarterAssetCard-info">
          <div className="BarterAssetCard-name">{label}</div>
          <div className="BarterAssetCard-meta">{meta}</div>
        </div>
      </div>
    );
  }

  assetGroup(assets: BarterAsset[], selections: string[], side: BarterSelectionSide, labelKey: string) {
    return (
      <div className="BarterComposerPanel-group">
        <div className="BarterComposerPanel-groupLabel">
          {this.trans(`donk-aigc-collectibles.forum.barter.${labelKey}`)}
        </div>
        {assets.length === 0 ? (
          <div className="BarterComposerPanel-empty">
            {this.trans('donk-aigc-collectibles.forum.barter.empty_cta')}
          </div>
        ) : (
          <div className="BarterAssetCardGrid">
            {assets.map((asset) => {
              const token = optionToken(asset);
              const checked = selections.includes(token);
              return this.assetCard(asset, checked, side);
            })}
          </div>
        )}
      </div>
    );
  }

  assetLabel(asset: BarterAsset): string {
    if (asset.assetType === 'collectible') {
      return displayCollectibleName(asset.name, asset.id);
    }
    return transText(`donk-aigc-collectibles.forum.blind_box.type_${asset.type || 'unknown'}`);
  }

  assetMeta(asset: BarterAsset): string {
    if (asset.assetType === 'collectible') {
      const rarity = asset.rarity ? collectibleRarityLabel(asset.rarity) : '';
      const tokenId = asset.tokenId ? `#${asset.tokenId}` : '';
      return [rarity, tokenId].filter(Boolean).join(' · ');
    }
    const status = transText(`donk-aigc-collectibles.forum.blind_box.status_${asset.status || 'unknown'}`);
    const budget =
      typeof asset.budget === 'number'
        ? String(asset.budget)
        : transText('donk-aigc-collectibles.forum.blind_box.budget_unknown');
    return `${status} · ${transText('donk-aigc-collectibles.forum.blind_box.budget_label')}: ${budget}`;
  }

  async toggleEnabled() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const nextEnabled = !fields.barterEnabled();

    if (!nextEnabled) {
      fields.barterEnabled(false);
      fields.barterExpanded(false);
      fields.barterError(null);
      fields.barterValidationError(null);
      resetBarterSelections(this.attrs.composer);
      fields.barterReplacesProposalId(null);
      m.redraw();
      return;
    }

    enableBarterComposer(this.attrs.composer);
    await loadBarterAssets(this.attrs.composer, this.attrs.dialog, true);

    // Focus first asset card after load
    requestAnimationFrame(() => {
      const firstCard = this.panelRef?.querySelector('.BarterAssetCard') as HTMLElement | null;
      firstCard?.focus();
    });
  }

  clearSelections() {
    resetBarterSelections(this.attrs.composer);
    m.redraw();
    this.announceSelection();
  }

  debouncedToggle(side: BarterSelectionSide, token: string, checked: boolean) {
    const key = `${side}:${token}`;
    if (debounceTimers[key]) {
      clearTimeout(debounceTimers[key]);
    }
    debounceTimers[key] = setTimeout(() => {
      toggleBarterSelection(this.attrs.composer, side, token, checked);
      this.announceSelection();
      delete debounceTimers[key];
    }, 100);
  }

  announceSelection() {
    const counts = barterSelectionCounts(this.attrs.composer);
    const yoursCollectibles = barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('collectible:')).length;
    const yoursBlindBoxes = barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('blind_box:')).length;

    if (this.liveRegion) {
      this.liveRegion.textContent = this.trans('donk-aigc-collectibles.forum.barter.selection_summary', {
        collectibles: String(yoursCollectibles),
        blindBoxes: String(yoursBlindBoxes),
      });
    }

    m.redraw();
  }

  trans(key: string, parameters: Record<string, unknown> = {}) {
    return app.translator.trans(key, parameters);
  }
}
