import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import { openBarterConfigOverlay } from '../installers/barterMessaging';
import {
  barterSelections,
  barterSelectionCounts,
  enableBarterComposer,
  ensureBarterComposerFields,
  loadBarterAssets,
  resetBarterSelections,
} from '../utils/barterComposer';

interface BarterComposerPanelAttrs {
  composer: any;
  dialog: any;
}

export default class BarterComposerPanel extends Component<BarterComposerPanelAttrs> {
  private liveRegion: HTMLElement | null = null;
  private panelRef: HTMLElement | null = null;

  view() {
    const fields = ensureBarterComposerFields(this.attrs.composer);
    const enabled = fields.barterEnabled();
    const counts = barterSelectionCounts(this.attrs.composer);

    if (!enabled) {
      return (
        <section className="BarterComposerPanel">
          <div className="BarterComposerPanel-header">
            <div>
              <div className="BarterComposerPanel-kicker">
                {this.trans('donk-aigc-collectibles.forum.barter.composer_kicker')}
              </div>
            </div>
            <Button
              className="Button Button--primary"
              onclick={() => void this.toggleEnabled()}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.enable_button')}
            </Button>
          </div>
        </section>
      );
    }

    return (
      <section className="BarterComposerPanel BarterComposerPanel--expanded">
        <div className="BarterComposerPanel-header">
          <div>
            <div className="BarterComposerPanel-kicker">
              {this.trans('donk-aigc-collectibles.forum.barter.composer_kicker')}
            </div>
          </div>

          <div className="BarterComposerPanel-headerActions">
            <Button
              className="BarterComposerPanel-editBtn"
              onclick={() => openBarterConfigOverlay(this.attrs.composer, this.attrs.dialog)}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.edit_negotiation')}
            </Button>
            {counts.yours > 0 || counts.theirs > 0 ? (
              <Button
                className="Button Button--text"
                onclick={() => void this.clearSelections()}
              >
                {this.trans('donk-aigc-collectibles.forum.barter.clear_selections')}
              </Button>
            ) : null}
            <Button
              className="Button Button--text"
              onclick={() => void this.toggleEnabled()}
            >
              {this.trans('donk-aigc-collectibles.forum.barter.disable_button')}
            </Button>
          </div>
        </div>

        {this.renderComposerSummary()}

        <div
          ref={(el: HTMLElement) => (this.liveRegion = el)}
          aria-live="polite"
          aria-atomic="true"
          className="BarterComposerPanel-liveRegion"
        />
      </section>
    );
  }

  // ─── Summary (count-based) ────────────────────────────────────────────────────

  private renderComposerSummary(): any {
    const yours = barterSelections(this.attrs.composer, 'yours');
    const theirs = barterSelections(this.attrs.composer, 'theirs');

    if (yours.length === 0 && theirs.length === 0) {
      return (
        <div className="BarterComposerPanel-summaryEmpty">
          {this.trans('donk-aigc-collectibles.forum.barter.empty_cta')}
        </div>
      );
    }

    const yoursC = yours.filter((t) => t.startsWith('collectible:')).length;
    const yoursB = yours.filter((t) => t.startsWith('blind_box:')).length;
    const theirsC = theirs.filter((t) => t.startsWith('collectible:')).length;
    const theirsB = theirs.filter((t) => t.startsWith('blind_box:')).length;

    const renderCount = (c: number, b: number): string => {
      const parts: string[] = [];
      if (c > 0) parts.push(`${c} ${this.trans('donk-aigc-collectibles.forum.barter.collectibles_label')}`);
      if (b > 0) parts.push(`${b} ${this.trans('donk-aigc-collectibles.forum.barter.blind_boxes_label')}`);
      return parts.length > 0 ? parts.join(' · ') : this.trans('donk-aigc-collectibles.forum.barter.config_overlay_nothing_selected');
    };

    return (
      <div className="BarterComposerPanel-summary">
        <div className="BarterComposerPanel-summarySide yours">
          <div className="BarterComposerPanel-summaryLabel">
            {this.trans('donk-aigc-collectibles.forum.barter.your_side')}
          </div>
          <div className="BarterComposerPanel-summaryItems">
            {renderCount(yoursC, yoursB)}
          </div>
        </div>
        <div className="BarterComposerPanel-summaryVs">↔</div>
        <div className="BarterComposerPanel-summarySide theirs">
          <div className="BarterComposerPanel-summaryLabel">
            {this.trans('donk-aigc-collectibles.forum.barter.their_side')}
          </div>
          <div className={`BarterComposerPanel-summaryItems ${theirs.length === 0 ? 'is-empty' : ''}`}>
            {renderCount(theirsC, theirsB)}
          </div>
        </div>
      </div>
    );
  }

  // ─── Actions ─────────────────────────────────────────────────────────────────

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

  announceSelection() {
    const yours = barterSelections(this.attrs.composer, 'yours');
    const yoursCollectibles = yours.filter((t) => t.startsWith('collectible:')).length;
    const yoursBlindBoxes = yours.filter((t) => t.startsWith('blind_box:')).length;

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
