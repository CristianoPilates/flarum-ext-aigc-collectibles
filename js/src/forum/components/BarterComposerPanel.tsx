import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import { collectibleRarityLabel, displayCollectibleName } from '../utils/collectibles';
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

export default class BarterComposerPanel extends Component<BarterComposerPanelAttrs> {
  view() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const payload = fields.barterAssets();
    const recipient = this.attrs.dialog?.recipient?.();
    const enabled = fields.barterEnabled();
    const expanded = enabled || fields.barterExpanded();
    const counts = barterSelectionCounts(this.attrs.composer);

    return (
      <section className={`BarterComposerPanel ${expanded ? 'BarterComposerPanel--expanded' : ''}`}>
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
            <Button className={`Button ${enabled ? '' : 'Button--primary'}`} onclick={() => void this.toggleEnabled()}>
              {enabled
                ? this.trans('donk-aigc-collectibles.forum.barter.disable_button')
                : this.trans('donk-aigc-collectibles.forum.barter.enable_button')}
            </Button>
            {enabled ? (
              <Button className="Button Button--icon" icon="fas fa-sync" onclick={() => void loadBarterAssets(this.attrs.composer, this.attrs.dialog, true)}>
                {this.trans('donk-aigc-collectibles.forum.barter.refresh')}
              </Button>
            ) : null}
          </div>
        </div>

        {enabled ? (
          <div className="BarterComposerPanel-summary">
            <span>
              {this.trans('donk-aigc-collectibles.forum.barter.summary_yours', { count: counts.yours })}
            </span>
            <span>
              {this.trans('donk-aigc-collectibles.forum.barter.summary_theirs', { count: counts.theirs })}
            </span>
          </div>
        ) : null}

        {enabled ? this.viewBody(payload) : null}
      </section>
    );
  }

  viewBody(payload: any) {
    const fields = ensureBarterComposerFields(this.attrs.composer);

    if (fields.barterError()) {
      return <div className="BarterComposerPanel-error">{fields.barterError()}</div>;
    }

    if (fields.barterValidationError()) {
      return <div className="BarterComposerPanel-error">{fields.barterValidationError()}</div>;
    }

    if (fields.barterLoading()) {
      return <div className="BarterComposerPanel-loading">{this.trans('donk-aigc-collectibles.forum.barter.loading')}</div>;
    }

    if (!payload) {
      return null;
    }

    return (
      <div className="BarterComposerPanel-grid">
        <div className="BarterComposerPanel-column">
          <div className="BarterComposerPanel-columnTitle">
            {this.trans('donk-aigc-collectibles.forum.barter.your_offer')}
          </div>
          {this.assetGroup(
            payload.yours?.collectibles || [],
            barterSelections(this.attrs.composer, 'yours'),
            'yours',
            'collectibles_label'
          )}
          {this.assetGroup(
            payload.yours?.blindBoxes || [],
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
            payload.theirs?.collectibles || [],
            barterSelections(this.attrs.composer, 'theirs'),
            'theirs',
            'collectibles_label'
          )}
          {this.assetGroup(
            payload.theirs?.blindBoxes || [],
            barterSelections(this.attrs.composer, 'theirs'),
            'theirs',
            'blind_boxes_label'
          )}
        </div>
      </div>
    );
  }

  assetCard(asset: BarterAsset, checked: boolean, side: BarterSelectionSide) {
    const token = optionToken(asset);
    const isCollectible = asset.assetType === 'collectible';
    const svgUrl = blindBoxSvgUrl(app.forum.attribute('baseUrl'), asset.type || '', asset.status || 'unappraised');
    const imageUrl = isCollectible && asset.ipfsCid ? gatewayUrl(asset.ipfsCid) : '';
    const label = this.assetLabel(asset);
    const meta = this.assetMeta(asset);

    return (
      <div
        className={`BarterAssetCard ${checked ? 'BarterAssetCard--selected' : ''}`}
        key={token}
        onclick={() => this.toggleSelection(side, token, !checked)}
        role="checkbox"
        aria-checked={checked}
        tabIndex={0}
        onkeydown={(e: KeyboardEvent) => {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            this.toggleSelection(side, token, !checked);
          }
        }}
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
            {this.trans('donk-aigc-collectibles.forum.barter.no_assets')}
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
  }

  toggleSelection(side: BarterSelectionSide, token: string, checked: boolean) {
    toggleBarterSelection(this.attrs.composer, side, token, checked);
  }

  trans(key: string, parameters: Record<string, unknown> = {}) {
    return app.translator.trans(key, parameters);
  }
}
