import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BlindBoxOpener from './BlindBoxOpener';
import {
  blindBoxBudget,
  blindBoxDrawRules,
  blindBoxId,
  blindBoxSeed,
  blindBoxStatus,
  blindBoxSvgUrl,
  blindBoxType,
  rarityFromBudget,
} from '../utils/blindBoxes';

interface BlindBoxInventoryAttrs {
  user: any;
}

interface BalanceEntry {
  type: string;
  count: number;
  expanded: boolean;
  boxes: any[];
}

const ACTIVE_STATUSES = ['unappraised', 'appraised'];

export default class BlindBoxInventory extends Component<BlindBoxInventoryAttrs> {
  loading: boolean = true;
  blindBoxes: any[] = [];
  lastLoadedUserId: string | null = null;
  expandedType: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = true;
    this.blindBoxes = [];
    this.lastLoadedUserId = vnode.attrs.user?.id?.() || null;
    this.loadBlindBoxes();
  }

  onbeforeupdate(vnode: any) {
    const userId = vnode.attrs.user?.id?.() || null;

    if (userId !== this.lastLoadedUserId) {
      this.lastLoadedUserId = userId;
      this.blindBoxes = [];
      this.expandedType = null;
      this.loadBlindBoxes();
    }

    return true;
  }

  /**
   * Build balance dictionary from loaded blind boxes.
   * Groups boxes by type and counts active ones.
   */
  balanceDictionary(): BalanceEntry[] {
    const byType = new Map<string, any[]>();

    for (const box of this.blindBoxes) {
      if (!ACTIVE_STATUSES.includes(blindBoxStatus(box))) continue;
      const type = blindBoxType(box);
      if (!byType.has(type)) byType.set(type, []);
      byType.get(type)!.push(box);
    }

    return Array.from(byType.entries()).map(([type, boxes]) => ({
      type,
      count: boxes.length,
      expanded: this.expandedType === type,
      boxes,
    }));
  }

  toggleType(type: string) {
    this.expandedType = this.expandedType === type ? null : type;
    m.redraw();
  }

  view() {
    const balances = this.balanceDictionary();
    const totalActive = balances.reduce((sum, e) => sum + e.count, 0);

    return (
      <div className="BlindBoxInventory">
        <div className="BlindBoxInventory-header">
          <h3 className="BlindBoxInventory-title">
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_title')}
          </h3>
        </div>

        {this.loading ? (
          <div className="BlindBoxInventory-loading">
            <LoadingIndicator />
          </div>
        ) : totalActive === 0 ? (
          <div className="BlindBoxInventory-empty">
            <p>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_empty')}</p>
          </div>
        ) : (
          <>
            {/* Balance Dictionary */}
            <div className="BlindBoxBalanceDictionary">
              {balances.map((entry) => (
                <div
                  key={entry.type}
                  className={`BlindBoxBalanceEntry BlindBoxBalanceEntry--${entry.type}${entry.expanded ? ' BlindBoxBalanceEntry--expanded' : ''}`}
                  onclick={() => this.toggleType(entry.type)}
                >
                  <div className="BlindBoxBalanceEntry-summary">
                    <span className="BlindBoxBalanceEntry-icon">
                      <img
                        src={blindBoxSvgUrl(app.forum.attribute('baseUrl'), entry.type, 'unappraised')}
                        alt="blind box"
                        className="BlindBoxBalanceEntry-svg"
                      />
                    </span>
                    <span className="BlindBoxBalanceEntry-type">
                      {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.type_${entry.type}`) || entry.type}
                    </span>
                    <span className="BlindBoxBalanceEntry-count">{entry.count}</span>
                    <span className="BlindBoxBalanceEntry-chevron">
                      <i className={`fas fa-chevron-${entry.expanded ? 'up' : 'down'}`} />
                    </span>
                  </div>

                  {entry.expanded && (
                    <div className="BlindBoxBalanceEntry-cards">
                      {entry.boxes.map((box) => this.viewBlindBox(box))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    );
  }

  viewBlindBox(blindBox: any) {
    const type = blindBoxType(blindBox);
    const status = blindBoxStatus(blindBox);
    const budget = blindBoxBudget(blindBox);
    const drawRules = blindBoxDrawRules(blindBox);
    const canAppraise = status === 'unappraised';
    const canOpen = status === 'appraised';
    const boxId = blindBoxId(blindBox);

    return (
      <article
        className={`BlindBoxCard BlindBoxCard--${type} BlindBoxCard--${status}`}
        data-id={boxId}
      >
        <div className="BlindBoxCard-shell">
          {/* Status seal for unappraised */}
          {canAppraise && (
            <div className="BlindBoxCard-seal">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.status_unappraised')}
            </div>
          )}
          {/* Opened indicator */}
          {status === 'opened' && (
            <div className="BlindBoxCard-opened">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.status_opened')}
            </div>
          )}
          {/* Blind box SVG illustration */}
          <img
            className="BlindBoxCard-svg"
            src={blindBoxSvgUrl(app.forum.attribute('baseUrl'), type, status)}
            alt={app.translator.trans('donk-aigc-collectibles.forum.blind_box.type_label')}
          />
          {/* Budget glow effect based on rarity */}
          {typeof budget === 'number' && (
            <div className={`BlindBoxCard-glow BlindBoxCard-glow--${rarityFromBudget(budget)}`} />
          )}
        </div>

        <div className="BlindBoxCard-info">
          <div className="BlindBoxCard-kicker">
            {app.translator.trans('donk-aigc-collectibles.forum.blind_box.type_label')}
          </div>
          <h4 className="BlindBoxCard-type">
            {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.type_${type}`)}
          </h4>
          <div className={`BlindBoxCard-status BlindBoxCard-status--${status}`}>
            {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.status_${status}`)}
          </div>
        </div>

        <div className="BlindBoxCard-meta">
          <div className="BlindBoxCard-row">
            <span className="BlindBoxCard-label">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.seed_label')}
            </span>
            <code className="BlindBoxCard-seed">{blindBoxSeed(blindBox) || '-'}</code>
          </div>

          <div className="BlindBoxCard-row">
            <span className="BlindBoxCard-label">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.budget_label')}
            </span>
            <strong className="BlindBoxCard-budget">
              {typeof budget === 'number'
                ? app.translator.trans('donk-aigc-collectibles.forum.blind_box.budget_value', { budget })
                : app.translator.trans('donk-aigc-collectibles.forum.blind_box.budget_unknown')}
            </strong>
          </div>

          <div className="BlindBoxCard-row BlindBoxCard-row--stacked">
            <span className="BlindBoxCard-label">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.draw_categories_label')}
            </span>
            <div className="BlindBoxCard-categories">
              {drawRules.map((rule) => (
                <span
                  className={`BlindBoxCard-category${rule.required ? ' BlindBoxCard-category--required' : ''}`}
                  key={`${boxId}-${rule.category}-${rule.required ? 'required' : 'optional'}`}
                >
                  {app.translator.trans(`donk-aigc-collectibles.forum.blind_box.category_${rule.category}`)}
                  {rule.required
                    ? ` · ${app.translator.trans('donk-aigc-collectibles.forum.blind_box.category_required')}`
                    : ` · ${app.translator.trans('donk-aigc-collectibles.forum.blind_box.category_optional')}`}
                </span>
              ))}
            </div>
          </div>
        </div>

        <div className="BlindBoxCard-actions">
          {canAppraise && (
            <Button className="Button Button--primary" onclick={() => this.openBlindBox(blindBox)}>
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.appraise_button')}
            </Button>
          )}

          {canOpen && (
            <Button className="Button Button--primary" onclick={() => this.openBlindBox(blindBox)}>
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.open_collectible_button')}
            </Button>
          )}
        </div>
      </article>
    );
  }

  openBlindBox(blindBox: any) {
    app.modal.show(BlindBoxOpener, {
      blindBox,
      onUpdated: () => this.loadBlindBoxes(),
    });
  }

  loadBlindBoxes() {
    this.loading = true;
    m.redraw();

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/blindboxes',
        params: {
          'page[limit]': 50,
          sort: '-createdAt',
        },
      })
      .then((response: any) => {
        const payload = app.store.pushPayload(response);
        this.blindBoxes = Array.isArray(payload) ? payload : payload ? [payload] : [];
      })
      .catch(() => {
        this.blindBoxes = [];
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
