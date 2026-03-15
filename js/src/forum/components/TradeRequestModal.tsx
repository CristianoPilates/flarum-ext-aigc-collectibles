import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

interface TradeRequestModalAttrs {
  collectible: any;
}

export default class TradeRequestModal extends Modal {
  offeredBoxes: number = 1;
  note: string = '';
  loading: boolean = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.offeredBoxes = 1;
    this.note = '';
    this.loading = false;
  }

  className() {
    return 'TradeRequestModal Modal--small';
  }

  title() {
    return app.translator.trans('donk-aigc-collectibles.forum.trade.create_title');
  }

  content() {
    const collectible = this.attrs.collectible;
    if (!collectible) return null;

    const owner = collectible.user();
    const user = app.session.user;
    const blindBoxCount = user ? user.attribute<number>('blindBoxCount') || 0 : 0;

    return (
      <div className="Modal-body TradeRequestModal-body">
        <div className="TradeRequestModal-target">
          <div className="TradeRequestModal-targetInfo">
            <strong>{collectible.name()}</strong>
            <span className={'CollectibleRarity CollectibleRarity--' + collectible.rarity()}>
              {collectible.rarity()}
            </span>
            {owner && (
              <span className="TradeRequestModal-owner">
                {app.translator.trans('donk-aigc-collectibles.forum.trade.owned_by', {
                  user: owner.displayName(),
                })}
              </span>
            )}
          </div>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('donk-aigc-collectibles.forum.trade.offer_amount')}</label>
          <div className="TradeRequestModal-inputGroup">
            <input
              type="number"
              className="FormControl"
              min="1"
              max={blindBoxCount}
              value={this.offeredBoxes}
              oninput={(e: Event) => {
                this.offeredBoxes = parseInt((e.target as HTMLInputElement).value, 10) || 1;
              }}
            />
            <span className="TradeRequestModal-balance">
              <i className="fas fa-box" /> {blindBoxCount}{' '}
              {app.translator.trans('donk-aigc-collectibles.forum.trade.available')}
            </span>
          </div>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('donk-aigc-collectibles.forum.trade.note_label')}</label>
          <textarea
            className="FormControl"
            rows={2}
            maxLength={500}
            value={this.note}
            oninput={(e: Event) => {
              this.note = (e.target as HTMLTextAreaElement).value;
            }}
            placeholder={String(app.translator.trans('donk-aigc-collectibles.forum.trade.note_placeholder'))}
          />
        </div>

        <div className="Form-group TradeRequestModal-actions">
          <Button
            className="Button Button--primary"
            onclick={() => this.submitTrade()}
            loading={this.loading}
            disabled={this.offeredBoxes < 1 || this.offeredBoxes > blindBoxCount}
          >
            {app.translator.trans('donk-aigc-collectibles.forum.trade.submit')}
          </Button>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('donk-aigc-collectibles.forum.trade.cancel_action')}
          </Button>
        </div>
      </div>
    );
  }

  submitTrade() {
    if (this.loading) return;

    const collectible = this.attrs.collectible;
    if (!collectible) return;

    this.loading = true;

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/trades',
        body: {
          data: {
            type: 'trades',
            attributes: {
              collectibleId: collectible.id(),
              offeredBoxes: this.offeredBoxes,
              note: this.note || null,
            },
          },
        },
      })
      .then(() => {
        this.loading = false;
        this.hide();
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('donk-aigc-collectibles.forum.trade.offer_sent')
        );
      })
      .catch((error: any) => {
        this.loading = false;
        m.redraw();
        throw error;
      });
  }
}
