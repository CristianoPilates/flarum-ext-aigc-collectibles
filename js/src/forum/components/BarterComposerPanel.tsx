import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import { collectibleRarityLabel, displayCollectibleName } from '../utils/collectibles';
import { displayUserName } from '../utils/users';
import type { BarterAsset } from '../utils/barterComposer';
import {
  barterSelectionCounts,
  ensureBarterComposerFields,
  loadBarterAssets,
  optionToken,
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
            fields.barterMySelections(),
            'collectibles_label'
          )}
          {this.assetGroup(
            payload.yours?.blindBoxes || [],
            fields.barterMySelections(),
            'blind_boxes_label'
          )}
        </div>

        <div className="BarterComposerPanel-column">
          <div className="BarterComposerPanel-columnTitle">
            {this.trans('donk-aigc-collectibles.forum.barter.their_offer')}
          </div>
          {this.assetGroup(
            payload.theirs?.collectibles || [],
            fields.barterTheirSelections(),
            'collectibles_label'
          )}
          {this.assetGroup(
            payload.theirs?.blindBoxes || [],
            fields.barterTheirSelections(),
            'blind_boxes_label'
          )}
        </div>
      </div>
    );
  }

  assetGroup(assets: BarterAsset[], selections: string[], labelKey: string) {
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
          <div className="BarterComposerPanel-options">
            {assets.map((asset) => {
              const token = optionToken(asset);
              const checked = selections.includes(token);

              return (
                <label className={`BarterComposerPanel-option ${checked ? 'is-selected' : ''}`} key={token}>
                  <input
                    type="checkbox"
                    checked={checked}
                    onchange={(event: InputEvent) => {
                      this.toggleSelection(selections, token, (event.target as HTMLInputElement).checked);
                    }}
                  />
                  <div className="BarterComposerPanel-optionCopy">
                    <div className="BarterComposerPanel-optionTitle">{this.assetLabel(asset)}</div>
                    <div className="BarterComposerPanel-optionMeta">{this.assetMeta(asset)}</div>
                  </div>
                </label>
              );
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

    return String(this.trans(`donk-aigc-collectibles.forum.blind_box.type_${asset.type || 'unknown'}`));
  }

  assetMeta(asset: BarterAsset): string {
    if (asset.assetType === 'collectible') {
      const rarity = asset.rarity ? collectibleRarityLabel(asset.rarity) : '';
      const tokenId = asset.tokenId ? `#${asset.tokenId}` : '';

      return [rarity, tokenId].filter(Boolean).join(' · ');
    }

    const status = String(this.trans(`donk-aigc-collectibles.forum.blind_box.status_${asset.status || 'unknown'}`));
    const budget =
      typeof asset.budget === 'number'
        ? String(asset.budget)
        : String(this.trans('donk-aigc-collectibles.forum.blind_box.budget_unknown'));

    return `${status} · ${String(this.trans('donk-aigc-collectibles.forum.blind_box.budget_label'))}: ${budget}`;
  }

  async toggleEnabled() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const nextEnabled = !fields.barterEnabled();

    fields.barterEnabled(nextEnabled);
    fields.barterExpanded(nextEnabled);
    fields.barterError(null);
    fields.barterValidationError(null);

    if (!nextEnabled) {
      fields.barterMySelections([]);
      fields.barterTheirSelections([]);
      fields.barterReplacesProposalId(null);
      m.redraw();
      return;
    }

    await loadBarterAssets(this.attrs.composer, this.attrs.dialog, true);
  }

  toggleSelection(selections: string[], token: string, checked: boolean) {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const current = [...selections];
    const index = current.indexOf(token);

    if (checked && index === -1) {
      current.push(token);
    }

    if (!checked && index !== -1) {
      current.splice(index, 1);
    }

    if (selections === fields.barterMySelections()) {
      fields.barterMySelections(current);
    } else {
      fields.barterTheirSelections(current);
    }

    fields.barterValidationError(null);
  }

  trans(key: string, parameters: Record<string, unknown> = {}) {
    return app.translator.trans(key, parameters);
  }
}
