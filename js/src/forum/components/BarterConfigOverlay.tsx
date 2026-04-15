import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import {
  barterSelections,
  barterSelectionCounts,
  toggleBarterSelection,
  optionToken,
} from '../utils/barterComposer';
import { displayCollectibleName, collectibleRarityLabel, sortAssetsByRarity } from '../utils/collectibles';
import { gatewayUrl } from '../utils/ipfs';
import { blindBoxSvgUrl } from '../utils/blindBoxes';
import { transText } from '../utils/i18n';
import type { BarterAsset, BarterAssetBucket, BarterSelectionSide } from '../utils/barterComposer';

const ITEMS_PER_PAGE = 12;
const RARITY_ORDER = ['legendary', 'epic', 'rare', 'common'] as const;

interface BarterConfigOverlayAttrs extends IInternalModalAttrs {
  composer: any;
  dialog: any;
}

export default class BarterConfigOverlay extends Modal<BarterConfigOverlayAttrs> {
  private activeTab: 'mine-collectibles' | 'mine-blindboxes' | 'theirs-collectibles' | 'theirs-blindboxes' = 'mine-collectibles';
  private searchQuery: string = '';
  private activeRarity: string | null = null;
  private activeStatus: string | null = null;
  private currentPage: number = 1;
  private bannerDismissed: boolean = false;
  private focusedIndex: number = 0;

  className() {
    return 'BarterConfigOverlay';
  }

  title() {
    return app.translator.trans('donk-aigc-collectibles.forum.barter.config_overlay_title');
  }

  oninit(vnode: any) {
    super.oninit(vnode);
    this.activeTab = 'mine-collectibles';
    this.searchQuery = '';
    this.activeRarity = null;
    this.activeStatus = null;
    this.currentPage = 1;
    this.focusedIndex = 0;
    this.bannerDismissed = false;
  }

  content() {
    return (
      <div className="BarterConfigOverlay-body">
        {this.renderSummary()}
        {this.renderTabBar()}
        {this.renderFilterBar()}
        {this.renderGrid()}
        {this.renderFooter()}
      </div>
    );
  }

  // ─── Summary Row ─────────────────────────────────────────────────────────────

  private renderSummary() {
    const yoursC = barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('collectible:')).length;
    const yoursB = barterSelections(this.attrs.composer, 'yours').filter((t) => t.startsWith('blind_box:')).length;
    const theirsC = barterSelections(this.attrs.composer, 'theirs').filter((t) => t.startsWith('collectible:')).length;
    const theirsB = barterSelections(this.attrs.composer, 'theirs').filter((t) => t.startsWith('blind_box:')).length;
    const total = yoursC + yoursB + theirsC + theirsB;

    if (total === 0) return null;

    return (
      <div className="BarterConfigOverlay-summary">
        <div className="BarterConfigOverlay-summaryRow">
          <div className="BarterConfigOverlay-summarySide">
            <div className="BarterConfigOverlay-summaryLabel">
              {app.translator.trans('donk-aigc-collectibles.forum.barter.your_side')}
            </div>
            <div className="BarterConfigOverlay-summaryValue">
              {yoursC > 0 && `${yoursC} ${transText('donk-aigc-collectibles.forum.barter.collectibles_label')}`}
              {yoursC > 0 && yoursB > 0 && ' · '}
              {yoursB > 0 && `${yoursB} ${transText('donk-aigc-collectibles.forum.barter.blind_boxes_label')}`}
              {yoursC === 0 && yoursB === 0 && (
                <span className="BarterConfigOverlay-summaryEmpty">
                  {transText('donk-aigc-collectibles.forum.barter.config_overlay_nothing_selected')}
                </span>
              )}
            </div>
          </div>
          <div className="BarterConfigOverlay-summaryVs">
            ↔
          </div>
          <div className="BarterConfigOverlay-summarySide">
            <div className="BarterConfigOverlay-summaryLabel">
              {app.translator.trans('donk-aigc-collectibles.forum.barter.their_side')}
            </div>
            <div className="BarterConfigOverlay-summaryValue">
              {theirsC > 0 && `${theirsC} ${transText('donk-aigc-collectibles.forum.barter.collectibles_label')}`}
              {theirsC > 0 && theirsB > 0 && ' · '}
              {theirsB > 0 && `${theirsB} ${transText('donk-aigc-collectibles.forum.barter.blind_boxes_label')}`}
              {theirsC === 0 && theirsB === 0 && (
                <span className="BarterConfigOverlay-summaryEmpty">
                  {transText('donk-aigc-collectibles.forum.barter.config_overlay_nothing_selected')}
                </span>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  // ─── Tab Bar ────────────────────────────────────────────────────────────────

  private renderTabBar() {
    const buckets = this.getActiveBucket();
    const total = buckets.collectibles.length + buckets.blindBoxes.length;

    return (
      <div className="BarterConfigOverlay-tabs" role="tablist">
        {this.tabButton('mine-collectibles', 'mine', 'collectibles', '📦')}
        {this.tabButton('mine-blindboxes', 'mine', 'blindboxes', '🎁')}
        {this.tabButton('theirs-collectibles', 'theirs', 'collectibles', '👤')}
        {this.tabButton('theirs-blindboxes', 'theirs', 'blindboxes', '🎁')}
      </div>
    );
  }

  private tabButton(
    key: typeof this.activeTab,
    side: 'mine' | 'theirs',
    kind: 'collectibles' | 'blindboxes',
    icon: string
  ) {
    const bucket = this.getBucket(side, kind);
    const selected = this.getSideSelections(side).length;
    const total = bucket.length;
    const isActive = this.activeTab === key;

    const labelKey = kind === 'collectibles'
      ? 'donk-aigc-collectibles.forum.barter.config_tab_mine_collectibles'
      : 'donk-aigc-collectibles.forum.barter.config_tab_mine_blindboxes';
    const theirsLabelKey = kind === 'collectibles'
      ? 'donk-aigc-collectibles.forum.barter.config_tab_theirs_collectibles'
      : 'donk-aigc-collectibles.forum.barter.config_tab_theirs_blindboxes';

    return (
      <button
        className={`BarterConfigOverlay-tab ${isActive ? 'active' : ''}`}
        role="tab"
        aria-selected={isActive}
        onclick={() => {
          this.activeTab = key;
          this.currentPage = 1;
          this.searchQuery = '';
          this.activeRarity = null;
          this.activeStatus = null;
          m.redraw();
        }}
      >
        <span>{icon} {app.translator.trans(side === 'mine' ? labelKey : theirsLabelKey)}</span>
        {total > 0 && (
          <span className={`BarterConfigOverlay-tabCount ${selected > 0 ? 'has-selections' : ''}`}>
            {selected > 0 ? `${selected}` : total}
          </span>
        )}
      </button>
    );
  }

  // ─── Filter Bar ────────────────────────────────────────────────────────────

  private renderFilterBar() {
    const isBlindBoxTab = this.activeTab.includes('blindboxes');

    return (
      <div className="BarterConfigOverlay-filters">
        <input
          className="BarterConfigOverlay-search"
          type="text"
          placeholder={transText('donk-aigc-collectibles.forum.barter.config_overlay_search_placeholder')}
          value={this.searchQuery}
          oninput={(e: InputEvent) => {
            this.searchQuery = (e.target as HTMLInputElement).value;
            this.currentPage = 1;
            m.redraw();
          }}
          aria-label={transText('donk-aigc-collectibles.forum.barter.config_overlay_search_aria')}
        />
        <div className="BarterConfigOverlay-rarityPills">
          <button
            className={`BarterConfigOverlay-pill ${(isBlindBoxTab ? this.activeStatus : this.activeRarity) === null ? 'active' : ''}`}
            onclick={() => {
              if (isBlindBoxTab) {
                this.activeStatus = null;
              } else {
                this.activeRarity = null;
              }
              this.currentPage = 1;
              m.redraw();
            }}
          >
            {transText('donk-aigc-collectibles.forum.barter.config_overlay_all')}
          </button>
          {isBlindBoxTab ? (
            <>
              <button
                className={`BarterConfigOverlay-pill ${this.activeStatus === 'appraised' ? 'active' : ''}`}
                onclick={() => {
                  this.activeStatus = this.activeStatus === 'appraised' ? null : 'appraised';
                  this.currentPage = 1;
                  m.redraw();
                }}
              >
                {app.translator.trans('donk-aigc-collectibles.forum.blind_box.status_appraised')}
              </button>
              <button
                className={`BarterConfigOverlay-pill ${this.activeStatus === 'unappraised' ? 'active' : ''}`}
                onclick={() => {
                  this.activeStatus = this.activeStatus === 'unappraised' ? null : 'unappraised';
                  this.currentPage = 1;
                  m.redraw();
                }}
              >
                {app.translator.trans('donk-aigc-collectibles.forum.blind_box.status_unappraised')}
              </button>
            </>
          ) : (
            RARITY_ORDER.map((r) => (
              <button
                key={r}
                className={`BarterConfigOverlay-pill rarity-${r} ${this.activeRarity === r ? 'active' : ''}`}
                onclick={() => {
                  this.activeRarity = this.activeRarity === r ? null : r;
                  this.currentPage = 1;
                  m.redraw();
                }}
              >
                {app.translator.trans('donk-aigc-collectibles.forum.collectible.rarity_' + r)}
              </button>
            ))
          )}
        </div>
      </div>
    );
  }

  // ─── Grid ─────────────────────────────────────────────────────────────────

  private renderGrid() {
    const bucket = this.getActiveBucket();
    const kind: 'collectibles' | 'blindboxes' = this.activeTab.includes('collectibles') ? 'collectibles' : 'blindboxes';
    const assets: BarterAsset[] = bucket?.[kind] ?? [];

    const filtered = this.filterAssets(assets);
    const selected = this.getSideSelections(this.activeTab.startsWith('mine') ? 'yours' : 'theirs');

    // Old-collectibles banner: only on "mine-collectibles" tab, only if there are unnamed items
    const unnamedBanner = this.renderUnnamedBanner();

    if (filtered.length === 0) {
      return (
        <div className="BarterConfigOverlay-gridArea">
          {unnamedBanner}
          <div className="BarterConfigOverlay-empty">
            <div className="BarterConfigOverlay-emptyIcon">📦</div>
            <div className="BarterConfigOverlay-emptyTitle">
              {transText('donk-aigc-collectibles.forum.barter.config_overlay_no_results')}
            </div>
            <div className="BarterConfigOverlay-emptyHint">
              {this.activeTab.startsWith('mine')
                ? transText('donk-aigc-collectibles.forum.barter.config_overlay_earn_hint')
                : transText('donk-aigc-collectibles.forum.barter.config_overlay_them_empty_hint')}
            </div>
          </div>
        </div>
      );
    }

    const totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE);
    const start = (this.currentPage - 1) * ITEMS_PER_PAGE;
    const pageItems = filtered.slice(start, start + ITEMS_PER_PAGE);

    return (
      <div className="BarterConfigOverlay-gridArea">
        {unnamedBanner}
        <div className="BarterConfigOverlay-grid" role="tabpanel">
          {pageItems.map((asset, idx) => {
            const token = optionToken(asset);
            const checked = selected.includes(token);
            const globalIdx = start + idx;
            return this.assetCard(asset, checked, globalIdx);
          })}
        </div>
        {totalPages > 1 && (
          <div className="BarterConfigOverlay-pagination">
            <button
              className="BarterConfigOverlay-pageBtn"
              disabled={this.currentPage <= 1}
              onclick={() => {
                this.currentPage--;
                m.redraw();
              }}
            >
              ←
            </button>
            <span className="BarterConfigOverlay-pageInfo">
              {transText('donk-aigc-collectibles.forum.barter.config_overlay_page', {
                current: String(this.currentPage),
                total: String(totalPages),
              })}
            </span>
            <button
              className="BarterConfigOverlay-pageBtn"
              disabled={this.currentPage >= totalPages}
              onclick={() => {
                this.currentPage++;
                m.redraw();
              }}
            >
              →
            </button>
          </div>
        )}
      </div>
    );
  }

  // ─── Old-collectibles banner ──────────────────────────────────────────────────

  private renderUnnamedBanner(): any {
    if (this.activeTab !== 'mine-collectibles' || this.bannerDismissed) return null;

    const bucket = this.getBucket('mine', 'collectibles');
    const unnamed = bucket.collectibles.filter((a) => !a.name);

    if (unnamed.length === 0) return null;

    return (
      <div className="BarterConfigOverlay-unnamedBanner">
        <i className="fas fa-exclamation-circle" />
        <span>
          {app.translator.trans('donk-aigc-collectibles.forum.barter.config_overlay_unnamed_count', {
            count: String(unnamed.length),
          })}
        </span>
        <button
          className="BarterConfigOverlay-unnamedDismiss"
          onclick={() => { this.bannerDismissed = true; m.redraw(); }}
          title={transText('donk-aigc-collectibles.forum.blind_box.close')}
        >
          <i className="fas fa-times" />
        </button>
      </div>
    );
  }

  private assetCard(asset: BarterAsset, checked: boolean, idx: number) {
    const side: BarterSelectionSide = this.activeTab.startsWith('mine') ? 'yours' : 'theirs';
    const isCollectible = asset.assetType === 'collectible';
    const svgUrl = blindBoxSvgUrl(app.forum.attribute('baseUrl'), asset.status || 'unappraised');
    const imageUrl = isCollectible && asset.ipfsCid ? gatewayUrl(asset.ipfsCid) : '';
    const name = displayCollectibleName(asset.name, asset.id);
    const rarity = asset.rarity || 'common';

    const meta = isCollectible
      ? [collectibleRarityLabel(rarity), asset.tokenId ? `#${asset.tokenId}` : ''].filter(Boolean).join(' · ')
      : [
          transText('donk-aigc-collectibles.forum.blind_box.status_' + (asset.status || 'unknown')),
          `${transText('donk-aigc-collectibles.forum.blind_box.budget_label')}: ${typeof asset.budget === 'number' ? asset.budget : transText('donk-aigc-collectibles.forum.blind_box.budget_unknown')}`,
        ].join(' · ');

    const thumbUrl = imageUrl || svgUrl;

    return (
      <div
        key={optionToken(asset)}
        className={`BarterAssetCard barter-card ${checked ? 'selected' : ''} rarity-${rarity}`}
        role="checkbox"
        aria-checked={checked}
        aria-label={`${name}, ${meta}${checked ? ', selected' : ''}`}
        tabIndex={idx === this.focusedIndex ? 0 : -1}
        onclick={() => this.handleToggle(side, asset, !checked)}
        onkeydown={(e: KeyboardEvent) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.handleToggle(side, asset, !checked);
          }
          if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            this.focusedIndex = Math.min(this.focusedIndex + 1, this.getActiveBucket()[this.activeTab.includes('collectibles') ? 'collectibles' : 'blindBoxes'].length - 1);
            m.redraw();
          }
          if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            this.focusedIndex = Math.max(this.focusedIndex - 1, 0);
            m.redraw();
          }
        }}
        title={name}
      >
        <div className="BarterAssetCard-thumb">
          <img
            src={thumbUrl}
            alt={name}
            className="BarterAssetCard-img"
            loading="lazy"
          />
          {checked && (
            <div className="BarterAssetCard-check">
              <i className="fas fa-check" />
            </div>
          )}
        </div>
        <div className="BarterAssetCard-info">
          <div className="BarterAssetCard-name">{name}</div>
          <div className="BarterAssetCard-meta">{meta}</div>
        </div>
      </div>
    );
  }

  // ─── Footer ─────────────────────────────────────────────────────────────

  private renderFooter() {
    return (
      <div className="BarterConfigOverlay-footer">
        <Button
          className="Button"
          onclick={() => this.hide()}
        >
          {transText('donk-aigc-collectibles.forum.barter.config_overlay_cancel')}
        </Button>
        <Button
          className="Button Button--primary"
          onclick={() => this.hide()}
        >
          {transText('donk-aigc-collectibles.forum.barter.config_overlay_confirm')}
        </Button>
      </div>
    );
  }

  // ─── Helpers ────────────────────────────────────────────────────────────────

  private handleToggle(side: BarterSelectionSide, asset: BarterAsset, checked: boolean) {
    const token = optionToken(asset);
    toggleBarterSelection(this.attrs.composer, side, token, checked);
    m.redraw();
  }

  private getBucket(side: 'mine' | 'theirs', kind: 'collectibles' | 'blindBoxes'): BarterAssetBucket {
    const payload = this.attrs.composer.fields?.barterAssets?.();
    if (!payload) return { collectibles: [], blindBoxes: [] };

    if (side === 'mine') {
      return { collectibles: payload.yours?.collectibles || [], blindBoxes: payload.yours?.blindBoxes || [] };
    }
    return { collectibles: payload.theirs?.collectibles || [], blindBoxes: payload.theirs?.blindBoxes || [] };
  }

  private getActiveBucket(): BarterAssetBucket {
    const isMine = this.activeTab.startsWith('mine');
    const kind = this.activeTab.includes('collectibles') ? 'collectibles' : 'blindBoxes';
    return this.getBucket(isMine ? 'mine' : 'theirs', kind);
  }

  private getSideSelections(side: 'yours' | 'theirs'): string[] {
    return barterSelections(this.attrs.composer, side);
  }

  private filterAssets(assets: BarterAsset[]): BarterAsset[] {
    let result = [...assets];
    const isBlindBoxTab = this.activeTab.includes('blindboxes');

    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase();
      result = result.filter((a) => {
        const name = displayCollectibleName(a.name, a.id).toLowerCase();
        return name.includes(q) || String(a.id).includes(q);
      });
    }

    // Apply filter based on asset type
    if (isBlindBoxTab) {
      if (this.activeStatus) {
        result = result.filter((a) => a.status === this.activeStatus);
      }
      // Sort blind boxes by budget descending
      result.sort((a, b) => (b.budget ?? 0) - (a.budget ?? 0));
    } else {
      if (this.activeRarity) {
        result = result.filter((a) => a.rarity === this.activeRarity);
      }
      // Sort collectibles by rarity
      const rarityOrder: Record<string, number> = { legendary: 4, epic: 3, rare: 2, common: 1 };
      result.sort((a, b) => (rarityOrder[b.rarity || 'common'] ?? 0) - (rarityOrder[a.rarity || 'common'] ?? 0));
    }

    return result;
  }
}
