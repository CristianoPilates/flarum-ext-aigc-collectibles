import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BlindBoxOpener from './BlindBoxOpener';

interface BlindBoxInventoryAttrs {
  user: any;
}

const ACTIVE_STATUSES = ['unappraised', 'appraised'];

export default class BlindBoxInventory extends Component<BlindBoxInventoryAttrs> {
  loading: boolean = true;
  blindBoxes: any[] = [];
  lastLoadedUserId: string | null = null;

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
      this.loadBlindBoxes();
    }

    return true;
  }

  view() {
    const activeBoxes = this.activeBlindBoxes();

    return (
      <div className="BlindBoxInventory">
        <div className="BlindBoxInventory-header">
          <div>
            <h3 className="BlindBoxInventory-title">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_title')}
            </h3>
            <p className="BlindBoxInventory-subtitle">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_subtitle', {
                count: activeBoxes.length,
              })}
            </p>
          </div>
        </div>

        {this.loading ? (
          <div className="BlindBoxInventory-loading">
            <LoadingIndicator />
          </div>
        ) : activeBoxes.length === 0 ? (
          <div className="BlindBoxInventory-empty">
            <p>{app.translator.trans('donk-aigc-collectibles.forum.blind_box.inventory_empty')}</p>
          </div>
        ) : (
          <div className="BlindBoxInventory-grid">
            {activeBoxes.map((blindBox: any) => this.viewBlindBox(blindBox))}
          </div>
        )}
      </div>
    );
  }

  activeBlindBoxes() {
    return this.blindBoxes.filter((blindBox: any) => ACTIVE_STATUSES.includes(blindBox.status?.() || ''));
  }

  viewBlindBox(blindBox: any) {
    const type = blindBox.type?.() || 'unknown';
    const status = blindBox.status?.() || 'unknown';
    const budget = blindBox.budget?.();
    const drawRules = Array.isArray(blindBox.drawRules?.()) ? blindBox.drawRules() : [];
    const canAppraise = status === 'unappraised';
    const canOpen = status === 'appraised';

    return (
      <article className={`BlindBoxCard BlindBoxCard--${type} BlindBoxCard--${status}`} key={blindBox.id()}>
        <div className="BlindBoxCard-shell">
          <div className="BlindBoxCard-face">
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
        </div>

        <div className="BlindBoxCard-meta">
          <div className="BlindBoxCard-row">
            <span className="BlindBoxCard-label">
              {app.translator.trans('donk-aigc-collectibles.forum.blind_box.seed_label')}
            </span>
            <code className="BlindBoxCard-seed">{blindBox.seed?.() || '-'}</code>
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
              {drawRules.map((rule: { category: string; required: boolean }) => (
                <span
                  className={`BlindBoxCard-category${rule.required ? ' BlindBoxCard-category--required' : ''}`}
                  key={`${blindBox.id()}-${rule.category}-${rule.required ? 'required' : 'optional'}`}
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
